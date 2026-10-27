<?php

require_once __DIR__ . '/../config/database.php';

class AuditEvaluationManager
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function cargar(int $tenantId, int $taskId, int $userId): array
    {
        if (!$this->db || $tenantId <= 0 || $taskId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Datos de evaluación inválidos.'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT t.id AS task_id, t.tenant_id, t.call_id, t.stage_id,
                       t.assigned_to, t.status AS task_status,
                       c.external_call_id, c.started_at, c.duration_seconds,
                       c.phone_masked, c.audio_status, c.audio_storage_key,
                       ca.name AS campaign_name,
                       s.stage_number, s.name AS stage_name, s.form_version_id,
                       v.version_number, v.status AS version_status,
                       v.passing_score, v.na_policy,
                       f.name AS form_name
                FROM audit_tasks t
                INNER JOIN audit_calls c ON c.id=t.call_id AND c.tenant_id=t.tenant_id
                INNER JOIN campaigns ca ON ca.id=c.campaign_id AND ca.tenant_id=c.tenant_id
                INNER JOIN audit_workflow_stages s ON s.id=t.stage_id AND s.tenant_id=t.tenant_id
                INNER JOIN audit_form_versions v ON v.id=s.form_version_id AND v.tenant_id=s.tenant_id
                INNER JOIN audit_forms f ON f.id=v.form_id AND f.tenant_id=v.tenant_id
                WHERE t.id=:task AND t.tenant_id=:tenant AND s.human_enabled=1
                LIMIT 1
            ");
            $stmt->execute([':task' => $taskId, ':tenant' => $tenantId]);
            $task = $stmt->fetch();
            if (!$task) {
                return ['success' => false, 'message' => 'La tarea no existe en esta empresa o no admite evaluación humana.'];
            }
            if ($task['task_status'] === 'CANCELLED') {
                return ['success' => false, 'message' => 'La tarea está cancelada.'];
            }
            if (in_array($task['task_status'], ['SUBMITTED', 'REVIEW', 'COMPLETED'], true)) {
                return ['success' => false, 'message' => 'La tarea ya fue enviada o cerrada; no se puede modificar desde esta pantalla.'];
            }
            if ($task['assigned_to'] !== null && (int)$task['assigned_to'] !== $userId) {
                return ['success' => false, 'message' => 'La tarea está asignada a otro evaluador.'];
            }

            $stmt = $this->db->prepare("
                SELECT id, position, question_text, help_text, answer_type, options_json,
                       weight, is_critical, required_answer, allow_na, na_score
                FROM audit_form_questions
                WHERE tenant_id=:tenant AND form_version_id=:version AND active=1
                ORDER BY position, id
            ");
            $stmt->execute([':tenant' => $tenantId, ':version' => $task['form_version_id']]);
            $questions = $stmt->fetchAll();
            if (!$questions) {
                return ['success' => false, 'message' => 'La versión del formulario no tiene preguntas activas.'];
            }
            foreach ($questions as &$q) {
                $decoded = json_decode((string)($q['options_json'] ?? ''), true);
                $q['options'] = is_array($decoded) ? $decoded : [];
            }
            unset($q);

            $stmt = $this->db->prepare("
                SELECT id, verdict, score, analysis, status
                FROM audit_results
                WHERE tenant_id=:tenant AND task_id=:task AND evaluator_type='HUMAN'
                  AND evaluator_user_id=:user AND status='DRAFT'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([':tenant' => $tenantId, ':task' => $taskId, ':user' => $userId]);
            $draft = $stmt->fetch() ?: null;
            $answers = [];
            if ($draft) {
                $stmt = $this->db->prepare("
                    SELECT question_id, answer_value, is_na, comment
                    FROM audit_result_answers
                    WHERE tenant_id=:tenant AND result_id=:result
                ");
                $stmt->execute([':tenant' => $tenantId, ':result' => $draft['id']]);
                foreach ($stmt->fetchAll() as $answer) {
                    $answers[(int)$answer['question_id']] = $answer;
                }
            }
            return ['success' => true, 'task' => $task, 'questions' => $questions, 'draft' => $draft, 'answers' => $answers];
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditEvaluationManager::cargar] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo cargar la evaluación. Revisa el log de PHP.'];
        }
    }

    public function guardar(int $tenantId, int $taskId, int $userId, array $posted, string $action, string $analysis): array
    {
        if (!$this->db || !in_array($action, ['draft', 'submit'], true)) {
            return ['success' => false, 'message' => 'Solicitud de evaluación inválida.'];
        }
        $loaded = $this->cargar($tenantId, $taskId, $userId);
        if (empty($loaded['success'])) return $loaded;
        $task = $loaded['task'];
        $questions = $loaded['questions'];

        if ($action === 'submit' && ($task['audio_status'] !== 'AVAILABLE' || empty($task['audio_storage_key']))) {
            return ['success' => false, 'message' => 'No se puede enviar una evaluación final: esta llamada no tiene audio disponible en el repositorio. Puedes guardar un borrador mientras se incorpora el audio real.'];
        }

        $clean = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $criticalFailure = false;
        $unansweredRequired = [];
        foreach ($questions as $q) {
            $id = (int)$q['id'];
            $raw = $posted['answers'][$id] ?? '';
            $value = is_string($raw) || is_numeric($raw) ? trim((string)$raw) : '';
            $na = isset($posted['na'][$id]) && (string)$posted['na'][$id] === '1';
            $commentRaw = $posted['comments'][$id] ?? '';
            $comment = is_string($commentRaw) ? trim($commentRaw) : '';
            if ($na && !(int)$q['allow_na']) $na = false;
            if ((int)$q['required_answer'] && !$na && $value === '') $unansweredRequired[] = (int)$q['position'];
            if ($action === 'submit' && (int)$q['required_answer'] && !$na && $value === '') {
                return ['success' => false, 'message' => 'Falta responder una pregunta obligatoria (posición ' . (int)$q['position'] . ').'];
            }
            $numeric = null;
            $critical = false;
            if (!$na && $value !== '') {
                if ($q['answer_type'] === 'YES_NO') {
                    if (!in_array($value, ['YES', 'NO'], true)) return ['success'=>false,'message'=>'Respuesta sí/no inválida en pregunta '.$q['position'].'.'];
                    $numeric = $value === 'YES' ? 100.0 : 0.0;
                    $critical = (int)$q['is_critical'] === 1 && $value === 'NO';
                    $weightedSum += $numeric * (float)$q['weight'];
                    $weightTotal += (float)$q['weight'];
                } elseif ($q['answer_type'] === 'SCALE') {
                    if (!is_numeric($value) || (float)$value < 1 || (float)$value > 5) return ['success'=>false,'message'=>'La escala debe estar entre 1 y 5 en la pregunta '.$q['position'].'.'];
                    $numeric = ((float)$value / 5.0) * 100.0;
                    $weightedSum += $numeric * (float)$q['weight'];
                    $weightTotal += (float)$q['weight'];
                    $critical = (int)$q['is_critical'] === 1 && (float)$value <= 2;
                } elseif ($q['answer_type'] === 'NUMBER') {
                    if (!is_numeric($value) || (float)$value < 0 || (float)$value > 100) return ['success'=>false,'message'=>'El valor numérico puntuable debe estar entre 0 y 100 en la pregunta '.$q['position'].'.'];
                    $numeric = (float)$value;
                    $weightedSum += $numeric * (float)$q['weight'];
                    $weightTotal += (float)$q['weight'];
                } elseif (in_array($q['answer_type'], ['SINGLE_CHOICE', 'MULTIPLE_CHOICE'], true)) {
                    $allowed = array_map('strval', $q['options']);
                    $values = $q['answer_type'] === 'MULTIPLE_CHOICE' ? (json_decode($value, true) ?: []) : [$value];
                    if (!is_array($values)) return ['success'=>false,'message'=>'Selección inválida en pregunta '.$q['position'].'.'];
                    foreach ($values as $choice) if (!in_array((string)$choice, $allowed, true)) return ['success'=>false,'message'=>'Opción no permitida en pregunta '.$q['position'].'.'];
                    $value = $q['answer_type'] === 'MULTIPLE_CHOICE' ? json_encode(array_values($values), JSON_UNESCAPED_UNICODE) : $value;
                }
            }
            $clean[] = ['question_id'=>$id, 'value'=>$value, 'na'=>$na, 'numeric'=>$numeric, 'critical'=>$critical, 'comment'=>mb_substr($comment, 0, 2000, 'UTF-8'), 'weight'=>(float)$q['weight'], 'na_score'=>$q['na_score']];
            if ($critical) $criticalFailure = true;
        }

        $versionPolicy = (string)$task['na_policy'];
        foreach ($clean as &$a) {
            if ($a['na']) {
                if ($versionPolicy === 'ZERO') { $weightedSum += 0; $weightTotal += $a['weight']; $a['numeric'] = 0.0; }
                elseif ($versionPolicy === 'FULL') { $weightedSum += 100.0 * $a['weight']; $weightTotal += $a['weight']; $a['numeric'] = 100.0; }
            }
        }
        unset($a);
        $score = $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;
        $passing = $task['passing_score'] !== null ? (float)$task['passing_score'] : 80.0;
        $verdict = $criticalFailure ? 'FAIL' : ($score === null ? 'REVIEW' : ($score >= $passing ? 'PASS' : 'FAIL'));
        if ($action === 'draft') $verdict = 'REVIEW';

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT id, status FROM audit_tasks WHERE id=:task AND tenant_id=:tenant FOR UPDATE");
            $stmt->execute([':task'=>$taskId, ':tenant'=>$tenantId]);
            $lockedTask = $stmt->fetch();
            if (!$lockedTask || !in_array($lockedTask['status'], ['PENDING','ASSIGNED','IN_PROGRESS'], true)) {
                $this->db->rollBack(); return ['success'=>false,'message'=>'La tarea cambió de estado y ya no admite edición.'];
            }
            $stmt = $this->db->prepare("SELECT id FROM audit_results WHERE tenant_id=:tenant AND task_id=:task AND evaluator_type='HUMAN' AND evaluator_user_id=:user AND status='DRAFT' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([':tenant'=>$tenantId, ':task'=>$taskId, ':user'=>$userId]);
            $resultId = (int)($stmt->fetchColumn() ?: 0);
            if ($resultId > 0) {
                $stmt = $this->db->prepare("UPDATE audit_results SET verdict=:verdict, score=:score, analysis=:analysis WHERE id=:id AND tenant_id=:tenant");
                $stmt->execute([':verdict'=>$verdict, ':score'=>$score, ':analysis'=>$analysis !== '' ? $analysis : null, ':id'=>$resultId, ':tenant'=>$tenantId]);
                $stmt = $this->db->prepare("DELETE FROM audit_result_answers WHERE tenant_id=:tenant AND result_id=:result");
                $stmt->execute([':tenant'=>$tenantId, ':result'=>$resultId]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO audit_results (tenant_id, task_id, evaluator_type, evaluator_user_id, verdict, score, analysis, status) VALUES (:tenant,:task,'HUMAN',:user,:verdict,:score,:analysis,'DRAFT')");
                $stmt->execute([':tenant'=>$tenantId, ':task'=>$taskId, ':user'=>$userId, ':verdict'=>$verdict, ':score'=>$score, ':analysis'=>$analysis !== '' ? $analysis : null]);
                $resultId = (int)$this->db->lastInsertId();
            }
            $insert = $this->db->prepare("INSERT INTO audit_result_answers (tenant_id,result_id,question_id,answer_value,numeric_score,is_na,critical_failure,comment) VALUES (:tenant,:result,:question,:value,:numeric,:na,:critical,:comment)");
            foreach ($clean as $a) {
                $insert->execute([':tenant'=>$tenantId, ':result'=>$resultId, ':question'=>$a['question_id'], ':value'=>$a['value'] !== '' ? $a['value'] : null, ':numeric'=>$a['numeric'], ':na'=>$a['na'] ? 1 : 0, ':critical'=>$a['critical'] ? 1 : 0, ':comment'=>$a['comment'] !== '' ? $a['comment'] : null]);
            }
            if ($action === 'draft') {
                $stmt = $this->db->prepare("UPDATE audit_tasks SET assigned_to=COALESCE(assigned_to,:user), status='IN_PROGRESS', started_at=COALESCE(started_at,NOW()) WHERE id=:task AND tenant_id=:tenant");
                $stmt->execute([':user'=>$userId, ':task'=>$taskId, ':tenant'=>$tenantId]);
                $this->db->commit();
                return ['success'=>true,'message'=>'Borrador de evaluación guardado. La tarea sigue en curso.','result_id'=>$resultId,'score'=>$score];
            }
            $stmt = $this->db->prepare("UPDATE audit_results SET status='SUBMITTED', verdict=:verdict, score=:score, evaluated_at=NOW() WHERE id=:id AND tenant_id=:tenant");
            $stmt->execute([':verdict'=>$verdict, ':score'=>$score, ':id'=>$resultId, ':tenant'=>$tenantId]);
            $stmt = $this->db->prepare("UPDATE audit_tasks SET assigned_to=COALESCE(assigned_to,:user), status='SUBMITTED', submitted_at=NOW(), started_at=COALESCE(started_at,NOW()) WHERE id=:task AND tenant_id=:tenant");
            $stmt->execute([':user'=>$userId, ':task'=>$taskId, ':tenant'=>$tenantId]);
            $this->db->commit();
            return ['success'=>true,'message'=>'Evaluación enviada correctamente.','result_id'=>$resultId,'score'=>$score,'verdict'=>$verdict];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[CallAudit][AuditEvaluationManager::guardar] '.$e->getMessage());
            return ['success'=>false,'message'=>'No se pudo guardar la evaluación. Revisa el log de PHP.'];
        }
    }
}

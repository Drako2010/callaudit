
<?php

require_once __DIR__ . '/../config/database.php';

class AuditTaskManager
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function listar(int $tenantId, array $filtros = []): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                t.id, t.tenant_id, t.call_id, t.stage_id,
                t.assigned_to, t.assigned_by, t.status, t.priority,
                t.due_at, t.started_at, t.submitted_at,
                t.completed_at, t.created_at,
                c.started_at AS call_started_at,
                c.duration_seconds, c.phone_masked,
                c.audio_status, c.audio_storage_key,
                s.stage_number, s.name AS stage_name,
                u.name AS evaluator_name,
                ca.name AS campaign_name
            FROM audit_tasks t
            INNER JOIN audit_calls c
                ON c.id = t.call_id AND c.tenant_id = t.tenant_id
            INNER JOIN audit_workflow_stages s
                ON s.id = t.stage_id AND s.tenant_id = t.tenant_id
            INNER JOIN campaigns ca
                ON ca.id = c.campaign_id AND ca.tenant_id = c.tenant_id
            LEFT JOIN users u
                ON u.id = t.assigned_to AND u.tenant_id = t.tenant_id
            WHERE t.tenant_id = :tenant
        ";

        $params = [':tenant' => $tenantId];

        $status = $filtros['status'] ?? '';
        $allowed = [
            'PENDING', 'ASSIGNED', 'IN_PROGRESS', 'SUBMITTED',
            'REVIEW', 'COMPLETED', 'CANCELLED'
        ];

        if (is_string($status) && in_array($status, $allowed, true)) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $status;
        }

        $campaignId = filter_var(
            $filtros['campaign_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($campaignId !== false && $campaignId !== null && $campaignId > 0) {
            $sql .= " AND c.campaign_id = :campaign";
            $params[':campaign'] = $campaignId;
        }

        $sql .= " ORDER BY
                    CASE t.status
                        WHEN 'PENDING' THEN 1
                        WHEN 'ASSIGNED' THEN 2
                        WHEN 'IN_PROGRESS' THEN 3
                        WHEN 'SUBMITTED' THEN 4
                        WHEN 'REVIEW' THEN 5
                        ELSE 6
                    END,
                    t.priority ASC, t.id DESC
                  LIMIT 500";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditTaskManager::listar] ' . $e->getMessage());
            return [];
        }
    }

    public function agentes(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, name, email
                FROM users
                WHERE tenant_id = :tenant
                  AND status = 'ACTIVE'
                ORDER BY name
            ");
            $stmt->execute([':tenant' => $tenantId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditTaskManager::agentes] ' . $e->getMessage());
            return [];
        }
    }

    public function asignar(
        int $tenantId,
        int $taskId,
        int $evaluatorId,
        int $assignedBy
    ): array {
        if (!$this->db || $tenantId <= 0 || $taskId <= 0 ||
            $evaluatorId <= 0 || $assignedBy <= 0) {
            return ['success' => false, 'message' => 'Datos de asignación inválidos.'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT id, status
                FROM audit_tasks
                WHERE id = :id AND tenant_id = :tenant
                FOR UPDATE
            ");
            $stmt->execute([':id' => $taskId, ':tenant' => $tenantId]);
            $task = $stmt->fetch();

            if (!$task) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'La tarea no existe en esta empresa.'];
            }

            if (in_array($task['status'], ['SUBMITTED', 'REVIEW', 'COMPLETED', 'CANCELLED'], true)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'No se puede reasignar una tarea en este estado.'];
            }

            $stmt = $this->db->prepare("
                SELECT id
                FROM users
                WHERE id = :user
                  AND tenant_id = :tenant
                  AND status = 'ACTIVE'
                LIMIT 1
            ");
            $stmt->execute([':user' => $evaluatorId, ':tenant' => $tenantId]);

            if (!$stmt->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'El evaluador no pertenece a esta empresa o está inactivo.'];
            }

            $stmt = $this->db->prepare("
                UPDATE audit_tasks
                SET assigned_to = :evaluator,
                    assigned_by = :assigned_by,
                    status = CASE WHEN status = 'PENDING' THEN 'ASSIGNED' ELSE status END
                WHERE id = :id AND tenant_id = :tenant
            ");
            $stmt->execute([
                ':evaluator' => $evaluatorId,
                ':assigned_by' => $assignedBy,
                ':id' => $taskId,
                ':tenant' => $tenantId
            ]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Tarea asignada correctamente.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('[CallAudit][AuditTaskManager::asignar] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo asignar la tarea.'];
        }
    }

    public function cambiarEstado(
        int $tenantId,
        int $taskId,
        string $nuevoEstado
    ): array {
        if (!$this->db || $tenantId <= 0 || $taskId <= 0) {
            return ['success' => false, 'message' => 'Datos inválidos.'];
        }

        $transiciones = [
            'ASSIGNED' => ['IN_PROGRESS', 'CANCELLED'],
            'IN_PROGRESS' => ['SUBMITTED', 'CANCELLED'],
            'SUBMITTED' => ['REVIEW'],
            'REVIEW' => ['COMPLETED', 'IN_PROGRESS']
        ];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT status
                FROM audit_tasks
                WHERE id = :id AND tenant_id = :tenant
                FOR UPDATE
            ");
            $stmt->execute([':id' => $taskId, ':tenant' => $tenantId]);
            $task = $stmt->fetch();

            if (!$task) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'La tarea no existe en esta empresa.'];
            }

            $actual = (string) $task['status'];

            if (!in_array($nuevoEstado, $transiciones[$actual] ?? [], true)) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "Transición no permitida: {$actual} → {$nuevoEstado}."
                ];
            }

            $stmt = $this->db->prepare("
                UPDATE audit_tasks
                SET status = :status,
                    started_at = CASE
                        WHEN :start_check = 'IN_PROGRESS' AND started_at IS NULL
                        THEN NOW() ELSE started_at END,
                    submitted_at = CASE
                        WHEN :submit_check = 'SUBMITTED' THEN NOW()
                        ELSE submitted_at END,
                    completed_at = CASE
                        WHEN :complete_check = 'COMPLETED' THEN NOW()
                        WHEN :clear_check = 'IN_PROGRESS' THEN NULL
                        ELSE completed_at END
                WHERE id = :id AND tenant_id = :tenant
            ");

            $stmt->execute([
                ':status' => $nuevoEstado,
                ':start_check' => $nuevoEstado,
                ':submit_check' => $nuevoEstado,
                ':complete_check' => $nuevoEstado,
                ':clear_check' => $nuevoEstado,
                ':id' => $taskId,
                ':tenant' => $tenantId
            ]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Estado actualizado correctamente.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('[CallAudit][AuditTaskManager::cambiarEstado] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo cambiar el estado.'];
        }
    }
}

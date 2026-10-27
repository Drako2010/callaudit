<?php
require_once __DIR__ . '/AuditJobQueue.php';
require_once __DIR__ . '/../config/database.php';

class AuditSelectionPolicy
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function campanias(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT id, name
            FROM campaigns
            WHERE tenant_id = :tenant
            ORDER BY name
        ");
        $stmt->execute([':tenant' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listar(int $tenantId, int $campaignId): array
    {
        if (!$this->db || $tenantId <= 0 || $campaignId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT id, tenant_id, campaign_id, name, method,
                   configuration_json, priority, enabled,
                   created_by, created_at, updated_at
            FROM audit_selection_policies
            WHERE tenant_id = :tenant
              AND campaign_id = :campaign
            ORDER BY priority, id
        ");
        $stmt->execute([
            ':tenant' => $tenantId,
            ':campaign' => $campaignId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(
        int $tenantId,
        int $campaignId,
        int $userId,
        array $data,
        ?int $id = null
    ): array {
        if (!$this->db || $tenantId <= 0 || $campaignId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Contexto inválido.'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $method = strtoupper(trim((string) ($data['method'] ?? '')));
        $priority = filter_var($data['priority'] ?? 100, FILTER_VALIDATE_INT);
        $allowedMethods = ['MANUAL', 'RANDOM', 'RULES', 'AUTOMATIC'];

        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            return ['success' => false, 'message' => 'El nombre es obligatorio y admite hasta 150 caracteres.'];
        }
        if (!in_array($method, $allowedMethods, true)) {
            return ['success' => false, 'message' => 'Método de selección inválido.'];
        }
        if ($priority === false || $priority < 0 || $priority > 1000000) {
            return ['success' => false, 'message' => 'Prioridad inválida.'];
        }

        $sampleSize = filter_var($data['sample_size'] ?? 1, FILTER_VALIDATE_INT);
        $minDuration = filter_var($data['min_duration_seconds'] ?? 0, FILTER_VALIDATE_INT);

        if ($sampleSize === false || $sampleSize < 1 || $sampleSize > 10000) {
            return ['success' => false, 'message' => 'La cantidad debe estar entre 1 y 10000 llamadas por ejecución.'];
        }
        if ($minDuration === false || $minDuration < 0 || $minDuration > 86400) {
            return ['success' => false, 'message' => 'Duración mínima inválida.'];
        }

        $configuration = [
            'sample_size' => $method === 'MANUAL' ? 1 : $sampleSize,
            'min_duration_seconds' => $minDuration,
            'only_with_audio' => isset($data['only_with_audio'])
        ];
        if ($method === 'RULES') {
            $configuration['rule'] = 'MIN_DURATION';
        }

        $json = json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['success' => false, 'message' => 'No se pudo procesar la configuración.'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id FROM campaigns
                WHERE id = :campaign AND tenant_id = :tenant
                LIMIT 1
            ");
            $stmt->execute([':campaign' => $campaignId, ':tenant' => $tenantId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'La campaña no pertenece a la empresa activa.'];
            }

            if ($id !== null) {
                $stmt = $this->db->prepare("
                    SELECT id FROM audit_selection_policies
                    WHERE id = :id AND tenant_id = :tenant AND campaign_id = :campaign
                    LIMIT 1
                ");
                $stmt->execute([':id' => $id, ':tenant' => $tenantId, ':campaign' => $campaignId]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'message' => 'La política no existe en esta campaña.'];
                }

                $stmt = $this->db->prepare("
                    UPDATE audit_selection_policies
                    SET name = :name, method = :method,
                        configuration_json = :configuration,
                        priority = :priority, enabled = :enabled
                    WHERE id = :id AND tenant_id = :tenant AND campaign_id = :campaign
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':method' => $method,
                    ':configuration' => $json,
                    ':priority' => $priority,
                    ':enabled' => isset($data['enabled']) ? 1 : 0,
                    ':id' => $id,
                    ':tenant' => $tenantId,
                    ':campaign' => $campaignId
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO audit_selection_policies
                        (tenant_id, campaign_id, name, method, configuration_json,
                         priority, enabled, created_by)
                    VALUES
                        (:tenant, :campaign, :name, :method, :configuration,
                         :priority, :enabled, :created_by)
                ");
                $stmt->execute([
                    ':tenant' => $tenantId,
                    ':campaign' => $campaignId,
                    ':name' => $name,
                    ':method' => $method,
                    ':configuration' => $json,
                    ':priority' => $priority,
                    ':enabled' => isset($data['enabled']) ? 1 : 0,
                    ':created_by' => $userId
                ]);
            }

            return ['success' => true, 'message' => 'Política guardada correctamente.'];
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditSelectionPolicy::guardar] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo guardar la política. Revisa el log del servidor.'];
        }
    }

    /**
     * Devuelve llamadas elegibles para una política MANUAL.
     * La consulta limita por empresa y campaña, y exige al menos una etapa activa pendiente.
     */
    public function llamadasElegibles(int $tenantId, int $policyId): array
    {
        if (!$this->db || $tenantId <= 0 || $policyId <= 0) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, campaign_id, method, configuration_json
                FROM audit_selection_policies
                WHERE id = :policy AND tenant_id = :tenant AND enabled = 1
                LIMIT 1
            ");
            $stmt->execute([':policy' => $policyId, ':tenant' => $tenantId]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy || $policy['method'] !== 'MANUAL') {
                return [];
            }

            $config = json_decode((string) ($policy['configuration_json'] ?? '{}'), true);
            if (!is_array($config)) {
                $config = [];
            }

            $whereAudio = (
                !array_key_exists('only_with_audio', $config) ||
                $config['only_with_audio'] === true
            ) ? " AND c.audio_status = 'AVAILABLE' " : '';

            $stmt = $this->db->prepare("
                SELECT c.id, c.started_at, c.duration_seconds,
                       c.phone_masked, c.audio_status
                FROM audit_calls c
                WHERE c.tenant_id = :tenant
                  AND c.campaign_id = :campaign
                  AND c.processing_status IN ('NEW', 'READY')
                  {$whereAudio}
                  AND COALESCE(c.duration_seconds, 0) >= :min_duration
                  AND EXISTS (
                      SELECT 1
                      FROM audit_workflow_stages s
                      WHERE s.tenant_id = c.tenant_id
                        AND s.campaign_id = c.campaign_id
                        AND s.status = 'ACTIVE'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM audit_tasks t
                            WHERE t.tenant_id = c.tenant_id
                              AND t.call_id = c.id
                              AND t.stage_id = s.id
                        )
                  )
                ORDER BY c.started_at DESC, c.id DESC
                LIMIT 500
            ");
            $stmt->execute([
                ':tenant' => $tenantId,
                ':campaign' => (int) $policy['campaign_id'],
                ':min_duration' => max(0, (int) ($config['min_duration_seconds'] ?? 0))
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditSelectionPolicy::llamadasElegibles] ' . $e->getMessage());
            return [];
        }
    }

    public function encolarGeneracion(
        int $tenantId,
        int $policyId,
        int $userId,
        string $requestKey,
        ?int $selectedCallId = null
    ): array {
        if (
            !$this->db || $tenantId <= 0 || $policyId <= 0 || $userId <= 0 ||
            !preg_match('/^[a-f0-9]{32,64}$/', $requestKey)
        ) {
            return ['success' => false, 'message' => 'Los datos para encolar la generación no son válidos.'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, campaign_id, method
                FROM audit_selection_policies
                WHERE id = :policy AND tenant_id = :tenant AND enabled = 1
                LIMIT 1
            ");
            $stmt->execute([':policy' => $policyId, ':tenant' => $tenantId]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                return ['success' => false, 'message' => 'La política no existe en esta empresa o está desactivada.'];
            }

            if ($policy['method'] === 'MANUAL') {
                if (!$selectedCallId || $selectedCallId <= 0) {
                    return ['success' => false, 'message' => 'Debes seleccionar una llamada para la política manual.'];
                }

                $candidates = $this->llamadasElegibles($tenantId, $policyId);
                $eligibleIds = array_map(
                    static fn(array $call): int => (int) $call['id'],
                    $candidates
                );
                if (!in_array($selectedCallId, $eligibleIds, true)) {
                    return ['success' => false, 'message' => 'La llamada seleccionada ya no es elegible. Actualiza la página y vuelve a seleccionarla.'];
                }
            } elseif ($selectedCallId !== null) {
                return ['success' => false, 'message' => 'Solo las políticas manuales admiten una llamada seleccionada.'];
            }

            $queue = new AuditJobQueue();
            $idempotencyKey = 'generate-audit-tasks:' . $policyId . ':' .
                ($selectedCallId ?? 0) . ':' . $requestKey;

            $job = $queue->encolar(
                $tenantId,
                'GENERATE_AUDIT_TASKS',
                'AUDIT_SELECTION_POLICY',
                $policyId,
                $idempotencyKey,
                [
                    'assigned_by' => $userId,
                    'selected_call_id' => $selectedCallId
                ],
                100,
                5
            );

            if (empty($job['success'])) {
                return ['success' => false, 'message' => $job['message'] ?? 'No se pudo encolar el trabajo.'];
            }

            return [
                'success' => true,
                'queued' => true,
                'job_id' => (int) $job['id'],
                'message' => 'Solicitud registrada en la cola. El trabajador generará las tareas.'
            ];
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditSelectionPolicy::encolarGeneracion] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo registrar la solicitud. Revisa el log del servidor.'];
        }
    }

    /**
     * Crea tareas para llamadas elegibles. En MANUAL solo procesa el ID elegido.
     * La consulta vuelve a validar empresa, campaña, estado, audio, duración y etapas.
     */
    public function generarTareas(
        int $tenantId,
        int $policyId,
        int $userId,
        ?int $selectedCallId = null
    ): array {
        if (!$this->db || $tenantId <= 0 || $policyId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Parámetros inválidos.'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT *
                FROM audit_selection_policies
                WHERE id = :id AND tenant_id = :tenant AND enabled = 1
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':id' => $policyId, ':tenant' => $tenantId]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'La política no existe o está desactivada.'];
            }

            $isManual = $policy['method'] === 'MANUAL';
            if ($isManual && (!$selectedCallId || $selectedCallId <= 0)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'La política manual requiere una llamada seleccionada.'];
            }
            if (!$isManual && $selectedCallId !== null) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'La llamada seleccionada no corresponde a una política manual.'];
            }

            $campaignId = (int) $policy['campaign_id'];
            $config = json_decode((string) ($policy['configuration_json'] ?? '{}'), true);
            if (!is_array($config)) {
                $config = [];
            }

            $sampleSize = max(1, min(10000, (int) ($config['sample_size'] ?? 1)));
            $minDuration = max(0, (int) ($config['min_duration_seconds'] ?? 0));
            $onlyWithAudio = !array_key_exists('only_with_audio', $config)
                || $config['only_with_audio'] === true;

            $whereAudio = $onlyWithAudio ? " AND c.audio_status = 'AVAILABLE' " : '';
            $whereManual = $isManual ? ' AND c.id = :manual_call_id ' : '';
            $order = $policy['method'] === 'RANDOM'
                ? ' ORDER BY RAND() '
                : ' ORDER BY c.started_at, c.id ';

            if ($isManual) {
                $sampleSize = 1;
            }

            $sql = "
                SELECT c.id
                FROM audit_calls c
                WHERE c.tenant_id = :tenant
                  AND c.campaign_id = :campaign
                  AND c.processing_status IN ('NEW', 'READY')
                  {$whereAudio}
                  AND COALESCE(c.duration_seconds, 0) >= :min_duration
                  {$whereManual}
                  AND EXISTS (
                      SELECT 1
                      FROM audit_workflow_stages s
                      WHERE s.tenant_id = c.tenant_id
                        AND s.campaign_id = c.campaign_id
                        AND s.status = 'ACTIVE'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM audit_tasks t
                            WHERE t.tenant_id = c.tenant_id
                              AND t.call_id = c.id
                              AND t.stage_id = s.id
                        )
                  )
                {$order}
                LIMIT {$sampleSize}
                FOR UPDATE
            ";

            $params = [
                ':tenant' => $tenantId,
                ':campaign' => $campaignId,
                ':min_duration' => $minDuration
            ];
            if ($isManual) {
                $params[':manual_call_id'] = $selectedCallId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($isManual && count($calls) !== 1) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'La llamada seleccionada dejó de ser elegible antes de procesarse. No se crearon tareas.'
                ];
            }

            $created = 0;
            $skipped = 0;

            $stmtStages = $this->db->prepare("
                SELECT id
                FROM audit_workflow_stages
                WHERE tenant_id = :tenant
                  AND campaign_id = :campaign
                  AND status = 'ACTIVE'
                ORDER BY stage_number, id
            ");
            $stmtStages->execute([':tenant' => $tenantId, ':campaign' => $campaignId]);
            $stages = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

            foreach ($calls as $call) {
                $callId = (int) $call['id'];

                foreach ($stages as $stage) {
                    $stageId = (int) $stage['id'];
                    $stmtTask = $this->db->prepare("
                        INSERT IGNORE INTO audit_tasks (
                            tenant_id, call_id, stage_id,
                            assigned_to, assigned_by, status, priority
                        ) VALUES (
                            :tenant, :call_id, :stage_id,
                            NULL, :assigned_by, 'PENDING', :priority
                        )
                    ");
                    $stmtTask->execute([
                        ':tenant' => $tenantId,
                        ':call_id' => $callId,
                        ':stage_id' => $stageId,
                        ':assigned_by' => $userId,
                        ':priority' => (int) $policy['priority']
                    ]);

                    if ($stmtTask->rowCount() > 0) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }

                $stmtReady = $this->db->prepare("
                    UPDATE audit_calls
                    SET processing_status = 'READY'
                    WHERE id = :call_id
                      AND tenant_id = :tenant
                      AND campaign_id = :campaign
                      AND processing_status = 'NEW'
                ");
                $stmtReady->execute([
                    ':call_id' => $callId,
                    ':tenant' => $tenantId,
                    ':campaign' => $campaignId
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'selected' => count($calls),
                'created' => $created,
                'skipped' => $skipped,
                'message' => 'Proceso finalizado. Tareas nuevas: ' . $created .
                    '; tareas ya existentes: ' . $skipped . '.'
            ];
        } catch (Throwable $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[CallAudit][AuditSelectionPolicy::generarTareas] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudieron generar las tareas. Revisa el log del servidor.'];
        }
    }
}

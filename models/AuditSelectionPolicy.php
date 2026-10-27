<?php

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

        return $stmt->fetchAll();
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

        return $stmt->fetchAll();
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
        $priority = filter_var(
            $data['priority'] ?? 100,
            FILTER_VALIDATE_INT
        );

        $allowedMethods = ['MANUAL', 'RANDOM', 'RULES', 'AUTOMATIC'];

        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            return [
                'success' => false,
                'message' => 'El nombre es obligatorio y admite hasta 150 caracteres.'
            ];
        }

        if (!in_array($method, $allowedMethods, true)) {
            return ['success' => false, 'message' => 'Método de selección inválido.'];
        }

        if ($priority === false || $priority < 0 || $priority > 1000000) {
            return ['success' => false, 'message' => 'Prioridad inválida.'];
        }

        $configuration = [
            'sample_size' => 1,
            'min_duration_seconds' => 0,
            'only_with_audio' => true
        ];

        $sampleSize = filter_var(
            $data['sample_size'] ?? 1,
            FILTER_VALIDATE_INT
        );

        $minDuration = filter_var(
            $data['min_duration_seconds'] ?? 0,
            FILTER_VALIDATE_INT
        );

        if (
            $sampleSize === false || $sampleSize < 1 ||
            $sampleSize > 10000
        ) {
            return [
                'success' => false,
                'message' => 'La cantidad debe estar entre 1 y 10000 llamadas por ejecución.'
            ];
        }

        if ($minDuration === false || $minDuration < 0 || $minDuration > 86400) {
            return ['success' => false, 'message' => 'Duración mínima inválida.'];
        }

        $configuration['sample_size'] = $sampleSize;
        $configuration['min_duration_seconds'] = $minDuration;
        $configuration['only_with_audio'] = isset($data['only_with_audio']);

        if ($method === 'RULES') {
            $configuration['rule'] = 'MIN_DURATION';
        }

        if ($method === 'MANUAL') {
            $configuration['sample_size'] = 1;
        }

        $json = json_encode(
            $configuration,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        try {
            $stmt = $this->db->prepare("
                SELECT id
                FROM campaigns
                WHERE id = :campaign
                  AND tenant_id = :tenant
                LIMIT 1
            ");
            $stmt->execute([
                ':campaign' => $campaignId,
                ':tenant' => $tenantId
            ]);

            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'La campaña no pertenece a la empresa activa.'
                ];
            }

            if ($id !== null) {
                $stmt = $this->db->prepare("
                    SELECT id
                    FROM audit_selection_policies
                    WHERE id = :id
                      AND tenant_id = :tenant
                      AND campaign_id = :campaign
                    LIMIT 1
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':tenant' => $tenantId,
                    ':campaign' => $campaignId
                ]);

                if (!$stmt->fetch()) {
                    return [
                        'success' => false,
                        'message' => 'La política no existe en esta campaña.'
                    ];
                }

                $stmt = $this->db->prepare("
                    UPDATE audit_selection_policies
                    SET name = :name,
                        method = :method,
                        configuration_json = :configuration,
                        priority = :priority,
                        enabled = :enabled
                    WHERE id = :id
                      AND tenant_id = :tenant
                      AND campaign_id = :campaign
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
                    INSERT INTO audit_selection_policies (
                        tenant_id, campaign_id, name, method,
                        configuration_json, priority, enabled, created_by
                    ) VALUES (
                        :tenant, :campaign, :name, :method,
                        :configuration, :priority, :enabled, :created_by
                    )
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

            return [
                'success' => true,
                'message' => 'Política guardada correctamente.'
            ];
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditSelectionPolicy::guardar] ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'No se pudo guardar la política. Revisa el log del servidor.'
            ];
        }
    }

    /**
     * Genera tareas para las llamadas elegibles de una política.
     * La restricción UNIQUE de audit_tasks evita duplicados por llamada/etapa.
     *
     * Este método no ejecuta IA ni realiza auditorías: prepara las tareas.
     */
    public function generarTareas(
        int $tenantId,
        int $policyId,
        int $userId
    ): array {
        if (!$this->db || $tenantId <= 0 || $policyId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Parámetros inválidos.'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT *
                FROM audit_selection_policies
                WHERE id = :id
                  AND tenant_id = :tenant
                  AND enabled = 1
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                ':id' => $policyId,
                ':tenant' => $tenantId
            ]);

            $policy = $stmt->fetch();

            if (!$policy) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'La política no existe o está desactivada.'
                ];
            }

            $campaignId = (int) $policy['campaign_id'];
            $config = json_decode(
                (string) ($policy['configuration_json'] ?? '{}'),
                true
            );

            if (!is_array($config)) {
                $config = [];
            }

            $sampleSize = max(
                1,
                min(10000, (int) ($config['sample_size'] ?? 1))
            );

            $minDuration = max(
                0,
                (int) ($config['min_duration_seconds'] ?? 0)
            );

            $onlyWithAudio = !array_key_exists('only_with_audio', $config)
                || $config['only_with_audio'] === true;

            $whereAudio = $onlyWithAudio
                ? " AND c.audio_status = 'AVAILABLE' "
                : '';

            $whereDuration = " AND COALESCE(c.duration_seconds, 0) >= :min_duration ";

            $order = $policy['method'] === 'RANDOM'
                ? ' ORDER BY RAND() '
                : ' ORDER BY c.started_at, c.id ';

            /*
             * Para MANUAL se elige una sola llamada en esta ejecución.
             * La selección manual específica de IDs se añadirá en el
             * flujo de selección manual, sin alterar este modelo de políticas.
             */
            if ($policy['method'] === 'MANUAL') {
                $sampleSize = 1;
            }

            $sql = "
                SELECT c.id
                FROM audit_calls c
                WHERE c.tenant_id = :tenant
                  AND c.campaign_id = :campaign
                  AND c.processing_status IN ('NEW', 'READY')
                  {$whereAudio}
                  {$whereDuration}
                  AND EXISTS (
                      SELECT 1
                      FROM audit_workflow_stages s
                      WHERE s.tenant_id = c.tenant_id
                        AND s.campaign_id = c.campaign_id
                        AND s.status = 'ACTIVE'
                  )
                {$order}
                LIMIT {$sampleSize}
                FOR UPDATE
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant' => $tenantId,
                ':campaign' => $campaignId,
                ':min_duration' => $minDuration
            ]);

            $calls = $stmt->fetchAll();

            $created = 0;
            $skipped = 0;

            foreach ($calls as $call) {
                $callId = (int) $call['id'];

                $stmtStages = $this->db->prepare("
                    SELECT id
                    FROM audit_workflow_stages
                    WHERE tenant_id = :tenant
                      AND campaign_id = :campaign
                      AND status = 'ACTIVE'
                    ORDER BY stage_number, id
                ");
                $stmtStages->execute([
                    ':tenant' => $tenantId,
                    ':campaign' => $campaignId
                ]);

                foreach ($stmtStages->fetchAll() as $stage) {
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('[CallAudit][AuditSelectionPolicy::generarTareas] ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'No se pudieron generar las tareas. Revisa el log del servidor.'
            ];
        }
    }
}
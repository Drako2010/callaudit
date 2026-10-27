<?php

require_once __DIR__ . '/../config/database.php';

class AuditCall
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Lista las llamadas de una empresa.
     * Nunca consulta llamadas de otros tenants.
     */
    public function listar(int $tenantId, array $filtros = []): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                c.id,
                c.tenant_id,
                c.campaign_id,
                c.source_id,
                c.external_call_id,
                c.agent_external_id,
                c.agent_user_id,
                c.phone_masked,
                c.started_at,
                c.duration_seconds,
                c.audio_status,
                c.processing_status,
                c.imported_at,
                ca.name AS campaign_name,
                ts.name AS source_name,
                u.name AS agent_name
            FROM audit_calls c
            INNER JOIN campaigns ca
                ON ca.id = c.campaign_id
               AND ca.tenant_id = c.tenant_id
            LEFT JOIN telephony_sources ts
                ON ts.id = c.source_id
               AND ts.tenant_id = c.tenant_id
            LEFT JOIN users u
                ON u.id = c.agent_user_id
               AND u.tenant_id = c.tenant_id
            WHERE c.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        $campaignId = filter_var(
            $filtros['campaign_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($campaignId !== false && $campaignId !== null && $campaignId > 0) {
            $sql .= " AND c.campaign_id = :campaign_id";
            $params[':campaign_id'] = $campaignId;
        }

        $status = $filtros['processing_status'] ?? '';

        $estadosPermitidos = [
            'NEW', 'READY', 'IN_PROGRESS', 'COMPLETED',
            'REVIEW', 'ERROR', 'ARCHIVED'
        ];

        if (is_string($status) && in_array($status, $estadosPermitidos, true)) {
            $sql .= " AND c.processing_status = :processing_status";
            $params[':processing_status'] = $status;
        }

        $sql .= " ORDER BY c.started_at DESC, c.id DESC LIMIT 500";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditCall::listar] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Opciones de campaña, origen telefónico y agentes de la empresa.
     */
    public function opciones(int $tenantId): array
    {
        $resultado = [
            'campaigns' => [],
            'sources' => [],
            'agents' => []
        ];

        if (!$this->db || $tenantId <= 0) {
            return $resultado;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, name
                FROM campaigns
                WHERE tenant_id = :tenant_id
                  AND status = 'ACTIVE'
                ORDER BY name
            ");
            $stmt->execute([':tenant_id' => $tenantId]);
            $resultado['campaigns'] = $stmt->fetchAll();

            $stmt = $this->db->prepare("
                SELECT id, name, platform, status
                FROM telephony_sources
                WHERE tenant_id = :tenant_id
                  AND status <> 'INACTIVE'
                ORDER BY name
            ");
            $stmt->execute([':tenant_id' => $tenantId]);
            $resultado['sources'] = $stmt->fetchAll();

            $stmt = $this->db->prepare("
                SELECT id, name, email
                FROM users
                WHERE tenant_id = :tenant_id
                  AND status = 'ACTIVE'
                ORDER BY name
            ");
            $stmt->execute([':tenant_id' => $tenantId]);
            $resultado['agents'] = $stmt->fetchAll();

            return $resultado;
        } catch (Throwable $e) {
            error_log('[CallAudit][AuditCall::opciones] ' . $e->getMessage());
            return $resultado;
        }
    }

    /**
     * Registra una llamada manualmente.
     *
     * No recibe rutas de audio del navegador ni guarda audio en la BD.
     * El ID externo evita importar dos veces la misma llamada cuando
     * existe un origen y un identificador externo.
     */
    public function crear(
        int $tenantId,
        int $userId,
        array $data
    ): array {
        if (!$this->db) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar con la base de datos.'
            ];
        }

        if ($tenantId <= 0 || $userId <= 0) {
            return [
                'success' => false,
                'message' => 'El contexto de empresa o usuario no es válido.'
            ];
        }

        $campaignId = filter_var(
            $data['campaign_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $sourceRaw = trim((string) ($data['source_id'] ?? ''));
        $sourceId = $sourceRaw === ''
            ? null
            : filter_var($sourceRaw, FILTER_VALIDATE_INT);

        $agentRaw = trim((string) ($data['agent_user_id'] ?? ''));
        $agentUserId = $agentRaw === ''
            ? null
            : filter_var($agentRaw, FILTER_VALIDATE_INT);

        $externalCallId = trim((string) ($data['external_call_id'] ?? ''));
        $agentExternalId = trim((string) ($data['agent_external_id'] ?? ''));
        $phoneMasked = trim((string) ($data['phone_masked'] ?? ''));
        $startedAt = trim((string) ($data['started_at'] ?? ''));
        $durationRaw = trim((string) ($data['duration_seconds'] ?? ''));

        if (!$campaignId || $campaignId <= 0) {
            return [
                'success' => false,
                'message' => 'Selecciona una campaña válida.'
            ];
        }

        if ($sourceId !== null && (!$sourceId || $sourceId <= 0)) {
            return [
                'success' => false,
                'message' => 'El origen telefónico no es válido.'
            ];
        }

        if ($agentUserId !== null && (!$agentUserId || $agentUserId <= 0)) {
            return [
                'success' => false,
                'message' => 'El agente seleccionado no es válido.'
            ];
        }

        if (mb_strlen($externalCallId, 'UTF-8') > 255) {
            return [
                'success' => false,
                'message' => 'El identificador externo supera los 255 caracteres.'
            ];
        }

        if (mb_strlen($agentExternalId, 'UTF-8') > 255) {
            return [
                'success' => false,
                'message' => 'El identificador externo del agente es demasiado largo.'
            ];
        }

        if (mb_strlen($phoneMasked, 'UTF-8') > 100) {
            return [
                'success' => false,
                'message' => 'El teléfono supera los 100 caracteres.'
            ];
        }

        $duration = null;

        if ($durationRaw !== '') {
            $duration = filter_var($durationRaw, FILTER_VALIDATE_INT);

            if ($duration === false || $duration < 0 || $duration > 2147483647) {
                return [
                    'success' => false,
                    'message' => 'La duración debe ser un número entero igual o mayor que cero.'
                ];
            }
        }

        $startedAtDb = null;

        if ($startedAt !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startedAt);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                !$date ||
                ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            ) {
                return [
                    'success' => false,
                    'message' => 'La fecha y hora de la llamada no son válidas.'
                ];
            }

            $startedAtDb = $date->format('Y-m-d H:i:s');
        }

        $externalCallId = $externalCallId === '' ? null : $externalCallId;
        $agentExternalId = $agentExternalId === '' ? null : $agentExternalId;
        $phoneMasked = $phoneMasked === '' ? null : $phoneMasked;

        try {
            $this->db->beginTransaction();

            // La campaña debe pertenecer al tenant activo.
            $stmt = $this->db->prepare("
                SELECT id
                FROM campaigns
                WHERE id = :campaign_id
                  AND tenant_id = :tenant_id
                  AND status = 'ACTIVE'
                LIMIT 1
            ");
            $stmt->execute([
                ':campaign_id' => $campaignId,
                ':tenant_id' => $tenantId
            ]);

            if (!$stmt->fetch()) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'La campaña no existe, no está activa o pertenece a otra empresa.'
                ];
            }

            // Si se especificó un origen, debe pertenecer al mismo tenant.
            if ($sourceId !== null) {
                $stmt = $this->db->prepare("
                    SELECT id
                    FROM telephony_sources
                    WHERE id = :source_id
                      AND tenant_id = :tenant_id
                      AND status <> 'INACTIVE'
                    LIMIT 1
                ");
                $stmt->execute([
                    ':source_id' => $sourceId,
                    ':tenant_id' => $tenantId
                ]);

                if (!$stmt->fetch()) {
                    $this->db->rollBack();

                    return [
                        'success' => false,
                        'message' => 'El origen telefónico no pertenece a esta empresa o está inactivo.'
                    ];
                }
            }

            // El agente, si se indicó, también debe ser de la misma empresa.
            if ($agentUserId !== null) {
                $stmt = $this->db->prepare("
                    SELECT id
                    FROM users
                    WHERE id = :agent_id
                      AND tenant_id = :tenant_id
                      AND status = 'ACTIVE'
                    LIMIT 1
                ");
                $stmt->execute([
                    ':agent_id' => $agentUserId,
                    ':tenant_id' => $tenantId
                ]);

                if (!$stmt->fetch()) {
                    $this->db->rollBack();

                    return [
                        'success' => false,
                        'message' => 'El agente no pertenece a esta empresa o está inactivo.'
                    ];
                }
            }

            // Evitar duplicados de una llamada identificada por origen + ID externo.
            if ($sourceId !== null && $externalCallId !== null) {
                $stmt = $this->db->prepare("
                    SELECT id
                    FROM audit_calls
                    WHERE tenant_id = :tenant_id
                      AND source_id = :source_id
                      AND external_call_id = :external_call_id
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([
                    ':tenant_id' => $tenantId,
                    ':source_id' => $sourceId,
                    ':external_call_id' => $externalCallId
                ]);

                $existente = $stmt->fetch();

                if ($existente) {
                    $this->db->commit();

                    return [
                        'success' => true,
                        'duplicate' => true,
                        'id' => (int) $existente['id'],
                        'message' => 'La llamada ya estaba registrada. No se creó un duplicado.'
                    ];
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_calls (
                    tenant_id,
                    campaign_id,
                    source_id,
                    external_call_id,
                    agent_external_id,
                    agent_user_id,
                    phone_masked,
                    started_at,
                    duration_seconds,
                    audio_status,
                    processing_status,
                    imported_at
                ) VALUES (
                    :tenant_id,
                    :campaign_id,
                    :source_id,
                    :external_call_id,
                    :agent_external_id,
                    :agent_user_id,
                    :phone_masked,
                    :started_at,
                    :duration_seconds,
                    'NOT_RECEIVED',
                    'NEW',
                    NOW()
                )
            ");

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':campaign_id' => $campaignId,
                ':source_id' => $sourceId,
                ':external_call_id' => $externalCallId,
                ':agent_external_id' => $agentExternalId,
                ':agent_user_id' => $agentUserId,
                ':phone_masked' => $phoneMasked,
                ':started_at' => $startedAtDb,
                ':duration_seconds' => $duration
            ]);

            $id = (int) $this->db->lastInsertId();

            $this->db->commit();

            return [
                'success' => true,
                'duplicate' => false,
                'id' => $id,
                'message' => 'Llamada registrada correctamente. El audio todavía no se ha recibido.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('[CallAudit][AuditCall::crear] ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'No se pudo registrar la llamada. Revisa el log del servidor.'
            ];
        }
    }
}
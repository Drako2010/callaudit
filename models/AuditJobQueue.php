
<?php

require_once __DIR__ . '/../config/database.php';

class AuditJobQueue
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Inserta un trabajo sin duplicar su clave de idempotencia.
     * Devuelve el ID existente o nuevo.
     */
    public function encolar(
        int $tenantId,
        string $tipo,
        string $entidadTipo,
        int $entidadId,
        string $clave,
        array $payload = [],
        int $prioridad = 100,
        int $maxIntentos = 5
    ): array {
        if (!$this->db) {
            return ['success' => false, 'message' => 'Sin conexión a la base de datos.'];
        }

        $tipo = trim($tipo);
        $entidadTipo = trim($entidadTipo);
        $clave = trim($clave);

        if (
            $tenantId <= 0 ||
            $tipo === '' ||
            $entidadTipo === '' ||
            $entidadId <= 0 ||
            $clave === '' ||
            mb_strlen($tipo) > 100 ||
            mb_strlen($entidadTipo) > 100 ||
            mb_strlen($clave) > 191
        ) {
            return ['success' => false, 'message' => 'Datos del trabajo inválidos.'];
        }

        $maxIntentos = max(1, min(20, $maxIntentos));
        $prioridad = max(0, min(100000, $prioridad));

        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            $sql = "INSERT INTO audit_jobs
                    (tenant_id, job_type, entity_type, entity_id,
                     idempotency_key, payload_json, status, priority,
                     attempts, max_attempts, available_at)
                    VALUES
                    (:tenant, :tipo, :entidad_tipo, :entidad_id,
                     :clave, :payload, 'PENDING', :prioridad,
                     0, :max_intentos, NOW())
                    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant' => $tenantId,
                ':tipo' => $tipo,
                ':entidad_tipo' => $entidadTipo,
                ':entidad_id' => $entidadId,
                ':clave' => $clave,
                ':payload' => $json,
                ':prioridad' => $prioridad,
                ':max_intentos' => $maxIntentos
            ]);

            $id = (int) $this->db->lastInsertId();

            if ($id <= 0) {
                $q = $this->db->prepare(
                    "SELECT id FROM audit_jobs
                     WHERE tenant_id = :tenant AND idempotency_key = :clave
                     LIMIT 1"
                );
                $q->execute([':tenant' => $tenantId, ':clave' => $clave]);
                $id = (int) $q->fetchColumn();
            }

            return [
                'success' => $id > 0,
                'id' => $id,
                'message' => $id > 0
                    ? 'Trabajo registrado o ya existente.'
                    : 'No se pudo identificar el trabajo.'
            ];
        } catch (Throwable $e) {
            error_log('[AuditJobQueue::encolar] ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al registrar el trabajo.'];
        }
    }

    /**
     * Recupera un trabajo vencido y lo bloquea para este trabajador.
     * Solo reclama tipos que tengan un procesador registrado.
     */
    public function reclamar(array $tipos, string $workerId): ?array
    {
        if (!$this->db || !$tipos || trim($workerId) === '') {
            return null;
        }

        $tipos = array_values(array_unique(array_filter(
            $tipos,
            static fn($tipo) => is_string($tipo)
                && trim($tipo) !== ''
                && mb_strlen($tipo) <= 100
        )));

        if (!$tipos) {
            return null;
        }

        $workerId = mb_substr($workerId, 0, 150);
        $placeholders = implode(',', array_fill(0, count($tipos), '?'));

        try {
            $this->db->beginTransaction();

            $sql = "SELECT *
                    FROM audit_jobs
                    WHERE status IN ('PENDING', 'RETRY')
                      AND available_at <= NOW()
                      AND attempts < max_attempts
                      AND job_type IN ($placeholders)
                    ORDER BY priority ASC, available_at ASC, id ASC
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($tipos);
            $job = $stmt->fetch();

            if (!$job) {
                $this->db->commit();
                return null;
            }

            $update = $this->db->prepare(
                "UPDATE audit_jobs
                 SET status = 'RUNNING',
                     attempts = attempts + 1,
                     locked_at = NOW(),
                     locked_by = :worker,
                     last_error_code = NULL,
                     last_error_message = NULL
                 WHERE id = :id AND tenant_id = :tenant
                   AND status IN ('PENDING', 'RETRY')"
            );

            $update->execute([
                ':worker' => $workerId,
                ':id' => (int) $job['id'],
                ':tenant' => (int) $job['tenant_id']
            ]);

            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return null;
            }

            $this->db->commit();

            $job['attempts'] = (int) $job['attempts'] + 1;
            $job['payload'] = null;

            if ($job['payload_json'] !== null) {
                $decoded = json_decode($job['payload_json'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $job['payload'] = $decoded;
                }
            }

            return $job;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('[AuditJobQueue::reclamar] ' . $e->getMessage());
            return null;
        }
    }

    public function completar(int $tenantId, int $jobId, string $workerId): bool
    {
        if (!$this->db || $tenantId <= 0 || $jobId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE audit_jobs
             SET status = 'COMPLETED',
                 completed_at = NOW(),
                 locked_at = NULL,
                 locked_by = NULL,
                 last_error_code = NULL,
                 last_error_message = NULL
             WHERE id = :id AND tenant_id = :tenant
               AND status = 'RUNNING' AND locked_by = :worker"
        );

        $stmt->execute([
            ':id' => $jobId,
            ':tenant' => $tenantId,
            ':worker' => $workerId
        ]);

        return $stmt->rowCount() === 1;
    }

    public function fallar(
        int $tenantId,
        int $jobId,
        string $workerId,
        string $codigo,
        string $mensaje
    ): bool {
        if (!$this->db || $tenantId <= 0 || $jobId <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE audit_jobs
                 SET status = CASE
                        WHEN attempts < max_attempts THEN 'RETRY'
                        ELSE 'FAILED'
                     END,
                     available_at = CASE
                        WHEN attempts < max_attempts
                        THEN DATE_ADD(
                            NOW(),
                            INTERVAL LEAST(3600, POW(2, LEAST(attempts, 10))) SECOND
                        )
                        ELSE available_at
                     END,
                     locked_at = NULL,
                     locked_by = NULL,
                     last_error_code = :codigo,
                     last_error_message = :mensaje
                 WHERE id = :id AND tenant_id = :tenant
                   AND status = 'RUNNING' AND locked_by = :worker"
            );

            $stmt->execute([
                ':codigo' => mb_substr($codigo, 0, 100),
                ':mensaje' => mb_substr($mensaje, 0, 1000),
                ':id' => $jobId,
                ':tenant' => $tenantId,
                ':worker' => $workerId
            ]);

            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            error_log('[AuditJobQueue::fallar] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recupera bloqueos antiguos.
     * No modifica trabajos cuyo bloqueo todavía está vigente.
     */
    public function recuperarBloqueados(int $minutos = 15): int
    {
        if (!$this->db) {
            return 0;
        }

        $minutos = max(1, min(1440, $minutos));

        try {
            $stmt = $this->db->prepare(
                "UPDATE audit_jobs
                 SET status = CASE
                        WHEN attempts < max_attempts THEN 'RETRY'
                        ELSE 'FAILED'
                     END,
                     available_at = CASE
                        WHEN attempts < max_attempts THEN NOW()
                        ELSE available_at
                     END,
                     locked_at = NULL,
                     locked_by = NULL,
                     last_error_code = 'WORKER_TIMEOUT',
                     last_error_message = 'El trabajador dejó el trabajo bloqueado; se recuperó automáticamente.'
                 WHERE status = 'RUNNING'
                   AND locked_at IS NOT NULL
                   AND locked_at < DATE_SUB(NOW(), INTERVAL $minutos MINUTE)"
            );

            $stmt->execute();
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('[AuditJobQueue::recuperarBloqueados] ' . $e->getMessage());
            return 0;
        }
    }
}

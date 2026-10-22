<?php
/**
 * CallAudit - Modelo de fuentes telefónicas.
 *
 * Administra el registro de fuentes de una empresa. Este módulo configura
 * metadatos de conexión; no ejecuta todavía importaciones ni conexiones
 * remotas. Las credenciales no se solicitan ni almacenan en texto plano.
 */
require_once __DIR__ . '/../config/database.php';

class TelephonySource
{
    private ?PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function listar(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT id, tenant_id, name, platform, connection_mode,
                           host, port, database_name, status, last_test_at,
                           last_error_code, last_error_at, created_at, updated_at
                    FROM telephony_sources
                    WHERE tenant_id = :tenant_id
                    ORDER BY id DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':tenant_id' => $tenantId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('[CallAudit][TelephonySource::listar] ' . $e->getMessage());
            return [];
        }
    }

    public function obtener(int $tenantId, int $id): ?array
    {
        if (!$this->db || $tenantId <= 0 || $id <= 0) {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT id, tenant_id, name, platform, connection_mode,
                        host, port, database_name, status, last_test_at,
                        last_error_code, last_error_at, created_at, updated_at
                 FROM telephony_sources
                 WHERE tenant_id = :tenant_id AND id = :id
                 LIMIT 1"
            );
            $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('[CallAudit][TelephonySource::obtener] ' . $e->getMessage());
            return null;
        }
    }

    public function guardar(
        int $tenantId,
        int $userId,
        ?int $id,
        string $name,
        string $platform,
        string $mode,
        ?string $host,
        ?int $port,
        ?string $databaseName
    ): array {
        if (!$this->db) {
            return ['success' => false, 'message' => 'No se pudo conectar con la base de datos.'];
        }

        $name = trim($name);
        $host = trim((string)$host);
        $databaseName = trim((string)$databaseName);
        $host = $host === '' ? null : $host;
        $databaseName = $databaseName === '' ? null : $databaseName;

        $platforms = ['ASTERISK','VICIDIAL','ISSABEL','GENERIC_API','DATABASE','CSV','EXCEL','MANUAL','OTHER'];
        $modes = ['API','READONLY_DATABASE','FILE_IMPORT','MANUAL','OTHER'];

        if ($tenantId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'El contexto de empresa o usuario no es válido.'];
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            return ['success' => false, 'message' => 'El nombre es obligatorio y no puede superar 150 caracteres.'];
        }
        if (!in_array($platform, $platforms, true) || !in_array($mode, $modes, true)) {
            return ['success' => false, 'message' => 'La plataforma o el modo de conexión no son válidos.'];
        }
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return ['success' => false, 'message' => 'El puerto debe estar entre 1 y 65535.'];
        }
        if ($mode === 'READONLY_DATABASE' && ($host === null || $databaseName === null)) {
            return ['success' => false, 'message' => 'Para una conexión de base de datos indica host y nombre de base de datos.'];
        }

        try {
            if ($id !== null) {
                // Confirmar propiedad del registro antes de modificarlo.
                $check = $this->db->prepare(
                    "SELECT id FROM telephony_sources WHERE id = :id AND tenant_id = :tenant_id LIMIT 1"
                );
                $check->execute([':id' => $id, ':tenant_id' => $tenantId]);
                if (!$check->fetchColumn()) {
                    return ['success' => false, 'message' => 'La fuente no existe en la empresa seleccionada.'];
                }

                $sql = "UPDATE telephony_sources
                        SET name = :name, platform = :platform,
                            connection_mode = :mode, host = :host, port = :port,
                            database_name = :database_name,
                            status = 'PENDING_TEST', last_error_code = NULL,
                            last_error_at = NULL
                        WHERE id = :id AND tenant_id = :tenant_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':name' => $name, ':platform' => $platform, ':mode' => $mode,
                    ':host' => $host, ':port' => $port, ':database_name' => $databaseName,
                    ':id' => $id, ':tenant_id' => $tenantId
                ]);
                return ['success' => true, 'message' => 'Fuente actualizada. Queda pendiente probar la conexión.'];
            }

            $sql = "INSERT INTO telephony_sources
                        (tenant_id, name, platform, connection_mode, host, port,
                         database_name, status, created_by)
                    VALUES
                        (:tenant_id, :name, :platform, :mode, :host, :port,
                         :database_name, 'PENDING_TEST', :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant_id' => $tenantId, ':name' => $name,
                ':platform' => $platform, ':mode' => $mode, ':host' => $host,
                ':port' => $port, ':database_name' => $databaseName,
                ':created_by' => $userId
            ]);
            return ['success' => true, 'message' => 'Fuente registrada. Queda pendiente configurar credenciales seguras y probar la conexión.'];
        } catch (Throwable $e) {
            error_log('[CallAudit][TelephonySource::guardar] ' . get_class($e) . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo guardar la fuente. Revisa el registro de errores del servidor.'];
        }
    }

    public function cambiarEstado(int $tenantId, int $id, string $status): array
    {
        if (!$this->db || $tenantId <= 0 || $id <= 0 ||
            !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            return ['success' => false, 'message' => 'Los datos de la operación no son válidos.'];
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE telephony_sources
                 SET status = :status
                 WHERE id = :id AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                ':status' => $status, ':id' => $id, ':tenant_id' => $tenantId
            ]);
            if ($stmt->rowCount() < 1) {
                // rowCount puede ser cero si ya tenía ese estado; comprobar existencia.
                if ($this->obtener($tenantId, $id) === null) {
                    return ['success' => false, 'message' => 'La fuente no existe en la empresa seleccionada.'];
                }
            }
            return ['success' => true, 'message' => 'Estado actualizado.'];
        } catch (Throwable $e) {
            error_log('[CallAudit][TelephonySource::cambiarEstado] ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo cambiar el estado de la fuente.'];
        }
    }
}

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/TelephonySecretVault.php';

/** Acceso a secretos y pruebas controladas de fuentes telefónicas. */
class TelephonySourceSecurity
{
    private ?PDO $db;
    private TelephonySecretVault $vault;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->vault = new TelephonySecretVault();
    }

    private function fuente(int $tenantId, int $sourceId, bool $includeSecret = false): ?array
    {
        if (!$this->db || $tenantId <= 0 || $sourceId <= 0) return null;
        $fields = 'id, tenant_id, name, platform, connection_mode, host, port, database_name, status';
        if ($includeSecret) $fields .= ', encrypted_secret, secret_key_version';
        $stmt = $this->db->prepare("SELECT $fields FROM telephony_sources WHERE tenant_id = :tenant AND id = :id LIMIT 1");
        $stmt->execute([':tenant' => $tenantId, ':id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function tieneCredenciales(int $tenantId, int $sourceId): bool
    {
        $row = $this->fuente($tenantId, $sourceId, true);
        return $row !== null && is_string($row['encrypted_secret']) && $row['encrypted_secret'] !== '';
    }

    public function guardarCredenciales(int $tenantId, int $sourceId, string $username, string $password): array
    {
        if (!$this->db) return ['success' => false, 'message' => 'No se pudo conectar con la base de datos de CallAudit.'];
        $source = $this->fuente($tenantId, $sourceId);
        if (!$source) return ['success' => false, 'message' => 'La fuente no existe en la empresa seleccionada.'];
        if ($source['connection_mode'] !== 'READONLY_DATABASE') {
            return ['success' => false, 'message' => 'En este bloque, el almacén de credenciales está habilitado para fuentes de base de datos de solo lectura.'];
        }
        if (trim($username) === '' || strlen($username) > 150 || $password === '' || strlen($password) > 4096) {
            return ['success' => false, 'message' => 'Indica un usuario y una contraseña válidos.'];
        }
        try {
            $encrypted = $this->vault->cifrar(['username' => $username, 'password' => $password]);
            $stmt = $this->db->prepare("UPDATE telephony_sources SET encrypted_secret = :secret, secret_key_version = :version, status = 'PENDING_TEST', last_error_code = NULL, last_error_at = NULL WHERE tenant_id = :tenant AND id = :id");
            $stmt->bindValue(':secret', $encrypted['blob'], PDO::PARAM_LOB);
            $stmt->bindValue(':version', $encrypted['key_version'], PDO::PARAM_STR);
            $stmt->bindValue(':tenant', $tenantId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $sourceId, PDO::PARAM_INT);
            $stmt->execute();
            return ['success' => true, 'message' => 'Credenciales cifradas y guardadas. No se muestran nuevamente. Ejecuta la prueba de conexión.'];
        } catch (Throwable $e) {
            error_log('[CallAudit][TelephonySourceSecurity::guardarCredenciales] ' . get_class($e) . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudieron guardar las credenciales. Verifica la clave externa y el registro de errores.'];
        }
    }

    public function probarConexion(int $tenantId, int $sourceId): array
    {
        if (!$this->db) return ['success' => false, 'message' => 'No se pudo conectar con la base de datos de CallAudit.'];
        $source = $this->fuente($tenantId, $sourceId, true);
        if (!$source) return ['success' => false, 'message' => 'La fuente no existe en la empresa seleccionada.'];
        if ($source['connection_mode'] !== 'READONLY_DATABASE') {
            return ['success' => false, 'message' => 'La prueba automática está habilitada en este bloque únicamente para fuentes READONLY_DATABASE. Las fuentes API/PBX requieren su adaptador específico.'];
        }
        try {
            if (!is_string($source['encrypted_secret']) || $source['encrypted_secret'] === '') {
                throw new RuntimeException('MISSING_CREDENTIALS');
            }
            $secret = $this->vault->descifrar((string)$source['encrypted_secret']);
            $host = trim((string)($source['host'] ?? ''));
            $database = trim((string)($source['database_name'] ?? ''));
            $port = (int)($source['port'] ?? 3306);
            // Evita inyección en DSN y mantiene el alcance del tester en MySQL/MariaDB.
            if ($host === '' || strlen($host) > 253 || !preg_match('/^[A-Za-z0-9.-]+$/', $host) || str_starts_with($host, '.') || str_ends_with($host, '.')) {
                throw new RuntimeException('INVALID_HOST');
            }
            if ($database === '' || !preg_match('/^[A-Za-z0-9_$-]{1,150}$/', $database)) throw new RuntimeException('INVALID_DATABASE');
            if ($port < 1 || $port > 65535) $port = 3306;
            $username = $secret['username'] ?? null;
            $password = $secret['password'] ?? null;
            if (!is_string($username) || !is_string($password) || $username === '') throw new RuntimeException('INVALID_CREDENTIALS');
            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5];
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET SESSION TRANSACTION READ ONLY';
            $remote = new PDO($dsn, $username, $password, $options);
            $check = $remote->query('SELECT 1 AS callaudit_connection_ok')->fetchColumn();
            $remote = null;
            if ((string)$check !== '1') throw new RuntimeException('TEST_QUERY_FAILED');
            $this->actualizarResultado($tenantId, $sourceId, true, null);
            return ['success' => true, 'message' => 'Conexión correcta. La consulta de verificación SELECT 1 respondió. Usa una cuenta remota dedicada con privilegios estrictamente de solo lectura.'];
        } catch (Throwable $e) {
            $code = $e->getMessage();
            $safeCode = in_array($code, ['MISSING_CREDENTIALS','INVALID_HOST','INVALID_DATABASE','INVALID_CREDENTIALS','TEST_QUERY_FAILED'], true) ? $code : 'CONNECTION_FAILED';
            $this->actualizarResultado($tenantId, $sourceId, false, $safeCode);
            error_log('[CallAudit][TelephonySourceSecurity::probarConexion] source_id=' . $sourceId . ' code=' . $safeCode . ' error=' . get_class($e) . ': ' . $code);
            $messages = [
                'MISSING_CREDENTIALS' => 'Primero guarda las credenciales cifradas.',
                'INVALID_HOST' => 'El host no tiene un formato válido. Usa un nombre DNS o una dirección IPv4.',
                'INVALID_DATABASE' => 'El nombre de base de datos contiene caracteres no permitidos.',
                'INVALID_CREDENTIALS' => 'Las credenciales guardadas no tienen el formato esperado.',
                'TEST_QUERY_FAILED' => 'El servidor respondió, pero la consulta de verificación falló.',
                'CONNECTION_FAILED' => 'No se pudo conectar. Revisa host, puerto, base de datos, credenciales, firewall y que PDO MySQL esté habilitado.'
            ];
            return ['success' => false, 'message' => $messages[$safeCode]];
        }
    }

    private function actualizarResultado(int $tenantId, int $sourceId, bool $success, ?string $errorCode): void
    {
        $status = $success ? 'ACTIVE' : 'ERROR';
        $stmt = $this->db->prepare("UPDATE telephony_sources SET status = :status, last_test_at = NOW(), last_error_code = :error_code, last_error_at = CASE WHEN :failed = 1 THEN NOW() ELSE NULL END WHERE tenant_id = :tenant AND id = :id");
        $stmt->execute([':status' => $status, ':error_code' => $errorCode, ':failed' => $success ? 0 : 1, ':tenant' => $tenantId, ':id' => $sourceId]);
    }
}

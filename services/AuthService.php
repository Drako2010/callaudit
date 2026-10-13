<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Servicio de autenticación de CallAudit.
 *
 * Permite:
 * - Autenticar cuentas que comparten correo entre empresas.
 * - Validar que el usuario y su empresa estén activos.
 * - Exigir un rol activo de la empresa correspondiente.
 * - Separar el acceso global SUPERADMIN del acceso empresarial.
 */
class AuthService
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Valida el correo y la contraseña.
     *
     * Retornos posibles:
     * GLOBAL:
     *   ['tipo' => 'GLOBAL', 'usuario' => [...]]
     *
     * EMPRESA_UNICA:
     *   ['tipo' => 'EMPRESA_UNICA', 'usuario' => [...]]
     *
     * SELECCIONAR_EMPRESA:
     *   ['tipo' => 'SELECCIONAR_EMPRESA', 'empresas' => [...]]
     *
     * null:
     *   No existe una cuenta válida con esas credenciales.
     */
    public function autenticar(
        string $email,
        string $password
    ): ?array {

        if ($this->db === null) {
            return null;
        }

        /*
         * No utilizamos LIMIT 1, porque el mismo correo puede
         * existir en distintas empresas.
         */
        $sql = "SELECT
                    u.id,
                    u.tenant_id,
                    u.name,
                    u.email,
                    u.password,
                    u.status,
                    t.name AS tenant_name,
                    t.status AS tenant_status
                FROM users u
                LEFT JOIN tenants t
                    ON t.id = u.tenant_id
                WHERE u.email = :email
                ORDER BY u.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':email' => $email
        ]);

        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $empresasValidas = [];

        foreach ($usuarios as $usuario) {

            // Rechazar usuarios inactivos.
            if ($usuario['status'] !== 'ACTIVE') {
                continue;
            }

            /*
             * Verificar la contraseña tal como fue ingresada.
             * No aplicar trim() a la contraseña.
             */
            if (!password_verify(
                $password,
                $usuario['password']
            )) {
                continue;
            }

            $userId = (int) $usuario['id'];

            /*
             * Acceso global:
             * el usuario no pertenece a una empresa y tiene
             * asignado el rol global SUPERADMIN activo.
             */
            if ($usuario['tenant_id'] === null) {

                if ($this->esSuperAdmin($userId)) {

                    unset(
                        $usuario['password'],
                        $usuario['tenant_status']
                    );

                    $usuario['id'] = $userId;
                    $usuario['tenant_id'] = null;
                    $usuario['scope'] = 'GLOBAL';

                    return [
                        'tipo' => 'GLOBAL',
                        'usuario' => $usuario
                    ];
                }

                // Una cuenta sin empresa no es automáticamente global.
                continue;
            }

            $tenantId = (int) $usuario['tenant_id'];

            // La empresa debe estar activa.
            if ($usuario['tenant_status'] !== 'ACTIVE') {
                continue;
            }

            /*
             * El usuario debe tener al menos un rol activo
             * perteneciente a su propia empresa.
             */
            if (!$this->tieneRolActivoDeEmpresa(
                $userId,
                $tenantId
            )) {
                continue;
            }

            // No devolver el hash de la contraseña al controlador.
            unset(
                $usuario['password'],
                $usuario['tenant_status']
            );

            $usuario['id'] = $userId;
            $usuario['tenant_id'] = $tenantId;
            $usuario['scope'] = 'TENANT';

            $empresasValidas[] = $usuario;
        }

        // Ninguna cuenta superó todas las validaciones.
        if (count($empresasValidas) === 0) {
            return null;
        }

        // Una única cuenta válida: acceso directo a esa empresa.
        if (count($empresasValidas) === 1) {
            return [
                'tipo' => 'EMPRESA_UNICA',
                'usuario' => $empresasValidas[0]
            ];
        }

        /*
         * Hay varias cuentas válidas.
         * login.php deberá mostrar únicamente estas empresas.
         */
        return [
            'tipo' => 'SELECCIONAR_EMPRESA',
            'empresas' => $empresasValidas
        ];
    }

    /**
     * Comprueba si el usuario tiene un rol activo de la empresa.
     *
     * La relación se valida usando user_roles y roles.
     */
    private function tieneRolActivoDeEmpresa(
        int $userId,
        int $tenantId
    ): bool {

        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT 1
                FROM user_roles ur
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE ur.user_id = :user_id
                  AND r.tenant_id = :tenant_id
                  AND r.status = 'ACTIVE'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Comprueba que el usuario sea un SUPERADMIN global activo.
     *
     * No basta con tener tenant_id NULL: también debe existir
     * una asignación al rol global SUPERADMIN activo.
     */
    public function esSuperAdmin(int $userId): bool
    {
        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT 1
                FROM users u
                INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE u.id = :user_id
                  AND u.tenant_id IS NULL
                  AND u.status = 'ACTIVE'
                  AND r.slug = 'SUPERADMIN'
                  AND r.tenant_id IS NULL
                  AND r.status = 'ACTIVE'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Vuelve a validar una cuenta de empresa antes de crear
     * la sesión después de seleccionar una empresa.
     *
     * La selección también deberá compararse con la lista
     * de cuentas guardada en la sesión del servidor.
     */
    public function obtenerUsuarioEmpresaActivo(
        int $userId
    ): ?array {

        if ($this->db === null) {
            return null;
        }

        $sql = "SELECT
                    u.id,
                    u.tenant_id,
                    u.name,
                    u.email,
                    u.status,
                    t.name AS tenant_name,
                    t.status AS tenant_status
                FROM users u
                INNER JOIN tenants t
                    ON t.id = u.tenant_id
                WHERE u.id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario === false) {
            return null;
        }

        // Validar el estado de la cuenta.
        if ($usuario['status'] !== 'ACTIVE') {
            return null;
        }

        // Validar el estado de la empresa.
        if ($usuario['tenant_status'] !== 'ACTIVE') {
            return null;
        }

        $userId = (int) $usuario['id'];
        $tenantId = (int) $usuario['tenant_id'];

        // Confirmar que conserva un rol activo de esa empresa.
        if (!$this->tieneRolActivoDeEmpresa(
            $userId,
            $tenantId
        )) {
            return null;
        }

        unset($usuario['tenant_status']);

        $usuario['id'] = $userId;
        $usuario['tenant_id'] = $tenantId;
        $usuario['scope'] = 'TENANT';

        return $usuario;
    }
}
<?php

require_once __DIR__ . '/../config/database.php';

class Authorization
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect();
    }

    public function tienePermiso(
        int $userId,
        string $permissionSlug
    ): bool {

        if ($this->db === null) {
            return false;
        }

        /*
         * 1. Verificar que el usuario exista y esté activo.
         */

        $sqlUser = "SELECT
                        id,
                        tenant_id,
                        status
                    FROM users
                    WHERE id = :user_id
                    LIMIT 1";

        $stmtUser = $this->db->prepare($sqlUser);

        $stmtUser->execute([
            ':user_id' => $userId
        ]);

        $user = $stmtUser->fetch();

        if ($user === false) {
            return false;
        }

        if ($user['status'] !== 'ACTIVE') {
            return false;
        }

        /*
         * 2. Verificar DENY individual.
         */

        $sqlDeny = "SELECT
                        up.user_id
                    FROM user_permissions up
                    INNER JOIN permissions p
                        ON p.id = up.permission_id
                    WHERE up.user_id = :user_id
                    AND p.slug = :permission_slug
                    AND p.status = 'ACTIVE'
                    AND up.effect = 'DENY'
                    LIMIT 1";

        $stmtDeny = $this->db->prepare($sqlDeny);

        $stmtDeny->execute([
            ':user_id' => $userId,
            ':permission_slug' => $permissionSlug
        ]);

        if ($stmtDeny->fetch() !== false) {
            return false;
        }

        /*
         * 3. Verificar GRANT individual.
         */

        $sqlGrant = "SELECT
                        up.user_id
                    FROM user_permissions up
                    INNER JOIN permissions p
                        ON p.id = up.permission_id
                    WHERE up.user_id = :user_id
                    AND p.slug = :permission_slug
                    AND p.status = 'ACTIVE'
                    AND up.effect = 'GRANT'
                    LIMIT 1";

        $stmtGrant = $this->db->prepare($sqlGrant);

        $stmtGrant->execute([
            ':user_id' => $userId,
            ':permission_slug' => $permissionSlug
        ]);

        if ($stmtGrant->fetch() !== false) {
            return true;
        }

        /*
         * 4. Verificar permisos provenientes de roles.
         */

        $sqlRole = "SELECT
                        rp.role_id
                    FROM user_roles ur
                    INNER JOIN roles r
                        ON r.id = ur.role_id
                    INNER JOIN role_permissions rp
                        ON rp.role_id = r.id
                    INNER JOIN permissions p
                        ON p.id = rp.permission_id
                    WHERE ur.user_id = :user_id
                    AND r.status = 'ACTIVE'
                    AND p.status = 'ACTIVE'
                    AND p.slug = :permission_slug
                    AND (
                        r.tenant_id IS NULL
                        OR r.tenant_id = :tenant_id
                    )
                    LIMIT 1";

        $stmtRole = $this->db->prepare($sqlRole);

        $stmtRole->execute([
            ':user_id' => $userId,
            ':permission_slug' => $permissionSlug,
            ':tenant_id' => $user['tenant_id']
        ]);

        if ($stmtRole->fetch() !== false) {
            return true;
        }

        /*
         * 5. Si no existe ninguna autorización,
         *    se deniega el acceso.
         */

        return false;
    }
}
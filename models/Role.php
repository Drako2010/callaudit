<?php

require_once __DIR__ . '/../config/database.php';

class Role
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect();
    }

    public function listarPorTenant(?int $tenantId): array
    {
        if ($this->db === null) {
            return [];
        }

        if ($tenantId === null) {

            $sql = "SELECT
                        id,
                        tenant_id,
                        name,
                        slug,
                        description,
                        status,
                        created_at,
                        updated_at
                    FROM roles
                    WHERE tenant_id IS NULL
                    ORDER BY id ASC";

            $stmt = $this->db->query($sql);

            return $stmt->fetchAll();
        }

        $sql = "SELECT
                    id,
                    tenant_id,
                    name,
                    slug,
                    description,
                    status,
                    created_at,
                    updated_at
                FROM roles
                WHERE tenant_id = :tenant_id
                ORDER BY id ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = "SELECT
                    id,
                    tenant_id,
                    name,
                    slug,
                    description,
                    status
                FROM roles
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $role = $stmt->fetch();

        if ($role === false) {
            return null;
        }

        return $role;
    }

    public function listarPorUsuario(int $userId): array
    {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    r.id,
                    r.tenant_id,
                    r.name,
                    r.slug,
                    r.description,
                    r.status
                FROM roles r
                INNER JOIN user_roles ur
                    ON ur.role_id = r.id
                WHERE ur.user_id = :user_id
                AND r.status = 'ACTIVE'
                ORDER BY r.id ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        return $stmt->fetchAll();
    }

    public function puedeAsignarseAUsuario( //valida que el rol sea compatible con el usuario.
        int $userId,
        int $roleId
    ): bool {
        if ($this->db === null) {
            return false;
        }

        /*
         * Obtener información del usuario.
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
         * Obtener información del rol.
         */

        $sqlRole = "SELECT
                        id,
                        tenant_id,
                        slug,
                        status
                    FROM roles
                    WHERE id = :role_id
                    LIMIT 1";

        $stmtRole = $this->db->prepare($sqlRole);

        $stmtRole->execute([
            ':role_id' => $roleId
        ]);

        $role = $stmtRole->fetch();

        if ($role === false) {
            return false;
        }

        if ($role['status'] !== 'ACTIVE') {
            return false;
        }

        /*
         * Un rol global solamente puede asignarse
         * a un usuario global.
         *
         * Un rol de empresa solamente puede asignarse
         * a un usuario de esa misma empresa.
         */

        if ($role['tenant_id'] === null) {

            if ($user['tenant_id'] === null) {
                return true;
            }

            return false;
        }

        if ($user['tenant_id'] === null) {
            return false;
        }

        return (int) $role['tenant_id'] === (int) $user['tenant_id'];
    }

    public function asignarAUsuario( // realiza la asignación.
        int $userId,
        int $roleId
    ): bool {
        if ($this->db === null) {
            return false;
        }

        if (!$this->puedeAsignarseAUsuario($userId, $roleId)) {
            return false;
        }

        $sql = "INSERT INTO user_roles
                    (user_id, role_id)
                VALUES
                    (:user_id, :role_id)";

        try {

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':user_id' => $userId,
                ':role_id' => $roleId
            ]);

        } catch (PDOException $e) {

            return false;
        }
    }
}
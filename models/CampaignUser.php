<?php

require_once __DIR__ . '/../config/database.php';

class CampaignUser
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Listar agentes asignados a una campaña.
     */
    public function listarPorCampania(
        int $tenantId,
        int $campaignId
    ): array {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    cu.id,
                    cu.tenant_id,
                    cu.campaign_id,
                    cu.user_id,
                    cu.status,
                    cu.assigned_at,
                    u.name,
                    u.email
                FROM campaign_users cu
                INNER JOIN users u
                    ON u.id = cu.user_id
                   AND u.tenant_id = cu.tenant_id
                WHERE cu.tenant_id = :tenant_id
                  AND cu.campaign_id = :campaign_id
                ORDER BY u.name ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':campaign_id' => $campaignId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Verificar si ya existe una asignación.
     */
    public function existeAsignacion(
        int $tenantId,
        int $campaignId,
        int $userId
    ): bool {
        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT id
                FROM campaign_users
                WHERE tenant_id = :tenant_id
                  AND campaign_id = :campaign_id
                  AND user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':campaign_id' => $campaignId,
            ':user_id' => $userId
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Asignar un agente a una campaña.
     */
    public function asignar(
        int $tenantId,
        int $campaignId,
        int $userId
    ): bool {
        if ($this->db === null) {
            return false;
        }

        try {
            $sql = "INSERT INTO campaign_users
                    (
                        tenant_id,
                        campaign_id,
                        user_id,
                        status,
                        assigned_at
                    )
                    VALUES
                    (
                        :tenant_id,
                        :campaign_id,
                        :user_id,
                        'ACTIVE',
                        CURRENT_TIMESTAMP
                    )";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':campaign_id' => $campaignId,
                ':user_id' => $userId
            ]);

            return true;

        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Cambiar estado de una asignación.
     */
    public function cambiarEstado(
        int $tenantId,
        int $campaignUserId,
        string $status
    ): bool {
        if ($this->db === null) {
            return false;
        }

        $sql = "UPDATE campaign_users
                SET status = :status
                WHERE id = :id
                  AND tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':status' => $status,
            ':id' => $campaignUserId,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Listar agentes activos del tenant que todavía
     * no están asignados a la campaña.
     */
    public function listarAgentesDisponibles(
        int $tenantId,
        int $campaignId
    ): array {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    u.id,
                    u.name,
                    u.email
                FROM users u
                INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE u.tenant_id = :user_tenant_id
                  AND r.tenant_id = :role_tenant_id
                  AND r.slug = 'AGENTE'
                  AND u.status = 'ACTIVE'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM campaign_users cu
                      WHERE cu.tenant_id = :cu_tenant_id
                        AND cu.campaign_id = :campaign_id
                        AND cu.user_id = u.id
                  )
                ORDER BY u.name ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_tenant_id' => $tenantId,
            ':role_tenant_id' => $tenantId,
            ':cu_tenant_id' => $tenantId,
            ':campaign_id' => $campaignId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Verificar que un usuario:
     * - pertenezca al tenant
     * - esté activo
     * - tenga el rol AGENTE
     * - no esté actualmente asignado a la campaña
     */
    public function esAgenteDisponible(
        int $tenantId,
        int $campaignId,
        int $userId
    ): bool {
        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT u.id
                FROM users u
                INNER JOIN user_roles ur
                    ON ur.user_id = u.id
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE u.id = :user_id
                  AND u.tenant_id = :user_tenant_id
                  AND u.status = 'ACTIVE'
                  AND r.tenant_id = :role_tenant_id
                  AND r.slug = 'AGENTE'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM campaign_users cu
                      WHERE cu.tenant_id = :cu_tenant_id
                        AND cu.campaign_id = :campaign_id
                        AND cu.user_id = :user_id_check
                  )
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId,
            ':user_tenant_id' => $tenantId,
            ':role_tenant_id' => $tenantId,
            ':cu_tenant_id' => $tenantId,
            ':campaign_id' => $campaignId,
            ':user_id_check' => $userId
        ]);

        return $stmt->fetch() !== false;
    }

    
}
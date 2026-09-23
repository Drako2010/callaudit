<?php
// Carga la conexion
require_once __DIR__ . '/../config/database.php';

class Campaign
{
    private ?PDO $db;

    // Constructor para conectarse a la base de datos.
    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect();
    }

    // Este metodo tiene como función obtener todas las campañas de una empresa.
    public function listar(int $tenantId): array
    {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    id,
                    tenant_id,
                    name,
                    slug,
                    description,
                    status,
                    start_date,
                    end_date,
                    created_by,
                    created_at,
                    updated_at
                FROM campaigns
                WHERE tenant_id = :tenant_id
                ORDER BY id DESC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll();
    }

    // Este método sirve para comprobar si ya existe una campaña
    // con determinado slug dentro de una empresa.
    public function existeSlug(int $tenantId, string $slug): bool
    {
        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT id
                FROM campaigns
                WHERE tenant_id = :tenant_id
                  AND slug = :slug
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':slug' => $slug
        ]);

        return $stmt->fetch() !== false;
    }

    // Este método comprueba si un slug ya pertenece a otra campaña
    // de la misma empresa.
    // Se utiliza cuando estamos editando una campaña.
    public function existeSlugExceptoId(
        int $tenantId,
        string $slug,
        int $id
    ): bool {

        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT id
                FROM campaigns
                WHERE tenant_id = :tenant_id
                  AND slug = :slug
                  AND id <> :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':slug' => $slug,
            ':id' => $id
        ]);

        return $stmt->fetch() !== false;
    }

    // Este método nos permite comprobar una campaña por ID
    // perteneciente a una empresa determinada.
    public function obtenerPorId(int $tenantId, int $id): ?array
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
                    status,
                    start_date,
                    end_date,
                    created_by,
                    created_at,
                    updated_at
                FROM campaigns
                WHERE id = :id
                  AND tenant_id = :tenant_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $tenantId
        ]);

        $campaign = $stmt->fetch();

        if ($campaign === false) {
            return null;
        }

        return $campaign;
    }

    // Este método inserta una nueva campaña.
    public function crear(
        int $tenantId,
        string $name,
        string $slug,
        ?string $description,
        ?string $startDate,
        ?string $endDate,
        ?int $createdBy
    ): bool {
        if ($this->db === null) {
            return false;
        }

        try {

            $sql = "INSERT INTO campaigns
                        (
                            tenant_id,
                            name,
                            slug,
                            description,
                            status,
                            start_date,
                            end_date,
                            created_by
                        )
                    VALUES
                        (
                            :tenant_id,
                            :name,
                            :slug,
                            :description,
                            'ACTIVE',
                            :start_date,
                            :end_date,
                            :created_by
                        )";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':created_by' => $createdBy
            ]);

            return true;

        } catch (PDOException $e) {

            return false;
        }
    }


    // Este método actualiza una campaña existente.
    public function actualizar(
        int $tenantId,
        int $id,
        string $name,
        string $slug,
        ?string $description,
        ?string $startDate,
        ?string $endDate
    ): bool {

        if ($this->db === null) {
            return false;
        }

        try {

            $sql = "UPDATE campaigns
                    SET
                        name = :name,
                        slug = :slug,
                        description = :description,
                        start_date = :start_date,
                        end_date = :end_date
                    WHERE id = :id
                      AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);

            return true;

        } catch (PDOException $e) {

            return false;
        }
    }


    // Este método cambia el estado de una campaña.
    // El tenant_id garantiza el aislamiento entre empresas.
    public function cambiarEstado(
        int $tenantId,
        int $id,
        string $status
    ): bool {

        if ($this->db === null) {
            return false;
        }

        try {

            $sql = "UPDATE campaigns
                    SET status = :status
                    WHERE id = :id
                      AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':status' => $status,
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {

            return false;
        }
    }


}
<?php
// Carga la conexion
require_once __DIR__ . '/../config/database.php';

class Tenant
{
    private ?PDO $db;

// Constructor para conectarse a la base de datos.
    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect();
    }

// Este metodo su función es obtener todas las empresas.
    public function listar(): array
    {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    id,
                    name,
                    slug,
                    status,
                    created_at,
                    updated_at
                FROM tenants
                ORDER BY id DESC";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

// Este método sirve para comprobar si ya existe una empresa con determinado slug.
    public function existeSlug(string $slug): bool
    {
        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT id
                FROM tenants
                WHERE slug = :slug
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':slug' => $slug
        ]);

        return $stmt->fetch() !== false;
    }

// Este método nos permite comprobar una empresa por ID.
    public function obtenerPorId(int $id): ?array
    {
    if ($this->db === null) {
        return null;
    }

    $sql = "SELECT
                id,
                name,
                slug,
                status
            FROM tenants
            WHERE id = :id
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        ':id' => $id
    ]);

    $tenant = $stmt->fetch();

    if ($tenant === false) {
        return null;
    }

    return $tenant;
    }

// Este método inserta una nueva empresa
    public function crear(string $name, string $slug): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {

            $this->db->beginTransaction(); // abre una transacción "Empiezo a trabajar.". "Voy a realizar varias operaciones. Todavía no quiero que este conjunto de cambios quede confirmado definitivamente."

            // Crear empresa
            $sqlTenant = "INSERT INTO tenants
                            (name, slug, status)
                          VALUES
                            (:name, :slug, 'ACTIVE')";

            $stmtTenant = $this->db->prepare($sqlTenant);

            $stmtTenant->execute([
                ':name' => $name,
                ':slug' => $slug
            ]);

            $tenantId = (int) $this->db->lastInsertId();

            // Roles base de la empresa
            $roles = [
                [
                    'name' => 'Administrador',
                    'slug' => 'ADMIN',
                    'description' => 'Administrador de la empresa.'
                ],
                [
                    'name' => 'Supervisor',
                    'slug' => 'SUPERVISOR',
                    'description' => 'Supervisor de la empresa.'
                ],
                [
                    'name' => 'Auditor',
                    'slug' => 'AUDITOR',
                    'description' => 'Auditor de llamadas y evaluaciones.'
                ],
                [
                    'name' => 'Agente',
                    'slug' => 'AGENTE',
                    'description' => 'Usuario operativo de la empresa.'
                ]
            ];

            $sqlRole = "INSERT INTO roles
                            (tenant_id, name, slug, description, status)
                        VALUES
                            (:tenant_id, :name, :slug, :description, 'ACTIVE')";

            $stmtRole = $this->db->prepare($sqlRole);

            foreach ($roles as $role) {

                $stmtRole->execute([
                    ':tenant_id' => $tenantId,
                    ':name' => $role['name'],
                    ':slug' => $role['slug'],
                    ':description' => $role['description']
                ]);
            }

            $this->db->commit(); // "Todo está correcto. GUARDAR." confirma toda la transaccion.

            return true;

        } catch (PDOException $e) {

            if ($this->db->inTransaction()) { // "¿Actualmente esta conexión está dentro de una transacción?"
                $this->db->rollBack(); // "Algo salió mal. DESHACER TODO." Si ocurre un error deshace todo.
            }

            return false;
        }
    }


}
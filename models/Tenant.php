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

        // Matriz oficial de permisos por rol.
        // Se usan slugs, no IDs numéricos.
        $roles = [
            [
                'name' => 'Administrador',
                'slug' => 'ADMIN',
                'description' => 'Administrador de la empresa.',
                'permissions' => [
                    'users.view',
                    'users.create',
                    'users.edit',
                    'users.delete',
                    'roles.view',
                    'roles.create',
                    'roles.edit',
                    'roles.delete',
                    'permissions.view',
                    'permissions.create',
                    'permissions.edit',
                    'permissions.delete',
                    'audits.view',
                    'audits.create',
                    'audits.edit',
                    'audits.delete',
                    'calls.view',
                    'campaigns.view',
                    'campaigns.create',
                    'campaigns.edit',
                    'reports.view',
                    'reports.export',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'Supervisor',
                'slug' => 'SUPERVISOR',
                'description' => 'Supervisor de la empresa.',
                'permissions' => [
                    'users.view',
                    'users.edit',
                    'audits.view',
                    'audits.create',
                    'audits.edit',
                    'audits.delete',
                    'calls.view',
                    'campaigns.view',
                    'reports.view',
                    'reports.export',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'Auditor',
                'slug' => 'AUDITOR',
                'description' => 'Auditor de llamadas y evaluaciones.',
                'permissions' => [
                    'audits.view',
                    'audits.create',
                    'audits.edit',
                    'calls.view',
                    'reports.view',
                    'reports.export',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'Agente',
                'slug' => 'AGENTE',
                'description' => 'Usuario operativo de la empresa.',
                'permissions' => [
                    'calls.view',
                    'dashboard.view'
                ]
            ]
        ];

        try {
            $this->db->beginTransaction();

            // 1. Crear empresa.
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

            // 2. Preparar consultas.
            $sqlRole = "INSERT INTO roles
                            (tenant_id, name, slug, description, status)
                        VALUES
                            (:tenant_id, :name, :slug, :description, 'ACTIVE')";

            $stmtRole = $this->db->prepare($sqlRole);

            $stmtPermission = $this->db->prepare(
                "SELECT id
                 FROM permissions
                 WHERE slug = :slug
                   AND status = 'ACTIVE'
                 LIMIT 1"
            );

            $stmtRolePermission = $this->db->prepare(
                "INSERT INTO role_permissions
                    (role_id, permission_id, created_at)
                 VALUES
                    (:role_id, :permission_id, CURRENT_TIMESTAMP)"
            );

            // 3. Crear cada rol y asignar sus permisos.
            foreach ($roles as $role) {
                $stmtRole->execute([
                    ':tenant_id' => $tenantId,
                    ':name' => $role['name'],
                    ':slug' => $role['slug'],
                    ':description' => $role['description']
                ]);

                $roleId = (int) $this->db->lastInsertId();

                foreach ($role['permissions'] as $permissionSlug) {
                    $stmtPermission->execute([
                        ':slug' => $permissionSlug
                    ]);

                    $permissionId = $stmtPermission->fetchColumn();

                    // Si falta un permiso en el catálogo,
                    // cancelar toda la operación.
                    if ($permissionId === false) {
                        throw new RuntimeException(
                            'No existe el permiso activo: ' . $permissionSlug
                        );
                    }

                    $stmtRolePermission->execute([
                        ':role_id' => $roleId,
                        ':permission_id' => (int) $permissionId
                    ]);
                }
            }

            // 4. Confirmar únicamente si todo salió bien.
            $this->db->commit();

            return true;

        
        } catch (Throwable $e) {

            // Intentar revertir toda la operación.
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable $rollbackError) {
                error_log(
                    '[CallAudit][Tenant::crear][ROLLBACK] '
                    . get_class($rollbackError)
                    . ': ' . $rollbackError->getMessage()
                );
            }

            // Registrar el error técnico en el log del servidor.
            error_log(
                '[CallAudit][Tenant::crear] '
                . get_class($e)
                . ': ' . $e->getMessage()
                . ' en ' . $e->getFile()
                . ':' . $e->getLine()
            );

            // No exponer detalles internos al usuario.
            return false;
        }
    }


}
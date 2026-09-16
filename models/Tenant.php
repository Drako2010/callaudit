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

// Su función es obtener todas las empresas.
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

// Este método inserta una nueva empresa
    public function crear(string $name, string $slug): bool
    {
        if ($this->db === null) {
            return false;
        }

        $sql = "INSERT INTO tenants
                    (name, slug, status)
                VALUES
                    (:name, :slug, 'ACTIVE')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':name' => $name,
            ':slug' => $slug
        ]);
    }
}
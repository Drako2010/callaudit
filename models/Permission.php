<?php

require_once __DIR__ . '/../config/database.php';

class Permission
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect();
    }

    public function listar(): array
    {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    id,
                    name,
                    slug,
                    description,
                    status,
                    created_at,
                    updated_at
                FROM permissions
                ORDER BY id ASC";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function obtenerPorSlug(string $slug): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = "SELECT
                    id,
                    name,
                    slug,
                    description,
                    status
                FROM permissions
                WHERE slug = :slug
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':slug' => $slug
        ]);

        $permission = $stmt->fetch();

        if ($permission === false) {
            return null;
        }

        return $permission;
    }
}
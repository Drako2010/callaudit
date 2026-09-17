<?php
// Cargamos la conexion
require_once __DIR__ . '/../config/database.php';

class User
{
    private ?PDO $db; // almacena la conexión.

    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect(); // obtiene la conexión a MariaDB y la almacena.
    }

    public function listar(): array // Lista usuarios de cada empresa
    {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT
                    u.id,
                    u.tenant_id,
                    t.name AS tenant_name,
                    u.name,
                    u.email,
                    u.status,
                    u.created_at,
                    u.updated_at
                FROM users u
                INNER JOIN tenants t
                    ON t.id = u.tenant_id
                ORDER BY u.id DESC";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function existeEmail(int $tenantId, string $email): bool // Validamos que no se repitan usuarios con mismo correo en una empresa
    {
        if ($this->db === null) {
            return false;
        }

        $sql = "SELECT id
                FROM users
                WHERE tenant_id = :tenant_id
                AND email = :email
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email' => $email
        ]);

        return $stmt->fetch() !== false;
    }

    public function crear(
    int $tenantId,
    string $name,
    string $email,
    string $password
    ): bool {
        if ($this->db === null) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users
                    (tenant_id, name, email, password, status)
                VALUES
                    (:tenant_id, :name, :email, :password, 'ACTIVE')";

        try {

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':email' => $email,
                ':password' => $passwordHash
            ]);

        } catch (PDOException $e) {

            return false;
        }
    }



    
}
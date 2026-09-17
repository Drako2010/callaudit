<?php

require_once __DIR__ . '/../config/database.php';

class AuthService
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();

        $this->db = $database->connect();
    }

    public function autenticar(
        string $email,
        string $password
    ): ?array {

        if ($this->db === null) {
            return null;
        }

        $sql = "SELECT
                    id,
                    tenant_id,
                    name,
                    email,
                    password,
                    status
                FROM users
                WHERE email = :email
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        if ($user['status'] !== 'ACTIVE') {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        unset($user['password']);

        return $user;
    }
}
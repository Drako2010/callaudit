<?php

class SessionService
{
    public function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {

            session_set_cookie_params([
                'httponly' => true,
                'secure' => false,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

    public function iniciarSesion(array $user): void
    {
        $this->iniciar();

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'] !== null
            ? (int) $user['tenant_id']
            : null;
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['status'] = $user['status'];
        $_SESSION['authenticated'] = true;
    }

    public function estaAutenticado(): bool
    {
        $this->iniciar();

        return isset($_SESSION['authenticated'])
            && $_SESSION['authenticated'] === true
            && isset($_SESSION['user_id']);
    }

    public function obtenerUsuario(): ?array
    {
        if (!$this->estaAutenticado()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'name' => $_SESSION['name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'status' => $_SESSION['status'] ?? ''
        ];
    }

    public function cerrarSesion(): void
    {
        $this->iniciar();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
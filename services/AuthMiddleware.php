<?php

require_once __DIR__ . '/SessionService.php';
require_once __DIR__ . '/Authorization.php';

class AuthMiddleware
{
    private SessionService $session;
    private Authorization $authorization;

    public function __construct()
    {
        $this->session = new SessionService();
        $this->authorization = new Authorization();
    }

    public function proteger(): void
    {
        if (!$this->session->estaAutenticado()) {

            header('Location: login.php');
            exit;
        }
    }

    public function requierePermiso(string $permissionSlug): void
    {
        $this->proteger();

        $usuario = $this->session->obtenerUsuario();

        if ($usuario === null) {

            header('Location: login.php');
            exit;
        }

        $tienePermiso = $this->authorization->tienePermiso(
            (int) $usuario['id'],
            $permissionSlug
        );

        if (!$tienePermiso) {

            http_response_code(403);

            echo 'ACCESO DENEGADO';
            exit;
        }
    }

    public function usuario(): ?array
    {
        if (!$this->session->estaAutenticado()) {
            return null;
        }

        return $this->session->obtenerUsuario();
    }
}
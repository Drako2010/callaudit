<?php

require_once __DIR__ . '/../models/AuditCall.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class AuditCallController
{
    private AuditCall $model;
    private AuthMiddleware $auth;

    public function __construct()
    {
        $this->model = new AuditCall();
        $this->auth = new AuthMiddleware();
    }

    private function validarAcceso(int $tenantId, string $permiso): array
    {
        $this->auth->requierePermiso($permiso);
        $usuario = $this->auth->usuario();

        if ($usuario === null) {
            http_response_code(401);
            exit('Usuario no autenticado.');
        }

        if ($tenantId <= 0) {
            http_response_code(400);
            exit('La empresa seleccionada no es válida.');
        }

        if ($usuario['tenant_id'] !== null) {
            if ((int) $usuario['tenant_id'] !== $tenantId) {
                http_response_code(403);
                exit('No tiene autorización para acceder a esta empresa.');
            }
        } else {
            $seleccionado = $_SESSION['campaign_tenant_id'] ?? null;

            if (
                (!is_int($seleccionado) &&
                    !(is_string($seleccionado) && ctype_digit($seleccionado))) ||
                (int) $seleccionado !== $tenantId
            ) {
                http_response_code(403);
                exit('El contexto de empresa no es válido.');
            }
        }

        return $usuario;
    }

    public function listar(int $tenantId, array $filtros = []): array
    {
        $this->validarAcceso($tenantId, 'audits.view');

        return $this->model->listar($tenantId, $filtros);
    }

    public function opciones(int $tenantId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');

        return $this->model->opciones($tenantId);
    }

    public function crear(int $tenantId, array $data): array
    {
        $usuario = $this->validarAcceso($tenantId, 'audits.create');
        $userId = (int) ($usuario['id'] ?? 0);

        if ($userId <= 0) {
            http_response_code(401);
            exit('No se pudo identificar al usuario autenticado.');
        }

        return $this->model->crear($tenantId, $userId, $data);
    }
}
<?php
/**
 * Controlador de fuentes telefónicas.
 * Usa permisos ya existentes: audits.view, audits.create y audits.edit.
 */
require_once __DIR__ . '/../models/TelephonySource.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class TelephonySourceController
{
    private TelephonySource $model;
    private AuthMiddleware $auth;

    public function __construct()
    {
        $this->model = new TelephonySource();
        $this->auth = new AuthMiddleware();
    }

    private function validarAcceso(int $tenantId, string $permission): array
    {
        $this->auth->requierePermiso($permission);
        $user = $this->auth->usuario();

        if ($user === null) {
            http_response_code(401);
            exit('Usuario no autenticado.');
        }
        if ($tenantId <= 0) {
            http_response_code(400);
            exit('La empresa seleccionada no es válida.');
        }

        if ($user['tenant_id'] !== null) {
            if ((int)$user['tenant_id'] !== $tenantId) {
                http_response_code(403);
                exit('No tiene autorización para acceder a esta empresa.');
            }
        } else {
            $selected = $_SESSION['campaign_tenant_id'] ?? null;
            if ((!is_int($selected) && !(is_string($selected) && ctype_digit($selected)))
                || (int)$selected !== $tenantId) {
                http_response_code(403);
                exit('El contexto de empresa no es válido o no está seleccionado.');
            }
        }
        return $user;
    }

    public function index(int $tenantId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        return $this->model->listar($tenantId);
    }

    public function show(int $tenantId, int $id): ?array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        return $this->model->obtener($tenantId, $id);
    }

    public function store(
        int $tenantId, ?int $id, string $name, string $platform,
        string $mode, ?string $host, ?int $port, ?string $databaseName
    ): array {
        $permission = $id === null ? 'audits.create' : 'audits.edit';
        $user = $this->validarAcceso($tenantId, $permission);
        return $this->model->guardar(
            $tenantId, (int)$user['id'], $id, $name, $platform,
            $mode, $host, $port, $databaseName
        );
    }

    public function changeStatus(int $tenantId, int $id, string $status): array
    {
        $this->validarAcceso($tenantId, 'audits.edit');
        return $this->model->cambiarEstado($tenantId, $id, $status);
    }
}

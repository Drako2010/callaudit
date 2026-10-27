<?php

require_once __DIR__ . '/../models/AuditTaskManager.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';
require_once __DIR__ . '/../services/Authorization.php';

class AuditTaskController
{
    private AuditTaskManager $model;
    private AuthMiddleware $auth;
    private Authorization $authorization;

    public function __construct()
    {
        $this->model = new AuditTaskManager();
        $this->auth = new AuthMiddleware();
        $this->authorization = new Authorization();
    }

    private function validarAcceso(int $tenantId, string $permiso): array
    {
        $this->auth->requierePermiso($permiso);
        $user = $this->auth->usuario();

        if (!$user) {
            http_response_code(401);
            exit('Usuario no autenticado.');
        }
        if ($tenantId <= 0) {
            http_response_code(400);
            exit('Empresa inválida.');
        }

        if ($user['tenant_id'] !== null) {
            if ((int) $user['tenant_id'] !== $tenantId) {
                http_response_code(403);
                exit('Acceso denegado a esta empresa.');
            }
        } else {
            $selected = $_SESSION['campaign_tenant_id'] ?? null;
            if ((!is_int($selected) && !(is_string($selected) && ctype_digit($selected))) || (int) $selected !== $tenantId) {
                http_response_code(403);
                exit('El contexto de empresa no es válido.');
            }
        }

        return $user;
    }

    public function listar(int $tenantId, array $filtros = []): array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        return $this->model->listar($tenantId, $filtros);
    }

    public function agentes(int $tenantId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        $users = $this->model->agentes($tenantId);
        $evaluators = [];

        // El permiso efectivo contempla roles, GRANT individuales y DENY prioritarios.
        foreach ($users as $candidate) {
            if ($this->authorization->tienePermiso((int) $candidate['id'], 'audits.edit')) {
                $evaluators[] = $candidate;
            }
        }

        return $evaluators;
    }

    public function asignar(int $tenantId, int $taskId, int $evaluatorId): array
    {
        $user = $this->validarAcceso($tenantId, 'audits.edit');

        // Validación del lado servidor: no confiar en el desplegable del navegador.
        if (!$this->authorization->tienePermiso($evaluatorId, 'audits.edit')) {
            return [
                'success' => false,
                'message' => 'El usuario seleccionado no tiene permiso efectivo para evaluar auditorías.'
            ];
        }

        return $this->model->asignar($tenantId, $taskId, $evaluatorId, (int) $user['id']);
    }

    public function cambiarEstado(int $tenantId, int $taskId, string $estado): array
    {
        $this->validarAcceso($tenantId, 'audits.edit');
        return $this->model->cambiarEstado($tenantId, $taskId, $estado);
    }
}

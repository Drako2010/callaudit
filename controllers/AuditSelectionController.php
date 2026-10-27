<?php

require_once __DIR__ . '/../models/AuditSelectionPolicy.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class AuditSelectionController
{
    private AuditSelectionPolicy $model;
    private AuthMiddleware $auth;

    public function __construct()
    {
        $this->model = new AuditSelectionPolicy();
        $this->auth = new AuthMiddleware();
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
            if (
                (!is_int($selected) && !(is_string($selected) && ctype_digit($selected))) ||
                (int) $selected !== $tenantId
            ) {
                http_response_code(403);
                exit('Selecciona una empresa válida en el contexto del sistema.');
            }
        }

        return $user;
    }

    public function campanias(int $tenantId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        return $this->model->campanias($tenantId);
    }

    public function listar(int $tenantId, int $campaignId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        return $this->model->listar($tenantId, $campaignId);
    }

    public function llamadasElegibles(int $tenantId, int $policyId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');
        return $this->model->llamadasElegibles($tenantId, $policyId);
    }

    public function guardar(
        int $tenantId,
        int $campaignId,
        array $data,
        ?int $id = null
    ): array {
        $user = $this->validarAcceso($tenantId, $id ? 'audits.edit' : 'audits.create');
        return $this->model->guardar(
            $tenantId,
            $campaignId,
            (int) $user['id'],
            $data,
            $id
        );
    }

    public function generar(
        int $tenantId,
        int $policyId,
        string $requestKey,
        ?int $selectedCallId = null
    ): array {
        $user = $this->validarAcceso($tenantId, 'audits.create');
        return $this->model->encolarGeneracion(
            $tenantId,
            $policyId,
            (int) $user['id'],
            $requestKey,
            $selectedCallId
        );
    }
}

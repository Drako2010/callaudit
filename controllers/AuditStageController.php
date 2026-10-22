<?php
/** Controlador de etapas. Reutiliza permisos existentes; no crea roles/permisos. */
require_once __DIR__ . '/../models/AuditStage.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class AuditStageController
{
    private AuditStage $model;
    private AuthMiddleware $auth;

    public function __construct()
    {
        $this->model=new AuditStage();
        $this->auth=new AuthMiddleware();
    }

    private function validarAcceso(int $tenantId,string $permiso): array
    {
        $this->auth->requierePermiso($permiso);
        $user=$this->auth->usuario();
        if (!$user) { http_response_code(401); exit('Usuario no autenticado.'); }
        if ($tenantId<=0) { http_response_code(400); exit('Empresa inválida.'); }
        if ($user['tenant_id']!==null) {
            if ((int)$user['tenant_id']!==$tenantId) { http_response_code(403); exit('Acceso denegado a esta empresa.'); }
        } else {
            $selected=$_SESSION['campaign_tenant_id']??null;
            if ((!is_int($selected)&&!(is_string($selected)&&ctype_digit($selected))) || (int)$selected!==$tenantId) {
                http_response_code(403); exit('Selecciona una empresa válida en el contexto del sistema.');
            }
        }
        return $user;
    }

    public function listar(int $tenantId,int $campaignId): array
    {
        $this->validarAcceso($tenantId,'audits.view');
        return $this->model->listar($tenantId,$campaignId);
    }

    public function datosFormulario(int $tenantId): array
    {
        $this->validarAcceso($tenantId,'audits.view');
        return ['campaigns'=>$this->model->campanias($tenantId),
                'versions'=>$this->model->versionesPublicadas($tenantId)];
    }

    public function guardar(int $tenantId,int $campaignId,array $data,?int $id=null): array
    {
        $user=$this->validarAcceso($tenantId,$id?'audits.edit':'audits.create');
        return $this->model->guardar($tenantId,$campaignId,$data,(int)($user['id']??0),$id);
    }
}

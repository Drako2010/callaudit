<?php

require_once __DIR__ . '/../models/AuditEvaluationManager.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class AuditEvaluationController
{
    private AuditEvaluationManager $model;
    private AuthMiddleware $auth;

    public function __construct()
    {
        $this->model = new AuditEvaluationManager();
        $this->auth = new AuthMiddleware();
    }

    private function usuarioAutorizado(int $tenantId): array
    {
        $this->auth->requierePermiso('audits.edit');
        $user = $this->auth->usuario();
        if (!$user) { http_response_code(401); exit('Usuario no autenticado.'); }
        if ($tenantId <= 0) { http_response_code(400); exit('Empresa inválida.'); }
        if ($user['tenant_id'] !== null) {
            if ((int)$user['tenant_id'] !== $tenantId) { http_response_code(403); exit('Acceso denegado a esta empresa.'); }
        } else {
            $selected = $_SESSION['campaign_tenant_id'] ?? null;
            if ((!is_int($selected) && !(is_string($selected) && ctype_digit($selected))) || (int)$selected !== $tenantId) {
                http_response_code(403); exit('El contexto de empresa no es válido.');
            }
        }
        return $user;
    }

    public function cargar(int $tenantId, int $taskId): array
    {
        $user = $this->usuarioAutorizado($tenantId);
        return $this->model->cargar($tenantId, $taskId, (int)$user['id']);
    }

    public function guardar(int $tenantId, int $taskId, array $post): array
    {
        $user = $this->usuarioAutorizado($tenantId);
        $action = isset($post['evaluation_action']) && is_string($post['evaluation_action']) ? $post['evaluation_action'] : '';
        $answers = isset($post['answers']) && is_array($post['answers']) ? $post['answers'] : [];
        $na = isset($post['na']) && is_array($post['na']) ? $post['na'] : [];
        $comments = isset($post['comments']) && is_array($post['comments']) ? $post['comments'] : [];
        $analysis = isset($post['analysis']) && is_string($post['analysis']) ? trim($post['analysis']) : '';
        if (mb_strlen($analysis, 'UTF-8') > 20000) return ['success'=>false,'message'=>'Las observaciones generales no pueden superar 20.000 caracteres.'];
        return $this->model->guardar($tenantId, $taskId, (int)$user['id'], ['answers'=>$answers,'na'=>$na,'comments'=>$comments], $action, $analysis);
    }
}

<?php
/*
|--------------------------------------------------------------------------
| Archivo: controllers/AuditFormQuestionController.php
|--------------------------------------------------------------------------
| Responsabilidad:
|   Coordinar la gestión de preguntas entre la página y el modelo.
|
| Seguridad:
|   - Exige autenticación y el permiso existente correspondiente.
|   - Los usuarios de empresa solo pueden trabajar con su tenant_id.
|   - El SUPERADMIN debe tener seleccionada una empresa mediante
|     CampaignTenantContext.
|   - El modelo vuelve a comprobar la propiedad de cada registro.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../models/AuditFormQuestion.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class AuditFormQuestionController
{
    private AuditFormQuestion $model;
    private AuthMiddleware $auth;

    public function __construct()
    {
        $this->model = new AuditFormQuestion();
        $this->auth = new AuthMiddleware();
    }

    /**
     * Comprueba el permiso y valida el contexto de empresa.
     *
     * La selección de empresa para SUPERADMIN se resuelve en la página
     * mediante CampaignTenantContext. Este método no acepta un tenant
     * alternativo enviado desde GET o POST.
     */
    private function validarAcceso(int $tenantId, string $permiso): array
    {
        $this->auth->requierePermiso($permiso);

        $usuario = $this->auth->usuario();

        if (!$usuario || $tenantId <= 0) {
            http_response_code(403);
            exit('No se pudo validar el acceso a la empresa.');
        }

        // Una cuenta empresarial nunca puede cambiar de empresa.
        if ($usuario['tenant_id'] !== null) {
            if ((int) $usuario['tenant_id'] !== $tenantId) {
                http_response_code(403);
                exit('No tienes acceso a esta empresa.');
            }
        } else {
            /*
             * El contexto global se valida previamente en la página.
             * No se considera suficiente tener tenant_id = NULL:
             * AuthMiddleware y Authorization validan el permiso del usuario.
             */
            require_once __DIR__ . '/../services/AuthService.php';

            $authService = new AuthService();

            if (!$authService->esSuperAdmin((int) $usuario['id'])) {
                http_response_code(403);
                exit('Acceso reservado al SUPERADMIN global.');
            }
        }

        return $usuario;
    }

    /**
     * Lista las versiones disponibles para el tenant autorizado.
     */
    public function listarVersiones(int $tenantId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');

        return $this->model->listarVersiones($tenantId);
    }

    /**
     * Lista las preguntas de una versión de formulario.
     */
    public function listarPreguntas(
        int $tenantId,
        int $versionId
    ): array {
        $this->validarAcceso($tenantId, 'audits.view');

        return $this->model->listarPreguntas($tenantId, $versionId);
    }

    /**
     * Registra una pregunta nueva.
     *
     * Se requiere audits.create, que ya existe en la base de datos.
     */
    public function crearPregunta(
        int $tenantId,
        int $versionId,
        array $datos
    ): array {
        $this->validarAcceso($tenantId, 'audits.create');

        return $this->model->crearPregunta(
            $tenantId,
            $versionId,
            $datos
        );
    }

    /**
     * Activa o desactiva una pregunta.
     *
     * Se requiere audits.edit, que ya existe en la base de datos.
     */
    public function cambiarEstado(
        int $tenantId,
        int $versionId,
        int $preguntaId,
        bool $activo
    ): array {
        $this->validarAcceso($tenantId, 'audits.edit');

        return $this->model->cambiarEstado(
            $tenantId,
            $versionId,
            $preguntaId,
            $activo
        );
    }
}
<?php
/**
 * ============================================================================
 * ARCHIVO: controllers/AuditFormVersionController.php
 * PROYECTO: CallAudit
 *
 * Coordina la consulta, creación y publicación de versiones.
 * Reutiliza permisos existentes: audits.view, audits.create, audits.edit.
 * ============================================================================
 */

require_once __DIR__ . '/../models/AuditFormVersion.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';
require_once __DIR__ . '/../services/AuthService.php';

class AuditFormVersionController
{
    private AuditFormVersion $model;
    private AuthMiddleware $auth;
    private AuthService $authService;

    public function __construct()
    {
        $this->model = new AuditFormVersion();
        $this->auth = new AuthMiddleware();
        $this->authService = new AuthService();
    }

    /**
     * Verifica permiso, sesión y empresa.
     *
     * Para un SUPERADMIN global, el tenant debe coincidir con el contexto
     * seleccionado previamente por CampaignTenantContext.
     */
    private function validarAcceso(
        int $tenantId,
        string $permiso
    ): array {
        $this->auth->requierePermiso($permiso);

        $usuario = $this->auth->usuario();

        if (!$usuario || $tenantId <= 0) {
            http_response_code(403);
            exit('No se pudo validar el acceso a la empresa.');
        }

        // Cuenta empresarial: nunca puede cambiar de tenant.
        if ($usuario['tenant_id'] !== null) {
            if ((int) $usuario['tenant_id'] !== $tenantId) {
                http_response_code(403);
                exit('No tienes acceso a esta empresa.');
            }

            return $usuario;
        }

        // Una cuenta sin tenant no se considera SUPERADMIN automáticamente.
        if (!$this->authService->esSuperAdmin((int) $usuario['id'])) {
            http_response_code(403);
            exit('Acceso reservado al SUPERADMIN global.');
        }

        // Validar el tenant elegido en la sesión, no desde GET o POST.
        $seleccion = $_SESSION['campaign_tenant_id'] ?? null;

        if (
            (!is_int($seleccion) &&
                !(is_string($seleccion) && ctype_digit($seleccion)))
            || (int) $seleccion !== $tenantId
        ) {
            http_response_code(403);
            exit('El contexto de empresa no es válido.');
        }

        return $usuario;
    }

    /** Lista las versiones de la empresa autorizada. */
    public function listar(int $tenantId): array
    {
        $this->validarAcceso($tenantId, 'audits.view');

        return $this->model->listar($tenantId);
    }

    /** Crea un borrador nuevo copiando una versión existente. */
    public function crearBorrador(
        int $tenantId,
        int $versionOrigenId
    ): array {
        $usuario = $this->validarAcceso($tenantId, 'audits.create');

        return $this->model->crearBorrador(
            $tenantId,
            $versionOrigenId,
            (int) $usuario['id']
        );
    }

    /** Publica una versión en borrador. */
    public function publicar(
        int $tenantId,
        int $versionId,
        ?float $passingScore,
        string $naPolicy
    ): array {
        $usuario = $this->validarAcceso($tenantId, 'audits.edit');

        return $this->model->publicar(
            $tenantId,
            $versionId,
            (int) $usuario['id'],
            $passingScore,
            $naPolicy
        );
    }
}

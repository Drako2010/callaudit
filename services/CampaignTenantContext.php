<?php

/*
|--------------------------------------------------------------------------
| Archivo: services/CampaignTenantContext.php
|--------------------------------------------------------------------------
| Resuelve la empresa sobre la que se trabajará en el módulo Campañas.
|
| - Usuario de empresa: utiliza exclusivamente su tenant_id de sesión.
| - SUPERADMIN global: utiliza la empresa previamente seleccionada.
| - La selección global se guarda en $_SESSION, nunca se acepta
|   directamente desde la URL ni desde los formularios de campañas.
| - Se vuelve a comprobar que la empresa esté activa.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/AuthService.php';

class CampaignTenantContext
{
    private Tenant $tenant;
    private AuthService $authService;

    public function __construct()
    {
        $this->tenant = new Tenant();
        $this->authService = new AuthService();
    }

    /**
     * Devuelve el ID de la empresa autorizada para la petición actual.
     * Redirige al selector si un SUPERADMIN aún no eligió empresa.
     */
    public function resolver(array $usuario): int
    {
        // Comprobar que el usuario tenga un identificador válido.
        $userId = isset($usuario['id']) ? (int) $usuario['id'] : 0;

        if ($userId <= 0) {
            http_response_code(401);
            exit('Usuario no autenticado.');
        }

        /*
         * Las cuentas empresariales nunca pueden cambiar de empresa
         * mediante el selector global.
         */
        if ($usuario['tenant_id'] !== null) {
            $tenantId = (int) $usuario['tenant_id'];
            $empresa = $this->tenant->obtenerPorId($tenantId);

            if ($empresa === null || $empresa['status'] !== 'ACTIVE') {
                http_response_code(403);
                exit('La empresa de esta cuenta no está activa.');
            }

            return $tenantId;
        }

        /*
         * Una cuenta sin tenant_id no es automáticamente SUPERADMIN.
         * Se verifica el rol global activo directamente en la BD.
         */
        if (!$this->authService->esSuperAdmin($userId)) {
            http_response_code(403);
            exit('Acceso reservado al SUPERADMIN global.');
        }

        /*
         * La empresa seleccionada previamente se almacena en la sesión.
         * No se lee un tenant_id de GET ni de POST en este servicio.
         */
        $seleccion = $_SESSION['campaign_tenant_id'] ?? null;

        if (
            !is_int($seleccion)
            && !(is_string($seleccion) && ctype_digit($seleccion))
        ) {
            header('Location: campaign_tenant.php');
            exit;
        }

        $tenantId = (int) $seleccion;

        if ($tenantId <= 0) {
            unset($_SESSION['campaign_tenant_id']);
            header('Location: campaign_tenant.php');
            exit;
        }

        // Revalidar que la empresa exista y siga activa.
        $empresa = $this->tenant->obtenerPorId($tenantId);

        if ($empresa === null || $empresa['status'] !== 'ACTIVE') {
            unset($_SESSION['campaign_tenant_id']);
            header('Location: campaign_tenant.php');
            exit;
        }

        return $tenantId;
    }

    /**
     * Invalida el contexto global seleccionado.
     * No modifica ni elimina datos de la empresa.
     */
    public function limpiarSeleccion(): void
    {
        unset($_SESSION['campaign_tenant_id']);
    }
}
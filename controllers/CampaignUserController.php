<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos los modelos necesarios y el middleware de autorización.
*/

require_once __DIR__ . '/../models/CampaignUser.php';
require_once __DIR__ . '/../models/Campaign.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';


class CampaignUserController
{
    private CampaignUser $campaignUser;

    private Campaign $campaign;

    private AuthMiddleware $auth;


    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    | Inicializamos los modelos y el middleware.
    */

    public function __construct()
    {
        $this->campaignUser = new CampaignUser();

        $this->campaign = new Campaign();

        $this->auth = new AuthMiddleware();
    }


    /*
    |--------------------------------------------------------------------------
    | Validar autorización y ámbito
    |--------------------------------------------------------------------------
    | Esta función verifica:
    |
    | 1. Que el usuario esté autenticado.
    | 2. Que tenga el permiso requerido.
    | 3. Que pueda trabajar sobre el tenant indicado.
    |
    | Un usuario normal solamente puede trabajar con su propio tenant.
    |
    | Un usuario global/SUPERADMIN puede trabajar sobre un tenant
    | explícitamente seleccionado.
    */

    private function validarAccesoTenant(
        int $tenantId,
        string $permission
    ): void {

        /*
         * Verificar autenticación y permiso.
         */
        $this->auth->requierePermiso($permission);


        /*
         * Obtener usuario autenticado.
         */
        $usuario = $this->auth->usuario();


        if ($usuario === null) {

            http_response_code(401);

            echo 'Usuario no autenticado.';
            exit;
        }


        /*
         * Validar tenant.
         */
        if ($tenantId <= 0) {

            http_response_code(400);

            echo 'Empresa inválida.';
            exit;
        }


        /*
         * Usuario global/SUPERADMIN.
         *
         * Puede trabajar sobre un tenant explícitamente seleccionado.
         */
        if ($usuario['tenant_id'] === null) {
            return;
        }


        /*
         * Usuario perteneciente a una empresa.
         *
         * Solo puede trabajar sobre su propio tenant.
         */
        if ((int) $usuario['tenant_id'] !== $tenantId) {

            http_response_code(403);

            echo 'No tiene autorización para trabajar con esta empresa.';
            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Verificar campaña
    |--------------------------------------------------------------------------
    | Centralizamos la comprobación de que una campaña pertenece al
    | tenant indicado.
    */

    private function obtenerCampania(
        int $tenantId,
        int $campaignId
    ): ?array {

        /*
         * Un ID de campaña válido debe ser mayor que cero.
         */
        if ($campaignId <= 0) {
            return null;
        }


        /*
         * El modelo consulta utilizando:
         *
         * campaign_id + tenant_id
         *
         * Por tanto, una campaña de otra empresa no será devuelta.
         */
        return $this->campaign->obtenerPorId(
            $tenantId,
            $campaignId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Listar agentes asignados
    |--------------------------------------------------------------------------
    | Requiere campaigns.view.
    */

    public function index(
        int $tenantId,
        int $campaignId
    ): array {

        /*
         * Validar permiso y ámbito.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.view'
        );


        /*
         * Comprobar que la campaña pertenece al tenant.
         */
        $campania = $this->obtenerCampania(
            $tenantId,
            $campaignId
        );


        if ($campania === null) {

            return [];
        }


        /*
         * Obtener agentes asignados.
         */
        return $this->campaignUser->listarPorCampania(
            $tenantId,
            $campaignId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Listar agentes disponibles
    |--------------------------------------------------------------------------
    | Requiere campaigns.view.
    */

    public function agentesDisponibles(
        int $tenantId,
        int $campaignId
    ): array {

        /*
         * Validar permiso y ámbito.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.view'
        );


        /*
         * Comprobar que la campaña pertenece al tenant.
         */
        $campania = $this->obtenerCampania(
            $tenantId,
            $campaignId
        );


        if ($campania === null) {

            return [];
        }


        /*
         * Obtener agentes que todavía pueden ser asignados.
         */
        return $this->campaignUser->listarAgentesDisponibles(
            $tenantId,
            $campaignId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Asignar agente
    |--------------------------------------------------------------------------
    | Asignar un agente modifica datos.
    |
    | Por eso requiere campaigns.edit.
    */

    public function store(
        int $tenantId,
        int $campaignId,
        int $userId
    ): array {

        /*
         * Validar permiso y ámbito.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.edit'
        );


        /*
         * Validar IDs.
         */
        if ($campaignId <= 0) {

            return [
                'success' => false,
                'message' => 'Campaña inválida.'
            ];
        }


        if ($userId <= 0) {

            return [
                'success' => false,
                'message' => 'Usuario inválido.'
            ];
        }


        /*
         * Verificar que la campaña pertenece al tenant.
         */
        $datosCampania = $this->obtenerCampania(
            $tenantId,
            $campaignId
        );


        if ($datosCampania === null) {

            return [
                'success' => false,
                'message' => 'La campaña no pertenece a esta empresa.'
            ];
        }


        /*
         * No permitimos asignar agentes a una campaña inactiva.
         *
         * La campaña debe estar ACTIVE para recibir asignaciones.
         */
        if ($datosCampania['status'] !== 'ACTIVE') {

            return [
                'success' => false,
                'message' => 'No se pueden asignar agentes a una campaña inactiva.'
            ];
        }


        /*
         * Verificar que el usuario sea:
         *
         * - del mismo tenant
         * - activo
         * - AGENTE
         * - no asignado previamente
         *
         * Toda esta comprobación se realiza en el modelo.
         */
        if (
            !$this->campaignUser->esAgenteDisponible(
                $tenantId,
                $campaignId,
                $userId
            )
        ) {

            return [
                'success' => false,
                'message' => 'El usuario no es un agente disponible para esta campaña.'
            ];
        }


        /*
         * Verificación adicional de duplicidad.
         *
         * La base de datos también dispone de un índice UNIQUE.
         */
        if (
            $this->campaignUser->existeAsignacion(
                $tenantId,
                $campaignId,
                $userId
            )
        ) {

            return [
                'success' => false,
                'message' => 'El usuario ya está asignado a esta campaña.'
            ];
        }


        /*
         * Crear asignación.
         */
        $resultado = $this->campaignUser->asignar(
            $tenantId,
            $campaignId,
            $userId
        );


        if (!$resultado) {

            return [
                'success' => false,
                'message' => 'No se pudo asignar el usuario a la campaña.'
            ];
        }


        return [
            'success' => true,
            'message' => 'Usuario asignado correctamente.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Cambiar estado de una asignación
    |--------------------------------------------------------------------------
    | Activar/desactivar una asignación modifica datos.
    |
    | Por eso requiere campaigns.edit.
    */

    public function cambiarEstado(
        int $tenantId,
        int $campaignUserId,
        string $status
    ): array {

        /*
         * Validar permiso y ámbito.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.edit'
        );


        /*
         * Validar ID de asignación.
         */
        if ($campaignUserId <= 0) {

            return [
                'success' => false,
                'message' => 'Asignación inválida.'
            ];
        }


        /*
         * Validar estado.
         */
        if (!in_array(
            $status,
            ['ACTIVE', 'INACTIVE'],
            true
        )) {

            return [
                'success' => false,
                'message' => 'Estado no válido.'
            ];
        }


        /*
         * Cambiar estado.
         *
         * El modelo debe utilizar tenant_id junto con el ID de asignación.
         */
        $resultado = $this->campaignUser->cambiarEstado(
            $tenantId,
            $campaignUserId,
            $status
        );


        if (!$resultado) {

            return [
                'success' => false,
                'message' => 'No se encontró la asignación.'
            ];
        }


        return [
            'success' => true,
            'message' => 'Estado actualizado correctamente.'
        ];
    }
}
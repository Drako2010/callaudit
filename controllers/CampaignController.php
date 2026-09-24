<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el modelo Campaign y el middleware de autenticación/autorización.
*/

require_once __DIR__ . '/../models/Campaign.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';


class CampaignController
{
    private Campaign $campaign;

    private AuthMiddleware $auth;


    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    | Inicializamos el modelo y el middleware.
    */

    public function __construct()
    {
        $this->campaign = new Campaign();

        $this->auth = new AuthMiddleware();
    }


    /*
    |--------------------------------------------------------------------------
    | Validar autorización y ámbito
    |--------------------------------------------------------------------------
    | Esta función centraliza dos comprobaciones:
    |
    | 1. El usuario tiene el permiso requerido.
    | 2. El tenant sobre el que se intenta trabajar está permitido.
    |
    | Un usuario de empresa solamente puede trabajar sobre su propio
    | tenant.
    |
    | Un usuario global/SUPERADMIN puede trabajar sobre un tenant
    | explícitamente seleccionado.
    |
    | IMPORTANTE:
    | El tenant_id recibido por el controller nunca se considera
    | automáticamente confiable.
    */

    private function validarAccesoTenant(
        int $tenantId,
        string $permission
    ): void {

        /*
         * Primero verificamos autenticación y permiso.
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
         * Un tenant válido debe ser mayor que cero.
         */
        if ($tenantId <= 0) {

            http_response_code(400);

            echo 'Empresa inválida.';
            exit;
        }


        /*
         * Si el usuario tiene tenant_id NULL, se trata de un usuario
         * global/SUPERADMIN.
         *
         * Estos usuarios pueden trabajar sobre cualquier tenant
         * siempre que el tenant haya sido seleccionado explícitamente.
         */
        if ($usuario['tenant_id'] === null) {
            return;
        }


        /*
         * Usuario perteneciente a una empresa:
         *
         * solamente puede trabajar sobre su propia empresa.
         */
        if ((int) $usuario['tenant_id'] !== $tenantId) {

            http_response_code(403);

            echo 'No tiene autorización para trabajar con esta empresa.';
            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Listar campañas
    |--------------------------------------------------------------------------
    | Requiere campaigns.view.
    */

    public function index(int $tenantId): array
    {
        /*
         * Validar permiso y ámbito antes de consultar la base de datos.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.view'
        );


        /*
         * Una vez validado el acceso, delegamos la consulta al modelo.
         */
        return $this->campaign->listar($tenantId);
    }


    /*
    |--------------------------------------------------------------------------
    | Crear campaña
    |--------------------------------------------------------------------------
    | Requiere campaigns.create.
    */

    public function store(
        int $tenantId,
        string $name,
        string $slug,
        ?string $description,
        ?string $startDate,
        ?string $endDate,
        ?int $createdBy
    ): array {

        /*
         * Validar permiso y ámbito antes de realizar cualquier operación.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.create'
        );


        /*
         * Elimina espacios innecesarios.
         */
        $name = trim($name);

        $slug = trim($slug);


        /*
         * Validar nombre.
         */
        if ($name === '') {

            return [
                'success' => false,
                'message' => 'El nombre de la campaña es obligatorio.'
            ];
        }


        /*
         * Validar slug.
         */
        if ($slug === '') {

            return [
                'success' => false,
                'message' => 'El slug de la campaña es obligatorio.'
            ];
        }


        /*
         * Comprobar slug duplicado dentro de la empresa.
         */
        if ($this->campaign->existeSlug($tenantId, $slug)) {

            return [
                'success' => false,
                'message' => 'El slug ya está registrado en esta empresa.'
            ];
        }


        /*
         * Normalizar fecha de inicio.
         */
        if ($startDate !== null && $startDate !== '') {

            $startDate = trim($startDate);

        } else {

            $startDate = null;
        }


        /*
         * Normalizar fecha de fin.
         */
        if ($endDate !== null && $endDate !== '') {

            $endDate = trim($endDate);

        } else {

            $endDate = null;
        }


        /*
         * Comprobar coherencia de fechas.
         */
        if ($startDate !== null && $endDate !== null) {

            if ($endDate < $startDate) {

                return [
                    'success' => false,
                    'message' => 'La fecha de fin no puede ser anterior a la fecha de inicio.'
                ];
            }
        }


        /*
         * Normalizar descripción.
         */
        if ($description !== null) {

            $description = trim($description);

            if ($description === '') {

                $description = null;
            }
        }


        /*
         * Crear campaña.
         */
        $creado = $this->campaign->crear(
            $tenantId,
            $name,
            $slug,
            $description,
            $startDate,
            $endDate,
            $createdBy
        );


        /*
         * Comprobar resultado.
         */
        if (!$creado) {

            return [
                'success' => false,
                'message' => 'No se pudo crear la campaña.'
            ];
        }


        return [
            'success' => true,
            'message' => 'Campaña creada correctamente.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar campaña
    |--------------------------------------------------------------------------
    | Requiere campaigns.edit.
    */

    public function update(
        int $tenantId,
        int $id,
        string $name,
        string $slug,
        ?string $description,
        ?string $startDate,
        ?string $endDate
    ): array {

        /*
         * Validar permiso y ámbito.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.edit'
        );


        /*
         * Normalizar nombre y slug.
         */
        $name = trim($name);

        $slug = trim($slug);


        /*
         * Validar nombre.
         */
        if ($name === '') {

            return [
                'success' => false,
                'message' => 'El nombre de la campaña es obligatorio.'
            ];
        }


        /*
         * Validar slug.
         */
        if ($slug === '') {

            return [
                'success' => false,
                'message' => 'El slug de la campaña es obligatorio.'
            ];
        }


        /*
         * Comprobar que la campaña pertenece al tenant.
         */
        $campaign = $this->campaign->obtenerPorId(
            $tenantId,
            $id
        );


        if ($campaign === null) {

            return [
                'success' => false,
                'message' => 'La campaña no existe.'
            ];
        }


        /*
         * Comprobar que el nuevo slug no pertenezca a otra campaña.
         */
        if (
            $this->campaign->existeSlugExceptoId(
                $tenantId,
                $slug,
                $id
            )
        ) {

            return [
                'success' => false,
                'message' => 'El slug ya está registrado en esta empresa.'
            ];
        }


        /*
         * Normalizar fecha de inicio.
         */
        if ($startDate !== null && $startDate !== '') {

            $startDate = trim($startDate);

        } else {

            $startDate = null;
        }


        /*
         * Normalizar fecha de fin.
         */
        if ($endDate !== null && $endDate !== '') {

            $endDate = trim($endDate);

        } else {

            $endDate = null;
        }


        /*
         * Comprobar coherencia de fechas.
         */
        if ($startDate !== null && $endDate !== null) {

            if ($endDate < $startDate) {

                return [
                    'success' => false,
                    'message' => 'La fecha de fin no puede ser anterior a la fecha de inicio.'
                ];
            }
        }


        /*
         * Normalizar descripción.
         */
        if ($description !== null) {

            $description = trim($description);

            if ($description === '') {

                $description = null;
            }
        }


        /*
         * Actualizar campaña.
         */
        $actualizado = $this->campaign->actualizar(
            $tenantId,
            $id,
            $name,
            $slug,
            $description,
            $startDate,
            $endDate
        );


        if (!$actualizado) {

            return [
                'success' => false,
                'message' => 'No se pudo actualizar la campaña.'
            ];
        }


        return [
            'success' => true,
            'message' => 'Campaña actualizada correctamente.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Cambiar estado
    |--------------------------------------------------------------------------
    | Activar/desactivar una campaña es una operación de edición.
    |
    | Por eso requiere campaigns.edit.
    */

    public function cambiarEstado(
        int $tenantId,
        int $id,
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
         * Solo permitimos los estados definidos en la base de datos.
         */
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {

            return [
                'success' => false,
                'message' => 'Estado de campaña no válido.'
            ];
        }


        /*
         * Comprobar que la campaña pertenece al tenant.
         */
        $campaign = $this->campaign->obtenerPorId(
            $tenantId,
            $id
        );


        if ($campaign === null) {

            return [
                'success' => false,
                'message' => 'La campaña no existe.'
            ];
        }


        /*
         * Evitar realizar nuevamente el mismo cambio.
         */
        if ($campaign['status'] === $status) {

            return [
                'success' => false,
                'message' => 'La campaña ya tiene ese estado.'
            ];
        }


        /*
         * Cambiar estado.
         */
        $actualizado = $this->campaign->cambiarEstado(
            $tenantId,
            $id,
            $status
        );


        if (!$actualizado) {

            return [
                'success' => false,
                'message' => 'No se pudo cambiar el estado de la campaña.'
            ];
        }


        /*
         * Preparar mensaje.
         */
        if ($status === 'ACTIVE') {

            $mensaje = 'Campaña activada correctamente.';

        } else {

            $mensaje = 'Campaña desactivada correctamente.';
        }


        return [
            'success' => true,
            'message' => $mensaje
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Obtener una campaña
    |--------------------------------------------------------------------------
    | Consultar una campaña requiere campaigns.view.
    */

    public function show(
        int $tenantId,
        int $id
    ): ?array {

        /*
         * Validar permiso y ámbito.
         */
        $this->validarAccesoTenant(
            $tenantId,
            'campaigns.view'
        );


        /*
         * El modelo consulta utilizando ID + tenant.
         */
        return $this->campaign->obtenerPorId(
            $tenantId,
            $id
        );
    }
}
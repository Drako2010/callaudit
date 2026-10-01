<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';


class TenantController
{
    private Tenant $tenant;

    private AuthMiddleware $auth;


    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->tenant = new Tenant();

        $this->auth = new AuthMiddleware();
    }


    /*
    |--------------------------------------------------------------------------
    | Validar acceso GLOBAL
    |--------------------------------------------------------------------------
    |
    | La administración de empresas pertenece al ámbito GLOBAL.
    |
    | Se necesitan dos condiciones:
    |
    | 1. Tener el permiso correspondiente.
    | 2. Ser un usuario GLOBAL (tenant_id = NULL).
    |
    | Tener solamente el permiso NO es suficiente.
    |--------------------------------------------------------------------------
    */

    private function validarAccesoGlobal(string $permission): void
    {
        /*
         * Primero validar autenticación y permiso.
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
         * Un usuario GLOBAL no pertenece a ningún tenant.
         *
         * tenant_id = NULL
         */
        if ($usuario['tenant_id'] !== null) {

            http_response_code(403);

            echo 'Esta operación solamente está disponible para usuarios globales.';

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Listar empresas
    |--------------------------------------------------------------------------
    */

    public function index(): array
    {
        /*
         * Validar autorización antes de consultar la BD.
         */
        $this->validarAccesoGlobal('tenants.view');


        /*
         * Usuario autorizado:
         * ahora sí podemos consultar las empresas.
         */
        return $this->tenant->listar();
    }


    /*
    |--------------------------------------------------------------------------
    | Crear empresa
    |--------------------------------------------------------------------------
    */

    public function store(string $name, string $slug): array
    {
        /*
         * Validar autorización antes de modificar la BD.
         */
        $this->validarAccesoGlobal('tenants.create');


        /*
         * Limpiar datos.
         */
        $name = trim($name);

        $slug = trim($slug);


        /*
         * Validar nombre.
         */
        if ($name === '') {

            return [
                'success' => false,
                'message' => 'El nombre de la empresa es obligatorio.'
            ];
        }


        /*
         * Validar slug.
         */
        if ($slug === '') {

            return [
                'success' => false,
                'message' => 'El slug de la empresa es obligatorio.'
            ];
        }


        /*
         * Validar slug duplicado.
         */
        if ($this->tenant->existeSlug($slug)) {

            return [
                'success' => false,
                'message' => 'El slug ya está registrado.'
            ];
        }


        /*
         * Crear empresa.
         */
        $creado = $this->tenant->crear(
            $name,
            $slug
        );


        /*
         * Verificar resultado.
         */
        if (!$creado) {

            return [
                'success' => false,
                'message' => 'No se pudo crear la empresa.'
            ];
        }


        return [
            'success' => true,
            'message' => 'Empresa creada correctamente.'
        ];
    }
}
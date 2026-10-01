<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/controllers/TenantController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| Crear una empresa es una operación GLOBAL.
|
| Se requiere:
| - autenticación;
| - permiso tenants.create;
| - usuario GLOBAL.
|
| El Controller volverá a validar el ámbito global.
|--------------------------------------------------------------------------
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('tenants.create');


/*
|--------------------------------------------------------------------------
| Servicios
|--------------------------------------------------------------------------
*/

$csrf = new CsrfService();

$controller = new TenantController();

$message = '';


/*
|--------------------------------------------------------------------------
| Procesamiento del formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Validar CSRF antes de procesar el cambio.
     */
    $csrf->validarRequest();


    /*
     * Obtener datos enviados.
     */
    $name = $_POST['name'] ?? '';

    $slug = $_POST['slug'] ?? '';


    /*
     * Crear empresa.
     *
     * El Controller vuelve a validar:
     * - permiso;
     * - ámbito GLOBAL;
     * - datos;
     * - slug duplicado.
     */
    $result = $controller->store(
        $name,
        $slug
    );


    $message = $result['message'];


    /*
     * Si se creó correctamente,
     * regresar al listado.
     */
    if ($result['success']) {

        header('Location: tenants.php');

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/tenants/create.php';
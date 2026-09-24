<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el middleware de autenticación/autorización y el controlador
| encargado de crear campañas.
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| El usuario debe:
|
| 1. Estar autenticado.
| 2. Tener el permiso campaigns.create.
|
| Esta comprobación se realiza en backend.
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('campaigns.create');


/*
|--------------------------------------------------------------------------
| Obtener usuario autenticado
|--------------------------------------------------------------------------
*/

$usuario = $auth->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determinar tenant
|--------------------------------------------------------------------------
| Para un usuario perteneciente a una empresa, utilizamos exclusivamente
| el tenant_id almacenado en su sesión.
|
| No recibimos tenant_id desde POST ni desde GET.
|
| Esto evita que un usuario pueda intentar crear una campaña enviando
| manualmente el ID de otra empresa.
*/

if ($usuario['tenant_id'] === null) {

    /*
     * Un usuario global/SUPERADMIN no tiene una empresa propia.
     *
     * Por ahora no permitimos crear campañas desde esta pantalla sin
     * seleccionar explícitamente una empresa.
     *
     * El manejo completo del ámbito global lo implementaremos después.
     */
    http_response_code(403);

    echo 'Debe seleccionar una empresa para crear campañas.';
    exit;
}

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| ID del usuario creador
|--------------------------------------------------------------------------
| El usuario que crea la campaña se obtiene de la sesión.
|
| Nunca confiamos en un created_by enviado desde el formulario.
*/

$userId = (int) $usuario['id'];


/*
|--------------------------------------------------------------------------
| Instanciar controlador
|--------------------------------------------------------------------------
*/

$controller = new CampaignController();


/*
|--------------------------------------------------------------------------
| Mensaje para la vista
|--------------------------------------------------------------------------
*/

$message = '';


/*
|--------------------------------------------------------------------------
| Procesamiento del formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Obtener los datos enviados por el formulario.
     *
     * El controlador volverá a validar los datos antes de enviarlos
     * al modelo.
     */

    $name = $_POST['name'] ?? '';

    $slug = $_POST['slug'] ?? '';

    $description = $_POST['description'] ?? null;

    $startDate = $_POST['start_date'] ?? null;

    $endDate = $_POST['end_date'] ?? null;


    /*
     * Crear campaña.
     *
     * El tenant_id y created_by NO vienen del formulario.
     * Ambos proceden de la sesión autenticada.
     */

    $resultado = $controller->store(
        $tenantId,
        $name,
        $slug,
        $description,
        $startDate,
        $endDate,
        $userId
    );


    /*
     * Guardar mensaje para mostrarlo en caso de error.
     */

    $message = $resultado['message'];


    /*
     * Si la creación fue correcta, regresar al listado.
     */

    if ($resultado['success']) {

        header('Location: campaigns.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/campaigns/create.php';
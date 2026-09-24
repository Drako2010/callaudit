<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el middleware de autenticación/autorización y el controlador
| encargado de consultar y actualizar campañas.
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
| 2. Tener el permiso campaigns.edit.
|
| La autorización se realiza en backend.
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('campaigns.edit');


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
| El tenant se obtiene exclusivamente del usuario autenticado.
|
| No aceptamos tenant_id desde GET ni POST.
|
| Esto evita que un usuario pueda intentar modificar campañas
| pertenecientes a otra empresa.
*/

if ($usuario['tenant_id'] === null) {

    /*
     * Un usuario global/SUPERADMIN no tiene tenant propio.
     *
     * Por ahora esta pantalla requiere que exista un ámbito de empresa
     * explícito. El manejo completo del ámbito global se implementará
     * posteriormente.
     */
    http_response_code(403);

    echo 'Debe seleccionar una empresa para editar campañas.';
    exit;
}

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| Obtener ID de campaña
|--------------------------------------------------------------------------
| El ID se recibe por GET porque forma parte de la URL de edición.
|
| Convertimos el valor a entero antes de utilizarlo.
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;


/*
|--------------------------------------------------------------------------
| Validar ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    http_response_code(400);

    echo 'ID de campaña inválido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Instanciar controlador
|--------------------------------------------------------------------------
*/

$controller = new CampaignController();


/*
|--------------------------------------------------------------------------
| Obtener campaña
|--------------------------------------------------------------------------
| El controlador recibe tanto el tenant_id como el campaign_id.
|
| El modelo utiliza ambos valores en la consulta:
|
| WHERE id = ? AND tenant_id = ?
|
| Por lo tanto, conocer el ID de una campaña de otra empresa no permite
| acceder a ella desde esta pantalla.
*/

$campaign = $controller->show($tenantId, $id);


/*
|--------------------------------------------------------------------------
| Validar existencia de campaña
|--------------------------------------------------------------------------
*/

if ($campaign === null) {

    http_response_code(404);

    echo 'Campaña no encontrada.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Variables iniciales del formulario
|--------------------------------------------------------------------------
| Inicializamos los valores utilizando la información actual de la
| campaña. Si posteriormente ocurre un error de validación al actualizar,
| los valores enviados por el usuario reemplazarán estos valores.
*/

$name = $campaign['name'];

$slug = $campaign['slug'];

$description = $campaign['description'];

$startDate = $campaign['start_date'];

$endDate = $campaign['end_date'];

$message = '';


/*
|--------------------------------------------------------------------------
| Procesamiento del formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Obtener datos enviados por el formulario.
     */

    $name = $_POST['name'] ?? '';

    $slug = $_POST['slug'] ?? '';

    $description = $_POST['description'] ?? null;

    $startDate = $_POST['start_date'] ?? null;

    $endDate = $_POST['end_date'] ?? null;


    /*
     * Actualizar campaña.
     *
     * El tenant_id continúa siendo el de la sesión.
     * Nunca utilizamos un tenant_id enviado por el navegador.
     */

    $resultado = $controller->update(
        $tenantId,
        $id,
        $name,
        $slug,
        $description,
        $startDate,
        $endDate
    );


    /*
     * Guardar mensaje para mostrarlo en la vista si existe un error.
     */

    $message = $resultado['message'];


    /*
     * Si la actualización fue correcta, regresar al listado.
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

require_once __DIR__ . '/views/campaigns/edit.php';
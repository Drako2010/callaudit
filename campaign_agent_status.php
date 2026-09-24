<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el middleware de autenticación y el controlador encargado
| de administrar las asignaciones de agentes a campañas.
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignUserController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| Cambiar el estado de una asignación modifica información en la base
| de datos, por lo que esta operación requiere campaigns.edit.
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
| El tenant se obtiene exclusivamente de la sesión.
|
| Nunca confiamos en un tenant_id enviado desde GET o POST.
*/

if ($usuario['tenant_id'] === null) {

    /*
     * Los usuarios globales/SUPERADMIN todavía requieren un ámbito
     * de empresa explícitamente seleccionado.
     */
    http_response_code(403);

    echo 'Debe seleccionar una empresa para modificar agentes.';
    exit;
}

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| Verificar método HTTP
|--------------------------------------------------------------------------
| Cambiar el estado modifica datos, por lo que solamente permitimos POST.
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    header('Allow: POST');

    echo 'Método no permitido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Obtener datos enviados
|--------------------------------------------------------------------------
| Recibimos únicamente el ID de la asignación y el nuevo estado.
|
| El tenant NO viene del formulario.
*/

$campaignUserId = isset($_POST['campaign_user_id'])
    ? (int) $_POST['campaign_user_id']
    : 0;

$status = $_POST['status'] ?? '';


/*
|--------------------------------------------------------------------------
| Validar ID de asignación
|--------------------------------------------------------------------------
*/

if ($campaignUserId <= 0) {

    http_response_code(400);

    echo 'Asignación inválida.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Validar estado
|--------------------------------------------------------------------------
| La tabla campaign_users solamente permite:
|
| ACTIVE
| INACTIVE
*/

if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {

    http_response_code(400);

    echo 'Estado no válido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Instanciar controlador
|--------------------------------------------------------------------------
*/

$controller = new CampaignUserController();


/*
|--------------------------------------------------------------------------
| Cambiar estado
|--------------------------------------------------------------------------
| El controlador volverá a comprobar:
|
| - autenticación;
| - campaigns.edit;
| - tenant;
| - existencia de la asignación.
|
| El modelo utiliza tenant_id junto con el ID de la asignación.
*/

$resultado = $controller->cambiarEstado(
    $tenantId,
    $campaignUserId,
    $status
);


/*
|--------------------------------------------------------------------------
| Resultado
|--------------------------------------------------------------------------
*/

if (!$resultado['success']) {

    http_response_code(400);

    echo htmlspecialchars($resultado['message']);

    exit;
}


/*
|--------------------------------------------------------------------------
| Obtener campaña para regresar a su pantalla
|--------------------------------------------------------------------------
| Como el formulario se ejecuta desde campaign_agents.php, recibimos
| también campaign_id para poder regresar al mismo listado.
*/

$campaignId = isset($_POST['campaign_id'])
    ? (int) $_POST['campaign_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Validar campaign_id
|--------------------------------------------------------------------------
*/

if ($campaignId <= 0) {

    http_response_code(400);

    echo 'ID de campaña inválido.';

    exit;
}


/*
|--------------------------------------------------------------------------
| Regresar a la pantalla de agentes
|--------------------------------------------------------------------------
*/

header(
    'Location: campaign_agents.php?id=' . $campaignId
);

exit;
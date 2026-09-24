<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos los middleware y controladores necesarios para consultar
| y administrar los agentes de una campaña.
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignController.php';
require_once __DIR__ . '/controllers/CampaignUserController.php';


/*
|--------------------------------------------------------------------------
| Obtener usuario autenticado
|--------------------------------------------------------------------------
*/

$auth = new AuthMiddleware();

$usuario = $auth->usuario();


/*
|--------------------------------------------------------------------------
| Verificar autenticación
|--------------------------------------------------------------------------
| Aunque posteriormente utilizaremos requierePermiso(), mantenemos esta
| comprobación explícita para garantizar que tenemos un usuario válido.
*/

if ($usuario === null) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determinar tenant
|--------------------------------------------------------------------------
| El tenant siempre procede de la sesión.
|
| Nunca utilizamos tenant_id enviado por GET o POST.
*/

if ($usuario['tenant_id'] === null) {

    /*
     * Los usuarios globales/SUPERADMIN no tienen tenant propio.
     *
     * Por ahora esta pantalla requiere que exista un ámbito de empresa
     * explícito.
     */
    http_response_code(403);

    echo 'Debe seleccionar una empresa para administrar los agentes.';
    exit;
}

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| Obtener ID de campaña
|--------------------------------------------------------------------------
| La campaña se identifica mediante el parámetro id de la URL.
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
| Controladores
|--------------------------------------------------------------------------
*/

$campaignController = new CampaignController();

$campaignUserController = new CampaignUserController();


/*
|--------------------------------------------------------------------------
| Verificar que la campaña pertenece al tenant
|--------------------------------------------------------------------------
| No basta con conocer el ID de la campaña.
|
| El controlador debe comprobar también el tenant_id.
*/

$campaign = $campaignController->show($tenantId, $id);

if ($campaign === null) {

    http_response_code(404);

    echo 'Campaña no encontrada.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Determinar si se está modificando la campaña
|--------------------------------------------------------------------------
| La asignación de agentes modifica datos, por lo que requiere
| campaigns.edit.
|
| Las consultas de agentes requieren campaigns.view.
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Para asignar agentes necesitamos permiso de edición.
     */
    $auth->requierePermiso('campaigns.edit');

} else {

    /*
     * Para visualizar agentes necesitamos permiso de consulta.
     */
    $auth->requierePermiso('campaigns.view');
}


/*
|--------------------------------------------------------------------------
| Procesar asignación de agente
|--------------------------------------------------------------------------
*/

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Obtener el usuario/agente enviado por el formulario.
     *
     * No recibimos tenant_id desde el formulario.
     */
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;


    /*
     * Validar ID del usuario.
     */

    if ($userId <= 0) {

        $message = 'Usuario inválido.';

    } else {

        /*
         * Delegar la asignación al controlador.
         *
         * El CampaignUserController volverá a comprobar:
         *
         * - que la campaña pertenece al tenant;
         * - que el usuario pertenece al tenant;
         * - que el usuario es un AGENTE;
         * - que está activo;
         * - que no está ya asignado.
         */

        $resultado = $campaignUserController->store(
            $tenantId,
            $id,
            $userId
        );


        /*
         * Guardar el mensaje para mostrarlo en la vista.
         */

        $message = $resultado['message'];


        /*
         * Si la asignación fue correcta, redirigimos.
         *
         * Esto evita reenviar el formulario al actualizar la página.
         */

        if ($resultado['success']) {

            header('Location: campaign_agents.php?id=' . $id);
            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Obtener agentes asignados
|--------------------------------------------------------------------------
*/

$assignedAgents = $campaignUserController->index(
    $tenantId,
    $id
);


/*
|--------------------------------------------------------------------------
| Obtener agentes disponibles
|--------------------------------------------------------------------------
*/

$availableAgents = $campaignUserController->agentesDisponibles(
    $tenantId,
    $id
);


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/campaigns/agents.php';
<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignController.php';
require_once __DIR__ . '/controllers/CampaignUserController.php';

$auth = new AuthMiddleware();

$auth->proteger();

$usuario = $auth->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}

$tenantId = (int) $usuario['tenant_id'];

$campaignId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($campaignId <= 0) {
    header('Location: campaigns.php');
    exit;
}

$campaignController = new CampaignController();

$campaign = $campaignController->show(
    $tenantId,
    $campaignId
);

if ($campaign === null) {
    header('Location: campaigns.php');
    exit;
}

$campaignUserController = new CampaignUserController();

$mensaje = null;
$tipoMensaje = null;

/*
 * Procesar asignación de agente.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userId = isset($_POST['user_id'])
        ? (int) $_POST['user_id']
        : 0;

    if ($userId <= 0) {

        $mensaje = 'Debe seleccionar un agente.';
        $tipoMensaje = 'error';

    } else {

        $resultado = $campaignUserController->store(
            $tenantId,
            $campaignId,
            $userId
        );

        $mensaje = $resultado['message'];

        $tipoMensaje = $resultado['success']
            ? 'success'
            : 'error';
    }
}

/*
 * Obtener agentes asignados.
 */
$agentesAsignados = $campaignUserController->index(
    $tenantId,
    $campaignId
);

/*
 * Obtener agentes disponibles.
 */
$agentesDisponibles = $campaignUserController->agentesDisponibles(
    $tenantId,
    $campaignId
);

require_once __DIR__ . '/views/campaigns/agents.php';
<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignController.php';

$auth = new AuthMiddleware();

$auth->proteger();

$usuario = $auth->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}

$tenantId = (int) $usuario['tenant_id'];

$controller = new CampaignController();

// Obtiene las campañas de la empresa del usuario autenticado.
$campaigns = $controller->index($tenantId);

// Envía los datos a la vista.
require_once __DIR__ . '/views/campaigns/index.php';
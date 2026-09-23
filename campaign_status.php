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

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$status = $_GET['status'] ?? '';

if ($id <= 0) {
    header('Location: campaigns.php');
    exit;
}

if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
    header('Location: campaigns.php');
    exit;
}

$resultado = $controller->cambiarEstado(
    $tenantId,
    $id,
    $status
);

// Por ahora volvemos directamente al listado.
header('Location: campaigns.php');

exit;
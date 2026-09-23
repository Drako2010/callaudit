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
$userId = (int) $usuario['id'];

$controller = new CampaignController();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    $resultado = $controller->store(
        $tenantId,
        $name,
        $slug,
        $description,
        $startDate,
        $endDate,
        $userId
    );

    $message = $resultado['message'];

    if ($resultado['success']) {
        header('Location: campaigns.php');
        exit;
    }
}

require_once __DIR__ . '/views/campaigns/create.php';
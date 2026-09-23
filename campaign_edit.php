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

if ($id <= 0) {
    header('Location: campaigns.php');
    exit;
}

// Obtener la campaña perteneciente a la empresa del usuario.
$campaign = $controller->show(
    $tenantId,
    $id
);

if ($campaign === null) {
    header('Location: campaigns.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    $resultado = $controller->update(
        $tenantId,
        $id,
        $name,
        $slug,
        $description,
        $startDate,
        $endDate
    );

    $message = $resultado['message'];

    if ($resultado['success']) {

        header(
            'Location: campaigns.php'
        );

        exit;
    }

    // Si hubo un error, volvemos a mostrar
    // los valores enviados en el formulario.
    $campaign['name'] = $name;
    $campaign['slug'] = $slug;
    $campaign['description'] = $description;
    $campaign['start_date'] = $startDate;
    $campaign['end_date'] = $endDate;
}

require_once __DIR__ . '/views/campaigns/edit.php';
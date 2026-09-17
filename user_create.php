<?php

require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/models/Tenant.php';

$controller = new UserController();
$tenantModel = new Tenant();

$tenants = $tenantModel->listar();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tenantId = (int) ($_POST['tenant_id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = $controller->store(
        $tenantId,
        $name,
        $email,
        $password
    );

    $message = $result['message'];

    if ($result['success']) {
        header('Location: users.php');
        exit;
    }
}

require_once __DIR__ . '/views/users/create.php';
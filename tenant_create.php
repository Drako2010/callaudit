<?php
// Carga el controlador
require_once __DIR__ . '/controllers/TenantController.php';
// Creamos nestro controlador
$controller = new TenantController();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // preguntamos: ¿El usuario acaba de enviar el formulario? Si es así:
    // obtenemos los valores enviados.
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';

    // mandamos los datos al Controller.
    $result = $controller->store($name, $slug);

    $message = $result['message'];

    // Si la creación fue correcta: redirigimos al listado.
    if ($result['success']) {
        header('Location: tenants.php');
        exit;
    }
}

// cargamos la vista
require_once __DIR__ . '/views/tenants/create.php';
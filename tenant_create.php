<?php
// Token
require_once __DIR__ . '/services/CsrfService.php';
// Carga el controlador
require_once __DIR__ . '/controllers/TenantController.php';
// Creamos nestro controlador
$controller = new TenantController();
//Token
$csrf = new CsrfService();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // preguntamos: ¿El usuario acaba de enviar el formulario? Si es así:
    /*
     * Validamos CSRF antes de procesar la creación de la empresa.
     */
    $csrf->validarRequest();
    
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
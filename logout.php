<?php

/*
|--------------------------------------------------------------------------
| Cierre de sesión
|--------------------------------------------------------------------------
| El cierre de sesión modifica el estado de autenticación.
|
| Por seguridad:
| - solamente aceptamos POST;
| - exigimos un token CSRF válido;
| - después cerramos la sesión.
|
| Una petición GET no puede cerrar la sesión.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/SessionService.php';


/*
|--------------------------------------------------------------------------
| Verificar que exista una sesión autenticada
|--------------------------------------------------------------------------
*/

$auth = new AuthMiddleware();

$usuario = $auth->usuario();

if ($usuario === null) {

    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar método HTTP
|--------------------------------------------------------------------------
| Cerrar sesión es una operación que modifica el estado.
| Por eso no permitimos GET.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    header('Allow: POST');

    echo 'Método no permitido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Validar token CSRF
|--------------------------------------------------------------------------
*/

$csrf = new CsrfService();

$csrf->validarRequest();


/*
|--------------------------------------------------------------------------
| Cerrar sesión
|--------------------------------------------------------------------------
*/

$session = new SessionService();

$session->cerrarSesion();


/*
|--------------------------------------------------------------------------
| Regresar al login
|--------------------------------------------------------------------------
*/

header('Location: login.php');
exit;
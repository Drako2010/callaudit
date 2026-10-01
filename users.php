<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/UserController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
|
| Para consultar el listado de usuarios se requiere:
|
| - usuario autenticado;
| - permiso users.view.
|
| El controlador también realizará la validación correspondiente.
|
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('users.view');


/*
|--------------------------------------------------------------------------
| Controlador
|--------------------------------------------------------------------------
*/

$controller = new UserController();


/*
|--------------------------------------------------------------------------
| Obtener usuarios
|--------------------------------------------------------------------------
|
| El Controller determinará posteriormente el ámbito:
|
| - usuario GLOBAL -> usuarios de todas las empresas;
| - usuario TENANT -> solamente usuarios de su empresa.
|
*/

$users = $controller->index();


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/users/index.php';
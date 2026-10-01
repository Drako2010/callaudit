<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/TenantController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| El listado de empresas es una operación global.
|
| El middleware verifica:
| - usuario autenticado;
| - permiso tenants.view.
|
| TenantController además verificará que el usuario
| pertenezca al ámbito GLOBAL.
|--------------------------------------------------------------------------
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('tenants.view');


/*
|--------------------------------------------------------------------------
| Controlador
|--------------------------------------------------------------------------
*/

$controller = new TenantController();


/*
|--------------------------------------------------------------------------
| Obtener empresas
|--------------------------------------------------------------------------
*/

$tenants = $controller->index();


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/tenants/index.php';
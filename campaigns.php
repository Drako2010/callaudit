<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el middleware de autenticación/autorización y el controlador
| encargado de obtener las campañas.
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| El usuario debe:
|
| 1. Estar autenticado.
| 2. Tener el permiso campaigns.view.
|
| La autorización se realiza en backend.
| El hecho de ocultar un enlace en la vista NO constituye seguridad.
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('campaigns.view');


/*
|--------------------------------------------------------------------------
| Obtener usuario autenticado
|--------------------------------------------------------------------------
| Después de requierePermiso() sabemos que el usuario está autenticado
| y tiene autorización para consultar campañas.
*/

$usuario = $auth->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determinar tenant
|--------------------------------------------------------------------------
| Los usuarios pertenecientes a una empresa tienen un tenant_id.
|
| IMPORTANTE:
| Un usuario SUPERADMIN/SUPERGLOBAL puede tener tenant_id = NULL.
| En ese caso no debemos convertir NULL silenciosamente a 0 y asumir
| que ese es un tenant válido.
|
| El tratamiento específico de usuarios globales lo implementaremos
| en el siguiente paso del módulo.
*/

if ($usuario['tenant_id'] === null) {

    /*
     * Por ahora dejamos preparado el control explícito.
     * No utilizamos 0 como tenant ficticio.
     */
    http_response_code(403);

    echo 'Debe seleccionar una empresa para administrar campañas.';
    exit;
}

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| Controlador
|--------------------------------------------------------------------------
*/

$controller = new CampaignController();


/*
|--------------------------------------------------------------------------
| Obtener campañas
|--------------------------------------------------------------------------
| El controlador recibe el tenant del usuario autenticado.
| El modelo vuelve a utilizar tenant_id en la consulta SQL para mantener
| el aislamiento entre empresas.
*/

$campaigns = $controller->index($tenantId);


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/campaigns/index.php';
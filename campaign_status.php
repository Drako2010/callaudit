<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el middleware de autenticación/autorización y el controlador
| encargado de modificar el estado de la campaña.
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| Cambiar el estado de una campaña es una operación de edición.
|
| Por eso utilizamos campaigns.edit.
*/

$auth = new AuthMiddleware();

$auth->requierePermiso('campaigns.edit');


/*
|--------------------------------------------------------------------------
| Obtener usuario autenticado
|--------------------------------------------------------------------------
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
| El tenant se obtiene exclusivamente de la sesión.
|
| Nunca aceptamos tenant_id enviado por GET o POST.
*/

if ($usuario['tenant_id'] === null) {

    /*
     * Un usuario global/SUPERADMIN no tiene un tenant propio.
     *
     * Por ahora esta pantalla requiere un tenant explícito.
     */
    http_response_code(403);

    echo 'Debe seleccionar una empresa para modificar campañas.';
    exit;
}

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| Verificar método HTTP
|--------------------------------------------------------------------------
| Cambiar el estado modifica información en la base de datos.
|
| Por seguridad, esta operación solamente puede ejecutarse mediante POST.
|
| Esto evita que una simple visita a una URL provoque un cambio de estado.
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    header('Allow: POST');

    echo 'Método no permitido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Obtener datos enviados
|--------------------------------------------------------------------------
| El campaign_id y el nuevo estado vienen del formulario POST.
*/

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$status = $_POST['status'] ?? '';


/*
|--------------------------------------------------------------------------
| Validar ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    http_response_code(400);

    echo 'ID de campaña inválido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Validar estado
|--------------------------------------------------------------------------
| Solo permitimos los dos estados definidos actualmente en la base:
|
| ACTIVE
| INACTIVE
*/

if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {

    http_response_code(400);

    echo 'Estado de campaña inválido.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Instanciar controlador
|--------------------------------------------------------------------------
*/

$controller = new CampaignController();


/*
|--------------------------------------------------------------------------
| Cambiar estado
|--------------------------------------------------------------------------
| El tenant_id utilizado es exclusivamente el obtenido de la sesión.
|
| El controlador/modelo volverá a comprobar:
|
| WHERE id = ? AND tenant_id = ?
|
| Por lo tanto, un usuario no puede cambiar el estado de una campaña
| perteneciente a otra empresa.
*/

$resultado = $controller->cambiarEstado(
    $tenantId,
    $id,
    $status
);


/*
|--------------------------------------------------------------------------
| Resultado
|--------------------------------------------------------------------------
*/

if (!$resultado['success']) {

    /*
     * Si la operación falla, mostramos el mensaje devuelto por el
     * controlador.
     */
    http_response_code(400);

    echo $resultado['message'];
    exit;
}


/*
|--------------------------------------------------------------------------
| Operación exitosa
|--------------------------------------------------------------------------
| Regresamos al listado de campañas.
*/

header('Location: campaigns.php');
exit;
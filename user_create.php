<?php

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
| Cargamos el middleware para proteger la página, los modelos necesarios
| para obtener empresas y roles, y el controlador encargado de crear
| el usuario de forma segura y transaccional.
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/SessionService.php';
require_once __DIR__ . '/models/Tenant.php';
require_once __DIR__ . '/models/Role.php';
require_once __DIR__ . '/controllers/UserController.php';


/*
|--------------------------------------------------------------------------
| Protección de acceso
|--------------------------------------------------------------------------
| La página solamente puede ser utilizada por usuarios autenticados
| que tengan el permiso users.create.
|
| Esta validación es de backend. El formulario HTML por sí solo nunca
| constituye un mecanismo de seguridad.
*/

$middleware = new AuthMiddleware();
$middleware->requierePermiso('users.create');


/*
|--------------------------------------------------------------------------
| Obtener usuario autenticado
|--------------------------------------------------------------------------
| Necesitamos conocer el tenant del usuario actual para determinar
| qué empresas puede administrar.
|
| tenant_id = NULL significa usuario global/SUPERADMIN.
| tenant_id distinto de NULL significa usuario perteneciente a una
| empresa concreta.
*/

$session = new SessionService();
$usuarioActual = $session->obtenerUsuario();

if ($usuarioActual === null) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Instanciar modelos y controlador
|--------------------------------------------------------------------------
*/

$controller = new UserController();
$tenantModel = new Tenant();
$roleModel = new Role();


/*
|--------------------------------------------------------------------------
| Determinar el ámbito de empresas
|--------------------------------------------------------------------------
| Un usuario global puede trabajar con cualquier empresa activa.
|
| Un usuario perteneciente a una empresa solamente puede trabajar
| con su propia empresa.
|
| Esta restricción también será validada nuevamente dentro del
| UserController. La vista no es el mecanismo de seguridad.
*/

if ($usuarioActual['tenant_id'] === null) {

    // Usuario global: obtiene todas las empresas disponibles.
    $tenants = $tenantModel->listar();

} else {

    // Usuario de empresa: solamente puede trabajar con su propia empresa.
    $tenant = $tenantModel->obtenerPorId(
        (int) $usuarioActual['tenant_id']
    );

    if ($tenant === null || $tenant['status'] !== 'ACTIVE') {
        http_response_code(403);
        echo 'La empresa del usuario no está disponible.';
        exit;
    }

    $tenants = [$tenant];
}


/*
|--------------------------------------------------------------------------
| Valores iniciales del formulario
|--------------------------------------------------------------------------
| Si estamos entrando por primera vez mediante GET, podemos recibir
| tenant_id desde la URL para cargar los roles de esa empresa.
|
| Si el formulario fue enviado mediante POST, el valor enviado por POST
| tiene prioridad.
*/

$selectedTenantId = (int) (
    $_POST['tenant_id']
    ?? $_GET['tenant_id']
    ?? 0
);

$selectedRoleId = (int) ($_POST['role_id'] ?? 0);

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';

$message = '';


/*
|--------------------------------------------------------------------------
| Determinar empresa inicial
|--------------------------------------------------------------------------
| Si el usuario pertenece a una empresa, la empresa queda seleccionada
| automáticamente y no puede cambiarse desde el formulario.
|
| Si es SUPERADMIN/global, podrá seleccionar la empresa.
*/

if ($usuarioActual['tenant_id'] !== null) {

    $selectedTenantId = (int) $usuarioActual['tenant_id'];
}


/*
|--------------------------------------------------------------------------
| Cargar roles disponibles
|--------------------------------------------------------------------------
| Los roles son específicos de cada empresa.
|
| Por eso solamente cargamos los roles correspondientes a la empresa
| seleccionada.
|
| Para la creación normal de usuarios de empresa no permitimos roles
| globales (tenant_id NULL).
*/

$roles = [];

if ($selectedTenantId > 0) {

    $roles = $roleModel->listarPorTenant($selectedTenantId);
}


/*
|--------------------------------------------------------------------------
| Procesamiento del formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';

    /*
    |----------------------------------------------------------------------
    | El controlador vuelve a validar:
    | - autenticación
    | - permiso users.create
    | - ámbito del tenant
    | - existencia y estado del tenant
    | - datos del usuario
    | - existencia y estado del rol
    | - pertenencia del rol al tenant
    | - creación transaccional
    |
    | Por seguridad no confiamos únicamente en las validaciones realizadas
    | en esta página.
    */

    $result = $controller->store(
        $selectedTenantId,
        $name,
        $email,
        $password,
        $selectedRoleId
    );

    $message = $result['message'];

    /*
    |----------------------------------------------------------------------
    | Si todo fue correcto, regresamos al listado de usuarios.
    */

    if ($result['success']) {

        header('Location: users.php');
        exit;
    }

    /*
    |----------------------------------------------------------------------
    | Si hubo error, volvemos a cargar los roles del tenant seleccionado.
    | Esto permite que el formulario conserve la selección realizada.
    */

    if ($selectedTenantId > 0) {

        $roles = $roleModel->listarPorTenant($selectedTenantId);
    }
}


/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/views/users/create.php';

<?php

/*
|--------------------------------------------------------------------------
| ARCHIVO: campaign_agent_status.php
|--------------------------------------------------------------------------
| PROPÓSITO:
| Activar o desactivar la asignación de un agente a una campaña.
|
| FUNCIONAMIENTO GENERAL:
| 1. Cargar los servicios y el controlador necesarios.
| 2. Comprobar que el usuario esté autenticado y tenga permiso.
| 3. Obtener la empresa desde la sesión, nunca desde el formulario.
| 4. Aceptar exclusivamente solicitudes POST.
| 5. Validar el token CSRF para impedir solicitudes externas no autorizadas.
| 6. Validar los identificadores y el estado solicitado.
| 7. Delegar el cambio al controlador, que vuelve a comprobar
|    los permisos y la pertenencia de los registros a la empresa.
| 8. Regresar al listado de agentes de la campaña.
|
| IMPORTANTE:
| Este archivo no ejecuta directamente consultas SQL.
| La modificación se realiza a través de CampaignUserController.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| 1. CARGAR LAS DEPENDENCIAS
|--------------------------------------------------------------------------
| require_once incorpora los archivos necesarios para utilizar sus clases.
|
| CsrfService:
| Genera y valida el token de seguridad asociado a la sesión.
|
| AuthMiddleware:
| Comprueba la autenticación y los permisos del usuario.
|
| CampaignUserController:
| Gestiona las operaciones de asignación de agentes a campañas.
|
| __DIR__ representa el directorio en el que se encuentra este archivo.
| Esto permite construir rutas independientes del directorio de ejecución.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/controllers/CampaignUserController.php';


/*
|--------------------------------------------------------------------------
| 2. INICIALIZAR LOS SERVICIOS DE SEGURIDAD
|--------------------------------------------------------------------------
| Creamos los objetos que utilizaremos durante la solicitud.
|
| $csrf:
| Permite comprobar que la solicitud procede de un formulario válido
| generado por la aplicación y asociado a la sesión del usuario.
|
| $auth:
| Permite comprobar la identidad del usuario y sus permisos de acceso.
|--------------------------------------------------------------------------
*/

$csrf = new CsrfService();

$auth = new AuthMiddleware();


/*
|--------------------------------------------------------------------------
| 3. COMPROBAR EL PERMISO PARA MODIFICAR ASIGNACIONES
|--------------------------------------------------------------------------
| campaigns.edit es el permiso requerido para modificar la asignación
| de agentes a una campaña.
|
| requierePermiso() utiliza el sistema de autorización existente.
| Si el usuario no tiene autorización, el middleware debe detener
| la ejecución de acuerdo con su implementación.
|
| Esta comprobación evita que un usuario con permisos exclusivamente
| de consulta pueda activar o desactivar asignaciones.
|--------------------------------------------------------------------------
*/

$auth->requierePermiso('campaigns.edit');


/*
|--------------------------------------------------------------------------
| 4. OBTENER EL USUARIO AUTENTICADO
|--------------------------------------------------------------------------
| usuario() recupera los datos del usuario autenticado a través
| del mecanismo de sesión utilizado por CallAudit.
|
| No debemos confiar en un user_id enviado por el formulario para
| identificar al usuario que está ejecutando la operación.
|--------------------------------------------------------------------------
*/

$usuario = $auth->usuario();


/*
|--------------------------------------------------------------------------
| 5. VERIFICAR QUE EXISTA UN USUARIO AUTENTICADO
|--------------------------------------------------------------------------
| Si no hay un usuario autenticado, no se permite continuar.
|
| Redirigimos al formulario de inicio de sesión y terminamos
| la ejecución con exit para impedir que se procese la solicitud.
|--------------------------------------------------------------------------
*/

if ($usuario === null) {

    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| 6. OBTENER Y VALIDAR LA EMPRESA DEL USUARIO
|--------------------------------------------------------------------------
| CallAudit es una aplicación multiempresa.
|
| Cada usuario normal trabaja dentro de su propia empresa o tenant.
| El identificador de empresa debe proceder de la sesión autenticada,
| no de un campo enviado por el navegador.
|
| Si tenant_id es NULL, el usuario no tiene una empresa asociada
| directamente en su sesión. Esta ruta no permite elegir una empresa
| globalmente, por lo que rechaza la operación.
|--------------------------------------------------------------------------
*/

if (
    !isset($usuario['tenant_id'])
    || $usuario['tenant_id'] === null
) {

    http_response_code(403);

    echo 'Debe seleccionar una empresa para modificar agentes.';

    exit;
}


/*
|--------------------------------------------------------------------------
| Convertir el identificador de empresa a entero
|--------------------------------------------------------------------------
| Convertimos el valor de sesión a entero para trabajar con un tipo
| consistente en las validaciones y llamadas al controlador.
|
| No estamos aceptando un tenant_id enviado por POST o GET.
|--------------------------------------------------------------------------
*/

$tenantId = (int) $usuario['tenant_id'];


/*
|--------------------------------------------------------------------------
| 7. COMPROBAR EL MÉTODO HTTP
|--------------------------------------------------------------------------
| Esta operación modifica información y solamente debe ejecutarse
| mediante una solicitud POST.
|
| GET se utiliza habitualmente para consultar información.
| No debe utilizarse para activar o desactivar registros.
|
| Si llega otro método HTTP:
| - Respondemos con el código 405: método no permitido.
| - Indicamos que el método permitido es POST.
| - Detenemos la ejecución.
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
| 8. VALIDAR EL TOKEN CSRF
|--------------------------------------------------------------------------
| CSRF significa Cross-Site Request Forgery.
|
| Este ataque intenta conseguir que el navegador de un usuario
| autenticado ejecute una operación que el usuario no ha autorizado.
|
| validarRequest():
| - Obtiene el token enviado en $_POST['csrf_token'].
| - Lo compara con el token almacenado en la sesión.
| - Rechaza la solicitud si el token falta o no coincide.
|
| Si la validación falla, CsrfService responde con HTTP 403
| y termina la ejecución.
|
| Esta comprobación se realiza antes de cambiar cualquier dato.
|
| IMPORTANTE:
| El formulario que llame a esta ruta debe enviar un campo oculto
| llamado csrf_token, generado mediante CsrfService::token().
|--------------------------------------------------------------------------
*/

$csrf->validarRequest();


/*
|--------------------------------------------------------------------------
| 9. OBTENER LOS DATOS DEL FORMULARIO
|--------------------------------------------------------------------------
| campaign_id:
| Identifica la campaña en la que se encuentra la asignación.
|
| campaign_user_id:
| Identifica el registro concreto de la tabla campaign_users.
| No debe confundirse con el ID del usuario de la tabla users.
|
| status:
| Es el nuevo estado que se desea establecer para la asignación.
|
| El tenant_id no se recibe del formulario: se utiliza el obtenido
| previamente desde la sesión autenticada.
|--------------------------------------------------------------------------
*/

$campaignId = isset($_POST['campaign_id'])
    ? (int) $_POST['campaign_id']
    : 0;

$campaignUserId = isset($_POST['campaign_user_id'])
    ? (int) $_POST['campaign_user_id']
    : 0;

$status = isset($_POST['status']) && is_string($_POST['status'])
    ? strtoupper(trim($_POST['status']))
    : '';


/*
|--------------------------------------------------------------------------
| 10. VALIDAR EL IDENTIFICADOR DE CAMPAÑA
|--------------------------------------------------------------------------
| Los identificadores válidos deben ser mayores que cero.
|
| Esta validación evita enviar identificadores vacíos, negativos
| o iguales a cero al controlador.
|
| La comprobación de que la campaña realmente pertenece al tenant
| se realiza posteriormente dentro de CampaignUserController.
|--------------------------------------------------------------------------
*/

if ($campaignId <= 0) {

    http_response_code(400);

    echo 'ID de campaña inválido.';

    exit;
}


/*
|--------------------------------------------------------------------------
| 11. VALIDAR EL IDENTIFICADOR DE LA ASIGNACIÓN
|--------------------------------------------------------------------------
| campaignUserId identifica la fila de campaign_users que queremos
| activar o desactivar.
|
| Un valor menor o igual que cero no representa un identificador válido
| para esta operación.
|
| La existencia de la asignación y su pertenencia a la campaña y empresa
| serán comprobadas por el controlador y el modelo.
|--------------------------------------------------------------------------
*/

if ($campaignUserId <= 0) {

    http_response_code(400);

    echo 'Asignación inválida.';

    exit;
}


/*
|--------------------------------------------------------------------------
| 12. VALIDAR EL NUEVO ESTADO
|--------------------------------------------------------------------------
| Solamente se permiten los estados definidos por la aplicación:
|
| ACTIVE:
| La asignación del agente está activa.
|
| INACTIVE:
| La asignación del agente está desactivada.
|
| La comparación estricta evita aceptar valores diferentes
| de los dos estados permitidos.
|--------------------------------------------------------------------------
*/

if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {

    http_response_code(400);

    echo 'Estado no válido.';

    exit;
}


/*
|--------------------------------------------------------------------------
| 13. CREAR EL CONTROLADOR DE ASIGNACIONES
|--------------------------------------------------------------------------
| El controlador centraliza las reglas de negocio de las asignaciones.
|
| No ejecutamos un UPDATE directamente desde este archivo porque
| las validaciones de autorización y aislamiento multiempresa deben
| permanecer centralizadas en las clases correspondientes.
|--------------------------------------------------------------------------
*/

$controller = new CampaignUserController();


/*
|--------------------------------------------------------------------------
| 14. SOLICITAR EL CAMBIO DE ESTADO
|--------------------------------------------------------------------------
| Enviamos los cuatro argumentos que requiere el método actual
| CampaignUserController::cambiarEstado():
|
| $tenantId:
| Empresa obtenida de la sesión autenticada.
|
| $campaignId:
| Campaña que se está administrando.
|
| $campaignUserId:
| Registro de asignación que se desea modificar.
|
| $status:
| Nuevo estado solicitado.
|
| El controlador vuelve a comprobar:
| - autenticación y permiso campaigns.edit;
| - autorización para operar dentro del tenant;
| - pertenencia de la campaña al tenant;
| - existencia de la asignación dentro de esa campaña;
| - validez del estado solicitado.
|
| De este modo, conocer un ID no debería permitir modificar
| asignaciones pertenecientes a otras empresas o campañas.
|--------------------------------------------------------------------------
*/

$resultado = $controller->cambiarEstado(
    $tenantId,
    $campaignId,
    $campaignUserId,
    $status
);


/*
|--------------------------------------------------------------------------
| 15. COMPROBAR EL RESULTADO DE LA OPERACIÓN
|--------------------------------------------------------------------------
| El controlador devuelve un arreglo con información sobre el resultado.
|
| success:
| Indica si la operación terminó correctamente.
|
| message:
| Contiene el mensaje informativo o de error producido por el controlador.
|
| Si la operación falla:
| - Respondemos con HTTP 400.
| - Escapamos el mensaje antes de mostrarlo en HTML.
| - Terminamos la ejecución sin redirigir como si hubiera sido exitosa.
|--------------------------------------------------------------------------
*/

if (
    !isset($resultado['success'])
    || $resultado['success'] !== true
) {

    http_response_code(400);

    $mensaje = isset($resultado['message'])
        && is_string($resultado['message'])
        ? $resultado['message']
        : 'No se pudo actualizar el estado de la asignación.';

    echo htmlspecialchars(
        $mensaje,
        ENT_QUOTES,
        'UTF-8'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| 16. REDIRIGIR AL LISTADO DE AGENTES
|--------------------------------------------------------------------------
| Si el controlador confirma que el cambio se realizó correctamente,
| regresamos a la pantalla de administración de agentes de la campaña.
|
| Utilizamos el identificador de campaña previamente validado.
|
| La redirección evita mostrar nuevamente el formulario de modificación
| si el usuario actualiza la página.
|--------------------------------------------------------------------------
*/

header(
    'Location: campaign_agents.php?id=' . $campaignId
);

exit;


/*
|--------------------------------------------------------------------------
| FIN DEL ARCHIVO
|--------------------------------------------------------------------------
| El flujo de ejecución termina después de la redirección.
|
| La lógica de autorización y modificación de datos permanece
| en los servicios, el controlador y el modelo correspondientes.
|--------------------------------------------------------------------------
*/
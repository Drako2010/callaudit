<?php
/**
 * ============================================================================
 * ARCHIVO: audit_forms.php
 * PROYECTO: CallAudit
 * ============================================================================
 *
 * OBJETIVO
 * --------
 * Página principal del módulo de formularios de auditoría.
 *
 * FUNCIONES DISPONIBLES
 * ---------------------
 * - Listar los formularios de la empresa autorizada.
 * - Mostrar el formulario de creación si el usuario tiene audits.create.
 * - Procesar la creación mediante POST.
 * - Mostrar mensajes de resultado.
 *
 * SERVICIOS UTILIZADOS
 * --------------------
 * AuthMiddleware        : autenticación y autorización.
 * CampaignTenantContext : selección y validación del tenant.
 * CsrfService           : protección contra solicitudes CSRF.
 * AuditFormController   : operaciones del módulo.
 *
 * SEGURIDAD
 * ---------
 * - Se exige el permiso audits.view para entrar.
 * - El tenant se obtiene desde el contexto de sesión validado.
 * - El POST valida el token CSRF.
 * - Los datos mostrados se escapan con htmlspecialchars().
 * - La página no acepta tenant_id desde GET o POST.
 * ============================================================================
 */

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/controllers/AuditFormController.php';

/*
|--------------------------------------------------------------------------
| 1. VALIDAR AUTENTICACIÓN Y PERMISO DE LECTURA
|--------------------------------------------------------------------------
|
| La protección se realiza en el servidor. Ocultar un enlace en HTML
| no sustituye la autorización del backend.
*/
$auth = new AuthMiddleware();
$auth->requierePermiso('audits.view');

/*
|--------------------------------------------------------------------------
| 2. OBTENER USUARIO AUTENTICADO
|--------------------------------------------------------------------------
*/
$usuario = $auth->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. RESOLVER EL CONTEXTO DE EMPRESA
|--------------------------------------------------------------------------
|
| Usuarios empresariales:
|   CampaignTenantContext devuelve el tenant asociado a la sesión.
|
| SUPERADMIN global:
|   Debe haber seleccionado previamente una empresa mediante el flujo
|   existente del proyecto. El contexto se valida en el servidor.
|
| No se toma tenant_id desde parámetros de URL o formulario.
*/
$tenantContext = new CampaignTenantContext();
$tenantId = $tenantContext->resolver($usuario);

/*
|--------------------------------------------------------------------------
| 4. INICIALIZAR SERVICIOS Y VARIABLES DE LA VISTA
|--------------------------------------------------------------------------
*/
$controller = new AuditFormController();
$csrf = new CsrfService();

$mensaje = '';
$tipoMensaje = 'info';
$formularios = [];

/*
|--------------------------------------------------------------------------
| 5. PROCESAR CREACIÓN MEDIANTE POST
|--------------------------------------------------------------------------
|
| Solo se permite crear si:
| - La petición utiliza POST.
| - El usuario tiene audits.create.
| - El token CSRF es válido.
| - El controlador confirma el ámbito de empresa.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
     * Validar token CSRF antes de procesar los campos.
     * El servicio existente devuelve HTTP 403 y finaliza si el token
     * falta o no coincide con el almacenado en la sesión.
     */
    $csrf->validarRequest();

    /*
     * Validar permiso de creación.
     *
     * Si el usuario no tiene audits.create, el middleware responde
     * con acceso denegado y la operación no continúa.
     */
    $auth->requierePermiso('audits.create');

    /*
     * Leer los campos sin confiar en su tipo.
     * Se rechazan arreglos u otros valores manipulados en el POST.
     */
    $nombreRecibido = $_POST['name'] ?? '';
    $descripcionRecibida = $_POST['description'] ?? '';

    if (!is_string($nombreRecibido)) {
        $nombreRecibido = '';
    }

    if (!is_string($descripcionRecibida)) {
        $descripcionRecibida = '';
    }

    $nombre = trim($nombreRecibido);
    $descripcion = trim($descripcionRecibida);

    if ($descripcion === '') {
        $descripcion = null;
    }

    /*
     * Solicitar al controlador que cree el formulario.
     * El ID del creador se obtiene internamente desde la sesión.
     */
    $resultado = $controller->store(
        $tenantId,
        $nombre,
        $descripcion
    );

    /*
     * Preparar mensaje para la interfaz.
     * No se exponen excepciones SQL ni información interna.
     */
    $mensaje = (string) ($resultado['message'] ?? 'Operación finalizada.');

    if (!empty($resultado['success'])) {
        $tipoMensaje = 'success';

        /*
         * PRG (Post/Redirect/Get)
         *
         * Evita que actualizar la página vuelva a enviar el formulario.
         * El mensaje de éxito se comunica mediante un parámetro fijo.
         */
        header('Location: audit_forms.php?created=1');
        exit;
    }

    $tipoMensaje = 'error';
}

/*
|--------------------------------------------------------------------------
| 6. MOSTRAR MENSAJE DE CONFIRMACIÓN DESPUÉS DE REDIRECCIÓN
|--------------------------------------------------------------------------
|
| El parámetro solo controla la presentación de un mensaje fijo.
| No se utiliza para autorizar operaciones ni modificar información.
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['created'])
    && $_GET['created'] === '1'
) {
    $mensaje = 'Formulario creado correctamente.';
    $tipoMensaje = 'success';
}

/*
|--------------------------------------------------------------------------
| 7. CARGAR FORMULARIOS
|--------------------------------------------------------------------------
|
| El modelo limita la consulta al tenant autorizado.
*/
$formularios = $controller->index($tenantId);

/*
|--------------------------------------------------------------------------
| 8. COMPROBAR SI SE PUEDE MOSTRAR EL FORMULARIO DE CREACIÓN
|--------------------------------------------------------------------------
|
| Este chequeo controla la interfaz.
| La seguridad real se vuelve a comprobar en el POST y en el controlador.
*/
$puedeCrear = false;

try {
    $authorization = new Authorization();
    $puedeCrear = $authorization->tienePermiso(
        (int) $usuario['id'],
        'audits.create'
    );
} catch (Throwable $e) {
    // Si no se puede confirmar el permiso, se oculta la opción.
    $puedeCrear = false;

    error_log(
        '[CallAudit][audit_forms.php][permiso] '
        . get_class($e) . ': ' . $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| 9. FUNCIONES DE ESCAPE HTML
|--------------------------------------------------------------------------
|
| Todo dato dinámico que se inserte en HTML debe escaparse.
| Esto reduce el riesgo de Cross-Site Scripting (XSS).
*/
function auditFormsEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| 10. OBTENER TOKEN PARA EL FORMULARIO
|--------------------------------------------------------------------------
*/
$csrfToken = $csrf->token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <!-- Ajuste para escritorio, tabletas y teléfonos. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Formularios de auditoría | CallAudit</title>

    <!--
        Estilos básicos incluidos en la página para que el módulo funcione
        sin depender de archivos CSS que aún no estén implementados.
        Se pueden mover posteriormente a public/css.
    -->
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #f4f6f9;
            color: #202733;
        }

        .contenedor {
            max-width: 1100px;
            margin: 0 auto;
        }

        .encabezado {
            margin-bottom: 24px;
        }

        .panel {
            margin-bottom: 24px;
            padding: 22px;
            background: #fff;
            border: 1px solid #dfe4ea;
            border-radius: 8px;
        }

        h1, h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin: 14px 0 6px;
            font-weight: bold;
        }

        input[type="text"],
        textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            border: 1px solid #bcc5d0;
            border-radius: 5px;
            font: inherit;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            margin-top: 16px;
            padding: 10px 16px;
            border: 0;
            border-radius: 5px;
            background: #2457a7;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        button:hover {
            background: #194580;
        }

        .mensaje {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 5px;
        }

        .mensaje-success {
            background: #e8f7ed;
            border: 1px solid #a9dfba;
            color: #185b2c;
        }

        .mensaje-error {
            background: #fdecec;
            border: 1px solid #efb6b6;
            color: #842020;
        }

        .mensaje-info {
            background: #edf3ff;
            border: 1px solid #bfd0f5;
            color: #1e3d72;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 11px;
            border-bottom: 1px solid #e2e6eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f7f8fa;
        }

        .estado {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: #e8edf5;
            font-size: 0.9rem;
        }

        .vacio {
            color: #5c6572;
        }
    </style>
</head>

<body>
<div class="contenedor">

    <!-- Encabezado principal del módulo. -->
    <header class="encabezado">
        <h1>Formularios de auditoría</h1>
        <p>
            Administra los formularios y prepara sus versiones para evaluar
            llamadas de la empresa.
        </p>
    </header>

    <!-- Mensaje de resultado de la operación. -->
    <?php if ($mensaje !== ''): ?>
        <div
            class="mensaje mensaje-<?php echo auditFormsEscape($tipoMensaje); ?>"
            role="status"
        >
            <?php echo auditFormsEscape($mensaje); ?>
        </div>
    <?php endif; ?>

    <!--
        Panel de creación.
        Solo se muestra si el usuario tiene el permiso audits.create.
    -->
    <?php if ($puedeCrear): ?>
        <section class="panel">
            <h2>Crear formulario</h2>

            <p>
                El nuevo formulario se creará con una primera versión
                en estado borrador. Las preguntas se configurarán después.
            </p>

            <form method="POST" action="audit_forms.php">
                <!-- Token CSRF obligatorio para proteger la operación. -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo auditFormsEscape($csrfToken); ?>"
                >

                <label for="name">Nombre del formulario</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="150"
                    required
                    autocomplete="off"
                    placeholder="Ejemplo: Auditoría de calidad de ventas"
                >

                <label for="description">Descripción (opcional)</label>
                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe el objetivo del formulario."
                ></textarea>

                <button type="submit">Crear formulario</button>
            </form>
        </section>
    <?php endif; ?>

    <!-- Listado de formularios que pertenecen al tenant autorizado. -->
    <section class="panel">
        <h2>Formularios registrados</h2>

        <?php if (count($formularios) === 0): ?>
            <p class="vacio">
                No hay formularios registrados para esta empresa.
            </p>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Versiones</th>
                        <th>Versión más reciente</th>
                        <th>Creación</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($formularios as $formulario): ?>
                        <tr>
                            <td>
                                <?php
                                echo auditFormsEscape($formulario['id'] ?? '');
                                ?>
                            </td>

                            <td>
                                <?php
                                echo auditFormsEscape($formulario['name'] ?? '');
                                ?>
                            </td>

                            <td>
                                <?php
                                echo auditFormsEscape(
                                    $formulario['description'] ?? ''
                                );
                                ?>
                            </td>

                            <td>
                                <span class="estado">
                                    <?php
                                    echo auditFormsEscape(
                                        $formulario['status'] ?? ''
                                    );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                echo auditFormsEscape(
                                    $formulario['total_versions'] ?? 0
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                $version = $formulario['latest_version'] ?? null;

                                echo $version !== null
                                    ? auditFormsEscape($version)
                                    : '—';
                                ?>
                            </td>

                            <td>
                                <?php
                                echo auditFormsEscape(
                                    $formulario['created_at'] ?? ''
                                );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
</body>
</html>

<?php
/**
 * Página de administración de fuentes telefónicas de CallAudit.
 *
 * Alcance de este bloque:
 * - Registrar varias fuentes por empresa.
 * - Guardar plataforma y modo de integración.
 * - Editar metadatos y activar/desactivar fuentes.
 *
 * La prueba real de conexión, sincronización y almacenamiento cifrado de
 * credenciales se implementarán en el bloque de conectores/secretos. Esta
 * página no pide contraseñas ni finge que una conexión fue probada.
 */
require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/controllers/TelephonySourceController.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.view');
$user = $auth->usuario();
if ($user === null) {
    header('Location: login.php');
    exit;
}

$tenantId = (new CampaignTenantContext())->resolver($user);
$csrf = new CsrfService();
$controller = new TelephonySourceController();
$message = '';
$messageType = 'info';

function telephonyEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$platforms = [
    'ASTERISK' => 'Asterisk',
    'VICIDIAL' => 'Vicidial',
    'ISSABEL' => 'Issabel',
    'GENERIC_API' => 'API genérica',
    'DATABASE' => 'Base de datos',
    'CSV' => 'CSV',
    'EXCEL' => 'Excel',
    'MANUAL' => 'Manual',
    'OTHER' => 'Otra'
];
$modes = [
    'API' => 'API',
    'READONLY_DATABASE' => 'Base de datos de solo lectura',
    'FILE_IMPORT' => 'Importación de archivos',
    'MANUAL' => 'Carga/registro manual',
    'OTHER' => 'Otro'
];

$editId = 0;
if (isset($_GET['edit']) && is_string($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
}
$editing = $editId > 0 ? $controller->show($tenantId, $editId) : null;
if ($editId > 0 && $editing === null) {
    http_response_code(404);
    exit('La fuente solicitada no existe en la empresa seleccionada.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();
    $action = $_POST['action'] ?? '';
    $idValue = $_POST['id'] ?? '';
    $id = (is_string($idValue) && ctype_digit($idValue) && (int)$idValue > 0)
        ? (int)$idValue : null;

    if ($action === 'save') {
        $name = $_POST['name'] ?? '';
        $platform = $_POST['platform'] ?? '';
        $mode = $_POST['connection_mode'] ?? '';
        $host = $_POST['host'] ?? '';
        $portRaw = $_POST['port'] ?? '';
        $databaseName = $_POST['database_name'] ?? '';

        foreach (['name', 'platform', 'connection_mode', 'host', 'port', 'database_name'] as $field) {
            if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                http_response_code(400);
                exit('Solicitud no válida.');
            }
        }
        $port = trim((string)$portRaw) === '' ? null :
            (ctype_digit((string)$portRaw) ? (int)$portRaw : -1);

        $result = $controller->store(
            $tenantId, $id, (string)$name, (string)$platform, (string)$mode,
            (string)$host !== '' ? (string)$host : null,
            $port,
            (string)$databaseName !== '' ? (string)$databaseName : null
        );
        $message = (string)($result['message'] ?? 'Operación finalizada.');
        $messageType = !empty($result['success']) ? 'success' : 'error';
        if (!empty($result['success'])) {
            header('Location: telephony_sources.php?saved=1');
            exit;
        }
    } elseif ($action === 'status') {
        if ($id === null) {
            http_response_code(400);
            exit('Identificador no válido.');
        }
        $status = $_POST['status'] ?? '';
        if (!is_string($status)) {
            http_response_code(400);
            exit('Estado no válido.');
        }
        $result = $controller->changeStatus($tenantId, $id, $status);
        $message = (string)($result['message'] ?? 'Operación finalizada.');
        $messageType = !empty($result['success']) ? 'success' : 'error';
        if (!empty($result['success'])) {
            header('Location: telephony_sources.php?saved=1');
            exit;
        }
    } else {
        http_response_code(400);
        exit('Operación no reconocida.');
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = 'Cambios guardados correctamente.';
    $messageType = 'success';
}

$sources = $controller->index($tenantId);
$csrfToken = $csrf->token();
$canCreate = false;
$canEdit = false;
try {
    require_once __DIR__ . '/services/Authorization.php';
    $authorization = new Authorization();
    $canCreate = $authorization->tienePermiso((int)$user['id'], 'audits.create');
    $canEdit = $authorization->tienePermiso((int)$user['id'], 'audits.edit');
} catch (Throwable $e) {
    error_log('[CallAudit][telephony_sources.php][permissions] ' . $e->getMessage());
}
$formValues = $editing ?: [
    'name' => '', 'platform' => 'ASTERISK', 'connection_mode' => 'API',
    'host' => '', 'port' => '', 'database_name' => ''
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fuentes telefónicas | CallAudit</title>
<style>
body{margin:0;padding:24px;background:#f4f6f9;color:#202733;font-family:Arial,Helvetica,sans-serif}
main{max-width:1200px;margin:auto}.panel{background:#fff;border:1px solid #dfe4ea;border-radius:8px;padding:22px;margin-bottom:22px}
label{display:block;font-weight:bold;margin:12px 0 5px}input,select{box-sizing:border-box;width:100%;padding:10px;border:1px solid #bcc5d0;border-radius:5px;font:inherit}
button,.button{display:inline-block;margin-top:14px;padding:9px 14px;border:0;border-radius:5px;background:#2457a7;color:#fff;text-decoration:none;cursor:pointer;font:inherit}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e2e6eb;text-align:left;vertical-align:top}.table-wrap{overflow-x:auto}
.notice{padding:12px;border-radius:5px;margin-bottom:16px}.success{background:#e8f7ed;color:#185b2c}.error{background:#fdecec;color:#842020}.info{background:#edf3ff;color:#1e3d72}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 18px}.muted{color:#5c6572}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.inline{display:inline}
@media(max-width:700px){body{padding:12px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body><main>
<header><h1>Fuentes telefónicas</h1>
<p>Registra varios servidores o métodos de entrada para las campañas de esta empresa.</p></header>

<?php if ($message !== ''): ?>
<div class="notice <?php echo telephonyEscape($messageType); ?>" role="status"><?php echo telephonyEscape($message); ?></div>
<?php endif; ?>

<section class="panel">
<h2><?php echo $editing ? 'Editar fuente' : 'Registrar fuente'; ?></h2>
<p class="muted">Las credenciales y la prueba real de conexión se incorporarán mediante el servicio seguro de conectores. No introduzcas contraseñas en estos campos.</p>
<?php if (($editing && $canEdit) || (!$editing && $canCreate)): ?>
<form method="post" action="telephony_sources.php<?php echo $editing ? '?edit=' . (int)$editing['id'] : ''; ?>">
<input type="hidden" name="csrf_token" value="<?php echo telephonyEscape($csrfToken); ?>">
<input type="hidden" name="action" value="save">
<?php if ($editing): ?><input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>"><?php endif; ?>
<div class="grid">
<div><label for="name">Nombre identificador</label><input id="name" name="name" maxlength="150" required value="<?php echo telephonyEscape($formValues['name']); ?>" placeholder="Ej. Vicidial Ventas Lima"></div>
<div><label for="platform">Plataforma</label><select id="platform" name="platform" required>
<?php foreach ($platforms as $key => $label): ?><option value="<?php echo telephonyEscape($key); ?>" <?php echo $formValues['platform'] === $key ? 'selected' : ''; ?>><?php echo telephonyEscape($label); ?></option><?php endforeach; ?>
</select></div>
<div><label for="connection_mode">Modo de conexión/entrada</label><select id="connection_mode" name="connection_mode" required>
<?php foreach ($modes as $key => $label): ?><option value="<?php echo telephonyEscape($key); ?>" <?php echo $formValues['connection_mode'] === $key ? 'selected' : ''; ?>><?php echo telephonyEscape($label); ?></option><?php endforeach; ?>
</select></div>
<div><label for="host">Servidor / host (opcional)</label><input id="host" name="host" maxlength="255" value="<?php echo telephonyEscape($formValues['host']); ?>" placeholder="Ej. 10.0.0.15 o pbx.empresa.local"></div>
<div><label for="port">Puerto (opcional)</label><input id="port" name="port" type="number" min="1" max="65535" value="<?php echo telephonyEscape($formValues['port']); ?>"></div>
<div><label for="database_name">Nombre de base de datos (opcional)</label><input id="database_name" name="database_name" maxlength="150" value="<?php echo telephonyEscape($formValues['database_name']); ?>"></div>
</div>
<button type="submit"><?php echo $editing ? 'Guardar cambios' : 'Registrar fuente'; ?></button>
<?php if ($editing): ?> <a class="button" href="telephony_sources.php">Cancelar</a><?php endif; ?>
</form>
<?php else: ?><p class="muted">Tu usuario no tiene permiso para <?php echo $editing ? 'editar' : 'crear'; ?> fuentes.</p><?php endif; ?>
</section>

<section class="panel">
<h2>Fuentes registradas</h2>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Nombre</th><th>Plataforma</th><th>Modo</th><th>Servidor</th><th>Estado</th><th>Última prueba</th><th>Acciones</th></tr></thead><tbody>
<?php if (!$sources): ?><tr><td colspan="8">Todavía no hay fuentes registradas.</td></tr><?php endif; ?>
<?php foreach ($sources as $source): ?>
<tr>
<td><?php echo (int)$source['id']; ?></td>
<td><?php echo telephonyEscape($source['name']); ?></td>
<td><?php echo telephonyEscape($platforms[$source['platform']] ?? $source['platform']); ?></td>
<td><?php echo telephonyEscape($modes[$source['connection_mode']] ?? $source['connection_mode']); ?></td>
<td><?php echo telephonyEscape($source['host'] ?: '—'); ?><?php echo $source['port'] ? ':' . (int)$source['port'] : ''; ?><?php echo $source['database_name'] ? '<br>' . telephonyEscape($source['database_name']) : ''; ?></td>
<td><?php echo telephonyEscape($source['status']); ?></td>
<td><?php echo telephonyEscape($source['last_test_at'] ?: 'Pendiente'); ?></td>
<td><div class="actions">
<?php if ($canEdit): ?><a href="telephony_sources.php?edit=<?php echo (int)$source['id']; ?>">Editar</a>
<form class="inline" method="post" action="telephony_sources.php">
<input type="hidden" name="csrf_token" value="<?php echo telephonyEscape($csrfToken); ?>">
<input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?php echo (int)$source['id']; ?>">
<input type="hidden" name="status" value="<?php echo $source['status'] === 'INACTIVE' ? 'ACTIVE' : 'INACTIVE'; ?>">
<button type="submit"><?php echo $source['status'] === 'INACTIVE' ? 'Activar' : 'Desactivar'; ?></button></form>
<?php else: ?>—<?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="panel">
<h2>Qué cubre este bloque</h2>
<ul>
<li>Registro de múltiples fuentes por empresa, con aislamiento por tenant.</li>
<li>Selección de plataforma y método de integración.</li>
<li>Edición y activación/desactivación con permisos y token CSRF.</li>
<li>No almacena contraseñas en texto plano ni muestra una prueba de conexión ficticia.</li>
</ul>
<p><strong>Pendiente para el siguiente bloque:</strong> almacén cifrado de credenciales, prueba de conexión por tipo de plataforma, importación/sincronización asíncrona y vinculación de cada fuente con las campañas correspondientes.</p>
</section>
</main></body></html>

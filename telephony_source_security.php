<?php
require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/services/TelephonySourceSecurity.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.edit');
$user = $auth->usuario();
if ($user === null) { header('Location: login.php'); exit; }
$tenantId = (new CampaignTenantContext())->resolver($user);
$sourceRaw = $_GET['source_id'] ?? '';
if (!is_string($sourceRaw) || !ctype_digit($sourceRaw) || (int)$sourceRaw <= 0) { http_response_code(400); exit('ID de fuente no válido.'); }
$sourceId = (int)$sourceRaw;
$security = new TelephonySourceSecurity();
$csrf = new CsrfService();
$pdo = (new Database())->connect();
if (!$pdo) { http_response_code(500); exit('No se pudo conectar con la base de datos.'); }
$stmt = $pdo->prepare('SELECT id, name, platform, connection_mode, host, port, database_name, status, last_test_at, last_error_code, encrypted_secret FROM telephony_sources WHERE tenant_id = :tenant AND id = :id LIMIT 1');
$stmt->execute([':tenant' => $tenantId, ':id' => $sourceId]);
$source = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$source) { http_response_code(404); exit('La fuente no existe en la empresa seleccionada.'); }
$message = '';
$messageType = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) { http_response_code(400); exit('Acción no válida.'); }
    if ($action === 'save_credentials') {
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        if (!is_string($username) || !is_string($password)) { http_response_code(400); exit('Credenciales no válidas.'); }
        $result = $security->guardarCredenciales($tenantId, $sourceId, $username, $password);
    } elseif ($action === 'test_connection') {
        $result = $security->probarConexion($tenantId, $sourceId);
    } else { http_response_code(400); exit('Acción desconocida.'); }
    $message = (string)($result['message'] ?? 'Operación terminada.');
    $messageType = !empty($result['success']) ? 'success' : 'error';
    // Vuelve a consultar estado para no renderizar valores enviados por el navegador.
    $stmt->execute([':tenant' => $tenantId, ':id' => $sourceId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
}
function escSourceSecurity($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$csrfToken = $csrf->token();
$hasCredentials = is_string($source['encrypted_secret'] ?? null) && $source['encrypted_secret'] !== '';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seguridad de fuente | CallAudit</title>
<style>body{margin:0;padding:24px;background:#f4f6f9;color:#202733;font-family:Arial,sans-serif}main{max-width:850px;margin:auto}.panel{background:#fff;border:1px solid #dfe4ea;border-radius:8px;padding:22px;margin-bottom:20px}label{display:block;font-weight:bold;margin:14px 0 6px}input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #bcc5d0;border-radius:5px;font:inherit}button,.button{display:inline-block;margin-top:14px;padding:10px 14px;border:0;border-radius:5px;background:#2457a7;color:#fff;text-decoration:none;cursor:pointer;font:inherit}.notice{padding:12px;border-radius:5px;margin:14px 0}.success{background:#e8f7ed;color:#185b2c}.error{background:#fdecec;color:#842020}.info{background:#edf3ff;color:#1e3d72}.muted{color:#5c6572}</style></head><body><main>
<h1>Seguridad y conexión de fuente</h1><p><a class="button" href="telephony_sources.php">Volver a fuentes telefónicas</a></p>
<?php if ($message !== ''): ?><div class="notice <?= escSourceSecurity($messageType) ?>" role="status"><?= escSourceSecurity($message) ?></div><?php endif; ?>
<section class="panel"><h2><?= escSourceSecurity($source['name']) ?></h2><p><strong>Plataforma:</strong> <?= escSourceSecurity($source['platform']) ?> · <strong>Modo:</strong> <?= escSourceSecurity($source['connection_mode']) ?></p><p><strong>Destino:</strong> <?= escSourceSecurity($source['host'] ?: 'sin host') ?><?= !empty($source['port']) ? ':' . (int)$source['port'] : '' ?> / <?= escSourceSecurity($source['database_name'] ?: 'sin base de datos') ?></p><p><strong>Estado:</strong> <?= escSourceSecurity($source['status']) ?> · <strong>Última prueba:</strong> <?= escSourceSecurity($source['last_test_at'] ?: 'Pendiente') ?><?= !empty($source['last_error_code']) ? ' · Error: ' . escSourceSecurity($source['last_error_code']) : '' ?></p>
<?php if ($source['connection_mode'] === 'READONLY_DATABASE'): ?><p class="muted">Las credenciales se cifran con AES-256-GCM. Por seguridad, la contraseña guardada nunca se vuelve a mostrar. Utiliza una cuenta remota dedicada con privilegios de solo lectura.</p>
<form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= escSourceSecurity($csrfToken) ?>"><input type="hidden" name="action" value="save_credentials"><label for="username">Usuario remoto</label><input id="username" name="username" maxlength="150" autocomplete="off" required><label for="password">Contraseña remota</label><input id="password" name="password" type="password" maxlength="4096" autocomplete="new-password" required><button type="submit">Guardar credenciales cifradas</button></form>
<form method="post"><input type="hidden" name="csrf_token" value="<?= escSourceSecurity($csrfToken) ?>"><input type="hidden" name="action" value="test_connection"><button type="submit">Probar conexión</button></form>
<?php else: ?><div class="notice info">La prueba automática aún no está habilitada para <?= escSourceSecurity($source['platform']) ?> / <?= escSourceSecurity($source['connection_mode']) ?>. Este bloque solo prueba MySQL/MariaDB en modo de solo lectura; no marcará una fuente PBX/API como probada sin su adaptador específico.</div><?php endif; ?>
</section><section class="panel"><h2>Seguridad</h2><ul><li>El secreto cifrado se almacena en <code>telephony_sources.encrypted_secret</code>.</li><li>La clave de cifrado debe permanecer fuera de la carpeta pública.</li><li>La prueba solo ejecuta <code>SELECT 1</code>; no importa ni modifica registros remotos.</li><li>Los errores técnicos detallados se escriben en el log del servidor, no se muestran al usuario.</li></ul><p><strong>Estado de credenciales:</strong> <?= $hasCredentials ? 'Hay un secreto cifrado guardado; no se revela su contenido.' : 'Todavía no se guardaron credenciales.' ?></p></section></main></body></html>

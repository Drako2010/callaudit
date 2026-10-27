<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/config/database.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('calls.view');
$user = $auth->usuario();
if (!$user) { http_response_code(401); exit('Usuario no autenticado.'); }
$tenantId = (new CampaignTenantContext())->resolver($user);
$callRaw = $_GET['call_id'] ?? '';
$callId = is_string($callRaw) && ctype_digit($callRaw) ? (int)$callRaw : 0;
if ($callId <= 0) { http_response_code(400); exit('Identificador de llamada inválido.'); }
$db = (new Database())->connect();
if (!$db) { http_response_code(500); exit('No se pudo conectar con la base de datos.'); }
$stmt = $db->prepare("SELECT id, tenant_id, audio_storage_key, audio_status, audio_mime_type, audio_size_bytes FROM audit_calls WHERE id=:id AND tenant_id=:tenant LIMIT 1");
$stmt->execute([':id'=>$callId, ':tenant'=>$tenantId]);
$call = $stmt->fetch();
if (!$call) { http_response_code(404); exit('Llamada no encontrada en esta empresa.'); }
if ($call['audio_status'] !== 'AVAILABLE' || empty($call['audio_storage_key'])) { http_response_code(404); exit('El audio no está disponible.'); }

$key = (string)$call['audio_storage_key'];
if (!preg_match('~^tenant_' . preg_quote((string)$tenantId, '~') . '/call_' . preg_quote((string)$callId, '~') . '/[a-f0-9]{32}\.(wav|mp3|ogg|flac|m4a|aac|gsm)$~D', $key)) {
    http_response_code(404); exit('Clave de audio no válida.');
}
$rootSetting = getenv('CALLAUDIT_AUDIO_STORAGE_PATH');
$root = is_string($rootSetting) && trim($rootSetting) !== ''
    ? rtrim($rootSetting, "\\/")
    : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'callaudit_storage' . DIRECTORY_SEPARATOR . 'audio';
$realRoot = realpath($root);
$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
$realPath = realpath($path);
$prefix = $realRoot ? rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if (!$realRoot || !$realPath || strpos($realPath, $prefix) !== 0 || !is_file($realPath) || !is_readable($realPath)) {
    http_response_code(404); exit('El archivo de audio no existe en el repositorio.');
}
$size = filesize($realPath);
if (!is_int($size) || $size <= 0) { http_response_code(404); exit('El archivo de audio está vacío o no se puede leer.'); }
$mimeAllowed = ['audio/wav','audio/mpeg','audio/ogg','audio/flac','audio/mp4','audio/aac','audio/gsm'];
$mime = in_array((string)$call['audio_mime_type'], $mimeAllowed, true) ? (string)$call['audio_mime_type'] : 'application/octet-stream';
$start = 0; $end = $size - 1; $status = 200;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if (is_string($range) && $range !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) || ($m[1] === '' && $m[2] === '')) {
        header('Content-Range: bytes */' . $size); http_response_code(416); exit;
    }
    if ($m[1] === '') {
        $suffix = (int)$m[2];
        if ($suffix <= 0) { header('Content-Range: bytes */' . $size); http_response_code(416); exit; }
        $start = max(0, $size - $suffix);
    } else {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = min($end, (int)$m[2]);
    }
    if ($start > $end || $start >= $size) { header('Content-Range: bytes */' . $size); http_response_code(416); exit; }
    $status = 206;
}
http_response_code($status);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="call-' . $callId . '.' . pathinfo($realPath, PATHINFO_EXTENSION) . '"');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Length: ' . ($end - $start + 1));
if ($status === 206) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
while (ob_get_level() > 0) ob_end_clean();
$handle = fopen($realPath, 'rb');
if (!$handle) { http_response_code(500); exit; }
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($handle);
exit;

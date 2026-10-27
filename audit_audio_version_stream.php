
<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/config/database.php';

/*
 * CallAudit: reproducción segura de versiones históricas.
 * Parámetro esperado: ?event_id=ID
 * No modifica la llamada ni el archivo almacenado.
 */

function audioVersionError(int $status, string $message): void
{
    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            break;
        }
    }

    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    exit($message);
}

function audioVersionRoot(): string
{
    $configured = getenv('CALLAUDIT_AUDIO_STORAGE_PATH');

    if (is_string($configured) && trim($configured) !== '') {
        return rtrim(trim($configured), "\\/");
    }

    return dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'callaudit_storage'
        . DIRECTORY_SEPARATOR . 'audio';
}

function audioVersionFfmpeg(): string
{
    $configured = getenv('CALLAUDIT_FFMPEG_PATH');

    if (is_string($configured) && trim($configured) !== '') {
        $configured = trim($configured);

        if (
            strlen($configured) >= 2 &&
            (
                ($configured[0] === '"' &&
                    substr($configured, -1) === '"') ||
                ($configured[0] === "'" &&
                    substr($configured, -1) === "'")
            )
        ) {
            $configured = substr($configured, 1, -1);
        }

        return $configured;
    }

    return 'D:\\tools\\ffmpeg\\bin\\ffmpeg.exe';
}

/* 1. Autenticación y permisos */

$auth = new AuthMiddleware();
$user = $auth->usuario();

if (!$user) {
    audioVersionError(401, 'Usuario no autenticado.');
}

$userId = (int) ($user['id'] ?? 0);

if ($userId <= 0) {
    audioVersionError(401, 'Identificador de usuario inválido.');
}

$authorization = new Authorization();

$allowed =
    $authorization->tienePermiso($userId, 'calls.view') ||
    $authorization->tienePermiso($userId, 'audits.view') ||
    $authorization->tienePermiso($userId, 'audits.edit');

if (!$allowed) {
    audioVersionError(
        403,
        'No tienes permiso para reproducir versiones históricas.'
    );
}

/* 2. Contexto de empresa */

$tenantId = (new CampaignTenantContext())->resolver($user);

if ($tenantId <= 0) {
    audioVersionError(403, 'Contexto de empresa inválido.');
}

/* 3. Validar ID del evento */

$eventRaw = $_GET['event_id'] ?? '';

$eventId = is_string($eventRaw) && ctype_digit($eventRaw)
    ? (int) $eventRaw
    : 0;

if ($eventId <= 0) {
    audioVersionError(400, 'ID de evento inválido.');
}

/* 4. Buscar la versión histórica, NO la ruta actual de audit_calls */

$db = (new Database())->connect();

if (!$db) {
    audioVersionError(500, 'No se pudo conectar con la base de datos.');
}

$stmt = $db->prepare("
    SELECT
        e.id,
        e.tenant_id,
        e.call_id,
        e.action,
        e.outcome,
        e.audio_storage_key,
        e.audio_sha256,
        e.audio_mime_type,
        e.audio_size_bytes
    FROM audit_audio_events e
    INNER JOIN audit_calls c
        ON c.id = e.call_id
       AND c.tenant_id = e.tenant_id
    WHERE e.id = :event_id
      AND e.tenant_id = :tenant_id
      AND e.outcome = 'SUCCESS'
      AND e.action IN ('UPLOAD', 'REPLACE')
    LIMIT 1
");

$stmt->execute([
    ':event_id' => $eventId,
    ':tenant_id' => $tenantId
]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    audioVersionError(
        404,
        'No existe una versión de audio válida para este evento y empresa.'
    );
}

$callId = (int) $event['call_id'];
$key = (string) ($event['audio_storage_key'] ?? '');
$expectedSha = strtolower((string) ($event['audio_sha256'] ?? ''));

/* 5. Validar clave relativa y extensión */

$pattern = '~^tenant_' .
    preg_quote((string) $tenantId, '~') .
    '/call_' .
    preg_quote((string) $callId, '~') .
    '/[a-f0-9]{32}\.(wav|mp3|ogg|flac|m4a|aac|gsm)$~D';

if (!preg_match($pattern, $key)) {
    audioVersionError(404, 'La ruta histórica no es válida.');
}

/* 6. Resolver el archivo físico fuera del directorio público */

$root = audioVersionRoot();
$realRoot = realpath($root);

if ($realRoot === false || !is_dir($realRoot)) {
    error_log('[CallAudit][audio_version] No existe el almacenamiento: ' . $root);
    audioVersionError(500, 'No se encontró el repositorio de audios.');
}

$path = $realRoot . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $key);

$realPath = realpath($path);
$prefix = rtrim($realRoot, "\\/") . DIRECTORY_SEPARATOR;

if (
    $realPath === false ||
    strpos(strtolower($realPath), strtolower($prefix)) !== 0 ||
    !is_file($realPath) ||
    !is_readable($realPath)
) {
    error_log(
        '[CallAudit][audio_version] Archivo histórico inexistente. Evento: ' .
        $eventId . ', llamada: ' . $callId
    );

    audioVersionError(
        404,
        'El archivo de esta versión no existe o Apache no puede leerlo.'
    );
}

$size = filesize($realPath);

if (!is_int($size) || $size <= 0) {
    audioVersionError(404, 'El archivo histórico está vacío o no se puede leer.');
}

/* 7. Verificar integridad contra el registro histórico */

if (
    !preg_match('/^[a-f0-9]{64}$/', $expectedSha)
) {
    audioVersionError(
        409,
        'El evento no contiene un SHA-256 válido. No se reproducirá esta versión.'
    );
}

$actualSha = hash_file('sha256', $realPath);

if (
    !is_string($actualSha) ||
    !hash_equals($expectedSha, strtolower($actualSha))
) {
    error_log(
        '[CallAudit][audio_version] SHA-256 no coincide. Evento: ' .
        $eventId . ', llamada: ' . $callId
    );

    audioVersionError(
        409,
        'El archivo no coincide con el SHA-256 de esta versión histórica.'
    );
}

$extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

/* 8. Convertir GSM a WAV sin alterar el original */

if ($extension === 'gsm') {
    if (!function_exists('proc_open')) {
        audioVersionError(503, 'La reproducción GSM requiere proc_open en PHP.');
    }

    $ffmpeg = audioVersionFfmpeg();

    if (!is_file($ffmpeg) || !is_readable($ffmpeg)) {
        audioVersionError(
            503,
            'No se encontró FFmpeg. Verifica D:\\tools\\ffmpeg\\bin\\ffmpeg.exe.'
        );
    }

    $command =
        escapeshellarg($ffmpeg) .
        ' -hide_banner -loglevel error -nostdin' .
        ' -f gsm -ar 8000 -ac 1' .
        ' -i ' . escapeshellarg($realPath) .
        ' -vn -f wav -c:a pcm_s16le -ar 8000 -ac 1 pipe:1';

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $pipes = [];
    $process = @proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        audioVersionError(503, 'PHP no pudo iniciar FFmpeg.');
    }

    if (
        !isset($pipes[0], $pipes[1], $pipes[2]) ||
        !is_resource($pipes[1]) ||
        !is_resource($pipes[2])
    ) {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        @proc_terminate($process);
        @proc_close($process);

        audioVersionError(500, 'No se pudieron preparar los canales de FFmpeg.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], true);
    stream_set_blocking($pipes[2], false);

    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            break;
        }
    }

    header('Content-Type: audio/wav');
    header(
        'Content-Disposition: inline; filename="call-' .
        $callId . '-evento-' . $eventId . '.wav"'
    );
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Accept-Ranges: none');

    $bytesSent = 0;

    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 8192);

        if ($chunk === false) {
            break;
        }

        if ($chunk === '') {
            if (feof($pipes[1])) {
                break;
            }
            continue;
        }

        echo $chunk;
        $bytesSent += strlen($chunk);
        flush();

        if (connection_aborted()) {
            break;
        }
    }

    fclose($pipes[1]);

    $diagnostic = '';

    while (!feof($pipes[2])) {
        $part = fread($pipes[2], 8192);

        if ($part === false || $part === '') {
            break;
        }

        $diagnostic .= $part;

        if (strlen($diagnostic) > 8192) {
            $diagnostic = substr($diagnostic, -8192);
        }
    }

    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || $bytesSent === 0) {
        error_log(
            '[CallAudit][audio_version] Error FFmpeg. Evento: ' .
            $eventId . ', código: ' . $exitCode .
            ', bytes: ' . $bytesSent .
            ', detalle: ' . substr($diagnostic, 0, 2000)
        );
    }

    exit;
}

/* 9. Reproducir formatos no GSM con soporte Range */

$mimeByExtension = [
    'wav' => 'audio/wav',
    'mp3' => 'audio/mpeg',
    'ogg' => 'audio/ogg',
    'flac' => 'audio/flac',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac'
];

$mime = (string) ($event['audio_mime_type'] ?? '');

$allowedMime = [
    'audio/wav',
    'audio/x-wav',
    'audio/mpeg',
    'audio/ogg',
    'audio/flac',
    'audio/mp4',
    'audio/aac'
];

if (!in_array($mime, $allowedMime, true)) {
    $mime = $mimeByExtension[$extension] ?? 'application/octet-stream';
}

$start = 0;
$end = $size - 1;
$status = 200;
$range = $_SERVER['HTTP_RANGE'] ?? '';

if (is_string($range) && $range !== '') {
    if (
        !preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches) ||
        ($matches[1] === '' && $matches[2] === '')
    ) {
        header('Content-Range: bytes */' . $size);
        audioVersionError(416, 'Rango de audio no válido.');
    }

    if ($matches[1] === '') {
        $suffix = (int) $matches[2];

        if ($suffix <= 0) {
            header('Content-Range: bytes */' . $size);
            audioVersionError(416, 'Rango de audio no válido.');
        }

        $start = max(0, $size - $suffix);
    } else {
        $start = (int) $matches[1];

        if ($matches[2] !== '') {
            $end = min($end, (int) $matches[2]);
        }
    }

    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        audioVersionError(416, 'El rango solicitado está fuera del archivo.');
    }

    $status = 206;
}

while (ob_get_level() > 0) {
    if (!@ob_end_clean()) {
        break;
    }
}

http_response_code($status);
header('Content-Type: ' . $mime);
header(
    'Content-Disposition: inline; filename="call-' .
    $callId . '-evento-' . $eventId . '.' . $extension . '"'
);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Length: ' . ($end - $start + 1));

if ($status === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

$handle = @fopen($realPath, 'rb');

if (!$handle) {
    audioVersionError(500, 'No se pudo abrir el archivo histórico.');
}

if (fseek($handle, $start) !== 0) {
    fclose($handle);
    audioVersionError(500, 'No se pudo posicionar la lectura del audio.');
}

$remaining = $end - $start + 1;

while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));

    if ($chunk === false || $chunk === '') {
        break;
    }

    echo $chunk;
    $remaining -= strlen($chunk);
    flush();

    if (connection_aborted()) {
        break;
    }
}

fclose($handle);
exit;

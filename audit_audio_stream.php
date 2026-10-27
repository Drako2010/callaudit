<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/config/database.php';

$auth = new AuthMiddleware();
$user = $auth->usuario();

if (!$user) {
    http_response_code(401);
    exit('Usuario no autenticado.');
}

/*
 * La reproducción puede requerirse desde:
 * - la consulta de llamadas: calls.view
 * - la evaluación humana: audits.edit
 *
 * Authorization conserva la prioridad de DENY individual y los permisos
 * asignados por rol o individualmente. No se concede ningún permiso nuevo.
 */
$authorization = new Authorization();
$userId = (int) ($user['id'] ?? 0);

$canViewCalls = $userId > 0
    && $authorization->tienePermiso($userId, 'calls.view');

$canEditAudits = $userId > 0
    && $authorization->tienePermiso($userId, 'audits.edit');

if (!$canViewCalls && !$canEditAudits) {
    http_response_code(403);
    exit('No tienes permiso para reproducir este audio.');
}

$tenantId = (new CampaignTenantContext())->resolver($user);

if ($tenantId <= 0) {
    http_response_code(403);
    exit('El contexto de empresa no es válido.');
}

$callRaw = $_GET['call_id'] ?? '';
$callId = is_string($callRaw) && ctype_digit($callRaw)
    ? (int) $callRaw
    : 0;

if ($callId <= 0) {
    http_response_code(400);
    exit('Identificador de llamada inválido.');
}

$db = (new Database())->connect();

if (!$db) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}

$stmt = $db->prepare("
    SELECT
        id,
        tenant_id,
        audio_storage_key,
        audio_status,
        audio_mime_type,
        audio_size_bytes
    FROM audit_calls
    WHERE id = :id
      AND tenant_id = :tenant
    LIMIT 1
");

$stmt->execute([
    ':id' => $callId,
    ':tenant' => $tenantId
]);

$call = $stmt->fetch();

if (!$call) {
    http_response_code(404);
    exit('Llamada no encontrada en esta empresa.');
}

if (
    $call['audio_status'] !== 'AVAILABLE' ||
    empty($call['audio_storage_key'])
) {
    http_response_code(404);
    exit('El audio no está disponible.');
}

/*
 * Aceptamos únicamente claves relativas con el formato generado
 * por el cargador de audios. Nunca se permite una ruta arbitraria.
 */
$key = (string) $call['audio_storage_key'];

$pattern = '~^tenant_' . preg_quote((string) $tenantId, '~') .
    '/call_' . preg_quote((string) $callId, '~') .
    '/[a-f0-9]{32}\.(wav|mp3|ogg|flac|m4a|aac|gsm)$~D';

if (!preg_match($pattern, $key)) {
    http_response_code(404);
    exit('Clave de audio no válida.');
}

/*
 * CALLAUDIT_AUDIO_STORAGE_PATH permite configurar el almacenamiento.
 * Valor predeterminado para el entorno indicado:
 * D:\Xampp\callaudit_storage\audio
 */
$rootSetting = getenv('CALLAUDIT_AUDIO_STORAGE_PATH');

$root = is_string($rootSetting) && trim($rootSetting) !== ''
    ? rtrim($rootSetting, "\\/")
    : dirname(__DIR__, 2) .
        DIRECTORY_SEPARATOR . 'callaudit_storage' .
        DIRECTORY_SEPARATOR . 'audio';

$realRoot = realpath($root);

$path = $root . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $key);

$realPath = realpath($path);

$prefix = $realRoot
    ? rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    : '';

if (
    !$realRoot ||
    !$realPath ||
    strpos($realPath, $prefix) !== 0 ||
    !is_file($realPath) ||
    !is_readable($realPath)
) {
    http_response_code(404);
    exit(
        'El archivo de audio no existe o Apache no tiene permiso para leerlo.'
    );
}

$size = filesize($realPath);

if (!is_int($size) || $size <= 0) {
    http_response_code(404);
    exit('El archivo de audio está vacío o no se puede leer.');
}

$extension = strtolower(
    (string) pathinfo($realPath, PATHINFO_EXTENSION)
);

/*
 * GSM crudo de Asterisk:
 * se convierte a WAV PCM en tiempo de reproducción.
 * El original nunca se modifica.
 *
 * Configure CALLAUDIT_FFMPEG_PATH con la ruta completa a ffmpeg.exe,
 * o asegúrese de que ffmpeg esté disponible en el PATH de Apache.
 */
if ($extension === 'gsm') {
    if (!function_exists('proc_open')) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit(
            'La reproducción GSM requiere proc_open habilitado en PHP ' .
            'y FFmpeg instalado en el servidor.'
        );
    }

    $ffmpegSetting = getenv('CALLAUDIT_FFMPEG_PATH');

    $ffmpeg = is_string($ffmpegSetting) &&
        trim($ffmpegSetting) !== ''
        ? trim($ffmpegSetting)
        : 'ffmpeg';

    /*
     * Se pasa el comando como array para evitar que la ruta del archivo
     * se interprete como comandos de shell.
     */
    $command = [
        $ffmpeg,
        '-hide_banner',
        '-loglevel', 'error',
        '-nostdin',
        '-f', 'gsm',
        '-ar', '8000',
        '-ac', '1',
        '-i', $realPath,
        '-vn',
        '-f', 'wav',
        '-c:a', 'pcm_s16le',
        '-ar', '8000',
        '-ac', '1',
        'pipe:1'
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = @proc_open(
        $command,
        $descriptors,
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($process)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit(
            'No se pudo iniciar FFmpeg. Configure CALLAUDIT_FFMPEG_PATH ' .
            'o agregue ffmpeg al PATH de Apache.'
        );
    }

    fclose($pipes[0]);

    /*
     * Evitamos bloquear la salida esperando el texto de diagnóstico.
     * Los errores se envían al log de Apache/PHP.
     */
    stream_set_blocking($pipes[1], true);
    stream_set_blocking($pipes[2], false);

    header('Content-Type: audio/wav');
    header(
        'Content-Disposition: inline; filename="call-' .
        $callId . '.wav"'
    );
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Accept-Ranges: none');

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
        flush();

        if (connection_aborted()) {
            break;
        }
    }

    fclose($pipes[1]);

    $diagnostic = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        error_log(
            '[CallAudit][audio_stream] FFmpeg falló para la llamada ' .
            $callId . '. Código: ' . $exitCode .
            '. Detalle: ' . substr((string) $diagnostic, 0, 2000)
        );
    }

    exit;
}

/*
 * Formatos que se transmiten directamente.
 */
$mimeAllowed = [
    'audio/wav',
    'audio/x-wav',
    'audio/mpeg',
    'audio/ogg',
    'audio/flac',
    'audio/mp4',
    'audio/aac'
];

$mime = (string) ($call['audio_mime_type'] ?? '');

if (!in_array($mime, $mimeAllowed, true)) {
    $mimeByExtension = [
        'wav' => 'audio/wav',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac'
    ];

    $mime = $mimeByExtension[$extension] ?? 'application/octet-stream';
}

/*
 * Compatibilidad con peticiones Range para WAV/MP3/OGG/FLAC/M4A/AAC.
 */
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
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '') {
        $suffix = (int) $matches[2];

        if ($suffix <= 0) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
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
        http_response_code(416);
        exit;
    }

    $status = 206;
}

http_response_code($status);

header('Content-Type: ' . $mime);
header(
    'Content-Disposition: inline; filename="call-' .
    $callId . '.' . $extension . '"'
);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Length: ' . ($end - $start + 1));

if ($status === 206) {
    header(
        'Content-Range: bytes ' .
        $start . '-' . $end . '/' . $size
    );
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$handle = fopen($realPath, 'rb');

if (!$handle) {
    http_response_code(500);
    exit('No se pudo abrir el archivo de audio.');
}

if (fseek($handle, $start) !== 0) {
    fclose($handle);
    http_response_code(500);
    exit('No se pudo posicionar la lectura del audio.');
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

<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| CallAudit - Reproducción segura de audios
|--------------------------------------------------------------------------
| - Respeta los permisos existentes.
| - Aísla las llamadas por empresa.
| - Almacena los audios fuera del directorio público.
| - Convierte GSM de Asterisk a WAV PCM mediante FFmpeg.
| - Transmite los demás formatos con soporte Range.
| - No modifica el audio original.
|--------------------------------------------------------------------------
*/

/**
 * Limpia los búferes PHP antes de transmitir audio binario.
 */
function callAuditLimpiarBuffers(): void
{
    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            break;
        }
    }
}

/**
 * Resuelve la carpeta de almacenamiento.
 */
function callAuditAudioRoot(): string
{
    $configured = getenv('CALLAUDIT_AUDIO_STORAGE_PATH');

    if (is_string($configured) && trim($configured) !== '') {
        return rtrim(trim($configured), "\\/");
    }

    return dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'callaudit_storage'
        . DIRECTORY_SEPARATOR . 'audio';
}

/**
 * Obtiene la ruta del ejecutable FFmpeg.
 *
 * En este entorno Windows la ruta predeterminada es:
 * D:\tools\ffmpeg\bin\ffmpeg.exe
 */
function callAuditFfmpegPath(): string
{
    $configured = getenv('CALLAUDIT_FFMPEG_PATH');

    if (is_string($configured) && trim($configured) !== '') {
        $configured = trim($configured);

        // Permite valores configurados con comillas exteriores.
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

/**
 * Termina una petición con un mensaje de texto.
 */
function callAuditAudioError(
    int $status,
    string $message
): void {
    callAuditLimpiarBuffers();

    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    exit($message);
}

/*
|--------------------------------------------------------------------------
| 1. Autenticación y autorización
|--------------------------------------------------------------------------
*/

$auth = new AuthMiddleware();
$user = $auth->usuario();

if (!$user) {
    callAuditAudioError(401, 'Usuario no autenticado.');
}

$userId = (int) ($user['id'] ?? 0);

if ($userId <= 0) {
    callAuditAudioError(401, 'Identificador de usuario inválido.');
}

$authorization = new Authorization();

$canViewCalls = $authorization->tienePermiso(
    $userId,
    'calls.view'
);

$canEditAudits = $authorization->tienePermiso(
    $userId,
    'audits.edit'
);

if (!$canViewCalls && !$canEditAudits) {
    callAuditAudioError(
        403,
        'No tienes permiso para reproducir este audio.'
    );
}

/*
|--------------------------------------------------------------------------
| 2. Resolver el contexto autorizado de empresa
|--------------------------------------------------------------------------
*/

$tenantId = (new CampaignTenantContext())->resolver($user);

if ($tenantId <= 0) {
    callAuditAudioError(
        403,
        'El contexto de empresa no es válido.'
    );
}

/*
|--------------------------------------------------------------------------
| 3. Validar identificador de llamada
|--------------------------------------------------------------------------
*/

$callRaw = $_GET['call_id'] ?? '';

$callId = is_string($callRaw) && ctype_digit($callRaw)
    ? (int) $callRaw
    : 0;

if ($callId <= 0) {
    callAuditAudioError(
        400,
        'Identificador de llamada inválido.'
    );
}

/*
|--------------------------------------------------------------------------
| 4. Consultar la llamada dentro de la empresa autorizada
|--------------------------------------------------------------------------
*/

$db = (new Database())->connect();

if (!$db) {
    callAuditAudioError(
        500,
        'No se pudo conectar con la base de datos.'
    );
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
    callAuditAudioError(
        404,
        'Llamada no encontrada en esta empresa.'
    );
}

if (
    $call['audio_status'] !== 'AVAILABLE' ||
    empty($call['audio_storage_key'])
) {
    callAuditAudioError(
        404,
        'El audio no está disponible.'
    );
}

/*
|--------------------------------------------------------------------------
| 5. Validar la clave relativa del archivo
|--------------------------------------------------------------------------
|
| Solo se admiten claves generadas por el módulo de carga.
| No se aceptan rutas absolutas, traversal ni otras empresas.
|--------------------------------------------------------------------------
*/

$key = (string) $call['audio_storage_key'];

$pattern = '~^tenant_' .
    preg_quote((string) $tenantId, '~') .
    '/call_' .
    preg_quote((string) $callId, '~') .
    '/[a-f0-9]{32}\.(wav|mp3|ogg|flac|m4a|aac|gsm)$~D';

if (!preg_match($pattern, $key)) {
    callAuditAudioError(
        404,
        'Clave de audio no válida.'
    );
}

/*
|--------------------------------------------------------------------------
| 6. Resolver y validar el almacenamiento físico
|--------------------------------------------------------------------------
*/

$root = callAuditAudioRoot();
$realRoot = realpath($root);

if ($realRoot === false || !is_dir($realRoot)) {
    error_log(
        '[CallAudit][audio_stream] No existe el almacenamiento: ' .
        $root
    );

    callAuditAudioError(
        500,
        'No se encontró el repositorio de audios configurado.'
    );
}

$path = $realRoot . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $key);

$realPath = realpath($path);

$prefix = rtrim($realRoot, "\\/") . DIRECTORY_SEPARATOR;

if (
    $realPath === false ||
    strpos(
        strtolower($realPath),
        strtolower($prefix)
    ) !== 0 ||
    !is_file($realPath) ||
    !is_readable($realPath)
) {
    error_log(
        '[CallAudit][audio_stream] Archivo inexistente o no legible. ' .
        'Call ID: ' . $callId .
        ', tenant ID: ' . $tenantId
    );

    callAuditAudioError(
        404,
        'El archivo de audio no existe o Apache no tiene permiso para leerlo.'
    );
}

$size = filesize($realPath);

if (!is_int($size) || $size <= 0) {
    callAuditAudioError(
        404,
        'El archivo de audio está vacío o no se puede leer.'
    );
}

$extension = strtolower(
    (string) pathinfo($realPath, PATHINFO_EXTENSION)
);

/*
|--------------------------------------------------------------------------
| 7. Convertir GSM a WAV usando FFmpeg
|--------------------------------------------------------------------------
|
| Se ejecuta un comando de texto con los argumentos correctamente
| escapados. Esta forma es compatible con la prueba realizada desde
| Apache en Windows.
|
| El WAV se transmite por stdout; el original GSM no se modifica.
|--------------------------------------------------------------------------
*/

if ($extension === 'gsm') {
    if (!function_exists('proc_open')) {
        callAuditAudioError(
            503,
            'La reproducción GSM requiere proc_open habilitado en PHP.'
        );
    }

    $ffmpeg = callAuditFfmpegPath();

    if (!is_file($ffmpeg) || !is_readable($ffmpeg)) {
        error_log(
            '[CallAudit][audio_stream] FFmpeg no encontrado: ' .
            $ffmpeg
        );

        callAuditAudioError(
            503,
            'No se encontró FFmpeg. Verifique ' .
            'D:\\tools\\ffmpeg\\bin\\ffmpeg.exe o configure ' .
            'CALLAUDIT_FFMPEG_PATH.'
        );
    }

    /*
     * escapeshellarg protege las rutas que contienen espacios.
     * Los parámetros son constantes; las rutas se escapan por separado.
     */
    $command =
        escapeshellarg($ffmpeg) .
        ' -hide_banner' .
        ' -loglevel error' .
        ' -nostdin' .
        ' -f gsm' .
        ' -ar 8000' .
        ' -ac 1' .
        ' -i ' . escapeshellarg($realPath) .
        ' -vn' .
        ' -f wav' .
        ' -c:a pcm_s16le' .
        ' -ar 8000' .
        ' -ac 1' .
        ' pipe:1';

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $pipes = [];

    $process = @proc_open(
        $command,
        $descriptors,
        $pipes,
        null,
        null
    );

    if (!is_resource($process)) {
        error_log(
            '[CallAudit][audio_stream] proc_open no pudo iniciar ' .
            'FFmpeg. Call ID: ' . $callId
        );

        callAuditAudioError(
            503,
            'PHP no pudo iniciar FFmpeg. Verifique los permisos ' .
            'del ejecutable y la configuración de PHP.'
        );
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

        callAuditAudioError(
            500,
            'No se pudieron preparar los canales de FFmpeg.'
        );
    }

    fclose($pipes[0]);

    /*
     * El diagnóstico se lee sin bloquear la transmisión del WAV.
     */
    stream_set_blocking($pipes[1], true);
    stream_set_blocking($pipes[2], false);

    callAuditLimpiarBuffers();

    header('Content-Type: audio/wav');
    header(
        'Content-Disposition: inline; filename="call-' .
        $callId . '.wav"'
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

    /*
     * Capturar los mensajes que FFmpeg haya enviado a stderr.
     */
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
            '[CallAudit][audio_stream] Falló la conversión GSM. ' .
            'Call ID: ' . $callId .
            ', código: ' . $exitCode .
            ', bytes enviados: ' . $bytesSent .
            ', detalle: ' . substr($diagnostic, 0, 2000)
        );
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| 8. Transmitir directamente formatos no GSM
|--------------------------------------------------------------------------
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

    $mime = $mimeByExtension[$extension]
        ?? 'application/octet-stream';
}

/*
|--------------------------------------------------------------------------
| 9. Soporte HTTP Range para el reproductor
|--------------------------------------------------------------------------
*/

$start = 0;
$end = $size - 1;
$status = 200;

$range = $_SERVER['HTTP_RANGE'] ?? '';

if (is_string($range) && $range !== '') {
    if (
        !preg_match(
            '/^bytes=(\d*)-(\d*)$/',
            trim($range),
            $matches
        ) ||
        ($matches[1] === '' && $matches[2] === '')
    ) {
        header('Content-Range: bytes */' . $size);
        callAuditAudioError(
            416,
            'Rango de audio no válido.'
        );
    }

    if ($matches[1] === '') {
        $suffix = (int) $matches[2];

        if ($suffix <= 0) {
            header('Content-Range: bytes */' . $size);
            callAuditAudioError(
                416,
                'Rango de audio no válido.'
            );
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
        callAuditAudioError(
            416,
            'El rango solicitado está fuera del archivo.'
        );
    }

    $status = 206;
}

/*
|--------------------------------------------------------------------------
| 10. Enviar cabeceras y transmitir el archivo
|--------------------------------------------------------------------------
*/

callAuditLimpiarBuffers();

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

$handle = @fopen($realPath, 'rb');

if (!$handle) {
    error_log(
        '[CallAudit][audio_stream] No se pudo abrir el archivo. ' .
        'Call ID: ' . $callId
    );

    callAuditAudioError(
        500,
        'No se pudo abrir el archivo de audio.'
    );
}

if (fseek($handle, $start) !== 0) {
    fclose($handle);

    callAuditAudioError(
        500,
        'No se pudo posicionar la lectura del audio.'
    );
}

$remaining = $end - $start + 1;

while ($remaining > 0 && !feof($handle)) {
    $chunk = fread(
        $handle,
        min(8192, $remaining)
    );

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

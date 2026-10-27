<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/config/database.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.create');
$user = $auth->usuario();
if (!$user) { header('Location: login.php'); exit; }
$tenantId = (new CampaignTenantContext())->resolver($user);
$csrf = new CsrfService();
$db = (new Database())->connect();
if (!$db) { http_response_code(500); exit('No se pudo conectar con la base de datos.'); }

const CALLAUDIT_AUDIO_MAX_BYTES = 104857600; // 100 MiB
const CALLAUDIT_AUDIO_EXTENSIONS = ['wav', 'mp3', 'ogg', 'flac', 'm4a', 'aac', 'gsm'];

function audioUploadH($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function audioUploadRoot(): string {
    $configured = getenv('CALLAUDIT_AUDIO_STORAGE_PATH');
    if (is_string($configured) && trim($configured) !== '') return rtrim($configured, "\\/");
    // Fuera de htdocs/document root: D:\\Xampp\\callaudit_storage\\audio por defecto.
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'callaudit_storage' . DIRECTORY_SEPARATOR . 'audio';
}
function audioUploadLog(PDO $db, int $tenantId, int $callId, int $actorId, string $action, string $outcome, ?string $key, ?string $sha, ?string $mime, ?int $size, string $message): void {
    try {
        $stmt = $db->prepare("INSERT INTO audit_audio_events (tenant_id, call_id, actor_user_id, action, outcome, audio_storage_key, audio_sha256, audio_mime_type, audio_size_bytes, message) VALUES (:tenant,:call,:actor,:action,:outcome,:key,:sha,:mime,:size,:message)");
        $stmt->execute([':tenant'=>$tenantId, ':call'=>$callId, ':actor'=>$actorId, ':action'=>$action, ':outcome'=>$outcome, ':key'=>$key, ':sha'=>$sha, ':mime'=>$mime, ':size'=>$size, ':message'=>mb_substr($message, 0, 1000, 'UTF-8')]);
    } catch (Throwable $e) {
        error_log('[CallAudit][audioUploadLog] ' . $e->getMessage());
    }
}
function audioUploadDetectFormat(string $path, string $extension): ?array {
    $handle = @fopen($path, 'rb');
    if (!$handle) return null;
    $head = fread($handle, 16);
    fclose($handle);
    if (!is_string($head)) return null;
    $valid = false; $mime = '';
    switch ($extension) {
        case 'wav': $valid = strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WAVE'; $mime = 'audio/wav'; break;
        case 'mp3': $valid = substr($head, 0, 3) === 'ID3' || (strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0); $mime = 'audio/mpeg'; break;
        case 'ogg': $valid = substr($head, 0, 4) === 'OggS'; $mime = 'audio/ogg'; break;
        case 'flac': $valid = substr($head, 0, 4) === 'fLaC'; $mime = 'audio/flac'; break;
        case 'm4a': $valid = strlen($head) >= 8 && substr($head, 4, 4) === 'ftyp'; $mime = 'audio/mp4'; break;
        case 'aac': $valid = strlen($head) >= 2 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xF6) === 0xF0; $mime = 'audio/aac'; break;
        // Asterisk suele guardar GSM 06.10 como tramas crudas de 33 bytes, sin cabecera mágica.
        case 'gsm': $size = @filesize($path); $valid = is_int($size) && $size >= 33 && ($size % 33) === 0; $mime = 'audio/gsm'; break;
    }
    return $valid ? ['mime'=>$mime, 'extension'=>$extension] : null;
}

$message = '';
$messageType = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();
    $callRaw = $_POST['call_id'] ?? '';
    $callId = is_string($callRaw) && ctype_digit($callRaw) ? (int)$callRaw : 0;
    $call = null;
    if ($callId > 0) {
        $stmt = $db->prepare("SELECT id, tenant_id, audio_status, audio_storage_key FROM audit_calls WHERE id=:id AND tenant_id=:tenant LIMIT 1");
        $stmt->execute([':id'=>$callId, ':tenant'=>$tenantId]);
        $call = $stmt->fetch() ?: null;
    }
    if (!$call) {
        $message = 'Selecciona una llamada válida de esta empresa.';
        $messageType = 'error';
    } elseif ($call['audio_status'] === 'DELETED') {
        $message = 'No se puede reemplazar un audio marcado como eliminado.';
        $messageType = 'error';
        audioUploadLog($db, $tenantId, $callId, (int)$user['id'], 'UPLOAD', 'REJECTED', null, null, null, null, $message);
    } else {
        $file = $_FILES['audio_file'] ?? null;
        $error = '';
        if (!is_array($file)) $error = 'Selecciona un archivo de audio.';
        elseif (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadError = (int)$file['error'];
            $error = $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
                ? 'El archivo supera el límite de carga permitido por PHP.'
                : ($uploadError === UPLOAD_ERR_NO_FILE ? 'Selecciona un archivo de audio.' : 'PHP no pudo recibir el archivo. Revisa los límites de carga.');
        } elseif (!isset($file['tmp_name'], $file['name'], $file['size']) || !is_string($file['tmp_name']) || !is_string($file['name'])) $error = 'Datos de archivo inválidos.';
        elseif (!is_uploaded_file($file['tmp_name'])) $error = 'El archivo recibido no es una carga HTTP válida.';
        elseif ((int)$file['size'] <= 0) $error = 'El archivo está vacío.';
        elseif ((int)$file['size'] > CALLAUDIT_AUDIO_MAX_BYTES) $error = 'El límite de CallAudit es 100 MiB por archivo.';

        $extension = '';
        $format = null;
        if ($error === '') {
            $extension = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
            if (!in_array($extension, CALLAUDIT_AUDIO_EXTENSIONS, true)) $error = 'Formato no admitido. Usa WAV, MP3, OGG, FLAC, M4A, AAC o GSM de Asterisk.';
            else $format = audioUploadDetectFormat($file['tmp_name'], $extension);
            if ($error === '' && $format === null) $error = 'El contenido real no coincide con el formato indicado o el archivo está dañado.';
        }

        if ($error !== '') {
            $message = $error; $messageType = 'error';
            audioUploadLog($db, $tenantId, $callId, (int)$user['id'], 'UPLOAD', 'REJECTED', null, null, null, isset($file['size']) ? (int)$file['size'] : null, $error);
        } else {
            $root = audioUploadRoot();
            $directory = $root . DIRECTORY_SEPARATOR . 'tenant_' . $tenantId . DIRECTORY_SEPARATOR . 'call_' . $callId;
            if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
                $message = 'No se pudo crear el directorio de almacenamiento. Verifica permisos y CALLAUDIT_AUDIO_STORAGE_PATH.';
                $messageType = 'error';
                audioUploadLog($db, $tenantId, $callId, (int)$user['id'], 'UPLOAD', 'ERROR', null, null, null, (int)$file['size'], $message);
            } else {
                $randomName = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $directory . DIRECTORY_SEPARATOR . $randomName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $message = 'No se pudo guardar el audio en el repositorio.'; $messageType = 'error';
                    audioUploadLog($db, $tenantId, $callId, (int)$user['id'], 'UPLOAD', 'ERROR', null, null, $format['mime'], (int)$file['size'], $message);
                } else {
                    $realFile = realpath($destination);
                    $realRoot = realpath($root);
                    $prefix = $realRoot ? rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
                    if (!$realFile || !$realRoot || strpos($realFile, $prefix) !== 0 || !is_file($realFile)) {
                        @unlink($destination);
                        $message = 'La ruta final de almacenamiento no pasó la validación de seguridad.'; $messageType = 'error';
                        audioUploadLog($db, $tenantId, $callId, (int)$user['id'], 'UPLOAD', 'ERROR', null, null, $format['mime'], (int)$file['size'], $message);
                    } else {
                        $sha = hash_file('sha256', $realFile);
                        $size = filesize($realFile);
                        $key = 'tenant_' . $tenantId . '/call_' . $callId . '/' . $randomName;
                        $action = !empty($call['audio_storage_key']) || $call['audio_status'] === 'AVAILABLE' ? 'REPLACE' : 'UPLOAD';
                        try {
                            $db->beginTransaction();
                            $update = $db->prepare("UPDATE audit_calls SET audio_storage_key=:key, audio_sha256=:sha, audio_mime_type=:mime, audio_size_bytes=:size, audio_status='AVAILABLE' WHERE id=:id AND tenant_id=:tenant AND audio_status<>'DELETED'");
                            $update->execute([':key'=>$key, ':sha'=>$sha, ':mime'=>$format['mime'], ':size'=>$size, ':id'=>$callId, ':tenant'=>$tenantId]);
                            if ($update->rowCount() !== 1) throw new RuntimeException('La llamada cambió de estado durante la carga.');
                            audioUploadLog($db, $tenantId, $callId, (int)$user['id'], $action, 'SUCCESS', $key, $sha, $format['mime'], (int)$size, 'Audio almacenado y validado.');
                            $db->commit();
                            $message = 'Audio recibido y validado. La llamada ahora figura como AVAILABLE.'; $messageType = 'success';
                        } catch (Throwable $e) {
                            if ($db->inTransaction()) $db->rollBack();
                            // El archivo se conserva para evitar pérdida accidental; no se marca AVAILABLE.
                            error_log('[CallAudit][audio_upload] ' . $e->getMessage());
                            $message = 'El archivo se almacenó, pero no se pudo actualizar la llamada en la base de datos. No se habilitó para evaluación; revisa el log de PHP.'; $messageType = 'error';
                        }
                    }
                }
            }
        }
    }
}

$stmt = $db->prepare("SELECT id, external_call_id, started_at, duration_seconds, audio_status FROM audit_calls WHERE tenant_id=:tenant AND audio_status<>'DELETED' ORDER BY started_at DESC, id DESC LIMIT 500");
$stmt->execute([':tenant'=>$tenantId]);
$calls = $stmt->fetchAll();
$token = $csrf->token();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Recepción de audios | CallAudit</title>
<style>body{font-family:Arial,sans-serif;background:#f4f6f9;color:#202938;margin:0;padding:22px}main{max-width:1000px;margin:auto}.panel{background:#fff;border:1px solid #dce2ea;border-radius:10px;padding:20px;margin-bottom:18px}label{display:block;font-weight:600;margin:12px 0 6px}select,input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #b9c3d0;border-radius:6px}.btn{display:inline-block;background:#174ea6;color:#fff;border:0;border-radius:6px;padding:10px 14px;text-decoration:none;cursor:pointer;margin-top:14px}.notice{padding:12px;border-radius:6px;margin:12px 0}.success{background:#e5f6eb}.error{background:#fde8e8}.info{background:#edf3ff}.small{color:#667085;font-size:13px;line-height:1.5}</style></head><body><main>
<section class="panel"><h1>Recepción de audios</h1><p class="small">Carga manual inicial para llamadas registradas. Los archivos se guardan fuera del directorio público; el tamaño máximo de CallAudit es 100 MiB. Una carga correcta no significa que la llamada haya sido evaluada.</p><p><a class="btn" href="audit_calls.php">Volver a llamadas</a> <a class="btn" href="audit_tasks.php">Tareas de auditoría</a></p>
<?php if ($message !== ''): ?><div class="notice <?= audioUploadH($messageType) ?>" role="status"><?= audioUploadH($message) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= audioUploadH($token) ?>"><label for="call_id">Llamada</label><select id="call_id" name="call_id" required><option value="">Selecciona una llamada</option><?php foreach ($calls as $call): ?><option value="<?= (int)$call['id'] ?>">#<?= (int)$call['id'] ?> · <?= audioUploadH($call['external_call_id'] ?: 'Sin ID externo') ?> · <?= audioUploadH($call['started_at'] ?: 'Sin fecha') ?> · <?= (int)($call['duration_seconds'] ?? 0) ?> s · <?= audioUploadH($call['audio_status']) ?></option><?php endforeach; ?></select><label for="audio_file">Archivo de audio</label><input id="audio_file" type="file" name="audio_file" accept=".wav,.mp3,.ogg,.flac,.m4a,.aac,.gsm,audio/*" required><p class="small">Formatos admitidos: WAV, MP3, OGG, FLAC, M4A, AAC y GSM 06.10 de Asterisk (tramas de 33 bytes). Se comprueba la firma del archivo, se calcula SHA-256 y se conserva la versión anterior cuando se reemplaza un audio. No se aceptan llamadas marcadas como DELETED.</p><button class="btn" type="submit">Recibir y validar audio</button></form></section>
<section class="panel"><h2>Comportamiento</h2><ul><li>Solo muestra llamadas de la empresa seleccionada.</li><li>El estado cambia a AVAILABLE únicamente después de guardar el archivo y actualizar los metadatos.</li><li>La ruta física no se expone en la web; la base guarda una clave relativa.</li><li>Se registran cargas, reemplazos y rechazos en audit_audio_events.</li><li>Este primer bloque es carga manual; la sincronización automática de Asterisk/Vicidial/Issabel queda separada.</li></ul></section>
</main></body></html>

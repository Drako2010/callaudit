<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/config/database.php';

function audioHistoryH($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$auth = new AuthMiddleware();
$user = $auth->usuario();

if (!$user) {
    header('Location: login.php');
    exit;
}

$userId = (int)($user['id'] ?? 0);
$authorization = new Authorization();

if (
    !$authorization->tienePermiso($userId, 'calls.view') &&
    !$authorization->tienePermiso($userId, 'audits.view') &&
    !$authorization->tienePermiso($userId, 'audits.edit')
) {
    http_response_code(403);
    exit('No tienes permiso para consultar el historial de audios.');
}

$tenantId = (new CampaignTenantContext())->resolver($user);
$db = (new Database())->connect();

if (!$db) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}

$callRaw = $_GET['call_id'] ?? '';
$callId = (
    is_string($callRaw) &&
    ctype_digit($callRaw) &&
    (int)$callRaw > 0
) ? (int)$callRaw : 0;

$error = '';
$call = null;

if ($callId > 0) {
    $stmt = $db->prepare("
        SELECT id, external_call_id, started_at, audio_status
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
        $error = 'La llamada indicada no existe en esta empresa.';
        $callId = 0;
    }
}

$sql = "
    SELECT
        e.id,
        e.call_id,
        e.actor_user_id,
        e.action,
        e.outcome,
        e.audio_storage_key,
        e.audio_sha256,
        e.audio_mime_type,
        e.audio_size_bytes,
        e.message,
        e.created_at,
        c.external_call_id,
        c.started_at
    FROM audit_audio_events e
    INNER JOIN audit_calls c
        ON c.id = e.call_id
       AND c.tenant_id = e.tenant_id
    WHERE e.tenant_id = :tenant
";

$params = [':tenant' => $tenantId];

if ($callId > 0) {
    $sql .= " AND e.call_id = :call_id";
    $params[':call_id'] = $callId;
}

$sql .= " ORDER BY e.created_at DESC, e.id DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

function audioHistorySize($bytes): string
{
    if ($bytes === null || $bytes === '') {
        return '—';
    }

    $bytes = (float)$bytes;

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MiB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KiB';
    }

    return number_format($bytes, 0) . ' bytes';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de audios | CallAudit</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #202938;
            margin: 0;
            padding: 22px;
        }
        main {
            max-width: 1250px;
            margin: auto;
        }
        .panel {
            background: #fff;
            border: 1px solid #dce2ea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .btn {
            display: inline-block;
            background: #174ea6;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 9px 12px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-secondary {
            background: #475467;
        }
        .notice {
            padding: 12px;
            border-radius: 6px;
            background: #fff0d5;
            margin: 12px 0;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border-bottom: 1px solid #e4e7ec;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
        }
        .small {
            color: #667085;
            font-size: 13px;
            line-height: 1.5;
        }
        .success {
            color: #067647;
            font-weight: bold;
        }
        .error {
            color: #b42318;
            font-weight: bold;
        }
        code {
            overflow-wrap: anywhere;
        }
        audio {
            width: 240px;
            max-width: 100%;
        }
    </style>
</head>
<body>
<main>
    <section class="panel">
        <h1>Historial de audios</h1>
        <p class="small">
            Se muestran hasta 500 eventos de la empresa activa.
            Las cargas y reemplazos correctos pueden reproducirse si
            el archivo histórico todavía existe en el almacenamiento.
        </p>

        <p>
            <a class="btn btn-secondary" href="audit_calls.php">
                Volver a llamadas
            </a>
            <a class="btn btn-secondary" href="audit_audio_upload.php">
                Recepción de audios
            </a>
        </p>

        <?php if ($error !== ''): ?>
            <div class="notice" role="alert">
                <?= audioHistoryH($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($call): ?>
            <p>
                <strong>Llamada:</strong>
                #<?= (int)$call['id'] ?>
                · <?= audioHistoryH($call['external_call_id'] ?: 'Sin ID externo') ?>
                · <?= audioHistoryH($call['started_at'] ?: 'Sin fecha') ?>
                · Estado actual: <?= audioHistoryH($call['audio_status']) ?>
            </p>
            <p>
                <a class="btn btn-secondary" href="audit_audio_history.php">
                    Ver todas las llamadas
                </a>
            </p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Eventos registrados</h2>

        <?php if (!$events): ?>
            <p>No hay eventos de audio para mostrar.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Llamada</th>
                        <th>Operación</th>
                        <th>Resultado</th>
                        <th>Archivo</th>
                        <th>Tamaño</th>
                        <th>Usuario</th>
                        <th>Detalle</th>
                        <th>Reproducción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $canPlay =
                            $event['outcome'] === 'SUCCESS' &&
                            in_array(
                                $event['action'],
                                ['UPLOAD', 'REPLACE'],
                                true
                            ) &&
                            !empty($event['audio_storage_key']);
                        ?>
                        <tr>
                            <td><?= audioHistoryH($event['created_at']) ?></td>
                            <td>
                                <a href="audit_audio_history.php?call_id=<?= (int)$event['call_id'] ?>">
                                    #<?= (int)$event['call_id'] ?>
                                </a>
                                <div class="small">
                                    <?= audioHistoryH($event['external_call_id'] ?: 'Sin ID externo') ?>
                                </div>
                            </td>
                            <td><?= audioHistoryH($event['action']) ?></td>
                            <td class="<?= $event['outcome'] === 'SUCCESS' ? 'success' : 'error' ?>">
                                <?= audioHistoryH($event['outcome']) ?>
                            </td>
                            <td>
                                <?= audioHistoryH($event['audio_mime_type'] ?: '—') ?>
                                <?php if (!empty($event['audio_sha256'])): ?>
                                    <details>
                                        <summary>SHA-256</summary>
                                        <code><?= audioHistoryH($event['audio_sha256']) ?></code>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td><?= audioHistorySize($event['audio_size_bytes']) ?></td>
                            <td><?= (int)$event['actor_user_id'] ?></td>
                            <td><?= audioHistoryH($event['message'] ?: '—') ?></td>
                            <td>
                                <?php if ($canPlay): ?>
                                    <audio controls preload="none">
                                        <source
                                            src="audit_audio_version_stream.php?event_id=<?= (int)$event['id'] ?>"
                                            type="<?= audioHistoryH($event['audio_mime_type'] ?: 'application/octet-stream') ?>"
                                        >
                                        Tu navegador no admite la reproducción de audio.
                                    </audio>
                                    <div class="small">
                                        Evento #<?= (int)$event['id'] ?>
                                    </div>
                                <?php else: ?>
                                    <span class="small">No reproducible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
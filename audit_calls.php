<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/controllers/AuditCallController.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.view');

$usuario = $auth->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}

$tenantContext = new CampaignTenantContext();
$tenantId = $tenantContext->resolver($usuario);

$csrf = new CsrfService();
$controller = new AuditCallController();

$mensaje = '';
$tipoMensaje = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();
    $auth->requierePermiso('audits.create');

    $campos = [
        'campaign_id',
        'source_id',
        'external_call_id',
        'agent_external_id',
        'agent_user_id',
        'phone_masked',
        'started_at',
        'duration_seconds'
    ];

    $datos = [];

    foreach ($campos as $campo) {
        $valor = $_POST[$campo] ?? '';

        $datos[$campo] = is_string($valor) ? trim($valor) : '';
    }

    $resultado = $controller->crear($tenantId, $datos);

    $mensaje = (string) ($resultado['message'] ?? 'Operación finalizada.');
    $tipoMensaje = !empty($resultado['success']) ? 'success' : 'error';

    if (!empty($resultado['success'])) {
        $parametro = !empty($resultado['duplicate'])
            ? 'duplicate'
            : 'created';

        header('Location: audit_calls.php?result=' . $parametro);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resultadoGet = $_GET['result'] ?? '';

    if ($resultadoGet === 'created') {
        $mensaje = 'Llamada registrada correctamente.';
        $tipoMensaje = 'success';
    } elseif ($resultadoGet === 'duplicate') {
        $mensaje = 'La llamada ya existía y no se duplicó.';
        $tipoMensaje = 'info';
    }
}

$filtros = [
    'campaign_id' => $_GET['campaign_id'] ?? '',
    'processing_status' => $_GET['processing_status'] ?? ''
];

$opciones = $controller->opciones($tenantId);
$llamadas = $controller->listar($tenantId, $filtros);

$puedeCrear = false;

try {
    $authorization = new Authorization();
    $puedeCrear = $authorization->tienePermiso(
        (int) $usuario['id'],
        'audits.create'
    );
} catch (Throwable $e) {
    error_log('[CallAudit][audit_calls.php][permiso] ' . $e->getMessage());
}

function auditCallsEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function auditCallsEstado(string $estado): string
{
    $estados = [
        'NEW' => 'Nueva',
        'READY' => 'Lista',
        'IN_PROGRESS' => 'En proceso',
        'COMPLETED' => 'Completada',
        'REVIEW' => 'Revisión',
        'ERROR' => 'Error',
        'ARCHIVED' => 'Archivada'
    ];

    return $estados[$estado] ?? $estado;
}

function auditCallsAudioEstado(string $estado): string
{
    $estados = [
        'NOT_RECEIVED' => 'Sin recibir',
        'PENDING' => 'Pendiente',
        'AVAILABLE' => 'Disponible',
        'MISSING' => 'No encontrado',
        'ERROR' => 'Error',
        'DELETED' => 'Eliminado'
    ];

    return $estados[$estado] ?? $estado;
}

$csrfToken = $csrf->token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Llamadas | CallAudit</title>

    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f4f6f9;
            color: #202733;
            font-family: Arial, Helvetica, sans-serif;
        }

        .contenedor {
            max-width: 1450px;
            margin: 0 auto;
        }

        .encabezado, .panel {
            background: #fff;
            border: 1px solid #dfe4ea;
            border-radius: 8px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .encabezado h1 {
            margin-top: 0;
        }

        .rejilla {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 14px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }

        input, select {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            border: 1px solid #bcc5d0;
            border-radius: 5px;
            font: inherit;
        }

        button, .enlace {
            display: inline-block;
            padding: 10px 15px;
            border: 0;
            border-radius: 5px;
            background: #175cd3;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
        }

        .secundario {
            background: #475467;
        }

        .acciones {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .mensaje {
            padding: 12px 14px;
            margin-bottom: 18px;
            border-radius: 5px;
            background: #e8eef8;
        }

        .success {
            background: #e5f7e9;
            color: #176b32;
        }

        .error {
            background: #fde8e8;
            color: #a11b1b;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            padding: 11px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        th {
            background: #f8fafc;
        }

        .nota {
            color: #475467;
            font-size: 14px;
            line-height: 1.5;
        }

        @media (max-width: 900px) {
            .rejilla {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }

        @media (max-width: 550px) {
            body {
                padding: 12px;
            }

            .rejilla {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="contenedor">

    <header class="encabezado">
        <h1>Llamadas de auditoría</h1>
        <p>
            Registra y consulta las llamadas de la empresa autorizada.
            La recepción y reproducción de audios se implementará en el módulo de almacenamiento.
        </p>
        <a class="enlace secundario" href="dashboard.php">Volver al panel</a>
        <a class="enlace secundario" href="audit_forms.php">Formularios</a>
        <a class="enlace secundario" href="audit_stages.php">Etapas</a>
    </header>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?= auditCallsEscape($tipoMensaje) ?>">
            <?= auditCallsEscape($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($puedeCrear): ?>
        <section class="panel">
            <h2>Registrar llamada manualmente</h2>

            <p class="nota">
                Los campos marcados como opcionales pueden completarse después
                durante la integración con el servidor telefónico. El teléfono
                debe guardarse enmascarado; no introduzcas contraseñas ni rutas locales.
            </p>

            <form method="post" action="audit_calls.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= auditCallsEscape($csrfToken) ?>"
                >

                <div class="rejilla">
                    <div>
                        <label for="campaign_id">Campaña *</label>
                        <select id="campaign_id" name="campaign_id" required>
                            <option value="">Seleccionar campaña</option>
                            <?php foreach ($opciones['campaigns'] as $campania): ?>
                                <option value="<?= (int) $campania['id'] ?>">
                                    <?= auditCallsEscape($campania['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="source_id">Origen telefónico</label>
                        <select id="source_id" name="source_id">
                            <option value="">Sin origen asignado</option>
                            <?php foreach ($opciones['sources'] as $origen): ?>
                                <option value="<?= (int) $origen['id'] ?>">
                                    <?= auditCallsEscape(
                                        $origen['name'] . ' (' . $origen['platform'] . ')'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="external_call_id">ID externo de llamada</label>
                        <input
                            id="external_call_id"
                            name="external_call_id"
                            maxlength="255"
                            placeholder="Identificador del sistema telefónico"
                        >
                    </div>

                    <div>
                        <label for="started_at">Fecha y hora</label>
                        <input
                            id="started_at"
                            name="started_at"
                            type="datetime-local"
                        >
                    </div>

                    <div>
                        <label for="agent_user_id">Agente interno</label>
                        <select id="agent_user_id" name="agent_user_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($opciones['agents'] as $agente): ?>
                                <option value="<?= (int) $agente['id'] ?>">
                                    <?= auditCallsEscape($agente['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="agent_external_id">ID externo del agente</label>
                        <input
                            id="agent_external_id"
                            name="agent_external_id"
                            maxlength="255"
                            placeholder="ID en Asterisk/Vicidial/etc."
                        >
                    </div>

                    <div>
                        <label for="phone_masked">Teléfono enmascarado</label>
                        <input
                            id="phone_masked"
                            name="phone_masked"
                            maxlength="100"
                            placeholder="Ej.: ******1234"
                        >
                    </div>

                    <div>
                        <label for="duration_seconds">Duración en segundos</label>
                        <input
                            id="duration_seconds"
                            name="duration_seconds"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Ej.: 180"
                        >
                    </div>
                </div>

                <div class="acciones">
                    <button type="submit">Registrar llamada</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Consultar llamadas</h2>

        <form method="get" action="audit_calls.php">
            <div class="rejilla">
                <div>
                    <label for="filter_campaign">Campaña</label>
                    <select id="filter_campaign" name="campaign_id">
                        <option value="">Todas las campañas</option>
                        <?php foreach ($opciones['campaigns'] as $campania): ?>
                            <option
                                value="<?= (int) $campania['id'] ?>"
                                <?= (string) ($filtros['campaign_id'] ?? '') === (string) $campania['id'] ? 'selected' : '' ?>
                            >
                                <?= auditCallsEscape($campania['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="filter_status">Estado de procesamiento</label>
                    <select id="filter_status" name="processing_status">
                        <option value="">Todos los estados</option>
                        <?php
                        $estados = [
                            'NEW', 'READY', 'IN_PROGRESS',
                            'COMPLETED', 'REVIEW', 'ERROR', 'ARCHIVED'
                        ];
                        ?>
                        <?php foreach ($estados as $estado): ?>
                            <option
                                value="<?= auditCallsEscape($estado) ?>"
                                <?= ($filtros['processing_status'] ?? '') === $estado ? 'selected' : '' ?>
                            >
                                <?= auditCallsEscape(auditCallsEstado($estado)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="acciones">
                <button type="submit">Filtrar</button>
                <a class="enlace secundario" href="audit_calls.php">Limpiar filtros</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Listado de llamadas</h2>

        <p class="nota">
            Se muestran hasta 500 registros por consulta, ordenados desde la llamada más reciente.
        </p>

        <div class="tabla-contenedor">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha y hora</th>
                    <th>Campaña</th>
                    <th>Origen</th>
                    <th>ID externo</th>
                    <th>Agente</th>
                    <th>Duración (s)</th>
                    <th>Audio</th>
                    <th>Procesamiento</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$llamadas): ?>
                    <tr>
                        <td colspan="9">No hay llamadas para los filtros seleccionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($llamadas as $llamada): ?>
                        <tr>
                            <td><?= (int) $llamada['id'] ?></td>
                            <td><?= auditCallsEscape($llamada['started_at'] ?? '—') ?></td>
                            <td><?= auditCallsEscape($llamada['campaign_name']) ?></td>
                            <td><?= auditCallsEscape($llamada['source_name'] ?? 'Manual/sin origen') ?></td>
                            <td><?= auditCallsEscape($llamada['external_call_id'] ?? '—') ?></td>
                            <td><?= auditCallsEscape($llamada['agent_name'] ?? $llamada['agent_external_id'] ?? 'Sin asignar') ?></td>
                            <td><?= auditCallsEscape($llamada['duration_seconds'] ?? '—') ?></td>
                            <td><?= auditCallsEscape(auditCallsAudioEstado($llamada['audio_status'])) ?></td>
                            <td><?= auditCallsEscape(auditCallsEstado($llamada['processing_status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
</body>
</html>
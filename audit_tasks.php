<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/controllers/AuditTaskController.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.view');

$user = $auth->usuario();

if (!$user) {
    header('Location: login.php');
    exit;
}

$context = new CampaignTenantContext();
$tenantId = $context->resolver($user);
$csrf = new CsrfService();
$controller = new AuditTaskController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();

    $action = (string) ($_POST['action'] ?? '');
    $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$taskId || $taskId <= 0) {
        $result = [
            'success' => false,
            'message' => 'Tarea inválida.'
        ];
    } elseif ($action === 'assign') {
        $evaluatorId = filter_var(
            $_POST['evaluator_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $result = (!$evaluatorId || $evaluatorId <= 0)
            ? [
                'success' => false,
                'message' => 'Selecciona un evaluador válido.'
            ]
            : $controller->asignar(
                $tenantId,
                $taskId,
                $evaluatorId
            );

    } elseif ($action === 'status') {
        $estado = is_string($_POST['status'] ?? null)
            ? $_POST['status']
            : '';

        $result = $controller->cambiarEstado(
            $tenantId,
            $taskId,
            $estado
        );

    } else {
        $result = [
            'success' => false,
            'message' => 'Acción no válida.'
        ];
    }

    $key = !empty($result['success'])
        ? 'success'
        : 'error';

    header(
        'Location: audit_tasks.php?result=' . $key .
        '&message=' . rawurlencode($result['message'])
    );
    exit;
}

$filters = [
    'status' => is_string($_GET['status'] ?? null)
        ? $_GET['status']
        : '',
    'campaign_id' => $_GET['campaign_id'] ?? ''
];

$tasks = $controller->listar($tenantId, $filters);
$agents = $controller->agentes($tenantId);
$token = $csrf->token();

$message = is_string($_GET['message'] ?? null)
    ? $_GET['message']
    : '';

$messageType = ($_GET['result'] ?? '') === 'success'
    ? 'success'
    : 'error';

function auditTasksH($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$labels = [
    'PENDING' => 'Pendiente',
    'ASSIGNED' => 'Asignada',
    'IN_PROGRESS' => 'En curso',
    'SUBMITTED' => 'Enviada',
    'REVIEW' => 'En revisión',
    'COMPLETED' => 'Completada',
    'CANCELLED' => 'Cancelada'
];

$nextStates = [
    'ASSIGNED' => [
        'IN_PROGRESS' => 'Iniciar'
    ],
    'IN_PROGRESS' => [
        'SUBMITTED' => 'Enviar'
    ],
    'SUBMITTED' => [
        'REVIEW' => 'Enviar a revisión'
    ],
    'REVIEW' => [
        'COMPLETED' => 'Completar',
        'IN_PROGRESS' => 'Reabrir'
    ]
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tareas de auditoría | CallAudit</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #202938;
    margin: 0;
    padding: 24px;
}

main {
    max-width: 1400px;
    margin: auto;
}

.panel {
    background: #fff;
    border: 1px solid #dce2ea;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

h1 {
    margin-top: 0;
}

label {
    display: block;
    font-weight: 600;
    margin: 8px 0;
}

input,
select {
    box-sizing: border-box;
    padding: 8px;
    border: 1px solid #b9c3d0;
    border-radius: 6px;
    max-width: 100%;
}

button,
.button {
    display: inline-block;
    padding: 8px 12px;
    border: 0;
    border-radius: 6px;
    background: #174ea6;
    color: white;
    text-decoration: none;
    cursor: pointer;
}

button:hover,
.button:hover {
    background: #123b7c;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th,
td {
    padding: 10px;
    border-bottom: 1px solid #e1e6ed;
    text-align: left;
    vertical-align: top;
}

th {
    background: #eef2f7;
}

.notice {
    padding: 12px;
    border-radius: 6px;
    margin: 12px 0;
}

.success {
    background: #e5f6eb;
}

.error {
    background: #fde8e8;
}

.small {
    font-size: 13px;
    color: #667085;
}

.actions form {
    margin: 0 0 8px;
}

.actions select {
    width: 160px;
}

.actions .evaluate-link {
    margin-bottom: 10px;
}

@media (max-width: 800px) {
    body {
        padding: 10px;
    }

    .panel {
        padding: 12px;
        overflow-x: auto;
    }

    table {
        min-width: 750px;
    }
}
</style>
</head>

<body>
<main>

<section class="panel">
    <h1>Tareas de auditoría</h1>

    <p class="small">
        Consulta, asigna y administra el avance de las tareas
        de la empresa seleccionada.
    </p>

    <p>
        <a class="button" href="dashboard.php">
            Volver al panel
        </a>

        <a class="button" href="audit_calls.php">
            Llamadas
        </a>

        <a class="button" href="audit_stages.php">
            Etapas
        </a>
    </p>
</section>

<?php if ($message !== ''): ?>
    <div class="notice <?= auditTasksH($messageType) ?>">
        <?= auditTasksH($message) ?>
    </div>
<?php endif; ?>

<section class="panel">
    <form method="get">
        <label for="status">Estado</label>

        <select name="status" id="status">
            <option value="">Todos</option>

            <?php foreach ($labels as $value => $label): ?>
                <option
                    value="<?= auditTasksH($value) ?>"
                    <?= $filters['status'] === $value
                        ? 'selected'
                        : '' ?>>
                    <?= auditTasksH($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filtrar</button>
    </form>
</section>

<section class="panel">
    <h2>Listado (máximo 500)</h2>

    <?php if (!$tasks): ?>
        <p>No hay tareas para los filtros seleccionados.</p>
    <?php else: ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Campaña / etapa</th>
                <th>Llamada</th>
                <th>Evaluador</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($tasks as $task): ?>
            <tr>
                <td>
                    #<?= (int) $task['id'] ?>
                </td>

                <td>
                    <strong>
                        <?= auditTasksH($task['campaign_name']) ?>
                    </strong>
                    <br>

                    Etapa <?= (int) $task['stage_number'] ?>:
                    <?= auditTasksH($task['stage_name']) ?>
                </td>

                <td>
                    <?= auditTasksH($task['call_started_at']) ?>
                    <br>

                    Duración:
                    <?= (int) ($task['duration_seconds'] ?? 0) ?> s
                    <br>

                    Audio:
                    <?= auditTasksH($task['audio_status']) ?>
                </td>

                <td>
                    <?= auditTasksH(
                        $task['evaluator_name'] ?? 'Sin asignar'
                    ) ?>
                </td>

                <td>
                    <?= auditTasksH(
                        $labels[$task['status']] ?? $task['status']
                    ) ?>
                </td>

                <td>
                    <?= (int) $task['priority'] ?>
                </td>

                <td class="actions">

                    <?php
                    $taskCanBeEvaluated = in_array(
                        $task['status'],
                        ['PENDING', 'ASSIGNED', 'IN_PROGRESS'],
                        true
                    );
                    ?>

                    <?php if ($taskCanBeEvaluated): ?>
                        <p class="evaluate-link">
                            <a
                                class="button"
                                href="audit_evaluate.php?task_id=<?= (int) $task['id'] ?>">
                                Evaluar
                            </a>
                        </p>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= auditTasksH($token) ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="assign">

                            <input
                                type="hidden"
                                name="task_id"
                                value="<?= (int) $task['id'] ?>">

                            <select
                                name="evaluator_id"
                                required>
                                <option value="">
                                    Asignar evaluador
                                </option>

                                <?php foreach ($agents as $agent): ?>
                                    <option
                                        value="<?= (int) $agent['id'] ?>"
                                        <?= (int) ($task['assigned_to'] ?? 0)
                                            === (int) $agent['id']
                                            ? 'selected'
                                            : '' ?>>
                                        <?= auditTasksH($agent['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit">
                                Asignar
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php foreach (
                        ($nextStates[$task['status']] ?? [])
                        as $state => $actionLabel
                    ): ?>
                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= auditTasksH($token) ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="status">

                            <input
                                type="hidden"
                                name="task_id"
                                value="<?= (int) $task['id'] ?>">

                            <input
                                type="hidden"
                                name="status"
                                value="<?= auditTasksH($state) ?>">

                            <button type="submit">
                                <?= auditTasksH($actionLabel) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>

                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>
</section>

</main>
</body>
</html>

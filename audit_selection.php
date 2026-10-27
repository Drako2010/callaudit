<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/controllers/AuditSelectionController.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.view');

$user = $auth->usuario();

if (!$user) {
    header('Location: login.php');
    exit;
}

$context = new CampaignTenantContext();
$tenantId = (int) $context->resolver($user);

$csrf = new CsrfService();
$controller = new AuditSelectionController();

$campaigns = $controller->campanias($tenantId);
$campaignId = filter_input(INPUT_GET, 'campaign_id', FILTER_VALIDATE_INT)
    ?: (int) ($_POST['campaign_id'] ?? 0);

$policies = $campaignId > 0
    ? $controller->listar($tenantId, $campaignId)
    : [];

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validar($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Token CSRF inválido. Recarga la página.');
    }

    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'generate') {
        $policyId = filter_var($_POST['policy_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$policyId || $policyId <= 0) {
            $result = [
                'success' => false,
                'message' => 'Política inválida.'
            ];
        } else {
            $result = $controller->generar($tenantId, $policyId);
        }
    } else {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        $result = $controller->guardar(
            $tenantId,
            $campaignId,
            $_POST,
            $id && $id > 0 ? $id : null
        );
    }

    $message = (string) ($result['message'] ?? 'Operación finalizada.');
    $messageType = !empty($result['success']) ? 'success' : 'error';

    if (!empty($result['success']) && $action !== 'generate') {
        header('Location: audit_selection.php?campaign_id=' . $campaignId . '&saved=1');
        exit;
    }

    if (!empty($result['success']) && $action === 'generate') {
        $message .= ' Llamadas seleccionadas: ' . (int) ($result['selected'] ?? 0) .
            '. Tareas nuevas: ' . (int) ($result['created'] ?? 0) .
            '. Tareas existentes: ' . (int) ($result['skipped'] ?? 0) . '.';

        $policies = $controller->listar($tenantId, $campaignId);
    }
}

if (isset($_GET['saved'])) {
    $message = 'Política guardada correctamente.';
    $messageType = 'success';
}

function auditSelectionH($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$token = $csrf->token();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Selección de llamadas | CallAudit</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;color:#202938;margin:0;padding:24px}
main{max-width:1200px;margin:auto}
.panel{background:white;border:1px solid #dce2ea;border-radius:10px;padding:20px;margin-bottom:20px}
label{display:block;font-weight:600;margin:12px 0 5px}
input,select{box-sizing:border-box;width:100%;max-width:520px;padding:10px;border:1px solid #b9c3d0;border-radius:6px}
button,.button{display:inline-block;padding:10px 15px;border:0;border-radius:6px;background:#174ea6;color:white;text-decoration:none;cursor:pointer}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e4e7ec;text-align:left}
.notice{padding:12px;border-radius:6px;margin-bottom:16px;background:#eef2f7}
.success{background:#e5f7e9}.error{background:#fde8e8}
.row{display:flex;gap:20px;flex-wrap:wrap}
.row>div{flex:1;min-width:220px}
.small{color:#475467;font-size:14px;line-height:1.5}
</style>
</head>
<body>
<main>
    <section class="panel">
        <h1>Selección de llamadas</h1>
        <p class="small">
            Configura cómo se seleccionan las llamadas y genera las tareas
            correspondientes a las etapas activas de la campaña.
        </p>
        <p>
            <a class="button" href="dashboard.php">Volver al panel</a>
            <a class="button" href="audit_calls.php">Llamadas</a>
            <a class="button" href="audit_stages.php">Etapas</a>
        </p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice <?= auditSelectionH($messageType) ?>">
            <?= auditSelectionH($message) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>Seleccionar campaña</h2>
        <form method="get">
            <label for="campaign_id">Campaña</label>
            <select name="campaign_id" id="campaign_id" required>
                <option value="">Seleccionar</option>
                <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?= (int) $campaign['id'] ?>"
                        <?= $campaignId === (int) $campaign['id'] ? 'selected' : '' ?>>
                        <?= auditSelectionH($campaign['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p><button type="submit">Consultar políticas</button></p>
        </form>
    </section>

    <?php if ($campaignId > 0): ?>
    <section class="panel">
        <h2>Nueva política</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= auditSelectionH($token) ?>">
            <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
            <input type="hidden" name="action" value="save">

            <div class="row">
                <div>
                    <label for="name">Nombre</label>
                    <input id="name" name="name" maxlength="150" required>
                </div>
                <div>
                    <label for="method">Método</label>
                    <select id="method" name="method" required>
                        <option value="MANUAL">Manual</option>
                        <option value="RANDOM">Aleatoria</option>
                        <option value="RULES">Por reglas</option>
                        <option value="AUTOMATIC">Automática</option>
                    </select>
                </div>
                <div>
                    <label for="priority">Prioridad</label>
                    <input id="priority" name="priority" type="number" min="0" max="1000000" value="100" required>
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="sample_size">Llamadas por ejecución</label>
                    <input id="sample_size" name="sample_size" type="number" min="1" max="10000" value="10" required>
                </div>
                <div>
                    <label for="min_duration_seconds">Duración mínima (segundos)</label>
                    <input id="min_duration_seconds" name="min_duration_seconds" type="number" min="0" max="86400" value="0" required>
                </div>
            </div>

            <label>
                <input type="checkbox" name="only_with_audio" value="1" checked style="width:auto">
                Seleccionar solamente llamadas con audio disponible
            </label>

            <label>
                <input type="checkbox" name="enabled" value="1" checked style="width:auto">
                Política activa
            </label>

            <p><button type="submit">Guardar política</button></p>
        </form>
    </section>

    <section class="panel">
        <h2>Políticas configuradas</h2>
        <table>
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Método</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Generar tareas</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$policies): ?>
                <tr><td colspan="5">No hay políticas configuradas para esta campaña.</td></tr>
            <?php else: ?>
                <?php foreach ($policies as $policy): ?>
                    <tr>
                        <td><?= auditSelectionH($policy['name']) ?></td>
                        <td><?= auditSelectionH($policy['method']) ?></td>
                        <td><?= (int) $policy['priority'] ?></td>
                        <td><?= (int) $policy['enabled'] === 1 ? 'Activa' : 'Inactiva' ?></td>
                        <td>
                            <?php if ((int) $policy['enabled'] === 1): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= auditSelectionH($token) ?>">
                                    <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                                    <input type="hidden" name="policy_id" value="<?= (int) $policy['id'] ?>">
                                    <input type="hidden" name="action" value="generate">
                                    <button type="submit">Generar tareas</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <p class="small">
            La generación de tareas no ejecuta una auditoría ni cobra por IA.
            Las tareas repetidas se omiten mediante la clave única existente de la base de datos.
        </p>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
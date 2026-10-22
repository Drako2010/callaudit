<?php
/**
 * CallAudit - configuración de etapas de auditoría por campaña.
 * Requiere la migración audit_workflow_stages ya presente en callaudit.sql.
 */
require_once __DIR__.'/services/AuthMiddleware.php';
require_once __DIR__.'/services/CsrfService.php';
require_once __DIR__.'/services/CampaignTenantContext.php';
require_once __DIR__.'/controllers/AuditStageController.php';

$auth=new AuthMiddleware();
$auth->requierePermiso('audits.view');
$user=$auth->usuario();
if (!$user) { header('Location: login.php'); exit; }
$context=new CampaignTenantContext();
$tenantId=(int)$context->resolver($user);
$csrf=new CsrfService();
$controller=new AuditStageController();
$data=$controller->datosFormulario($tenantId);
$campaigns=$data['campaigns'];
$versions=$data['versions'];
$campaignId=filter_input(INPUT_GET,'campaign_id',FILTER_VALIDATE_INT) ?: (int)($_POST['campaign_id']??0);
$editId=filter_input(INPUT_GET,'edit',FILTER_VALIDATE_INT) ?: 0;
$editing=null;
$stages=[];
$message='';
$messageType='info';

if ($campaignId>0) {
    // La lista se valida contra tenant_id en el modelo.
    $stages=$controller->listar($tenantId,$campaignId);
    if ($editId>0) {
        foreach ($stages as $stage) if ((int)$stage['id']===$editId) $editing=$stage;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$csrf->validar($_POST['csrf_token']??'')) {
        http_response_code(419); exit('Token CSRF inválido. Recarga la página e inténtalo nuevamente.');
    }
    $action=(string)($_POST['action']??'save');
    if ($action==='save') {
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        $result=$controller->guardar($tenantId,$campaignId,$_POST,$id && $id>0 ? $id : null);
        $message=$result['message']??'Operación finalizada.';
        $messageType=!empty($result['success'])?'success':'error';
        if (!empty($result['success'])) {
            header('Location: audit_stages.php?campaign_id='.$campaignId.'&saved=1');
            exit;
        }
        $editing=$_POST;
    }
}
if (isset($_GET['saved'])) { $message='Etapa guardada correctamente.'; $messageType='success'; }

function h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$token=$csrf->token();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Etapas de auditoría | CallAudit</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;color:#202938;margin:0;padding:24px}
main{max-width:1200px;margin:auto}.panel{background:#fff;border:1px solid #dce2ea;border-radius:10px;padding:20px;margin-bottom:20px}
h1{margin-top:0}label{display:block;font-weight:600;margin:12px 0 5px}
input,select{box-sizing:border-box;padding:10px;border:1px solid #b9c3d0;border-radius:6px;width:100%;max-width:520px}
.inline{display:flex;gap:18px;flex-wrap:wrap}.inline label{font-weight:400;display:flex;align-items:center;gap:8px}.inline input{width:auto}
button,.button{display:inline-block;padding:10px 15px;border:0;border-radius:6px;background:#174ea6;color:white;text-decoration:none;cursor:pointer;margin-top:14px}
table{border-collapse:collapse;width:100%;background:#fff}th,td{padding:10px;text-align:left;border-bottom:1px solid #e1e6ed}th{background:#eef2f7}
.notice{padding:12px;border-radius:6px;margin:12px 0}.success{background:#e5f6eb}.error{background:#fde8e8}.muted{color:#667085}
</style>
</head>
<body><main>
<h1>Etapas de auditoría</h1>
<p class="muted">Configura el flujo de cada campaña. Cada etapa utiliza una versión publicada de un formulario y puede ejecutarse por humanos, IA o ambos.</p>
<?php if ($message!==''): ?><div class="notice <?=h($messageType)?>"><?=h($message)?></div><?php endif; ?>

<section class="panel">
<h2>Seleccionar campaña</h2>
<form method="get">
<label for="campaign_id">Campaña</label>
<select id="campaign_id" name="campaign_id" required>
<option value="">Selecciona una campaña</option>
<?php foreach($campaigns as $campaign): ?>
<option value="<?= (int)$campaign['id']?>" <?=((int)$campaign['id']===$campaignId?'selected':'')?>><?=h($campaign['name'])?></option>
<?php endforeach; ?>
</select>
<button type="submit">Ver etapas</button>
</form>
<?php if (!$campaigns): ?><p>No hay campañas registradas para esta empresa.</p><?php endif; ?>
</section>

<?php if ($campaignId>0): ?>
<section class="panel">
<h2><?= $editing ? 'Editar etapa' : 'Crear etapa' ?></h2>
<?php if (!$versions): ?>
<p>No hay versiones publicadas disponibles. Publica una versión de formulario antes de configurar etapas.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?=h($token)?>">
<input type="hidden" name="campaign_id" value="<?=$campaignId?>">
<input type="hidden" name="id" value="<?= (int)($editing['id']??0)?>">
<label for="stage_number">Número de etapa</label>
<input type="number" id="stage_number" name="stage_number" min="1" required value="<?=h($editing['stage_number']??(count($stages)+1))?>">
<label for="name">Nombre</label>
<input type="text" id="name" name="name" maxlength="150" required value="<?=h($editing['name']??'')?>">
<label for="form_version_id">Formulario publicado y versión</label>
<select id="form_version_id" name="form_version_id" required>
<option value="">Selecciona una versión</option>
<?php foreach($versions as $version): ?>
<option value="<?=(int)$version['id']?>" <?=((int)($editing['form_version_id']??0)===(int)$version['id']?'selected':'')?>><?=h($version['form_name'].' — v'.$version['version_number'])?></option>
<?php endforeach; ?>
</select>
<div class="inline">
<label><input type="checkbox" name="human_enabled" value="1" <?=(!isset($editing['human_enabled'])||(int)$editing['human_enabled']===1?'checked':'')?>> Auditoría humana</label>
<label><input type="checkbox" name="ai_enabled" value="1" <?=((int)($editing['ai_enabled']??0)===1?'checked':'')?>> Análisis IA</label>
<label><input type="checkbox" name="ai_required" value="1" <?=((int)($editing['ai_required']??0)===1?'checked':'')?>> IA obligatoria</label>
</div>
<label for="status">Estado</label>
<select name="status" id="status">
<option value="ACTIVE" <?=(($editing['status']??'ACTIVE')==='ACTIVE'?'selected':'')?>>Activa</option>
<option value="INACTIVE" <?=(($editing['status']??'ACTIVE')==='INACTIVE'?'selected':'')?>>Inactiva</option>
</select>
<button type="submit" name="action" value="save">Guardar etapa</button>
<?php if($editing): ?> <a class="button" href="audit_stages.php?campaign_id=<?=$campaignId?>">Cancelar edición</a><?php endif; ?>
</form>
<?php endif; ?>
</section>

<section class="panel">
<h2>Etapas configuradas</h2>
<?php if (!$stages): ?><p>Aún no hay etapas para esta campaña.</p><?php else: ?>
<table><thead><tr><th>N.º</th><th>Etapa</th><th>Formulario</th><th>Ejecutor</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php foreach($stages as $stage): ?>
<tr><td><?= (int)$stage['stage_number']?></td><td><?=h($stage['name'])?></td>
<td><?=h($stage['form_name'].' v'.$stage['version_number'])?></td>
<td><?=((int)$stage['human_enabled']?'Humano':'')?><?=((int)$stage['human_enabled']&&(int)$stage['ai_enabled']?' + ':'')?><?=((int)$stage['ai_enabled']?'IA':'')?></td>
<td><?=h($stage['status'])?></td>
<td><a href="audit_stages.php?campaign_id=<?=$campaignId?>&edit=<?=(int)$stage['id']?>">Editar</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>
<?php endif; ?>
<p><a href="dashboard.php">Volver al dashboard</a> · <a href="audit_forms.php">Formularios de auditoría</a></p>
</main></body></html>

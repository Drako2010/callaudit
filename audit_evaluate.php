
<?php

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/controllers/AuditEvaluationController.php';

$auth = new AuthMiddleware();
$auth->requierePermiso('audits.edit');

$user = $auth->usuario();

if (!$user) {
    header('Location: login.php');
    exit;
}

$context = new CampaignTenantContext();
$tenantId = $context->resolver($user);
$csrf = new CsrfService();
$controller = new AuditEvaluationController();

$taskId = filter_var(
    $_GET['task_id'] ?? $_POST['task_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$taskId || $taskId <= 0) {
    http_response_code(400);
    exit('Identificador de tarea inválido.');
}

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();

    $result = $controller->guardar(
        $tenantId,
        (int) $taskId,
        $_POST
    );

    if (!empty($result['success'])) {
        $key = (string) ($_POST['evaluation_action'] ?? '') === 'submit'
            ? 'submitted'
            : 'saved';

        header(
            'Location: audit_evaluate.php?task_id=' .
            (int) $taskId .
            '&result=' . $key .
            '&message=' . rawurlencode(
                (string) ($result['message'] ?? 'Operación completada.')
            )
        );
        exit;
    }

    $message = (string) (
        $result['message'] ?? 'No se pudo guardar la evaluación.'
    );
}

$data = $controller->cargar($tenantId, (int) $taskId);

if (empty($data['success'])) {
    http_response_code(404);
    exit(
        htmlspecialchars(
            (string) ($data['message'] ?? 'No se pudo cargar la tarea.'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
    );
}

$task = $data['task'];
$questions = $data['questions'];
$draft = $data['draft'];
$answers = $data['answers'];

if (isset($_GET['message']) && is_string($_GET['message'])) {
    $message = $_GET['message'];
}

if (in_array($_GET['result'] ?? '', ['saved', 'submitted'], true)) {
    $messageType = 'success';
}

$token = $csrf->token();

function auditEvalH($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function auditEvalOptions(array $options, string $selected): string
{
    $html = '<option value="">Selecciona...</option>';

    foreach ($options as $option) {
        if (!is_scalar($option)) {
            continue;
        }

        $value = (string) $option;

        $html .= '<option value="' . auditEvalH($value) . '"' .
            ($selected === $value ? ' selected' : '') . '>' .
            auditEvalH($value) .
            '</option>';
    }

    return $html;
}

$audioAvailable =
    ($task['audio_status'] ?? '') === 'AVAILABLE' &&
    !empty($task['audio_storage_key']);

$audioExtension = strtolower(
    (string) pathinfo(
        (string) ($task['audio_storage_key'] ?? ''),
        PATHINFO_EXTENSION
    )
);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Evaluación humana | CallAudit</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #202938;
    margin: 0;
    padding: 22px;
}
main {
    max-width: 1050px;
    margin: auto;
}
.panel {
    background: #fff;
    border: 1px solid #dce2ea;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 18px;
}
h1, h2 {
    margin-top: 0;
}
.meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
}
.meta div {
    background: #f7f9fc;
    padding: 10px;
    border-radius: 6px;
    overflow-wrap: anywhere;
}
.question {
    border: 1px solid #e1e6ed;
    border-radius: 8px;
    padding: 16px;
    margin: 14px 0;
}
.question label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}
.help, .small {
    font-size: 13px;
    color: #667085;
    line-height: 1.5;
}
.field {
    box-sizing: border-box;
    width: 100%;
    padding: 9px;
    border: 1px solid #b9c3d0;
    border-radius: 6px;
    font: inherit;
}
.answer-row {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    margin: 10px 0;
}
.answer-row label {
    font-weight: 400;
}
.btn {
    display: inline-block;
    padding: 10px 14px;
    border: 0;
    border-radius: 6px;
    background: #174ea6;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    font: inherit;
}
.btn.secondary {
    background: #4b5563;
}
.notice {
    padding: 12px;
    border-radius: 6px;
    margin: 12px 0;
    overflow-wrap: anywhere;
}
.success {
    background: #e5f6eb;
}
.error {
    background: #fde8e8;
}
.warning {
    background: #fff4d6;
    border: 1px solid #f0d98a;
}
.required {
    color: #b42318;
}
.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.audio-player {
    display: block;
    width: 100%;
    max-width: 850px;
    margin-top: 12px;
}
@media (max-width: 600px) {
    body {
        padding: 10px;
    }
    .panel {
        padding: 14px;
    }
}
</style>
</head>

<body>
<main>

<section class="panel">
    <h1>Evaluación humana</h1>

    <p>
        <a class="btn secondary" href="audit_tasks.php">
            Volver a tareas
        </a>
    </p>

    <div class="meta">
        <div>
            <strong>Tarea</strong><br>
            #<?= (int) $task['task_id'] ?>
        </div>

        <div>
            <strong>Campaña</strong><br>
            <?= auditEvalH($task['campaign_name']) ?>
        </div>

        <div>
            <strong>Etapa</strong><br>
            <?= (int) $task['stage_number'] ?> —
            <?= auditEvalH($task['stage_name']) ?>
        </div>

        <div>
            <strong>Formulario</strong><br>
            <?= auditEvalH($task['form_name']) ?> · versión
            <?= (int) $task['version_number'] ?>
        </div>

        <div>
            <strong>Llamada</strong><br>
            <?= auditEvalH($task['external_call_id']) ?>
        </div>

        <div>
            <strong>Duración</strong><br>
            <?= (int) $task['duration_seconds'] ?> segundos
        </div>
    </div>

    <?php if (!$audioAvailable): ?>

        <div class="notice warning">
            <strong>Audio no disponible.</strong>

            Esta llamada está en estado
            <?= auditEvalH($task['audio_status'] ?? 'DESCONOCIDO') ?>.

            Puedes guardar un borrador, pero el sistema bloqueará el envío
            final hasta que exista un audio real disponible.

            No evalúes el contenido de la llamada sin escucharla.
        </div>

    <?php else: ?>

        <div class="notice">
            <strong>Audio de la llamada</strong>

            <p class="small">
                El audio se transmite desde el repositorio protegido de
                CallAudit. La ruta física del archivo no se expone al navegador.
            </p>

            <audio
                id="callAudio"
                class="audio-player"
                controls
                preload="metadata"
                src="audit_audio_stream.php?call_id=<?= (int) $task['call_id'] ?>"
            >
                Tu navegador no admite el reproductor de audio HTML5.
            </audio>

            <p id="audioError" class="help" hidden>
                No se pudo reproducir el audio. Verifica que el archivo exista
                en el repositorio, que Apache tenga acceso a él y que el
                formato sea compatible. Para archivos GSM se necesita FFmpeg
                en el servidor.
            </p>

            <?php if ($audioExtension === 'gsm'): ?>
                <p class="help">
                    Este archivo es GSM 06.10 de Asterisk. CallAudit intentará
                    convertirlo a WAV para reproducirlo en el navegador.
                </p>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="notice <?= auditEvalH($messageType) ?>">
            <?= auditEvalH($message) ?>
        </div>
    <?php endif; ?>
</section>

<form method="post">
<input
    type="hidden"
    name="csrf_token"
    value="<?= auditEvalH($token) ?>"
>
<input
    type="hidden"
    name="task_id"
    value="<?= (int) $taskId ?>"
>

<section class="panel">
    <h2>Preguntas del formulario</h2>

    <?php foreach ($questions as $q): ?>
        <?php
        $qid = (int) $q['id'];
        $old = $answers[$qid] ?? [];
        $value = (string) ($old['answer_value'] ?? '');
        $isNa = (int) ($old['is_na'] ?? 0) === 1;
        ?>

        <div class="question">
            <label for="q<?= $qid ?>">
                <?= (int) $q['position'] ?>.
                <?= auditEvalH($q['question_text']) ?>

                <?php if ((int) $q['required_answer']): ?>
                    <span class="required">*</span>
                <?php endif; ?>

                <?php if ((int) $q['is_critical']): ?>
                    <span class="required">(crítica)</span>
                <?php endif; ?>
            </label>

            <?php if (!empty($q['help_text'])): ?>
                <p class="help">
                    <?= auditEvalH($q['help_text']) ?>
                </p>
            <?php endif; ?>

            <?php if ($q['answer_type'] === 'YES_NO'): ?>

                <div class="answer-row">
                    <label>
                        <input
                            type="radio"
                            name="answers[<?= $qid ?>]"
                            value="YES"
                            <?= $value === 'YES' ? 'checked' : '' ?>
                        >
                        Sí
                    </label>

                    <label>
                        <input
                            type="radio"
                            name="answers[<?= $qid ?>]"
                            value="NO"
                            <?= $value === 'NO' ? 'checked' : '' ?>
                        >
                        No
                    </label>
                </div>

            <?php elseif ($q['answer_type'] === 'SCALE'): ?>

                <select
                    class="field"
                    id="q<?= $qid ?>"
                    name="answers[<?= $qid ?>]"
                >
                    <option value="">Selecciona una escala (1–5)</option>

                    <?php for ($n = 1; $n <= 5; $n++): ?>
                        <option
                            value="<?= $n ?>"
                            <?= $value === (string) $n ? 'selected' : '' ?>
                        >
                            <?= $n ?>
                        </option>
                    <?php endfor; ?>
                </select>

            <?php elseif ($q['answer_type'] === 'SINGLE_CHOICE'): ?>

                <select
                    class="field"
                    id="q<?= $qid ?>"
                    name="answers[<?= $qid ?>]"
                >
                    <?= auditEvalOptions($q['options'], $value) ?>
                </select>

            <?php elseif ($q['answer_type'] === 'MULTIPLE_CHOICE'): ?>

                <?php
                $chosen = json_decode($value, true);
                if (!is_array($chosen)) {
                    $chosen = [];
                }
                ?>

                <div class="answer-row">
                    <?php foreach ($q['options'] as $opt): ?>
                        <?php
                        if (!is_scalar($opt)) {
                            continue;
                        }
                        $opt = (string) $opt;
                        ?>

                        <label>
                            <input
                                type="checkbox"
                                name="answers_multi[<?= $qid ?>][]"
                                value="<?= auditEvalH($opt) ?>"
                                <?= in_array($opt, $chosen, true) ? 'checked' : '' ?>
                            >
                            <?= auditEvalH($opt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <input
                    type="hidden"
                    name="answers[<?= $qid ?>]"
                    value="<?= auditEvalH($value) ?>"
                    data-multiple-source="<?= $qid ?>"
                >

            <?php elseif ($q['answer_type'] === 'NUMBER'): ?>

                <input
                    class="field"
                    id="q<?= $qid ?>"
                    type="number"
                    min="0"
                    max="100"
                    step="any"
                    name="answers[<?= $qid ?>]"
                    value="<?= auditEvalH($value) ?>"
                    placeholder="Valor de 0 a 100"
                >

            <?php else: ?>

                <textarea
                    class="field"
                    id="q<?= $qid ?>"
                    name="answers[<?= $qid ?>]"
                    rows="3"
                ><?= auditEvalH($value) ?></textarea>

            <?php endif; ?>

            <?php if ((int) $q['allow_na']): ?>
                <div class="answer-row">
                    <label>
                        <input
                            type="checkbox"
                            name="na[<?= $qid ?>]"
                            value="1"
                            <?= $isNa ? 'checked' : '' ?>
                        >
                        No aplica
                    </label>
                </div>
            <?php endif; ?>

            <label for="comment<?= $qid ?>">
                Comentario de esta respuesta
            </label>

            <textarea
                class="field"
                id="comment<?= $qid ?>"
                name="comments[<?= $qid ?>]"
                rows="2"
            ><?= auditEvalH($old['comment'] ?? '') ?></textarea>

            <p class="help">
                Peso: <?= auditEvalH($q['weight']) ?>

                <?= (int) $q['is_critical']
                    ? ' · Un incumplimiento puede provocar veredicto FAIL.'
                    : '' ?>
            </p>
        </div>
    <?php endforeach; ?>

    <label for="analysis">
        Observaciones generales del auditor
    </label>

    <textarea
        class="field"
        id="analysis"
        name="analysis"
        rows="4"
    ><?= auditEvalH($draft['analysis'] ?? '') ?></textarea>

    <p class="small">
        Los borradores se pueden actualizar. Al enviar, el resultado queda
        registrado y las respuestas dejan de ser editables desde esta pantalla.
    </p>

    <div class="actions">
        <button
            class="btn secondary"
            type="submit"
            name="evaluation_action"
            value="draft"
        >
            Guardar borrador
        </button>

        <button
            class="btn"
            type="submit"
            name="evaluation_action"
            value="submit"
            onclick="return confirm('¿Confirmas el envío definitivo de esta evaluación?');"
        >
            Enviar evaluación final
        </button>
    </div>
</section>
</form>
</main>

<script>
document.querySelectorAll('[data-multiple-source]').forEach(function (hidden) {
    const id = hidden.getAttribute('data-multiple-source');
    const boxes = document.querySelectorAll(
        'input[name="answers_multi[' + id + '][]"]'
    );

    function sync() {
        hidden.value = JSON.stringify(
            Array.from(boxes)
                .filter(function (box) {
                    return box.checked;
                })
                .map(function (box) {
                    return box.value;
                })
        );
    }

    boxes.forEach(function (box) {
        box.addEventListener('change', sync);
    });

    sync();
});

const audio = document.getElementById('callAudio');
const audioError = document.getElementById('audioError');

if (audio && audioError) {
    audio.addEventListener('error', function () {
        audioError.hidden = false;
    });

    audio.addEventListener('playing', function () {
        audioError.hidden = true;
    });
}
</script>
</body>
</html>

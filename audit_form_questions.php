<?php
/*
|--------------------------------------------------------------------------
| Archivo: audit_form_questions.php
|--------------------------------------------------------------------------
| Pantalla de administración de preguntas de auditoría.
|
| Ubicación:
|   D:\Xampp\htdocs\callaudit\audit_form_questions.php
|
| Funcionalidades:
|   - Seleccionar la versión de un formulario.
|   - Mostrar preguntas y sus características.
|   - Crear preguntas con distintos tipos de respuesta.
|   - Configurar peso, obligatoriedad, criticidad y No aplica.
|   - Activar/desactivar preguntas en versiones DRAFT.
|
| Importante:
|   La pantalla no permite modificar versiones publicadas o retiradas.
|   La misma restricción se aplica nuevamente en el modelo.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/controllers/AuditFormQuestionController.php';

/* -----------------------------------------------------------------------
 * 1. Autenticación y contexto de empresa
 * -------------------------------------------------------------------- */

// Solo los usuarios autenticados pueden acceder.
$auth = new AuthMiddleware();
$auth->proteger();

$usuario = $auth->usuario();

if (!$usuario) {
    header('Location: login.php');
    exit;
}

/*
 * Para usuarios de empresa, el contexto corresponde a su tenant_id.
 * Para SUPERADMIN, el servicio exige seleccionar previamente la empresa.
 */
$contextoEmpresa = new CampaignTenantContext();
$tenantId = $contextoEmpresa->resolver($usuario);

/* -----------------------------------------------------------------------
 * 2. Preparar servicios, controlador y permisos de interfaz
 * -------------------------------------------------------------------- */

$csrf = new CsrfService();
$csrfToken = $csrf->token();

$controller = new AuditFormQuestionController();
$authorization = new Authorization();

$usuarioId = (int) $usuario['id'];

// Comprobar permisos para mostrar u ocultar las acciones de la pantalla.
$puedeCrear = $authorization->tienePermiso($usuarioId, 'audits.create');
$puedeEditar = $authorization->tienePermiso($usuarioId, 'audits.edit');

/* -----------------------------------------------------------------------
 * 3. Función para escapar valores al imprimir HTML
 * -------------------------------------------------------------------- */

/**
 * Escapa cualquier valor dinámico para reducir el riesgo de XSS.
 */
function auditQuestionEscape(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/* -----------------------------------------------------------------------
 * 4. Obtener versiones disponibles
 * -------------------------------------------------------------------- */

$versiones = $controller->listarVersiones($tenantId);

/*
 * La versión seleccionada se toma de GET solo como filtro de visualización.
 * El controlador y el modelo comprueban que pertenezca al tenant autorizado.
 */
$versionSeleccionada = filter_input(
    INPUT_GET,
    'version_id',
    FILTER_VALIDATE_INT
);

if (!$versionSeleccionada || $versionSeleccionada <= 0) {
    $versionSeleccionada = 0;
}

/*
 * Comprobar que la versión elegida esté dentro de las versiones visibles.
 * Si no corresponde a esta empresa, no se muestran preguntas.
 */
$versionActual = null;

foreach ($versiones as $version) {
    if ((int) $version['version_id'] === (int) $versionSeleccionada) {
        $versionActual = $version;
        break;
    }
}

if ($versionActual === null && !empty($versiones)) {
    // Seleccionar la primera versión visible si no se indicó una válida.
    $versionActual = $versiones[0];
    $versionSeleccionada = (int) $versionActual['version_id'];
}

/* -----------------------------------------------------------------------
 * 5. Procesar las operaciones POST
 * -------------------------------------------------------------------- */

$mensaje = '';
$tipoMensaje = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF antes de procesar cualquier modificación.
    $csrf->validarRequest();

    $accion = $_POST['accion'] ?? '';

    // Nunca aceptar arreglos u otros tipos inesperados como identificadores.
    $versionPost = filter_var(
        $_POST['version_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$versionPost || $versionPost <= 0) {
        $mensaje = 'Selecciona una versión de formulario válida.';
        $tipoMensaje = 'error';
    } else {
        $versionSeleccionada = (int) $versionPost;

        /*
         * Verificar que la versión enviada por POST esté autorizada
         * para la empresa actual antes de realizar cualquier operación.
         */
        $versionAutorizada = null;

        foreach ($versiones as $version) {
            if ((int) $version['version_id'] === $versionSeleccionada) {
                $versionAutorizada = $version;
                break;
            }
        }

        if ($versionAutorizada === null) {
            $mensaje = 'La versión seleccionada no pertenece a esta empresa.';
            $tipoMensaje = 'error';
        } elseif ($accion === 'crear') {
            // Crear una pregunta nueva.
            $opcionesTexto = $_POST['opciones'] ?? '';

            if (!is_string($opcionesTexto)) {
                $opcionesTexto = '';
            }

            // Cada línea del campo de opciones se convierte en una opción.
            $opciones = preg_split('/\r\n|\r|\n/', $opcionesTexto) ?: [];

            $datos = [
                'question_text' => $_POST['question_text'] ?? '',
                'help_text' => $_POST['help_text'] ?? '',
                'answer_type' => $_POST['answer_type'] ?? 'YES_NO',
                'options' => $opciones,
                'weight' => $_POST['weight'] ?? '1',
                'is_critical' => isset($_POST['is_critical']),
                'required_answer' => isset($_POST['required_answer']),
                'allow_na' => isset($_POST['allow_na']),
                'na_score' => $_POST['na_score'] ?? null
            ];

            $resultado = $controller->crearPregunta(
                $tenantId,
                $versionSeleccionada,
                $datos
            );

            $mensaje = $resultado['mensaje'] ?? 'No se pudo procesar la solicitud.';
            $tipoMensaje = !empty($resultado['ok']) ? 'success' : 'error';
        } elseif ($accion === 'cambiar_estado') {
            // Cambiar estado lógico de una pregunta, sin borrarla.
            $preguntaId = filter_var(
                $_POST['pregunta_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $estadoSolicitado = $_POST['nuevo_estado'] ?? '';

            if (
                !$preguntaId
                || $preguntaId <= 0
                || !in_array($estadoSolicitado, ['0', '1', 0, 1], true)
            ) {
                $mensaje = 'Los datos para cambiar el estado no son válidos.';
                $tipoMensaje = 'error';
            } else {
                $resultado = $controller->cambiarEstado(
                    $tenantId,
                    $versionSeleccionada,
                    (int) $preguntaId,
                    (string) $estadoSolicitado === '1'
                );

                $mensaje = $resultado['mensaje'] ?? 'No se pudo cambiar el estado.';
                $tipoMensaje = !empty($resultado['ok']) ? 'success' : 'error';
            }
        } else {
            $mensaje = 'La operación solicitada no es válida.';
            $tipoMensaje = 'error';
        }
    }
}

/* -----------------------------------------------------------------------
 * 6. Cargar preguntas para la versión seleccionada
 * -------------------------------------------------------------------- */

$preguntas = [];

if ($versionActual !== null) {
    $preguntas = $controller->listarPreguntas(
        $tenantId,
        (int) $versionActual['version_id']
    );
}

/*
 * La interfaz también deshabilita acciones cuando la versión no es DRAFT.
 * Esta condición es visual; el modelo sigue siendo la barrera definitiva.
 */
$esBorrador = (
    $versionActual !== null
    && $versionActual['version_status'] === 'DRAFT'
);

/* -----------------------------------------------------------------------
 * 7. Renderizar interfaz
 * -------------------------------------------------------------------- */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Preguntas de auditoría | CallAudit</title>

    <style>
        /* Estilos básicos autónomos para no depender de archivos CSS externos. */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 24px;
            color: #222;
            background: #f5f6f8;
        }

        .contenedor {
            max-width: 1200px;
            margin: 0 auto;
        }

        .panel {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        h1, h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin: 12px 0 5px;
            font-weight: bold;
        }

        input, select, textarea, button {
            box-sizing: border-box;
            max-width: 100%;
            padding: 9px;
            font: inherit;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
        }

        textarea {
            min-height: 80px;
        }

        .fila {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .casilla {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .casilla label {
            display: inline;
            margin: 0;
            font-weight: normal;
        }

        .boton {
            margin-top: 16px;
            padding: 10px 16px;
            cursor: pointer;
            border: 1px solid #777;
            border-radius: 5px;
            background: #eee;
        }

        .mensaje {
            padding: 12px;
            margin-bottom: 16px;
            border-radius: 5px;
        }

        .mensaje.success {
            background: #e6f4ea;
            color: #175c2c;
        }

        .mensaje.error {
            background: #fde8e7;
            color: #8a1c16;
        }

        .mensaje.info {
            background: #e8f0fe;
            color: #174ea6;
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
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f0f1f3;
        }

        .estado {
            font-weight: bold;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .fila {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="contenedor">

    <h1>Preguntas y criterios de auditoría</h1>

    <p>
        Configura las preguntas de cada formulario. Las versiones publicadas
        se conservan sin cambios para proteger el historial de auditoría.
    </p>

    <p>
        <a href="audit_forms.php">Volver a formularios de auditoría</a>
    </p>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?= auditQuestionEscape($tipoMensaje) ?>">
            <?= auditQuestionEscape($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Selección de formulario y versión -->
    <section class="panel">
        <h2>Seleccionar formulario</h2>

        <?php if (empty($versiones)): ?>
            <p>No hay versiones de formularios disponibles para esta empresa.</p>
            <p>Primero crea un formulario desde la pantalla de formularios.</p>
        <?php else: ?>
            <form method="get" action="audit_form_questions.php">
                <label for="version_id">Formulario y versión</label>

                <select name="version_id" id="version_id" required>
                    <?php foreach ($versiones as $version): ?>
                        <option
                            value="<?= (int) $version['version_id'] ?>"
                            <?= (int) $versionSeleccionada === (int) $version['version_id'] ? 'selected' : '' ?>
                        >
                            <?= auditQuestionEscape($version['form_name']) ?>
                            — v<?= (int) $version['version_number'] ?>
                            — <?= auditQuestionEscape($version['version_status']) ?>
                            — <?= (int) $version['total_preguntas'] ?> pregunta(s)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="boton" type="submit">Abrir versión</button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($versionActual !== null): ?>

        <section class="panel">
            <h2>
                <?= auditQuestionEscape($versionActual['form_name']) ?>
                — Versión <?= (int) $versionActual['version_number'] ?>
            </h2>

            <p>
                Estado:
                <span class="estado">
                    <?= auditQuestionEscape($versionActual['version_status']) ?>
                </span>
            </p>

            <?php if (!$esBorrador): ?>
                <div class="mensaje info">
                    Esta versión no está en borrador. Puedes consultar sus
                    preguntas, pero no agregar, activar ni desactivar criterios.
                </div>
            <?php elseif (!$puedeCrear): ?>
                <div class="mensaje info">
                    Tu usuario no tiene el permiso necesario para crear preguntas.
                </div>
            <?php else: ?>

                <!-- Formulario para crear una pregunta -->
                <h2>Agregar pregunta</h2>

                <form method="post" action="audit_form_questions.php?version_id=<?= (int) $versionSeleccionada ?>">
                    <input type="hidden" name="csrf_token" value="<?= auditQuestionEscape($csrfToken) ?>">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="version_id" value="<?= (int) $versionSeleccionada ?>">

                    <label for="question_text">Pregunta o criterio</label>
                    <textarea
                        id="question_text"
                        name="question_text"
                        maxlength="1000"
                        required
                        placeholder="Ejemplo: ¿El agente se identificó correctamente?"
                    ></textarea>

                    <label for="help_text">Instrucciones o descripción (opcional)</label>
                    <textarea
                        id="help_text"
                        name="help_text"
                        placeholder="Explica cómo debe evaluarse este criterio."
                    ></textarea>

                    <div class="fila">
                        <div>
                            <label for="answer_type">Tipo de respuesta</label>
                            <select name="answer_type" id="answer_type" required>
                                <option value="YES_NO">Sí / No</option>
                                <option value="SCALE">Escala numérica</option>
                                <option value="SINGLE_CHOICE">Selección única</option>
                                <option value="MULTIPLE_CHOICE">Selección múltiple</option>
                                <option value="TEXT">Texto libre</option>
                                <option value="NUMBER">Número</option>
                            </select>
                        </div>

                        <div>
                            <label for="weight">Ponderación (peso)</label>
                            <input
                                type="number"
                                id="weight"
                                name="weight"
                                min="0.01"
                                max="999999.99"
                                step="0.01"
                                value="1.00"
                                required
                            >
                        </div>
                    </div>

                    <div id="opciones-contenedor">
                        <label for="opciones">
                            Opciones (solo para selección única o múltiple)
                        </label>
                        <textarea
                            id="opciones"
                            name="opciones"
                            placeholder="Una opción por línea&#10;Ejemplo:&#10;Correcto&#10;Incorrecto&#10;No corresponde"
                        ></textarea>
                    </div>

                    <div class="casilla">
                        <input
                            type="checkbox"
                            id="is_critical"
                            name="is_critical"
                            value="1"
                        >
                        <label for="is_critical">Es un criterio crítico</label>
                    </div>

                    <div class="casilla">
                        <input
                            type="checkbox"
                            id="required_answer"
                            name="required_answer"
                            value="1"
                            checked
                        >
                        <label for="required_answer">La respuesta es obligatoria</label>
                    </div>

                    <div class="casilla">
                        <input
                            type="checkbox"
                            id="allow_na"
                            name="allow_na"
                            value="1"
                        >
                        <label for="allow_na">Permitir «No aplica»</label>
                    </div>

                    <label for="na_score">Puntuación de «No aplica» (opcional)</label>
                    <input
                        type="number"
                        id="na_score"
                        name="na_score"
                        min="0"
                        max="999999.99"
                        step="0.01"
                        placeholder="Dejar vacío para usar la política de la versión"
                    >

                    <button class="boton" type="submit">Guardar pregunta</button>
                </form>

            <?php endif; ?>
        </section>

        <!-- Tabla de preguntas existentes -->
        <section class="panel">
            <h2>Preguntas configuradas</h2>

            <?php if (empty($preguntas)): ?>
                <p>Esta versión todavía no tiene preguntas.</p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                        <tr>
                            <th>Posición</th>
                            <th>Pregunta</th>
                            <th>Tipo</th>
                            <th>Peso</th>
                            <th>Crítica</th>
                            <th>Obligatoria</th>
                            <th>No aplica</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($preguntas as $pregunta): ?>
                            <tr>
                                <td><?= (int) $pregunta['position'] ?></td>

                                <td>
                                    <strong>
                                        <?= auditQuestionEscape($pregunta['question_text']) ?>
                                    </strong>

                                    <?php if (!empty($pregunta['help_text'])): ?>
                                        <p><?= nl2br(auditQuestionEscape($pregunta['help_text'])) ?></p>
                                    <?php endif; ?>

                                    <?php
                                    // Decodificar las opciones sin confiar en JSON almacenado.
                                    $opcionesPregunta = [];

                                    if (is_string($pregunta['options_json'] ?? null)) {
                                        $decodificadas = json_decode(
                                            $pregunta['options_json'],
                                            true
                                        );

                                        if (is_array($decodificadas)) {
                                            $opcionesPregunta = $decodificadas;
                                        }
                                    }
                                    ?>

                                    <?php if (!empty($opcionesPregunta)): ?>
                                        <small>
                                            Opciones:
                                            <?= auditQuestionEscape(implode(', ', array_map('strval', $opcionesPregunta))) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td><?= auditQuestionEscape($pregunta['answer_type']) ?></td>
                                <td><?= auditQuestionEscape($pregunta['weight']) ?></td>
                                <td><?= (int) $pregunta['is_critical'] === 1 ? 'Sí' : 'No' ?></td>
                                <td><?= (int) $pregunta['required_answer'] === 1 ? 'Sí' : 'No' ?></td>
                                <td>
                                    <?= (int) $pregunta['allow_na'] === 1 ? 'Sí' : 'No' ?>
                                    <?php if ($pregunta['na_score'] !== null): ?>
                                        (<?= auditQuestionEscape($pregunta['na_score']) ?>)
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (int) $pregunta['active'] === 1 ? 'Activa' : 'Inactiva' ?>
                                </td>
                                <td>
                                    <?php if ($esBorrador && $puedeEditar): ?>
                                        <form
                                            method="post"
                                            action="audit_form_questions.php?version_id=<?= (int) $versionSeleccionada ?>"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= auditQuestionEscape($csrfToken) ?>"
                                            >
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input
                                                type="hidden"
                                                name="version_id"
                                                value="<?= (int) $versionSeleccionada ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="pregunta_id"
                                                value="<?= (int) $pregunta['id'] ?>"
                                            >

                                            <?php if ((int) $pregunta['active'] === 1): ?>
                                                <input type="hidden" name="nuevo_estado" value="0">
                                                <button class="boton" type="submit">
                                                    Desactivar
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="nuevo_estado" value="1">
                                                <button class="boton" type="submit">
                                                    Activar
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span>Solo consulta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>

</div>

<script>
/*
 * Mostrar el campo de opciones únicamente cuando el tipo de respuesta
 * lo necesita. La validación definitiva se realiza también en PHP.
 */
(function () {
    const tipo = document.getElementById('answer_type');
    const contenedor = document.getElementById('opciones-contenedor');
    const opciones = document.getElementById('opciones');

    if (!tipo || !contenedor || !opciones) {
        return;
    }

    function actualizarOpciones() {
        const requiereOpciones = [
            'SINGLE_CHOICE',
            'MULTIPLE_CHOICE'
        ].includes(tipo.value);

        contenedor.hidden = !requiereOpciones;
        opciones.required = requiereOpciones;
    }

    tipo.addEventListener('change', actualizarOpciones);
    actualizarOpciones();
})();
</script>
</body>
</html>
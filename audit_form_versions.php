<?php
/**
 * ============================================================================
 * ARCHIVO: audit_form_versions.php
 * PROYECTO: CallAudit
 *
 * Pantalla para administrar versiones de formularios de auditoría.
 *
 * Funciones:
 * - Listar versiones y cantidad de preguntas.
 * - Crear un borrador a partir de una versión existente.
 * - Publicar borradores.
 * - Configurar umbral de aprobación y política N/A.
 *
 * Seguridad:
 * - Sesión autenticada.
 * - Contexto de empresa resuelto por CampaignTenantContext.
 * - Token CSRF para operaciones POST.
 * - Permisos existentes y validación adicional en el controlador/modelo.
 * ============================================================================
 */

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/CampaignTenantContext.php';
require_once __DIR__ . '/services/Authorization.php';
require_once __DIR__ . '/controllers/AuditFormVersionController.php';

$auth = new AuthMiddleware();
$auth->proteger();

$usuario = $auth->usuario();

if (!$usuario) {
    header('Location: login.php');
    exit;
}

// Resuelve el tenant de la sesión empresarial o del contexto SUPERADMIN.
$contexto = new CampaignTenantContext();
$tenantId = $contexto->resolver($usuario);

$csrf = new CsrfService();
$csrfToken = $csrf->token();

$controller = new AuditFormVersionController();
$authorization = new Authorization();

$usuarioId = (int) $usuario['id'];
$puedeCrear = $authorization->tienePermiso(
    $usuarioId,
    'audits.create'
);
$puedeEditar = $authorization->tienePermiso(
    $usuarioId,
    'audits.edit'
);

/** Escapa valores antes de mostrarlos en HTML. */
function auditVersionEscape(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
 * Procesamiento de operaciones.
 * Todas las modificaciones usan POST y validación CSRF.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();

    $accion = $_POST['accion'] ?? '';

    if (!is_string($accion)) {
        $accion = '';
    }

    $resultado = [
        'success' => false,
        'message' => 'La operación solicitada no es válida.'
    ];

    if ($accion === 'crear_borrador') {
        $origenId = filter_var(
            $_POST['version_origen_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$origenId || $origenId <= 0) {
            $resultado['message'] = 'Selecciona una versión de origen válida.';
        } else {
            $resultado = $controller->crearBorrador(
                $tenantId,
                (int) $origenId
            );
        }
    } elseif ($accion === 'publicar') {
        $versionId = filter_var(
            $_POST['version_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $naPolicy = $_POST['na_policy'] ?? 'EXCLUDE';
        $scoreTexto = $_POST['passing_score'] ?? '';

        if (!is_string($naPolicy) || !is_string($scoreTexto)) {
            $resultado['message'] = 'La configuración enviada no es válida.';
        } elseif (!$versionId || $versionId <= 0) {
            $resultado['message'] = 'La versión seleccionada no es válida.';
        } else {
            $passingScore = null;

            if (trim($scoreTexto) !== '') {
                if (!is_numeric($scoreTexto)) {
                    $resultado['message'] =
                        'El puntaje de aprobación debe ser numérico.';
                    $versionId = 0;
                } else {
                    $passingScore = (float) $scoreTexto;
                }
            }

            if ($versionId > 0) {
                $resultado = $controller->publicar(
                    $tenantId,
                    (int) $versionId,
                    $passingScore,
                    $naPolicy
                );
            }
        }
    }

    // Patrón POST-Redirect-GET para evitar repetir la operación al recargar.
    $_SESSION['audit_version_flash'] = [
        'success' => (bool) ($resultado['success'] ?? false),
        'message' => (string) ($resultado['message'] ?? 'Operación finalizada.')
    ];

    header('Location: audit_form_versions.php');
    exit;
}

// Leer el mensaje de la última operación, si existe.
$flash = $_SESSION['audit_version_flash'] ?? null;
unset($_SESSION['audit_version_flash']);

// Consultar versiones para la empresa autorizada.
$versiones = $controller->listar($tenantId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Versiones de formularios | CallAudit</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
            background: #f5f6f8;
        }

        h1, h2 { margin-top: 0; }

        .panel {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .mensaje {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 16px;
        }

        .correcto { background: #e6f5e9; color: #205b2c; }
        .error { background: #fdeaea; color: #8b2020; }

        .tabla-contenedor { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            text-align: left;
            border-bottom: 1px solid #ddd;
            padding: 10px;
            vertical-align: top;
        }

        th { background: #f0f2f4; }

        input, select, button {
            padding: 8px;
            margin: 3px 0;
            max-width: 100%;
        }

        button {
            cursor: pointer;
            border: 1px solid #888;
            border-radius: 4px;
            background: #f7f7f7;
        }

        .acciones {
            min-width: 230px;
        }

        .estado {
            font-weight: bold;
        }

        .ayuda {
            color: #555;
            font-size: 0.9rem;
        }

        a { color: #0645ad; }
    </style>
</head>
<body>

    <h1>Versiones de formularios de auditoría</h1>

    <p>
        <a href="audit_forms.php">Volver a formularios</a>
        |
        <a href="audit_form_questions.php">Gestionar preguntas</a>
    </p>

    <?php if (is_array($flash)): ?>
        <div class="mensaje <?= !empty($flash['success'])
            ? 'correcto' : 'error' ?>">
            <?= auditVersionEscape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>Crear una nueva versión</h2>

        <p class="ayuda">
            Se copiarán las preguntas de la versión seleccionada.
            La versión de origen no se modificará.
        </p>

        <?php if ($puedeCrear): ?>
            <form method="post" action="audit_form_versions.php">
                <input type="hidden" name="csrf_token"
                       value="<?= auditVersionEscape($csrfToken) ?>">
                <input type="hidden" name="accion"
                       value="crear_borrador">

                <label for="version_origen_id">
                    Versión de origen:
                </label>

                <select id="version_origen_id"
                        name="version_origen_id" required>
                    <option value="">Selecciona una versión</option>

                    <?php foreach ($versiones as $v): ?>
                        <option value="<?= (int) $v['id'] ?>">
                            <?= auditVersionEscape($v['form_name']) ?>
                            — v<?= (int) $v['version_number'] ?>
                            (<?= auditVersionEscape($v['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Crear borrador</button>
            </form>
        <?php else: ?>
            <p>No tienes permiso para crear versiones.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Versiones registradas</h2>

        <?php if (empty($versiones)): ?>
            <p>No hay versiones para esta empresa.</p>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>Formulario</th>
                            <th>Versión</th>
                            <th>Estado</th>
                            <th>Preguntas activas / total</th>
                            <th>Umbral</th>
                            <th>Política N/A</th>
                            <th>Publicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($versiones as $v): ?>
                        <tr>
                            <td>
                                <?= auditVersionEscape($v['form_name']) ?>
                            </td>

                            <td>
                                v<?= (int) $v['version_number'] ?>
                            </td>

                            <td class="estado">
                                <?= auditVersionEscape($v['status']) ?>
                            </td>

                            <td>
                                <?= (int) $v['active_questions'] ?>
                                /
                                <?= (int) $v['total_questions'] ?>
                            </td>

                            <td>
                                <?= $v['passing_score'] === null
                                    ? 'No definido'
                                    : auditVersionEscape($v['passing_score']) ?>
                            </td>

                            <td>
                                <?= auditVersionEscape($v['na_policy']) ?>
                            </td>

                            <td>
                                <?= auditVersionEscape($v['published_at'] ?? '—') ?>
                            </td>

                            <td class="acciones">
                                <a href="audit_form_questions.php?version_id=<?= (int) $v['id'] ?>">
                                    Ver preguntas
                                </a>

                                <?php if ($v['status'] === 'DRAFT' && $puedeEditar): ?>
                                    <hr>

                                    <form method="post"
                                          action="audit_form_versions.php">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= auditVersionEscape($csrfToken) ?>">
                                        <input type="hidden" name="accion"
                                            value="publicar">
                                        <input type="hidden" name="version_id"
                                            value="<?= (int) $v['id'] ?>">

                                        <label>
                                            Puntaje mínimo de aprobación
                                            (opcional, 0 a 100):
                                        </label>
                                        <input type="number"
                                            name="passing_score"
                                            min="0" max="100" step="0.01"
                                            placeholder="Sin umbral">

                                        <label>Tratamiento de N/A:</label>
                                        <select name="na_policy" required>
                                            <option value="EXCLUDE">
                                                Excluir del cálculo
                                            </option>
                                            <option value="ZERO">
                                                Contar como cero
                                            </option>
                                            <option value="FULL">
                                                Contar como puntaje completo
                                            </option>
                                        </select>

                                        <button type="submit"
                                            onclick="return confirm(
                                                '¿Publicar esta versión? Después no podrás modificarla.'
                                            );">
                                            Publicar versión
                                        </button>
                                    </form>
                                <?php elseif ($v['status'] === 'DRAFT'): ?>
                                    <p class="ayuda">
                                        No tienes permiso para publicar.
                                    </p>
                                <?php else: ?>
                                    <p class="ayuda">
                                        Versión protegida.
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</body>
</html>

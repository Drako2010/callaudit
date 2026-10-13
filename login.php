<?php

// Cargar los servicios necesarios para autenticación y seguridad.
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/SessionService.php';

// Crear las instancias de los servicios.
$auth = new AuthService();
$session = new SessionService();
$csrf = new CsrfService();

// Variables utilizadas por la vista.
$message = '';
$loginPendiente = null;

// Redirigir según el ámbito de una sesión ya autenticada.
if ($session->estaAutenticado()) {
    $usuarioSesion = $session->obtenerUsuario();

    if (($usuarioSesion['scope'] ?? '') === 'GLOBAL') {
        header('Location: global_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }

    exit;
}

// Procesar los formularios enviados por POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Comprobar el token CSRF antes de procesar cualquier acción.
    $csrf->validarRequest();

    $accion = $_POST['accion'] ?? 'autenticar';

    /*
     * ACCIÓN 1:
     * Cancelar la selección de empresa.
     */
    if ($accion === 'cancelar_seleccion') {

        $session->limpiarLoginPendiente();

        header('Location: login.php');
        exit;
    }

    /*
     * ACCIÓN 2:
     * Seleccionar una de las cuentas previamente validadas.
     */
    if ($accion === 'seleccionar_empresa') {

        $empresasPendientes = $session->obtenerLoginPendiente();
        $usuarioId = filter_var(
            $_POST['usuario_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        // La selección solo es válida si existe una lista pendiente.
        if ($empresasPendientes === null || $usuarioId === false) {

            $session->limpiarLoginPendiente();

            $message = 'La selección ha caducado. Inicia sesión nuevamente.';

        } else {

            $usuarioPermitido = false;

            // Verificar que el identificador esté en la lista del servidor.
            foreach ($empresasPendientes as $empresa) {
                if ((int) $empresa['id'] === (int) $usuarioId) {
                    $usuarioPermitido = true;
                    break;
                }
            }

            if (!$usuarioPermitido) {

                $session->limpiarLoginPendiente();

                $message = 'La empresa seleccionada no es válida. Inicia sesión nuevamente.';

            } else {

                /*
                 * Volver a consultar la base de datos.
                 * No confiar en el nombre ni en el tenant enviados
                 * por el navegador.
                 */
                $usuario = $auth->obtenerUsuarioEmpresaActivo(
                    (int) $usuarioId
                );

                if ($usuario === null) {

                    $session->limpiarLoginPendiente();

                    $message = 'La cuenta seleccionada ya no está disponible. Inicia sesión nuevamente.';

                } else {

                    // Eliminar la selección pendiente antes de iniciar sesión.
                    $session->limpiarLoginPendiente();

                    $session->iniciarSesion($usuario);

                    header('Location: dashboard.php');
                    exit;
                }
            }
        }
    }

    /*
     * ACCIÓN 3:
     * Autenticar con correo electrónico y contraseña.
     */
    elseif ($accion === 'autenticar') {

        // Limpiar cualquier selección anterior.
        $session->limpiarLoginPendiente();

        // Solo se recorta el correo; la contraseña se conserva intacta.
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {

            $message = 'El correo electrónico y la contraseña son obligatorios.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $message = 'El correo electrónico no es válido.';

        } else {

            // El servicio comprueba las credenciales y las cuentas válidas.
            $resultado = $auth->autenticar($email, $password);

            if ($resultado === null) {

                $message = 'Correo electrónico o contraseña incorrectos.';

            } elseif (($resultado['tipo'] ?? '') === 'GLOBAL') {

                // El acceso global debe tener un panel separado.
                $usuario = $resultado['usuario'];

                $session->iniciarSesion($usuario);

                header('Location: global_dashboard.php');
                exit;

            } elseif (($resultado['tipo'] ?? '') === 'EMPRESA_UNICA') {

                // Solo existe una cuenta de empresa válida.
                $usuario = $resultado['usuario'];

                $session->iniciarSesion($usuario);

                header('Location: dashboard.php');
                exit;

            } elseif (
                ($resultado['tipo'] ?? '') === 'SELECCIONAR_EMPRESA'
                && isset($resultado['empresas'])
                && is_array($resultado['empresas'])
            ) {

                // Guardar las cuentas válidas exclusivamente en el servidor.
                $session->guardarLoginPendiente(
                    $resultado['empresas']
                );

                // Confirmar que la lista se guardó correctamente.
                $loginPendiente = $session->obtenerLoginPendiente();

                if ($loginPendiente === null) {
                    $message = 'No se pudo preparar la selección. Inicia sesión nuevamente.';
                }

            } else {

                // No aceptar resultados de autenticación desconocidos.
                $message = 'No se pudo completar el inicio de sesión.';
            }
        }
    }

    /*
     * Rechazar cualquier acción desconocida.
     */
    elseif ($accion !== 'seleccionar_empresa') {

        $message = 'La solicitud no es válida.';
    }
}

// Si existe una selección pendiente, recuperarla para mostrar el formulario.
if ($loginPendiente === null) {
    $loginPendiente = $session->obtenerLoginPendiente();
}

// Función para escapar los datos que se muestran en HTML.
function escapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - CallAudit</title>
</head>
<body>

    <h1>CallAudit</h1>

    <?php if ($loginPendiente !== null): ?>

        <h2>Seleccionar empresa</h2>

        <p>
            Tu correo y contraseña son válidos para más de una empresa.
            Selecciona con qué empresa deseas ingresar.
        </p>

        <?php if ($message !== ''): ?>
            <p>
                <strong><?= escapar($message) ?></strong>
            </p>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar($csrf->token()) ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="seleccionar_empresa"
            >

            <?php foreach ($loginPendiente as $empresa): ?>

                <div>
                    <label>
                        <input
                            type="radio"
                            name="usuario_id"
                            value="<?= (int) $empresa['id'] ?>"
                            required
                        >

                        <strong>
                            <?= escapar($empresa['tenant_name']) ?>
                        </strong>

                        — <?= escapar($empresa['name']) ?>
                    </label>
                </div>

                <br>

            <?php endforeach; ?>

            <button type="submit">
                Continuar
            </button>

        </form>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar($csrf->token()) ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="cancelar_seleccion"
            >

            <button type="submit">
                Cancelar
            </button>

        </form>

    <?php else: ?>

        <h2>Iniciar sesión</h2>

        <?php if ($message !== ''): ?>
            <p>
                <strong><?= escapar($message) ?></strong>
            </p>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar($csrf->token()) ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="autenticar"
            >

            <div>
                <label for="email">
                    Correo electrónico:
                </label>

                <br>

                <input
                    type="email"
                    name="email"
                    id="email"
                    value="<?= escapar($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="username"
                >
            </div>

            <br>

            <div>
                <label for="password">
                    Contraseña:
                </label>

                <br>

                <input
                    type="password"
                    name="password"
                    id="password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <br>

            <button type="submit">
                Iniciar sesión
            </button>

        </form>

    <?php endif; ?>

</body>
</html>
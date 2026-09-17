<?php

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/SessionService.php';

$auth = new AuthService();
$session = new SessionService();

if ($session->estaAutenticado()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $message = 'El correo electrónico y la contraseña son obligatorios.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'El correo electrónico no es válido.';

    } else {

        $user = $auth->autenticar(
            $email,
            $password
        );

        if ($user === null) {

            $message = 'Correo electrónico o contraseña incorrectos.';

        } else {

            $session->iniciarSesion($user);

            header('Location: dashboard.php');
            exit;
        }
    }
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

    <h2>Iniciar sesión</h2>

    <?php if ($message !== ''): ?>

        <p>
            <strong>
                <?= htmlspecialchars($message) ?>
            </strong>
        </p>

    <?php endif; ?>

    <form method="POST">

        <div>
            <label for="email">
                Correo electrónico:
            </label>

            <br>

            <input
                type="email"
                name="email"
                id="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
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

</body>
</html>
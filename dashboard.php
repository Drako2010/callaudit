<?php
require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php'; // Token

$middleware = new AuthMiddleware();

$middleware->requierePermiso('dashboard.view');

$usuario = $middleware->usuario();
$csrf = new CsrfService();  // Token
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CallAudit</title>
</head>
<body>

    <h1>CallAudit</h1>

    <h2>Dashboard</h2>

    <?php if ($usuario !== null): ?>

        <p>
            Bienvenido,
            <strong>
                <?= htmlspecialchars($usuario['name']) ?>
            </strong>
        </p>

        <hr>

        <p>
            <strong>ID de usuario:</strong>
            <?= htmlspecialchars((string) $usuario['id']) ?>
        </p>

        <p>
            <strong>Correo:</strong>
            <?= htmlspecialchars($usuario['email']) ?>
        </p>

        <p>
            <strong>Tenant ID:</strong>
            <?= htmlspecialchars(
                $usuario['tenant_id'] !== null
                    ? (string) $usuario['tenant_id']
                    : 'NULL'
            ) ?>
        </p>

        <p>
            <strong>Estado:</strong>
            <?= htmlspecialchars($usuario['status']) ?>
        </p>

        <hr>

        <p>
            <form method="POST" action="logout.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf->token()) ?>"
                >

                <button type="submit">
                    Cerrar sesión
                </button>

            </form>
        </p>

    <?php endif; ?>

</body>
</html>
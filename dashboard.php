<?php

require_once __DIR__ . '/services/AuthMiddleware.php';

$middleware = new AuthMiddleware();

$middleware->requierePermiso('dashboard.view');

$usuario = $middleware->usuario();

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
            <a href="logout.php">Cerrar sesión</a>
        </p>

    <?php endif; ?>

</body>
</html>
<?php

// Cargar los servicios de autenticación y protección.
require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/CsrfService.php';

// Crear las instancias.
$middleware = new AuthMiddleware();
$csrf = new CsrfService();

// Exigir que exista una sesión autenticada.
$middleware->proteger();

// Recuperar los datos del usuario desde la sesión del servidor.
$usuario = $middleware->usuario();

// Si no existe un usuario válido, volver al login.
if ($usuario === null) {
    header('Location: login.php');
    exit;
}

// El panel de empresa no debe utilizarse para el acceso global.
if (($usuario['scope'] ?? '') === 'GLOBAL') {
    header('Location: global_dashboard.php');
    exit;
}

// Rechazar sesiones que no correspondan al ámbito de una empresa.
if (
    ($usuario['scope'] ?? '') !== 'TENANT'
    || $usuario['tenant_id'] === null
) {
    http_response_code(403);
    exit('ACCESO DENEGADO');
}

// Verificar el permiso requerido para acceder al panel.
$middleware->requierePermiso('dashboard.view');
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
    <h2>Panel de empresa</h2>

    <p>
        Bienvenido,
        <strong>
            <?= htmlspecialchars(
                $usuario['name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

    <hr>

    <p>
        <strong>ID de usuario:</strong>
        <?= (int) $usuario['id'] ?>
    </p>

    <p>
        <strong>Correo:</strong>
        <?= htmlspecialchars(
            $usuario['email'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        <strong>Tenant ID:</strong>
        <?= (int) $usuario['tenant_id'] ?>
    </p>

    <p>
        <strong>Estado:</strong>
        <?= htmlspecialchars(
            $usuario['status'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <hr>

    <!-- Cerrar sesión mediante POST protegido por CSRF. -->
    <form method="POST" action="logout.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrf->token(),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <button type="submit">Cerrar sesión</button>

    </form>

</body>
</html>
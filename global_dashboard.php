<?php

// Cargar los servicios de autenticación y seguridad.
require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/CsrfService.php';

// Crear las instancias de los servicios.
$middleware = new AuthMiddleware();
$auth = new AuthService();
$csrf = new CsrfService();

// Verificar que exista una sesión autenticada.
$middleware->proteger();

// Obtener los datos de la sesión actual.
$usuario = $middleware->usuario();

// Si no existe un usuario válido, regresar al login.
if ($usuario === null) {
    header('Location: login.php');
    exit;
}

// El panel global no admite sesiones de empresa.
if (
    ($usuario['scope'] ?? '') !== 'GLOBAL'
    || $usuario['tenant_id'] !== null
) {
    header('Location: dashboard.php');
    exit;
}

// Volver a comprobar en la base de datos que sea SUPERADMIN activo.
if (!$auth->esSuperAdmin((int) $usuario['id'])) {
    http_response_code(403);
    exit('ACCESO DENEGADO');
}

// Exigir el permiso de acceso al dashboard.
$middleware->requierePermiso('dashboard.view');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración global - CallAudit</title>
</head>
<body>

    <h1>CallAudit</h1>
    <h2>Administración global</h2>

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

    <p>
        Este panel está destinado a la administración global
        de la plataforma CallAudit.
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
        <strong>Ámbito:</strong>
        GLOBAL
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
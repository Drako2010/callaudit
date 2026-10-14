<?php

/*
|--------------------------------------------------------------------------
| Archivo: campaign_tenant.php
|--------------------------------------------------------------------------
| Selector de empresa para el módulo Campañas.
| Solo puede utilizarlo un SUPERADMIN global activo.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/services/AuthMiddleware.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/CsrfService.php';
require_once __DIR__ . '/models/Tenant.php';

$middleware = new AuthMiddleware();
$authService = new AuthService();
$csrf = new CsrfService();
$tenantModel = new Tenant();

// Exigir sesión autenticada.
$middleware->proteger();
$usuario = $middleware->usuario();

if ($usuario === null) {
    header('Location: login.php');
    exit;
}

// No basta con tener tenant_id NULL: debe ser SUPERADMIN real.
if (
    $usuario['tenant_id'] !== null
    || !$authService->esSuperAdmin((int) $usuario['id'])
) {
    http_response_code(403);
    exit('Acceso reservado al SUPERADMIN global.');
}

$message = '';

/*
 * Procesar la selección.
 * El ID recibido se valida contra la base de datos; nunca se utiliza
 * directamente como autorización.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf->validarRequest();

    $tenantRaw = $_POST['tenant_id'] ?? null;

    $tenantId = (
        is_string($tenantRaw) && ctype_digit($tenantRaw)
    ) ? (int) $tenantRaw : 0;

    if ($tenantId <= 0) {
        $message = 'Selecciona una empresa válida.';
    } else {
        $empresa = $tenantModel->obtenerPorId($tenantId);

        if ($empresa === null || $empresa['status'] !== 'ACTIVE') {
            $message = 'La empresa no existe o no está activa.';
        } else {
            /*
             * Guardar solo el identificador validado en la sesión.
             * El contexto volverá a verificarlo en cada página.
             */
            $_SESSION['campaign_tenant_id'] = $tenantId;

            header('Location: campaigns.php');
            exit;
        }
    }
}

// Mostrar solamente empresas activas.
$tenants = array_values(array_filter(
    $tenantModel->listar(),
    static function (array $tenant): bool {
        return ($tenant['status'] ?? '') === 'ACTIVE';
    }
));

$csrfToken = htmlspecialchars(
    $csrf->token(),
    ENT_QUOTES,
    'UTF-8'
);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar empresa - CallAudit</title>
</head>
<body>

    <h1>Campañas</h1>
    <h2>Seleccionar empresa</h2>

    <p>
        Estás accediendo con una cuenta SUPERADMIN global.
        Selecciona la empresa cuyas campañas necesitas administrar.
    </p>

    <?php if ($message !== ''): ?>
        <p role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if (empty($tenants)): ?>

        <p>No hay empresas activas disponibles.</p>

    <?php else: ?>

        <form method="POST" action="campaign_tenant.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= $csrfToken ?>"
            >

            <label for="tenant_id">Empresa:</label>

            <select id="tenant_id" name="tenant_id" required>
                <option value="">-- Selecciona una empresa --</option>

                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= (int) $tenant['id'] ?>">
                        <?= htmlspecialchars(
                            $tenant['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Continuar a campañas</button>

        </form>

    <?php endif; ?>

    <p>
        <a href="global_dashboard.php">Volver al panel global</a>
    </p>

</body>
</html>
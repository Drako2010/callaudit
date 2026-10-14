
<?php

/*
|--------------------------------------------------------------------------
| Archivo: views/campaigns/index.php
|--------------------------------------------------------------------------
| Vista del listado de campañas.
|
| Responsabilidades:
| - Mostrar las campañas recibidas desde campaigns.php.
| - Mostrar únicamente las acciones permitidas al usuario.
| - Incluir el token CSRF en los formularios que cambian el estado.
|
| IMPORTANTE:
| Ocultar botones no sustituye la autorización del backend.
| campaign_create.php, campaign_edit.php y campaign_status.php
| deben seguir validando sus permisos de forma independiente.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../services/Authorization.php';
require_once __DIR__ . '/../../services/CsrfService.php';

/*
|--------------------------------------------------------------------------
| Servicios de autorización y protección CSRF
|--------------------------------------------------------------------------
*/

$authorization = new Authorization();
$csrf = new CsrfService();

/*
|--------------------------------------------------------------------------
| Verificar permisos efectivos del usuario
|--------------------------------------------------------------------------
| $usuario procede de campaigns.php, que ya exige autenticación
| y el permiso campaigns.view.
|
| No suponemos que un rol tiene permisos por su nombre.
| Authorization::tienePermiso() comprueba:
| - usuario activo;
| - DENY individual;
| - GRANT individual;
| - permisos heredados del rol;
| - denegación por defecto.
|--------------------------------------------------------------------------
*/

$userId = (int) $usuario['id'];

$puedeCrear = $authorization->tienePermiso(
    $userId,
    'campaigns.create'
);

$puedeEditar = $authorization->tienePermiso(
    $userId,
    'campaigns.edit'
);

/*
|--------------------------------------------------------------------------
| Preparar token CSRF
|--------------------------------------------------------------------------
| El mismo token puede utilizarse en los formularios de esta página.
| El servidor lo validará al recibir cada POST.
|--------------------------------------------------------------------------
*/

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

    <title>CallAudit - Campañas</title>
</head>

<body>

    <h1>Campañas</h1>

    <!--
        Mostrar la opción de crear solamente cuando el usuario
        tenga efectivamente campaigns.create.
    -->
    <?php if ($puedeCrear): ?>

        <p>
            <a href="campaign_create.php">
                Crear campaña
            </a>
        </p>

    <?php endif; ?>

    <?php if (empty($campaigns)): ?>

        <p>
            No existen campañas registradas.
        </p>

    <?php else: ?>

        <table border="1" cellpadding="8" cellspacing="0">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Fecha inicio</th>
                    <th>Fecha fin</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($campaigns as $campaign): ?>

                    <tr>

                        <td>
                            <?= (int) $campaign['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['slug'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['description'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['start_date'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['end_date'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $campaign['created_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>

                            <?php if ($puedeEditar): ?>

                                <!--
                                    La edición requiere campaigns.edit.
                                    campaign_edit.php también valida
                                    el permiso en el backend.
                                -->
                                <a href="campaign_edit.php?id=<?= (int) $campaign['id'] ?>">
                                    Editar
                                </a>

                                |

                                <?php if ($campaign['status'] === 'ACTIVE'): ?>

                                    <!--
                                        Desactivar mediante POST.
                                        Incluimos el token CSRF obligatorio.
                                    -->
                                    <form
                                        method="POST"
                                        action="campaign_status.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('¿Deseas desactivar esta campaña?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= $csrfToken ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $campaign['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="INACTIVE"
                                        >

                                        <button type="submit">
                                            Desactivar
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <!--
                                        Activar mediante POST.
                                        Incluimos el token CSRF obligatorio.
                                    -->
                                    <form
                                        method="POST"
                                        action="campaign_status.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('¿Deseas activar esta campaña?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= $csrfToken ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $campaign['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="ACTIVE"
                                        >

                                        <button type="submit">
                                            Activar
                                        </button>

                                    </form>

                                <?php endif; ?>

                            <?php else: ?>

                                <!--
                                    Sin campaigns.edit no mostramos
                                    acciones de edición ni de estado.
                                    El permiso no se concede aquí.
                                -->
                                <span>Sin acciones de edición</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</body>
</html>

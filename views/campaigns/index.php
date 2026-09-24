<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CallAudit - Campañas</title>

</head>

<body>

    <h1>Campañas</h1>

    <p>
        <!--
            La creación de campañas se realiza mediante una página
            independiente. La autorización real se valida también
            en campaign_create.php y en el controlador.
        -->
        <a href="campaign_create.php">
            Crear campaña
        </a>
    </p>

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
                            <?= htmlspecialchars($campaign['id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['slug']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['description'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['start_date'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['end_date'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($campaign['created_at']) ?>
                        </td>

                        <td>

                            <!--
                                Editar solamente abre el formulario de edición.
                                Por eso se mantiene como GET.

                                La autorización para editar NO depende de
                                ocultar este enlace: campaign_edit.php y
                                CampaignController vuelven a validar
                                campaigns.edit.
                            -->
                            <a href="campaign_edit.php?id=<?= (int) $campaign['id'] ?>">
                                Editar
                            </a>

                            |

                            <?php if ($campaign['status'] === 'ACTIVE'): ?>

                                <!--
                                    Cambiar el estado modifica información
                                    en la base de datos.

                                    Por seguridad utilizamos POST y no GET.
                                    campaign_status.php también exige POST.
                                -->
                                <form
                                    method="POST"
                                    action="campaign_status.php"
                                    style="display:inline;"
                                    onsubmit="return confirm('¿Deseas desactivar esta campaña?');"
                                >

                                    <!-- ID de la campaña que se desea modificar -->
                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $campaign['id'] ?>"
                                    >

                                    <!-- Nuevo estado solicitado -->
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
                                    Misma lógica para activar una campaña
                                    actualmente inactiva.
                                -->
                                <form
                                    method="POST"
                                    action="campaign_status.php"
                                    style="display:inline;"
                                    onsubmit="return confirm('¿Deseas activar esta campaña?');"
                                >

                                    <!-- ID de la campaña que se desea modificar -->
                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $campaign['id'] ?>"
                                    >

                                    <!-- Nuevo estado solicitado -->
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

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</body>

</html>
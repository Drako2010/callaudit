<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CallAudit - Empresas</title>

</head>

<body>

    <h1>Empresas</h1>

    <table border="1" cellpadding="8" cellspacing="0">

        <thead>

            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Slug</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Actualizado</th>
            </tr>

        </thead>

        <tbody>

            <?php foreach ($tenants as $tenant): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($tenant['id']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tenant['name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tenant['slug']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tenant['status']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tenant['created_at']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tenant['updated_at']) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</body>

</html>
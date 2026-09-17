<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios - CallAudit</title>
</head>
<body>

    <h1>Usuarios</h1>

    <p>
        <a href="user_create.php">Crear usuario</a>
    </p>

    <?php if (empty($users)): ?>

        <p>No existen usuarios registrados.</p>

    <?php else: ?>

        <table border="1" cellpadding="8" cellspacing="0">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Fecha creación</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($users as $user): ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars($user['id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['tenant_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['created_at']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</body>
</html>
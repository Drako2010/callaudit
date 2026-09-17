<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear usuario - CallAudit</title>
</head>
<body>

    <h1>Crear usuario</h1>

    <?php if ($message !== ''): ?>

        <p>
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="">

        <div>
            <label for="tenant_id">Empresa:</label>

            <select name="tenant_id" id="tenant_id" required>

                <option value="">Seleccione una empresa</option>

                <?php foreach ($tenants as $tenant): ?>

                    <option
                        value="<?= htmlspecialchars($tenant['id']) ?>"
                        <?= (
                            isset($_POST['tenant_id'])
                            && $_POST['tenant_id'] == $tenant['id']
                        ) ? 'selected' : ''
                        ?>
                    >
                        <?= htmlspecialchars($tenant['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <br>

        <div>
            <label for="name">Nombre:</label>

            <input
                type="text"
                name="name"
                id="name"
                maxlength="150"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                required
            >
        </div>

        <br>

        <div>
            <label for="email">Correo electrónico:</label>

            <input
                type="email"
                name="email"
                id="email"
                maxlength="150"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
            >
        </div>

        <br>

        <div>
            <label for="password">Contraseña:</label>

            <input
                type="password"
                name="password"
                id="password"
                minlength="8"
                required
            >
        </div>

        <br>

        <button type="submit">
            Crear usuario
        </button>

        <a href="users.php">
            Cancelar
        </a>

    </form>

</body>
</html>
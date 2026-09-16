<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CallAudit - Nueva empresa</title>

</head>

<body>

    <h1>Nueva empresa</h1>

    <?php if (!empty($message)): ?>

        <p>
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <div>

            <label for="name">
                Nombre de la empresa
            </label>

            <br>

            <input
                type="text"
                id="name"
                name="name"
                maxlength="150"
                required
            >

        </div>

        <br>

        <div>

            <label for="slug">
                Slug
            </label>

            <br>

            <input
                type="text"
                id="slug"
                name="slug"
                maxlength="150"
                required
            >

        </div>

        <br>

        <button type="submit">
            Crear empresa
        </button>

    </form>

    <br>

    <a href="tenants.php">
        Volver a empresas
    </a>

</body>

</html>
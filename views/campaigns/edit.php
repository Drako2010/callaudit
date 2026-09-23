<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>CallAudit - Editar campaña</title>

</head>

<body>

    <h1>Editar campaña</h1>

    <?php if (!empty($message)): ?>

        <p>
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <div>

            <label for="name">
                Nombre de la campaña
            </label>

            <br>

            <input
                type="text"
                id="name"
                name="name"
                maxlength="150"
                value="<?= htmlspecialchars($campaign['name']) ?>"
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
                value="<?= htmlspecialchars($campaign['slug']) ?>"
                required
            >

        </div>

        <br>

        <div>

            <label for="description">
                Descripción
            </label>

            <br>

            <textarea
                id="description"
                name="description"
                rows="5"
            ><?= htmlspecialchars($campaign['description'] ?? '') ?></textarea>

        </div>

        <br>

        <div>

            <label for="start_date">
                Fecha de inicio
            </label>

            <br>

            <input
                type="date"
                id="start_date"
                name="start_date"
                value="<?= htmlspecialchars($campaign['start_date'] ?? '') ?>"
            >

        </div>

        <br>

        <div>

            <label for="end_date">
                Fecha de fin
            </label>

            <br>

            <input
                type="date"
                id="end_date"
                name="end_date"
                value="<?= htmlspecialchars($campaign['end_date'] ?? '') ?>"
            >

        </div>

        <br>

        <button type="submit">
            Guardar cambios
        </button>

    </form>

    <br>

    <a href="campaigns.php">
        Volver a campañas
    </a>

</body>

</html>
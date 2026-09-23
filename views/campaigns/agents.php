<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>
        Agentes de campaña
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f7f7f7;
        }

        h1 {
            margin-bottom: 5px;
        }

        h2 {
            margin-top: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .campaign-info {
            background: #ffffff;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }

        .panel {
            background: #ffffff;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #ddd;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        select {
            width: 100%;
            max-width: 500px;
            padding: 9px;
            border: 1px solid #ccc;
        }

        button {
            padding: 10px 18px;
            border: none;
            cursor: pointer;
            background: #333;
            color: white;
        }

        button:hover {
            background: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: #ffffff;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }

        .empty {
            margin-top: 15px;
            padding: 12px;
            background: #eeeeee;
        }

        .success {
            padding: 12px;
            margin-bottom: 20px;
            background: #dff0d8;
            border: 1px solid #c8e6c9;
        }

        .error {
            padding: 12px;
            margin-bottom: 20px;
            background: #f8d7da;
            border: 1px solid #f1b0b7;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
        }

    </style>

</head>

<body>

<div class="container">

    <a
        class="back"
        href="campaigns.php"
    >
        ← Volver a campañas
    </a>

    <h1>
        Agentes de la campaña
    </h1>

    <div class="campaign-info">

        <strong>
            Campaña:
        </strong>

        <?= htmlspecialchars($campaign['name']) ?>

    </div>


    <?php if ($mensaje !== null): ?>

        <div class="<?= htmlspecialchars($tipoMensaje) ?>">

            <?= htmlspecialchars($mensaje) ?>

        </div>

    <?php endif; ?>


    <div class="panel">

        <h2>
            Asignar agente
        </h2>

        <?php if (empty($agentesDisponibles)): ?>

            <div class="empty">

                No hay agentes disponibles para asignar
                a esta campaña.

            </div>

        <?php else: ?>

            <form
                method="POST"
                action="campaign_agents.php?id=<?= (int) $campaignId ?>"
            >

                <div class="form-group">

                    <label for="user_id">
                        Seleccionar agente
                    </label>

                    <select
                        name="user_id"
                        id="user_id"
                        required
                    >

                        <option value="">
                            -- Seleccione un agente --
                        </option>

                        <?php foreach ($agentesDisponibles as $agente): ?>

                            <option
                                value="<?= (int) $agente['id'] ?>"
                            >

                                <?= htmlspecialchars($agente['name']) ?>
                                -
                                <?= htmlspecialchars($agente['email']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button type="submit">
                    Asignar agente
                </button>

            </form>

        <?php endif; ?>

    </div>


    <div class="panel">

        <h2>
            Agentes asignados
        </h2>

        <?php if (empty($agentesAsignados)): ?>

            <div class="empty">

                No hay agentes asignados a esta campaña.

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Nombre
                        </th>

                        <th>
                            Correo
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Fecha de asignación
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($agentesAsignados as $agente): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($agente['name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($agente['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($agente['status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($agente['assigned_at']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>

</html>
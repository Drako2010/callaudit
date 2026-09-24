<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Crear usuario - CallAudit</title>

</head>

<body>

    <h1>Crear usuario</h1>


    <?php
    /*
    |--------------------------------------------------------------------------
    | Mensaje del proceso
    |--------------------------------------------------------------------------
    | Si el controlador detectó un error, mostramos el mensaje al usuario.
    */

    if ($message !== ''):
    ?>

        <p>
            <strong>
                <?= htmlspecialchars($message) ?>
            </strong>
        </p>

    <?php endif; ?>


    <form method="POST" action="">


        <!--
        =====================================================================
        EMPRESA
        =====================================================================
        La empresa determina el ámbito al que pertenecerá el nuevo usuario.
        El backend vuelve a validar esta selección.
        -->

        <div>

            <label for="tenant_id">
                Empresa:
            </label>

            <select
                name="tenant_id"
                id="tenant_id"
                required

                <?php
                /*
                |--------------------------------------------------------------------------
                | Usuarios globales
                |--------------------------------------------------------------------------
                | SUPERADMIN puede cambiar de empresa y la página se recarga para
                | obtener los roles correspondientes.
                */
                if ($usuarioActual['tenant_id'] === null):
                ?>
                    onchange="window.location.href='user_create.php?tenant_id=' + this.value"
                <?php endif; ?>

                <?php
                /*
                |--------------------------------------------------------------------------
                | Usuarios de empresa
                |--------------------------------------------------------------------------
                | No pueden cambiar de empresa.
                */
                if ($usuarioActual['tenant_id'] !== null):
                ?>
                    disabled
                <?php endif; ?>
            >

                <?php if ($usuarioActual['tenant_id'] !== null): ?>

                    <!--
                    El select está deshabilitado visualmente, por lo que utilizamos
                    este campo oculto para enviar el tenant al servidor.
                    -->
                    <input
                        type="hidden"
                        name="tenant_id"
                        value="<?= htmlspecialchars($selectedTenantId) ?>"
                    >

                <?php endif; ?>
                

                <?php if (count($tenants) > 1): ?>

                    <!--
                    Para un SUPERADMIN que administra varias empresas,
                    permitimos seleccionar la empresa.
                    -->

                    <option value="">
                        Seleccione una empresa
                    </option>

                <?php endif; ?>


                <?php foreach ($tenants as $tenant): ?>

                    <option
                        value="<?= htmlspecialchars($tenant['id']) ?>"
                        <?= (
                            $selectedTenantId === (int) $tenant['id']
                        ) ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($tenant['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <br>


        <!--
        =====================================================================
        ROL
        =====================================================================
        El rol determina el conjunto de permisos heredados que tendrá
        el nuevo usuario.
        -->

        <div>

            <label for="role_id">
                Rol:
            </label>

            <select
                name="role_id"
                id="role_id"
                required
            >

                <option value="">
                    Seleccione un rol
                </option>


                <?php foreach ($roles as $role): ?>

                    <option
                        value="<?= htmlspecialchars($role['id']) ?>"
                        <?= (
                            $selectedRoleId === (int) $role['id']
                        ) ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($role['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <br>


        <!--
        =====================================================================
        NOMBRE
        =====================================================================
        -->

        <div>

            <label for="name">
                Nombre:
            </label>

            <input
                type="text"
                name="name"
                id="name"
                maxlength="150"
                value="<?= htmlspecialchars($name) ?>"
                required
            >

        </div>


        <br>


        <!--
        =====================================================================
        CORREO ELECTRÓNICO
        =====================================================================
        -->

        <div>

            <label for="email">
                Correo electrónico:
            </label>

            <input
                type="email"
                name="email"
                id="email"
                maxlength="150"
                value="<?= htmlspecialchars($email) ?>"
                required
                autocomplete="username"
            >

        </div>


        <br>


        <!--
        =====================================================================
        CONTRASEÑA
        =====================================================================
        -->

        <div>

            <label for="password">
                Contraseña:
            </label>

            <input
                type="password"
                name="password"
                id="password"
                minlength="8"
                required
                autocomplete="new-password"
            >

        </div>


        <br>


        <!--
        =====================================================================
        ACCIONES
        =====================================================================
        -->

        <button type="submit">
            Crear usuario
        </button>


        <a href="users.php">
            Cancelar
        </a>


    </form>

</body>

</html>

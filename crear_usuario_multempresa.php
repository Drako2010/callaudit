
<?php
/**
 * Script de prueba para el login multiempresa de CallAudit.
 *
 * Crea un usuario de prueba con el mismo correo en dos empresas
 * activas y le asigna el rol SUPERVISOR correspondiente a cada una.
 *
 * IMPORTANTE:
 * Ejecutar solo en desarrollo y eliminar este archivo al terminar.
 */

require_once __DIR__ . '/config/database.php';

$database = new Database();
$pdo = $database->connect();

if (!$pdo) {
    exit('ERROR: No se pudo conectar con la base de datos CallAudit.');
}

// Credenciales reservadas para esta prueba.
$nombre = 'Usuario Prueba Multiempresa';
$email = 'prueba.multempresa@callaudit.local';
$passwordPlano = 'PruebaMulti2026!';

// Generar un hash compatible con el login actual de PHP.
$passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);

try {
    // Ejecutar todo como una operación única.
    $pdo->beginTransaction();

    // Buscar dos empresas activas que tengan un rol SUPERVISOR activo.
    $sqlEmpresas = "
        SELECT
            t.id AS tenant_id,
            t.name AS tenant_name,
            r.id AS role_id
        FROM tenants t
        INNER JOIN roles r
            ON r.tenant_id = t.id
            AND r.slug = 'SUPERVISOR'
            AND r.status = 'ACTIVE'
        WHERE t.status = 'ACTIVE'
        ORDER BY t.id
        LIMIT 2
    ";

    $consultaEmpresas = $pdo->query($sqlEmpresas);
    $empresas = $consultaEmpresas->fetchAll();

    if (count($empresas) < 2) {
        throw new RuntimeException(
            'Se necesitan al menos dos empresas activas con rol SUPERVISOR activo.'
        );
    }

    // Preparar la creación o actualización del usuario por empresa.
    // El índice único actual permite repetir el correo en empresas distintas.
    $sqlUsuario = "
        INSERT INTO users (
            tenant_id,
            name,
            email,
            password,
            status
        )
        VALUES (
            :tenant_id,
            :name,
            :email,
            :password,
            'ACTIVE'
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password = VALUES(password),
            status = 'ACTIVE'
    ";

    $stmtUsuario = $pdo->prepare($sqlUsuario);

    // Consultar el ID real del usuario dentro de cada empresa.
    $sqlBuscarUsuario = "
        SELECT id
        FROM users
        WHERE tenant_id = :tenant_id
          AND email = :email
        LIMIT 1
    ";

    $stmtBuscarUsuario = $pdo->prepare($sqlBuscarUsuario);

    // Asignar el rol de la empresa al usuario correspondiente.
    $sqlAsignarRol = "
        INSERT IGNORE INTO user_roles (user_id, role_id)
        VALUES (:user_id, :role_id)
    ";

    $stmtAsignarRol = $pdo->prepare($sqlAsignarRol);

    $resultados = [];

    foreach ($empresas as $empresa) {
        // Crear o actualizar la cuenta en esta empresa.
        $stmtUsuario->execute([
            ':tenant_id' => (int) $empresa['tenant_id'],
            ':name'      => $nombre,
            ':email'     => $email,
            ':password'  => $passwordHash
        ]);

        // Recuperar el ID de esa cuenta.
        $stmtBuscarUsuario->execute([
            ':tenant_id' => (int) $empresa['tenant_id'],
            ':email'     => $email
        ]);

        $usuario = $stmtBuscarUsuario->fetch();

        if (!$usuario) {
            throw new RuntimeException(
                'No se pudo recuperar el usuario de la empresa ' .
                $empresa['tenant_name']
            );
        }

        // Asignar el rol SUPERVISOR de esa misma empresa.
        $stmtAsignarRol->execute([
            ':user_id' => (int) $usuario['id'],
            ':role_id' => (int) $empresa['role_id']
        ]);

        $resultados[] = [
            'usuario_id' => (int) $usuario['id'],
            'empresa'    => $empresa['tenant_name'],
            'tenant_id'  => (int) $empresa['tenant_id']
        ];
    }

    // Confirmar los cambios en la base de datos.
    $pdo->commit();

    echo '<h2>Usuario de prueba creado correctamente</h2>';
    echo '<p><strong>Correo:</strong> ' .
        htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><strong>Contraseña:</strong> ' .
        htmlspecialchars($passwordPlano, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><strong>Rol:</strong> SUPERVISOR</p>';
    echo '<h3>Empresas asociadas</h3><ul>';

    foreach ($resultados as $resultado) {
        echo '<li>' .
            htmlspecialchars($resultado['empresa'], ENT_QUOTES, 'UTF-8') .
            ' — Tenant ID: ' . $resultado['tenant_id'] .
            ' — Usuario ID: ' . $resultado['usuario_id'] .
            '</li>';
    }

    echo '</ul>';
    echo '<p>Ahora puedes probar el inicio de sesión multiempresa.</p>';

} catch (Throwable $e) {
    // Revertir las inserciones si alguna operación falla.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo '<h2>Error al preparar los datos de prueba</h2>';
    echo '<p>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</p>';
}
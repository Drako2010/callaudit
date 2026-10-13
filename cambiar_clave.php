<?php

require_once __DIR__ . '/config/database.php';

$email = 'supervisor.protecta@callaudit.local';
$nuevaClave = '123456789';

try {
    $database = new Database();

    // El método correcto es connect()
    $conexion = $database->connect();

    if ($conexion === null) {
        throw new RuntimeException(
            'No se pudo conectar a la base de datos CallAudit.'
        );
    }

    // Buscar las cuentas asociadas al correo.
    $consulta = $conexion->prepare(
        'SELECT id, name, email
         FROM users
         WHERE email = :email'
    );

    $consulta->execute([':email' => $email]);
    $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);

    if (count($usuarios) === 0) {
        exit('No existe un usuario con ese correo.');
    }

    // El mismo correo puede existir en varias empresas.
    if (count($usuarios) > 1) {
        exit(
            'El correo pertenece a varias cuentas. ' .
            'No se modificó ninguna contraseña.'
        );
    }

    $usuario = $usuarios[0];
    $hash = password_hash($nuevaClave, PASSWORD_DEFAULT);

    if ($hash === false) {
        throw new RuntimeException(
            'No se pudo generar el hash de la contraseña.'
        );
    }

    $actualizar = $conexion->prepare(
        'UPDATE users
         SET password = :password
         WHERE id = :id'
    );

    $actualizar->execute([
        ':password' => $hash,
        ':id' => $usuario['id']
    ]);

    echo 'Contraseña actualizada correctamente para la cuenta: '
        . htmlspecialchars(
            $usuario['email'],
            ENT_QUOTES,
            'UTF-8'
        );

} catch (Throwable $e) {
    error_log('Error al cambiar contraseña: ' . $e->getMessage());

    http_response_code(500);
    echo 'No se pudo actualizar la contraseña. ' .
         'Revisa el registro de errores de PHP.';
}
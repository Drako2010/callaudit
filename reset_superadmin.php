<?php

/*
|--------------------------------------------------------------------------
| Restablecer contraseña del SUPERADMIN
|--------------------------------------------------------------------------
| Este archivo genera un nuevo hash utilizando password_hash()
| y actualiza únicamente la contraseña del usuario ID 7.
|
| Es un archivo TEMPORAL para pruebas.
*/

require_once __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| Nueva contraseña
|--------------------------------------------------------------------------
| Cambia este valor por la contraseña que quieras utilizar
| temporalmente para probar el acceso.
*/

$newPassword = 'CallAudit123!';


/*
|--------------------------------------------------------------------------
| Conexión a la base de datos
|--------------------------------------------------------------------------
*/

$database = new Database();
$db = $database->connect();


/*
|--------------------------------------------------------------------------
| Verificar conexión
|--------------------------------------------------------------------------
*/

if ($db === null) {

    echo 'No se pudo conectar a la base de datos.';
    exit;
}


/*
|--------------------------------------------------------------------------
| Generar hash seguro
|--------------------------------------------------------------------------
| Nunca almacenamos la contraseña directamente.
*/

$passwordHash = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);


/*
|--------------------------------------------------------------------------
| Actualizar contraseña del SUPERADMIN
|--------------------------------------------------------------------------
*/

$sql = "UPDATE users
        SET password = :password
        WHERE id = 7";

$stmt = $db->prepare($sql);

$ok = $stmt->execute([
    ':password' => $passwordHash
]);


/*
|--------------------------------------------------------------------------
| Resultado
|--------------------------------------------------------------------------
*/

if ($ok && $stmt->rowCount() === 1) {

    echo 'CONTRASEÑA DEL SUPERADMIN ACTUALIZADA CORRECTAMENTE.';

} else {

    echo 'NO SE PUDO ACTUALIZAR LA CONTRASEÑA.';
}

<?php

require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->connect();

if (!$pdo) {
    exit('No se pudo conectar a la base de datos.');
}

$password = '123456789';

$hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users
        SET password = :password
        WHERE id = 15
        AND email = 'prueba.agente2@callaudit.local'
        AND tenant_id = 6";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':password' => $hash
]);

if ($stmt->rowCount() > 0) {

    echo 'Contraseña restablecida correctamente.<br>';
    echo 'Usuario: prueba.agente2@callaudit.local<br>';
    echo 'Nueva contraseña: AgenteTemp2026!';

} else {

    echo 'No se actualizó ningún registro. Verifica que el usuario exista.';
}
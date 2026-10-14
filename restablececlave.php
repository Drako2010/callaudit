
<?php
require_once __DIR__ . '/config/database.php';

$email = 'auditor.protecta@callaudit.local';
$nuevaClave = '123456789';

$db = (new Database())->connect();

if (!$db) {
    exit('No se pudo conectar a la base de datos.');
}

$stmt = $db->prepare(
    'SELECT id FROM users WHERE email = :email AND tenant_id = 6'
);
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario) {
    exit('No se encontró el usuario Auditor Protecta.');
}

$hash = password_hash($nuevaClave, PASSWORD_DEFAULT);

$update = $db->prepare(
    'UPDATE users SET password = :password WHERE id = :id'
);
$update->execute([
    'password' => $hash,
    'id' => $usuario['id']
]);

echo 'Contraseña restablecida correctamente para Auditor Protecta.';

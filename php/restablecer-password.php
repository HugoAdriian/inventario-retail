<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/conexion.php';

$correo = trim(readline('Correo del usuario: '));

$consulta = $pdo->prepare(
    'SELECT id FROM usuarios WHERE correo = ?'
);
$consulta->execute([$correo]);
$usuario = $consulta->fetch();

if (!$usuario) {
    exit("No se encontró ese usuario.\n");
}

$password = readline('Nueva contraseña (visible mientras escribes): ');
$confirmacion = readline('Repite la nueva contraseña: ');

if (strlen($password) < 12) {
    exit("Usa al menos 12 caracteres.\n");
}

if ($password !== $confirmacion) {
    exit("Las contraseñas no coinciden. Ejecuta otra vez el script.\n");
}

$consulta = $pdo->prepare(
    'UPDATE usuarios SET password_hash = ? WHERE id = ?'
);

$consulta->execute([
    password_hash($password, PASSWORD_DEFAULT),
    $usuario['id'],
]);

echo "Contraseña actualizada correctamente.\n";
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/conexion.php';

$nombre = trim(readline('Nombre del administrador: '));
$correo = trim(readline('Correo electrónico: '));
$password = readline('Contraseña (se verá mientras escribes): ');

if (
    $nombre === '' ||
    !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
    strlen($password) < 12
) {
    exit("Datos inválidos. Usa una contraseña de al menos 12 caracteres.\n");
}

$consulta = $pdo->prepare(
    'SELECT id FROM usuarios WHERE correo = ?'
);
$consulta->execute([$correo]);

if ($consulta->fetch()) {
    exit("Ya existe un usuario con ese correo.\n");
}

$consulta = $pdo->prepare(
    'INSERT INTO usuarios (nombre, correo, password_hash, rol)
     VALUES (?, ?, ?, ?)'
);

$consulta->execute([
    $nombre,
    $correo,
    password_hash($password, PASSWORD_DEFAULT),
    'administrador',
]);

echo "Administrador creado correctamente.\n";

//Humbertoute302210
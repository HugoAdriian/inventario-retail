<?php

require __DIR__ . '/sesion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

$correo = $_POST['correo'] ?? '';
$password = $_POST['password'] ?? '';

if (!is_string($correo) || !is_string($password)) {
    header('Location: ../index.html?error=credenciales');
    exit;
}

$correo = trim($correo);

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $password === '') {
    header('Location: ../index.html?error=credenciales');
    exit;
}

try {
    require __DIR__ . '/conexion.php';

    $consulta = $pdo->prepare(
        'SELECT id, nombre, password_hash, rol, activo
         FROM usuarios
         WHERE correo = ?
         LIMIT 1'
    );

    $consulta->execute([$correo]);
    $usuario = $consulta->fetch();
 

    if (
        !$usuario ||
        !password_verify($password, $usuario['password_hash']) ||
        (int) $usuario['activo'] !== 1
    ) {
        header('Location: ../index.html?error=credenciales');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    header('Location: ../inventario.php');
    exit;
} catch (PDOException $error) {
    error_log($error->getMessage());

    header('Location: ../index.html?error=servidor');
    exit;
}
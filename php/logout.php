<?php

require __DIR__ . '/sesion.php';

$token = $_POST['csrf'] ?? '';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !is_string($token) ||
    !hash_equals($_SESSION['csrf'], $token)
) {
    http_response_code(403);
    exit('Solicitud inválida.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $parametros['path'],
        'domain' => $parametros['domain'],
        'secure' => $parametros['secure'],
        'httponly' => $parametros['httponly'],
        'samesite' => 'Lax',
    ]);
}

session_destroy();

header('Location: ../index.html');
exit;
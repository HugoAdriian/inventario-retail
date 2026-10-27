<?php

require __DIR__ . '/config.local.php';

$dsn = "mysql:host={$dbHost};port={$dbPuerto};dbname={$dbNombre};charset=utf8mb4";

$pdo = new PDO($dsn, $dbUsuario, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
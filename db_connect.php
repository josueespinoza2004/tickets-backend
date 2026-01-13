<?php
// db_connect.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
$config = require_once __DIR__ . '/db_config.php';

try {
    // Usamos las claves que tiene tu archivo de configuración real
    $host = isset($config['host']) ? $config['host'] : (isset($config['127.0.0.1']) ? '127.0.0.1' : '127.0.0.1');
    $port = isset($config['port']) ? $config['port'] : (isset($config['3306']) ? '3306' : '3306');
    $dbname = isset($config['dbname']) ? $config['dbname'] : (isset($config['tickets']) ? 'tickets' : 'tickets');

    // Credenciales con fallback
    $user = isset($config['user']) ? $config['user'] : (isset($config['root']) ? $config['root'] : 'root');
    $pass = isset($config['pass']) ? $config['pass'] : '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    // Return standard error format if connection fails
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

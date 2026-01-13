<?php
// api/get_branches.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

try {
    // Priorizamos 'Nueva Guinea' en el ordenamiento
    $stmt = $pdo->query("SELECT id, name FROM branches ORDER BY CASE WHEN name = 'Nueva Guinea' THEN 0 ELSE 1 END, name ASC");
    $branches = $stmt->fetchAll();

    echo json_encode($branches);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch branches: " . $e->getMessage()]);
}

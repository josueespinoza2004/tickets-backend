<?php
// api/create_user.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth/app_auth.php';

// Validate Session
authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Required fields
    if (empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
        throw new Exception('Missing required fields (email, password, full_name)');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        throw new Exception('Email already exists');
    }

    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (email, password, full_name, role, branch_id, area_id, cargo, profile_photo) 
            VALUES (:email, :password, :full_name, :role, :branch_id, :area_id, :cargo, :profile_photo)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $data['email'],
        ':password' => $hashed_password,
        ':full_name' => $data['full_name'],
        ':role' => $data['role'] ?? 'user',
        ':branch_id' => !empty($data['branch_id']) ? $data['branch_id'] : null,
        ':area_id' => !empty($data['area_id']) ? $data['area_id'] : null,
        ':cargo' => $data['cargo'] ?? null,
        ':profile_photo' => $data['profile_photo'] ?? null
    ]);

    echo json_encode(['message' => 'User created successfully', 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    // Determine error code (500 by default if not already set)
    if (http_response_code() === 200)
        http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

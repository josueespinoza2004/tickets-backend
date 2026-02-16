<?php
// auth/login.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST method is allowed']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Email and password are required');
    }

    $email = $data['email'];
    $password = $data['password'];

    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.password, u.full_name, u.role, u.branch_id, u.area_id, u.cargo,
               b.name as branch_name,
               a.name as area_name
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN areas a ON u.area_id = a.id
        WHERE u.email = :email 
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Remove password from response
        unset($user['password']);

        // Generate Token
        $token = bin2hex(random_bytes(32)); // 64 chars
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Insert into user_sessions
        $insertStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
        $insertStmt->execute([$user['id'], $token, $expires_at]);

        // Return token and user data
        echo json_encode([
            'success' => true,
            'user' => $user,
            'token' => $token
        ]);
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid credentials']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
}

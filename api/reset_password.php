<?php
// api/reset_password.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = $input['email'] ?? '';
    $code = $input['code'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
    if (!$email || !$code || !$newPassword) {
        throw new Exception('Email, código y nueva contraseña son requeridos');
    }
    
    if (strlen($newPassword) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    // Buscar usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Email no encontrado');
    }
    
    // Verificar código
    $stmt = $pdo->prepare("
        SELECT id FROM password_resets 
        WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used = 0
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$user['id'], $code]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        throw new Exception('Código inválido o expirado');
    }
    
    // Actualizar contraseña
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $user['id']]);
    
    // Marcar código como usado
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
    $stmt->execute([$reset['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

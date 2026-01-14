<?php
// auth/app_auth.php
require_once __DIR__ . '/../db_connect.php';

/**
 * Validates the Authorization header and returns the user ID if valid.
 * Terminate request with 401 if invalid.
 * 
 * @return int User ID
 */
function authenticate()
{
    global $pdo;

    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Missing or invalid token']);
        exit;
    }

    $token = $matches[1];

    // Check token in DB and ensure it's not expired (24 hours validity for example)
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM user_sessions WHERE token = ?");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid token']);
        exit;
    }

    if (strtotime($session['expires_at']) < time()) {
        // Expired
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Token expired']);
        exit;
    }

    return $session['user_id'];
}

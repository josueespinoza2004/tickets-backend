<?php
// api/request_password_reset.php

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
    
    if (!$email) {
        throw new Exception('Email es requerido');
    }
    
    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Por seguridad, no revelar si el email existe o no
        echo json_encode(['success' => true, 'message' => 'Si el email existe, recibirás un código de verificación']);
        exit;
    }
    
    // Generar código de 6 dígitos (criptográficamente seguro)
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Guardar código en la base de datos
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $code, $expiresAt]);
    
    // Preparar contenido del email
    require_once __DIR__ . '/../send_email.php';
    
    $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
                            <!-- Header con logo -->
                            <tr>
                                <td style='padding: 40px 40px 30px 40px; background-color: #ffffff;'>
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td style='vertical-align: middle; text-align: center;'>
                                                <table cellpadding='0' cellspacing='0' style='display: inline-block;'>
                                                    <tr>
                                                        <td style='vertical-align: middle; padding-right: 15px;'>
                                                            <img src='https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDCa-jCPCvDE4kHKJP3pKyfMZjTqcwsxLliQ&s' alt='COOPEFACSA Logo' style='width: 70px; height: auto; display: block;' />
                                                        </td>
                                                        <td style='vertical-align: middle; text-align: left;'>
                                                            <h1 style='margin: 0; color: #1e3a8a; font-size: 28px; font-weight: bold; line-height: 1.2;'>Gestión de Tickets</h1>
                                                            <p style='margin: 5px 0 0 0; color: #6b7280; font-size: 16px; line-height: 1.2;'>COOPEFACSA R.L.</p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                            <!-- Línea separadora -->
                            <tr>
                                <td style='padding: 0 40px;'>
                                    <div style='height: 3px; background-color: #1e3a8a;'></div>
                                </td>
                            </tr>
                            
                            <!-- Contenido principal -->
                            <tr>
                                <td style='padding: 40px; background-color: #f9fafb;'>
                                    <h2 style='margin: 0 0 20px 0; color: #374151; font-size: 24px; font-weight: bold;'>
                                        Hola " . htmlspecialchars($user['full_name'] ?: 'Usuario') . ",
                                    </h2>
                                    
                                    <p style='margin: 0 0 15px 0; color: #6b7280; font-size: 16px; line-height: 1.5;'>
                                        Has solicitado recuperar tu contraseña.
                                    </p>
                                    
                                    <p style='margin: 0 0 20px 0; color: #6b7280; font-size: 16px; line-height: 1.5;'>
                                        Tu código de verificación es:
                                    </p>
                                    
                                    <!-- Código en caja -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='margin: 0 0 20px 0;'>
                                        <tr>
                                            <td style='padding: 30px; background-color: #ffffff; border: 3px solid #1e3a8a; border-radius: 8px; text-align: center;'>
                                                <span style='font-size: 48px; font-weight: bold; color: #1e3a8a; letter-spacing: 12px;'>" . $code . "</span>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style='margin: 0 0 15px 0; color: #374151; font-size: 16px; font-weight: bold;'>
                                        Este código expira en 15 minutos.
                                    </p>
                                    
                                    <p style='margin: 0; color: #6b7280; font-size: 15px; line-height: 1.5;'>
                                        Si no solicitaste este cambio, ignora este correo y tu contraseña permanecerá sin cambios.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='padding: 30px 40px; background-color: #ffffff; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #9ca3af; font-size: 13px;'>
                                        Este es un correo automático, por favor no respondas.
                                    </p>
                                    <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                                        © 2026 COOPEFACSA R.L. - Todos los derechos reservados
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ";
    
    // Enviar email
    $emailSent = sendEmail($email, 'Código de Recuperación de Contraseña - COOPEFACSA', $htmlBody, true);
    
    if ($emailSent) {
        echo json_encode(['success' => true, 'message' => 'Código enviado a tu correo']);
    } else {
        // Si falla el envío, devolver el código en desarrollo (QUITAR EN PRODUCCIÓN)
        error_log("Error enviando email de recuperación a: {$email}");
        echo json_encode([
            'success' => true, 
            'message' => 'Código generado (error al enviar email)',
            'debug_code' => $code // SOLO PARA DESARROLLO - QUITAR EN PRODUCCIÓN
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

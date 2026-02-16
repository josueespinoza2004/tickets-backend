<?php
// send_email.php
// Función para enviar emails usando sockets nativos de PHP (sin PHPMailer)

function sendEmail($to, $subject, $body, $isHtml = true) {
    $config = require __DIR__ . '/email_config.php';
    
    $from = $config['from_email'];
    $fromName = $config['from_name'];
    $host = $config['smtp_host'];
    $port = $config['smtp_port'];
    $username = $config['smtp_user'];
    $password = $config['smtp_pass'];
    
    try {
        // Crear conexión SSL directa (puerto 465)
        $socket = stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );
        
        if (!$socket) {
            throw new Exception("No se pudo conectar al servidor SMTP: {$errstr} ({$errno})");
        }
        
        // Leer respuesta inicial del servidor
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Error en respuesta inicial: {$response}");
        }
        
        // Enviar EHLO
        fputs($socket, "EHLO {$host}\r\n");
        $response = fgets($socket, 515);
        
        // Leer todas las capacidades del servidor
        while (substr($response, 3, 1) == '-') {
            $response = fgets($socket, 515);
        }
        
        // Autenticación
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Error en AUTH LOGIN: {$response}");
        }
        
        // Enviar usuario
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Error en usuario: {$response}");
        }
        
        // Enviar contraseña
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Error en autenticación: {$response}");
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM: <{$from}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Error en MAIL FROM: {$response}");
        }
        
        // RCPT TO
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Error en RCPT TO: {$response}");
        }
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            throw new Exception("Error en DATA: {$response}");
        }
        
        // Construir headers
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($isHtml) {
            $boundary = md5(time());
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= strip_tags($body) . "\r\n\r\n";
            
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $body . "\r\n\r\n";
            $message .= "--{$boundary}--\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message = $body . "\r\n";
        }
        
        // Enviar email
        fputs($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Error al enviar mensaje: {$response}");
        }
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error enviando email: " . $e->getMessage());
        return false;
    }
}

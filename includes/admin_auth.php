<?php
/**
 * ATENÇÃO: este é um MODELO.
 * Cole aqui o usuário/senha reais que já estavam no seu admin_auth.php original
 * (o README menciona login "adrian" / senha "1234" como exemplo).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_USUARIO', 'adrian');

// Gerado com password_hash('1234', PASSWORD_DEFAULT) — troque pela sua senha real.
define('ADMIN_SENHA_HASH', '$2y$10$abcdefghijklmnopqrstuuVWXYZ1234567890abcdefghijklmno');

function adminAutenticado(): bool {
    return !empty($_SESSION['admin_autenticado']);
}

function exigirAdmin(): void {
    if (!adminAutenticado()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não autenticado.']);
        exit;
    }
}

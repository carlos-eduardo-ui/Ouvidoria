<?php
/**
 * api/adm/session_check.php — Middleware de autenticação admin
 * Ouvidoria Escolar
 */

declare(strict_types=1);

$emProducao = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') !== 'development';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $emProducao,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['adm_IDadm'])) {
    // Detecta requisição AJAX/fetch pelo header Accept ou X-Requested-With
    // O frontend deve sempre enviar Accept: application/json nos fetches
    $accept  = $_SERVER['HTTP_ACCEPT']           ?? '';
    $xrw     = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $isAjax  = str_contains($accept, 'application/json') || $xrw !== '';

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }

    // Acesso direto pelo navegador → redireciona para login
    $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/ouvidoriaDW', '/');
    header('Location: ' . $appUrl . '/adm/login.html');
    exit;
}

$adminSession = [
    'IDadm' => (int) $_SESSION['adm_IDadm'],
    'nome'  => $_SESSION['adm_nome'],
    'email' => $_SESSION['adm_email'],
];
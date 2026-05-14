<?php
/**
 * api/adm/logout.php — Encerrar sessão do admin
 * Ouvidoria Escolar
 */

declare(strict_types=1);

// db.php carrega o .env antes do cors.php usar getenv('APP_URL')
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

$emProducao = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') !== 'development';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $emProducao,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

jsonResponse(true, 'Sessão encerrada.', ['redirect' => 'login.html']);
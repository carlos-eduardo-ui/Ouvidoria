<?php
/**
 * logout.php — Encerrar sessão
 * Ouvidoria Municipal — Ceará
 *
 * POST /api/logout.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/cors.php';

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

jsonResponse(true, 'Sessão encerrada.', ['redirect' => '../login.html']);

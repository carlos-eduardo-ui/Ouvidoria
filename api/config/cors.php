<?php
/**
 * cors.php — Headers de resposta e helpers
 * Ouvidoria Municipal — Ceará
 */

declare(strict_types=1);

// ── CORS ────────────────────────────────────────────────────
// Ajuste ALLOWED_ORIGIN para o domínio real em produção.
define('ALLOWED_ORIGIN', getenv('APP_URL') ?: 'http://localhost');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === ALLOWED_ORIGIN || str_starts_with($origin, 'http://localhost')) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Responder preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Rate Limiting simples (baseado em IP + sessão) ───────────
function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    $now     = time();
    $session = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'start' => $now];

    // Reset janela
    if ($now - $session['start'] > $windowSeconds) {
        $session = ['count' => 0, 'start' => $now];
    }

    $session['count']++;
    $_SESSION['rate_limit'][$key] = $session;

    if ($session['count'] > $maxAttempts) {
        jsonResponse(false, 'Muitas tentativas. Aguarde alguns minutos.', [], 429);
    }
}

// ── Helpers de resposta JSON ─────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Ler corpo JSON da requisição ─────────────────────────────
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Corpo da requisição inválido.', [], 400);
    }
    return $data ?? [];
}

// ── Sanitização ──────────────────────────────────────────────
function sanitize(mixed $value): string
{
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}

// ── Validações ───────────────────────────────────────────────
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidCPF(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1+$/', $cpf)) return false;
    $sum = 0;
    for ($i = 0; $i < 9; $i++) $sum += (int)$cpf[$i] * (10 - $i);
    $r = 11 - ($sum % 11);
    if ($r >= 10) $r = 0;
    if ($r !== (int)$cpf[9]) return false;
    $sum = 0;
    for ($i = 0; $i < 10; $i++) $sum += (int)$cpf[$i] * (11 - $i);
    $r = 11 - ($sum % 11);
    if ($r >= 10) $r = 0;
    return $r === (int)$cpf[10];
}

function isStrongPassword(string $pwd): bool
{
    return strlen($pwd) >= 8
        && preg_match('/[A-Z]/', $pwd)
        && preg_match('/\d/', $pwd)
        && preg_match('/[^A-Za-z0-9]/', $pwd);
}

// ── Método HTTP obrigatório ──────────────────────────────────
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonResponse(false, 'Método não permitido.', [], 405);
    }
}

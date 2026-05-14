<?php
/**
 * api/adm/login.php — Autenticação de membros do Grêmio
 * Ouvidoria Escolar
 *
 * POST /api/adm/login.php
 * Body JSON: { "email": string, "senha": string }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

requireMethod('POST');

// session_set_cookie_params e session_start ANTES do checkRateLimit
// (checkRateLimit chama session_start internamente se sessão não estiver aberta,
//  o que ignoraria os parâmetros do cookie definidos abaixo)
$emProducao = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') !== 'development';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $emProducao,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

// Rate limit: máximo 5 tentativas a cada 5 minutos por IP
checkRateLimit('adm_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 300);

// ── Lê body ───────────────────────────────────────────────────
$body  = getJsonBody();
$email = trim(strtolower($body['email'] ?? ''));
$senha = $body['senha'] ?? '';

if (empty($email) || empty($senha)) {
    jsonResponse(false, 'E-mail e senha são obrigatórios.', [], 422);
}

if (!isValidEmail($email)) {
    jsonResponse(false, 'Formato de e-mail inválido.', [], 422);
}

// ── Busca o admin no banco ────────────────────────────────────
try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare(
        'SELECT IDadm, nome, email, senha, ativo FROM tbadm WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[ADM LOGIN] Erro BD: ' . $e->getMessage());
    jsonResponse(false, 'Erro interno. Tente novamente.', [], 500);
}

if (!$admin) {
    jsonResponse(false, 'E-mail não encontrado.', [], 404);
}

if (!(bool) $admin['ativo']) {
    jsonResponse(false, 'Conta inativa. Entre em contato com o responsável.', [], 403);
}

if (!password_verify($senha, $admin['senha'])) {
    jsonResponse(false, 'Senha incorreta.', [], 401);
}

// ── Cria sessão admin ─────────────────────────────────────────
// Prefixo adm_ para não conflitar com a sessão do aluno
session_regenerate_id(true);

$_SESSION['adm_IDadm'] = (int) $admin['IDadm'];
$_SESSION['adm_nome']  = $admin['nome'];
$_SESSION['adm_email'] = $admin['email'];
$_SESSION['adm_login'] = time();

// Log de acesso
try {
    $pdo->prepare("
        INSERT INTO log_acesso (usuario_id, acao, ip, user_agent)
        VALUES (NULL, :acao, :ip, :ua)
    ")->execute([
        ':acao' => 'adm:login:' . $admin['IDadm'],
        ':ip'   => $_SERVER['REMOTE_ADDR']     ?? null,
        ':ua'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
} catch (PDOException) { /* log não bloqueia */ }

jsonResponse(true, 'Login realizado com sucesso.', [
    'admin'    => [
        'IDadm' => (int) $admin['IDadm'],
        'nome'  => $admin['nome'],
        'email' => $admin['email'],
    ],
    'redirect' => 'index.html',
]);
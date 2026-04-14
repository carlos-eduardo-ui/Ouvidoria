<?php
/**
 * login.php — Autenticação de alunos e membros da escola
 * Ouvidoria Escolar — Grêmio Estudantil — EEEP Dom Walfrido
 *
 * POST /api/login.php
 * Body JSON: { "email": string, "senha": string }
 *
 * Respostas:
 *   200 { success: true,  usuario: {...}, redirect: "index.html#manifestacao" }
 *   401 { success: false, message: "Senha incorreta." }
 *   403 { success: false, message: "Conta inativa." }
 *   404 { success: false, message: "Usuário não encontrado." }
 *   422 { success: false, message: "Dados inválidos." }
 *   500 { success: false, message: "Erro interno." }
 *
 * O auth.js captura o 404 e redireciona para cadastro.html.
 */

declare(strict_types=1);

/* ── BLOCO 1: Configuração inicial ─────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

session_set_cookie_params([
    'httponly' => true,   // JS não consegue ler o cookie
    'samesite' => 'Strict', // cookie só vai no mesmo domínio
]);
session_start();

require_once __DIR__ . '/config/db.php';

/* ── BLOCO 2: Apenas POST ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

/* ── BLOCO 3: Ler o JSON enviado pelo auth.js ───────────────────── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

// Email: lowercase — 'Aluno@escola.br' == 'aluno@escola.br'
// Senha: sem trim() — espaços podem ser parte da senha escolhida
$email = trim(strtolower($body['email'] ?? ''));
$senha = $body['senha'] ?? '';

/* ── BLOCO 4: Validação antes de ir ao banco ────────────────────── */
if (empty($email) || empty($senha)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'E-mail e senha são obrigatórios.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Formato de e-mail inválido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 5: Buscar na tabela correta com colunas corretas ─────── */
// CORREÇÃO: tabela era 'usuarios'    → agora 'tbusuarios'
// CORREÇÃO: coluna era 'senha_hash'  → agora 'senha'
// CORREÇÃO: chave era 'id'           → agora 'IDusu'
try {
    $pdo = Database::connect();

    $stmt = $pdo->prepare(
        'SELECT IDusu, nome, email, senha, ativo
           FROM tbusuarios
          WHERE email = :email
          LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[LOGIN] Erro DB: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno. Tente novamente mais tarde.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 6: Verificar existência e estado da conta ───────────── */
if (!$usuario) {
    // 404 → auth.js redireciona para cadastro.html automaticamente
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não encontrado. Redirecionando para cadastro...',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!(bool)$usuario['ativo']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Sua conta está inativa. Entre em contato com o Grêmio Estudantil.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 7: Verificar senha com bcrypt ────────────────────────── */
// password_verify() compara sem "decriptar" — matematicamente impossível
// 401 (não 404) para não redirecionar ao cadastro por senha errada
if (!password_verify($senha, $usuario['senha'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Senha incorreta. Verifique e tente novamente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 8: Criar a sessão PHP ────────────────────────────────── */
// CORREÇÃO CRÍTICA: era $_SESSION['usuario_id'] → agora $_SESSION['IDusu']
// session.php lê exatamente 'IDusu' — chave errada = sessão nunca reconhecida

session_regenerate_id(true); // bloqueia session fixation attack

$_SESSION['IDusu']    = (int) $usuario['IDusu'];
$_SESSION['nome']     = $usuario['nome'];
$_SESSION['email']    = $usuario['email'];
$_SESSION['login_em'] = time();

/* ── BLOCO 9: Resposta de sucesso ───────────────────────────────── */
// Nunca enviar a senha ou o hash de volta — apenas dados seguros
http_response_code(200);
echo json_encode([
    'success'  => true,
    'message'  => 'Login realizado com sucesso.',
    'usuario'  => [
        'IDusu' => (int) $usuario['IDusu'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
    ],
    'redirect' => 'index.html#manifestacao',
], JSON_UNESCAPED_UNICODE);

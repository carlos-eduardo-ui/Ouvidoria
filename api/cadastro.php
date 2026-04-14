<?php
/**
 * cadastro.php — Registro de novos usuários
 * Ouvidoria Escolar — Grêmio Estudantil — EEEP Dom Walfrido
 *
 * POST /api/cadastro.php
 * Body JSON:
 * {
 *   "nome":      string,
 *   "email":     string,
 *   "matricula": string (opcional),
 *   "serie":     1|2|3,
 *   "curso":     string,
 *   "senha":     string
 * }
 *
 * Respostas:
 *   201 { success: true,  IDusu: N, message: "Conta criada." }
 *   405 { success: false, message: "Método não permitido." }
 *   409 { success: false, message: "E-mail já cadastrado." }
 *   422 { success: false, message: "Dados inválidos." }
 *   500 { success: false, message: "Erro interno." }
 */

declare(strict_types=1);

/* ── BLOCO 1: Configuração inicial ─────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

// __DIR__ garante o caminho certo independente de onde o PHP é chamado
require_once __DIR__ . '/config/db.php';

/* ── BLOCO 2: Apenas POST ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

/* ── BLOCO 3: Ler o JSON enviado pelo auth.js ───────────────────── */
// $.ajax com contentType:'application/json' envia como corpo bruto
// Por isso usamos php://input, não $_POST
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

// Extrair e limpar os campos reais de tbusuarios
// Email: lowercase para evitar duplicatas por capitalização
// Senha: sem trim() — espaços podem ser intencionais
$nome      = trim($body['nome']      ?? '');
$email     = trim(strtolower($body['email'] ?? ''));
$matricula = trim($body['matricula'] ?? '');
$serie     = intval($body['serie']   ?? 0);
$curso     = trim($body['curso']     ?? '');
$senha     = $body['senha'] ?? '';

/* ── BLOCO 4: Validação dos campos ─────────────────────────────── */
// mb_strlen() conta caracteres reais (com acento), não bytes
$erros = [];

if (mb_strlen($nome) < 3)
    $erros[] = 'Nome inválido (mínimo 3 caracteres).';

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $erros[] = 'E-mail inválido.';

if (mb_strlen($senha) < 6)
    $erros[] = 'A senha deve ter ao menos 6 caracteres.';

// Serie deve ser 1, 2 ou 3 — true = comparação estrita de tipo
if (!in_array($serie, [1, 2, 3], true))
    $erros[] = 'Série inválida. Selecione 1, 2 ou 3.';

if (mb_strlen($curso) < 2)
    $erros[] = 'Selecione o curso.';

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' | ', $erros),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 5: Conexão com o banco ──────────────────────────────── */
try {
    $pdo = Database::connect();
} catch (Throwable $e) {
    error_log('[CADASTRO] Conexão falhou: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 6: Verificar e-mail duplicado ───────────────────────── */
// tbusuarios tem UNIQUE KEY em email — checar antes para dar
// mensagem amigável em vez de erro de banco
try {
    $chk = $pdo->prepare(
        'SELECT IDusu FROM tbusuarios WHERE email = :email LIMIT 1'
    );
    $chk->execute([':email' => $email]);

    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Este e-mail já está cadastrado. Faça login.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    error_log('[CADASTRO] Erro ao checar duplicidade: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 7: Gerar hash bcrypt da senha ───────────────────────── */
// password_hash() gera hash seguro e único — nunca gravar senha em texto puro
// cost=12 é o padrão seguro atual (quanto maior, mais lento para atacante)
$hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

/* ── BLOCO 8: Inserir na tabela correta com colunas corretas ────── */
// CORREÇÃO: tabela 'tbusuarios' (não 'usuarios')
// CORREÇÃO: coluna 'senha' (não 'senha_hash')
// CORREÇÃO: removidas colunas inexistentes: cpf, nascimento, municipio, perfil, token_ativacao
// ativo=1 direto — sem ativação por e-mail (adequado para escola)
try {
    $ins = $pdo->prepare(
        'INSERT INTO tbusuarios
            (nome, matricula, serie, curso, email, senha, ativo, criado_em)
         VALUES
            (:nome, :matricula, :serie, :curso, :email, :senha, 1, NOW())'
    );
    $ins->execute([
        ':nome'      => $nome,
        ':matricula' => $matricula ?: null, // vazio → NULL no banco
        ':serie'     => $serie,
        ':curso'     => $curso,
        ':email'     => $email,
        ':senha'     => $hash,
    ]);

    $IDusu = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('[CADASTRO] Erro ao inserir: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar o cadastro. Tente novamente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── BLOCO 9: Criar sessão automaticamente após o cadastro ─────── */
// O aluno acabou de criar a conta — já logamos ele direto
// Mesmas chaves que o login.php e o session.php esperam
session_regenerate_id(true);
$_SESSION['IDusu']    = $IDusu;
$_SESSION['nome']     = $nome;
$_SESSION['email']    = $email;
$_SESSION['login_em'] = time();

/* ── BLOCO 10: Resposta de sucesso ─────────────────────────────── */
http_response_code(201);
echo json_encode([
    'success'  => true,
    'message'  => 'Conta criada com sucesso! Bem-vindo(a), ' . $nome . '.',
    'IDusu'    => $IDusu,
    'redirect' => 'index.html#manifestacao',
], JSON_UNESCAPED_UNICODE);
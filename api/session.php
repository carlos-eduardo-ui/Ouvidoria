<?php
/**
 * session.php — Verifica se existe sessão ativa e retorna dados do usuário
 * Ouvidoria Escolar — Grêmio Estudantil
 *
 * GET /api/session.php
 *
 * Resposta quando logado:
 *   { "logado": true, "usuario": { "IDusu": 1, "nome": "...", ... } }
 *
 * Resposta quando não logado:
 *   { "logado": false, "usuario": null }
 *
 * Chamado pelo form.js toda vez que a página index.html carrega,
 * para decidir como apresentar o formulário de manifestação.
 */

declare(strict_types=1);

/* ── 1. Headers ─────────────────────────────────────────────────────
   Sempre JSON. Nunca cache — sessão muda a qualquer momento.
─────────────────────────────────────────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

/* ── 2. Configuração segura do cookie de sessão ─────────────────────
   httponly  → JavaScript não consegue ler o cookie (bloqueia XSS)
   samesite  → cookie só vai junto em requisições do mesmo site (bloqueia CSRF)
─────────────────────────────────────────────────────────────────── */
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
]);

/* ── 3. Abre a sessão ───────────────────────────────────────────────
   Necessário para ler $_SESSION. Sem isso, $_SESSION fica vazio.
─────────────────────────────────────────────────────────────────── */
session_start();

/* ── 4. Verificação rápida: existe IDusu na sessão? ─────────────────
   Se não existe, não tem ninguém logado. Responde e encerra.
   Economiza uma consulta ao banco desnecessária.
─────────────────────────────────────────────────────────────────── */
if (empty($_SESSION['IDusu'])) {
    echo json_encode(
        ['logado' => false, 'usuario' => null],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ── 5. Confirma no banco de dados ──────────────────────────────────
   A sessão pode ser antiga. O usuário pode ter sido desativado
   ou deletado depois que ele logou. Confirmar aqui garante que
   só contas válidas e ativas passam.
─────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/config/db.php';

try {
    $pdo  = Database::connect();

    $stmt = $pdo->prepare(
        'SELECT IDusu, nome, serie, curso, email
           FROM tbusuarios
          WHERE IDusu = :id
            AND ativo = 1
          LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['IDusu']]);
    $usuario = $stmt->fetch();

} catch (PDOException $e) {
    // Erro de banco — não travar a página, apenas retornar não logado
    error_log('[SESSION] Erro DB: ' . $e->getMessage());
    echo json_encode(
        ['logado' => false, 'usuario' => null],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ── 6. Usuário não encontrado ou desativado ────────────────────────
   Sessão estava gravada mas o usuário não existe mais no banco.
   Limpa a sessão para não acumular dados inválidos.
─────────────────────────────────────────────────────────────────── */
if (!$usuario) {
    session_destroy();
    echo json_encode(
        ['logado' => false, 'usuario' => null],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ── 7. Tudo certo — retorna os dados seguros do usuário ────────────
   NUNCA inclua senha, hash ou qualquer campo sensível aqui.
   O JavaScript vai receber exatamente isso e usar para
   pré-preencher o formulário.
─────────────────────────────────────────────────────────────────── */
echo json_encode([
    'logado'  => true,
    'usuario' => [
        'IDusu' => (int) $usuario['IDusu'],
        'nome'  => $usuario['nome'],
        'serie' => $usuario['serie'],
        'curso' => $usuario['curso'],
        'email' => $usuario['email'],
    ],
], JSON_UNESCAPED_UNICODE);

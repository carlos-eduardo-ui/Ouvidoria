<?php
/**
 * consulta.php — Consulta pública de manifestação por protocolo
 * Ouvidoria Escolar — Grêmio Estudantil — EEEP Dom Walfrido
 *
 * GET /api/consulta.php?protocolo=OUV-2025-00042
 *
 * Endpoint PÚBLICO — não exige login.
 * Qualquer pessoa com o número do protocolo pode consultar.
 *
 * Regra de privacidade:
 *   Se anonimo=1 → nunca retorna nome, email ou contato.
 *   Se anonimo=0 → retorna só tipo, setor, status e feedback.
 *   Dados de identificação NUNCA são expostos nesta rota.
 *
 * Respostas:
 *   200 { success: true,  protocolo, tipo, setor, status, feedback, ... }
 *   400 { success: false, message: "Protocolo inválido." }
 *   404 { success: false, message: "Protocolo não encontrado." }
 *   500 { success: false, message: "Erro interno." }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/db.php';

/* ── Helper de resposta ──────────────────────────────────────────── */
function respond(bool $ok, string $msg, array $extra = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $ok, 'message' => $msg], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 1 — Só aceita GET
══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Método não permitido.', [], 405);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 2 — Ler e validar o protocolo da query string
   Formato esperado: OUV-AAAA-NNNNN (ex: OUV-2025-00042)
   Validar com regex antes de ir ao banco evita consultas inúteis
   e possíveis tentativas de SQL injection (mesmo com PDO).
══════════════════════════════════════════════════════════════════ */
$protocolo = strtoupper(trim($_GET['protocolo'] ?? ''));

if (empty($protocolo)) {
    respond(false, 'Informe o número do protocolo.', [], 400);
}

// Validar formato: OUV-AAAA-NNNNN
if (!preg_match('/^OUV-\d{4}-\d{5}$/', $protocolo)) {
    respond(false, 'Formato de protocolo inválido. Use o padrão OUV-2025-00042.', [], 400);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 3 — Buscar no banco usando a view v_manifestacoes
   A view já faz o JOIN com tipos, tbsetores e tbadm,
   e já aplica a regra de anonimato (CASE WHEN anonimo=1 THEN NULL).
   Buscamos só o que o aluno precisa ver — sem dados internos.
══════════════════════════════════════════════════════════════════ */
try {
    $pdo  = Database::connect();

    $stmt = $pdo->prepare(
        'SELECT
            m.protocolo,
            m.STATUS       AS status,
            m.anonimo,
            m.feedback,
            t.descricao    AS tipo,
            s.nome         AS setor,
            DATE_FORMAT(m.criado_em,     "%d/%m/%Y %H:%i")  AS criado_em,
            DATE_FORMAT(m.atualizado_em, "%d/%m/%Y %H:%i")  AS atualizado_em
         FROM tbmanifest m
         LEFT JOIN tipos      t ON m.IDtipo  = t.IDtipo
         LEFT JOIN tbsetores  s ON m.IDsetor = s.IDsetor
         WHERE m.protocolo = :protocolo
         LIMIT 1'
    );
    $stmt->execute([':protocolo' => $protocolo]);
    $row = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[CONSULTA] Erro DB: ' . $e->getMessage());
    respond(false, 'Erro ao consultar o banco de dados.', [], 500);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 4 — Protocolo não encontrado
══════════════════════════════════════════════════════════════════ */
if (!$row) {
    respond(false, 'Protocolo não encontrado. Verifique o número e tente novamente.', [], 404);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 5 — Montar resposta pública
   Apenas dados que o aluno pode ver.
   Nunca expõe: IDusu, nome, email, contato, IDadm.
══════════════════════════════════════════════════════════════════ */
respond(true, 'Protocolo encontrado.', [
    'protocolo'    => $row['protocolo'],
    'tipo'         => $row['tipo'],
    'setor'        => $row['setor'],
    'status'       => $row['status'],
    'feedback'     => $row['feedback'],   // null se ainda não respondida
    'criado_em'    => $row['criado_em'],
    'atualizado_em'=> $row['atualizado_em'],
]);

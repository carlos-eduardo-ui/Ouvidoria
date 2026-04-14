<?php
/**
 * manifestacoes.php — Recebe, valida e salva uma manifestação no banco
 * Ouvidoria Escolar — Grêmio Estudantil — EEEP Dom Walfrido
 *
 * POST /api/manifestacoes.php
 *
 * Body JSON esperado (vem do getFormData() em form.js):
 * {
 *   "IDusu":    1 | null,
 *   "anonimo":  0 | 1,
 *   "nome":     "string | null",
 *   "email":    "string | null",
 *   "IDtipo":   1-5,
 *   "IDsetor":  1-N,
 *   "manifest": "texto da manifestação",
 *   "contato":  "string | null",
 *   "arquivos": []
 * }
 *
 * Respostas:
 *   201 { success: true,  protocolo: "OUV-2025-00042" }
 *   405 { success: false, message: "Método não permitido." }
 *   422 { success: false, message: "Descrição: ..." }
 *   404 { success: false, message: "Usuário não encontrado." }
 *   500 { success: false, message: "Erro interno." }
 */

declare(strict_types=1);

/* ── Headers ─────────────────────────────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/db.php';

/* ── Helpers locais ───────────────────────────────────────────────── */
function respond(bool $success, string $message, array $extra = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function getBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE) ? ($data ?? []) : [];
}

function clean(mixed $val): string
{
    return htmlspecialchars(strip_tags(trim((string)$val)), ENT_QUOTES, 'UTF-8');
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 1 — Método HTTP
   Só aceita POST. Qualquer outra coisa retorna 405.
   Isso evita que alguém abra o arquivo pelo navegador e veja erros.
══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Método não permitido.', [], 405);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 2 — Ler o body JSON
   Tudo que o form.js enviou via $.ajax chega aqui como JSON.
══════════════════════════════════════════════════════════════════ */
$body = getBody();

// Extrair campos
$anonimo  = !empty($body['anonimo']) ? 1 : 0;
$IDusuRaw = isset($body['IDusu']) ? (int)$body['IDusu'] : null;
$IDtipo   = isset($body['IDtipo'])  ? (int)$body['IDtipo']  : 0;
$IDsetor  = isset($body['IDsetor']) ? (int)$body['IDsetor'] : 0;
$manifest = clean($body['manifest'] ?? '');
$contato  = clean($body['contato']  ?? '');
$nome     = clean($body['nome']     ?? '');
$email    = clean($body['email']    ?? '');

/* ══════════════════════════════════════════════════════════════════
   BLOCO 3 — Validação dos campos obrigatórios
   IDtipo, IDsetor e manifest são os únicos obrigatórios para
   qualquer manifestação (identificada ou anônima).
══════════════════════════════════════════════════════════════════ */
$erros = [];

if ($IDtipo  <= 0) $erros[] = 'Tipo de manifestação inválido.';
if ($IDsetor <= 0) $erros[] = 'Setor inválido.';
if (mb_strlen($manifest) < 10) $erros[] = 'A descrição deve ter ao menos 10 caracteres.';
if (mb_strlen($manifest) > 5000) $erros[] = 'A descrição excede o limite de 5000 caracteres.';

if (!empty($erros)) {
    respond(false, implode(' | ', $erros), [], 422);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 4 — Conexão com o banco
   Feita aqui (após validação) para não abrir conexão à toa
   em requisições inválidas.
══════════════════════════════════════════════════════════════════ */
try {
    $pdo = Database::connect();
} catch (Throwable $e) {
    error_log('[MANIFEST] Conexão falhou: ' . $e->getMessage());
    respond(false, 'Erro ao conectar ao banco de dados.', [], 500);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 5 — Verificar se IDtipo e IDsetor existem no banco
   Evita gravar FKs inválidas que quebrariam a integridade.
══════════════════════════════════════════════════════════════════ */
try {
    // Checar tipo
    $stmtTipo = $pdo->prepare('SELECT IDtipo FROM tipos WHERE IDtipo = ? LIMIT 1');
    $stmtTipo->execute([$IDtipo]);
    if (!$stmtTipo->fetch()) {
        respond(false, 'Tipo de manifestação não encontrado.', [], 422);
    }

    // Checar setor
    $stmtSetor = $pdo->prepare('SELECT IDsetor FROM tbsetores WHERE IDsetor = ? AND ativo = 1 LIMIT 1');
    $stmtSetor->execute([$IDsetor]);
    if (!$stmtSetor->fetch()) {
        respond(false, 'Setor não encontrado ou inativo.', [], 422);
    }
} catch (PDOException $e) {
    error_log('[MANIFEST] Erro ao checar FK: ' . $e->getMessage());
    respond(false, 'Erro ao validar dados.', [], 500);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 6 — Garantia de anonimato (regra de segurança backend)

   IMPORTANTE: o backend SEMPRE decide o IDusu final.
   Mesmo que alguém manipule o JS e mande IDusu=5 com anonimo=1,
   o PHP vai ignorar o IDusu e gravar NULL.

   Fluxo:
     anonimo=1              → IDusu=NULL, contato=NULL (sem exceção)
     anonimo=0, IDusu veio  → confirma sessão + existência no banco
     anonimo=0, IDusu=null  → manifestação identificada sem login
                               (nome e email vieram no body)
══════════════════════════════════════════════════════════════════ */
$IDusuFinal  = null;
$contatoFinal = null;

if ($anonimo === 1) {
    // Anônima: zera tudo — não importa o que veio no body
    $IDusuFinal   = null;
    $contatoFinal = null;

} else {
    // Identificada
    $contatoFinal = !empty($contato) ? $contato : null;

    if ($IDusuRaw > 0) {
        // Tem IDusu — confirmar sessão PHP e existência no banco
        // Abre sessão para checar se o IDusu da sessão bate com o enviado
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
            session_start();
        }

        $sessaoId = isset($_SESSION['IDusu']) ? (int)$_SESSION['IDusu'] : 0;

        // Se o IDusu do body não bate com a sessão ativa, ignora
        // (tentativa de falsificar identidade)
        if ($sessaoId !== $IDusuRaw) {
            // Grava como se fosse sem login — não rejeita, apenas não vincula
            $IDusuFinal = null;
            error_log("[MANIFEST] IDusu {$IDusuRaw} rejeitado — sessão ativa é {$sessaoId}");
        } else {
            // Confirmar no banco que o usuário existe e está ativo
            try {
                $stmtUsu = $pdo->prepare(
                    'SELECT IDusu FROM tbusuarios WHERE IDusu = ? AND ativo = 1 LIMIT 1'
                );
                $stmtUsu->execute([$IDusuRaw]);
                if ($stmtUsu->fetch()) {
                    $IDusuFinal = $IDusuRaw;
                } else {
                    // Conta desativada entre o login e o envio
                    $IDusuFinal = null;
                    error_log("[MANIFEST] IDusu {$IDusuRaw} não encontrado ou inativo no banco.");
                }
            } catch (PDOException $e) {
                error_log('[MANIFEST] Erro ao checar usuário: ' . $e->getMessage());
                $IDusuFinal = null;
            }
        }

        // Se mesmo assim o contato está vazio, usa o email da sessão/body
        if (empty($contatoFinal) && !empty($email)) {
            $contatoFinal = $email;
        }
    }
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 7 — Gerar número de protocolo único
   Formato: OUV-AAAA-NNNNN
   O número sequencial é baseado no AUTO_INCREMENT atual da tabela,
   garantindo unicidade mesmo com múltiplas requisições simultâneas.
   Zero-padding de 5 dígitos: OUV-2025-00001, OUV-2025-00042...
══════════════════════════════════════════════════════════════════ */
try {
    // Pega o próximo ID que será gerado (AUTO_INCREMENT atual)
    $stmtAI = $pdo->query(
        "SELECT AUTO_INCREMENT
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'tbmanifest'
          LIMIT 1"
    );
    $nextId = (int)($stmtAI->fetchColumn() ?? 1);
} catch (PDOException $e) {
    // Fallback: usa timestamp para garantir unicidade mesmo se falhar
    $nextId = (int)(microtime(true) * 100) % 99999;
    error_log('[MANIFEST] Fallback de protocolo: ' . $e->getMessage());
}

$ano      = date('Y');
$protocolo = sprintf('OUV-%s-%05d', $ano, $nextId);

/* ══════════════════════════════════════════════════════════════════
   BLOCO 8 — Inserir no banco
   Todos os campos mapeados para as colunas reais de tbmanifest.
   IDadm fica NULL — será atribuído pelo Grêmio no painel deles.
   STATUS começa sempre como 'Aberta'.
══════════════════════════════════════════════════════════════════ */
try {
    $insert = $pdo->prepare(
        'INSERT INTO tbmanifest
            (protocolo, IDusu, IDadm, IDtipo, IDsetor, manifest,
             STATUS, anonimo, contato, criado_em)
         VALUES
            (:protocolo, :IDusu, NULL, :IDtipo, :IDsetor, :manifest,
             :status, :anonimo, :contato, NOW())'
    );

    $insert->execute([
        ':protocolo' => $protocolo,
        ':IDusu'     => $IDusuFinal,   // null se anônimo ou não confirmado
        ':IDtipo'    => $IDtipo,
        ':IDsetor'   => $IDsetor,
        ':manifest'  => $manifest,
        ':status'    => 'Aberta',
        ':anonimo'   => $anonimo,
        ':contato'   => $contatoFinal, // null se anônimo
    ]);

    $IDmanifest = (int)$pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('[MANIFEST] Erro ao inserir: ' . $e->getMessage());

    // Tratar violação de chave única do protocolo (rarissimo mas possível)
    if (str_contains($e->getMessage(), 'uq_protocolo')) {
        respond(false, 'Protocolo duplicado. Tente novamente.', [], 500);
    }

    respond(false, 'Não foi possível registrar a manifestação. Tente novamente.', [], 500);
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 9 — Log de auditoria (opcional mas recomendado)
   Registra o IP de quem enviou para fins de segurança.
   Não vincula ao usuário em manifestações anônimas.
══════════════════════════════════════════════════════════════════ */
try {
    $pdo->prepare(
        'INSERT INTO log_acesso (usuario_id, acao, ip, user_agent, criado_em)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([
        $IDusuFinal,                         // null se anônimo
        'manifestacao:' . $IDmanifest,
        $_SERVER['REMOTE_ADDR']     ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
} catch (PDOException) {
    // Log falhou — não impede o fluxo principal
}

/* ══════════════════════════════════════════════════════════════════
   BLOCO 10 — Resposta de sucesso
   O ajax.js recebe o protocolo e repassa para o form.js
   exibir na tela de confirmação (step-success).
══════════════════════════════════════════════════════════════════ */
respond(true, 'Manifestação registrada com sucesso.', [
    'protocolo'   => $protocolo,
    'IDmanifest'  => $IDmanifest,
], 201);

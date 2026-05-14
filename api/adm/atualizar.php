<?php
/**
 * api/adm/atualizar.php — Ver detalhe e atualizar manifestação
 * Ouvidoria Escolar
 *
 * GET  ?id=123           → retorna todos os dados da manifestação
 * POST { IDmanifest, status?, feedback? } → atualiza status e/ou feedback
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/session_check.php';

// ════════════════════════════════════════════════════════════
// GET — detalhe completo de uma manifestação
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'ID inválido.', [], 422);

    try {
        $pdo  = Database::connect();
        $stmt = $pdo->prepare("
            SELECT
                m.IDmanifest,
                m.protocolo,
                m.anonimo,
                m.STATUS,
                m.manifest,
                m.feedback,
                m.contato,
                m.criado_em,
                m.atualizado_em,
                t.descricao                              AS tipo,
                s.nome                                   AS setor,
                CASE WHEN m.anonimo = 1 THEN 'Anônimo'
                     ELSE u.nome END                     AS autor_nome,
                CASE WHEN m.anonimo = 1 THEN NULL
                     ELSE u.email END                    AS autor_email,
                CASE WHEN m.anonimo = 1 THEN NULL
                     ELSE u.serie END                    AS autor_serie,
                CASE WHEN m.anonimo = 1 THEN NULL
                     ELSE u.curso END                    AS autor_curso,
                a.nome                                   AS atendente,
                a.IDadm                                  AS IDadm_atual
            FROM tbmanifest m
            LEFT JOIN tbusuarios u ON m.IDusu   = u.IDusu
            LEFT JOIN tbadm      a ON m.IDadm   = a.IDadm
            LEFT JOIN tipos      t ON m.IDtipo  = t.IDtipo
            LEFT JOIN tbsetores  s ON m.IDsetor = s.IDsetor
            WHERE m.IDmanifest = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $manifest = $stmt->fetch();

        if (!$manifest) jsonResponse(false, 'Manifestação não encontrada.', [], 404);

        // Busca arquivos anexados
        $stmtArq = $pdo->prepare("
            SELECT id, nome_original, mime_type, tamanho, criado_em
            FROM tbmanifest_arquivos
            WHERE IDmanifest = :id
            ORDER BY criado_em ASC
        ");
        $stmtArq->execute([':id' => $id]);
        $arquivos = $stmtArq->fetchAll();

        jsonResponse(true, 'ok', [
            'manifestacao' => $manifest,
            'arquivos'     => $arquivos,
        ]);

    } catch (PDOException $e) {
        error_log('[ADM ATUALIZAR GET] Erro BD: ' . $e->getMessage());
        jsonResponse(false, 'Erro ao carregar manifestação.', [], 500);
    }
}

// ════════════════════════════════════════════════════════════
// POST — atualizar status e/ou feedback
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body       = getJsonBody();
    $id         = (int) ($body['IDmanifest'] ?? 0);
    $novoStatus = $body['status']   ?? null;
    $feedback   = $body['feedback'] ?? null;

    if ($id <= 0) jsonResponse(false, 'ID inválido.', [], 422);

    $statusValidos = ['Aberta', 'Em análise', 'Respondida', 'Encerrada'];
    if ($novoStatus !== null && !in_array($novoStatus, $statusValidos, true)) {
        jsonResponse(false, 'Status inválido.', [], 422);
    }

    if ($feedback !== null && mb_strlen(trim($feedback)) > 5000) {
        jsonResponse(false, 'Feedback muito longo (máx. 5000 caracteres).', [], 422);
    }

    try {
        $pdo = Database::connect();

        // Verifica se existe
        $check = $pdo->prepare('SELECT IDmanifest, STATUS FROM tbmanifest WHERE IDmanifest = :id LIMIT 1');
        $check->execute([':id' => $id]);
        $atual = $check->fetch();
        if (!$atual) jsonResponse(false, 'Manifestação não encontrada.', [], 404);

        // Monta SET dinamicamente (só atualiza o que foi enviado)
        $sets   = ['IDadm = :IDadm'];  // sempre vincula o atendente
        $params = [':id' => $id, ':IDadm' => $adminSession['IDadm']];

        if ($novoStatus !== null) {
            $sets[]           = 'STATUS = :status';
            $params[':status'] = $novoStatus;
        }

        if ($feedback !== null) {
            $sets[]            = 'feedback = :feedback';
            $params[':feedback'] = trim($feedback);
        }

        $setSql = implode(', ', $sets);
        $pdo->prepare("UPDATE tbmanifest SET $setSql WHERE IDmanifest = :id")
            ->execute($params);

        // Log da ação
        try {
            $pdo->prepare("
                INSERT INTO log_acesso (usuario_id, acao, ip, user_agent)
                VALUES (NULL, :acao, :ip, :ua)
            ")->execute([
                ':acao' => 'adm:atualizar:' . $id . ':' . ($novoStatus ?? 'feedback'),
                ':ip'   => $_SERVER['REMOTE_ADDR']     ?? null,
                ':ua'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (PDOException) { /* log não bloqueia */ }

        jsonResponse(true, 'Manifestação atualizada com sucesso.');

    } catch (PDOException $e) {
        error_log('[ADM ATUALIZAR POST] Erro BD: ' . $e->getMessage());
        jsonResponse(false, 'Erro ao atualizar. Tente novamente.', [], 500);
    }
}

// Método não suportado
jsonResponse(false, 'Método não permitido.', [], 405);

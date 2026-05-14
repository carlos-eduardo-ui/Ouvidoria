<?php
/**
 * api/adm/stats.php — Estatísticas para o dashboard admin
 * Ouvidoria Escolar
 *
 * GET /api/adm/stats.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/session_check.php';

requireMethod('GET');

try {
    $pdo = Database::connect();

    // ── Totais por status ─────────────────────────────────────
    $stmtStatus = $pdo->query("
        SELECT
            COUNT(*)                       AS total,
            SUM(STATUS = 'Aberta')         AS abertas,
            SUM(STATUS = 'Em análise')     AS em_analise,
            SUM(STATUS = 'Respondida')     AS respondidas,
            SUM(STATUS = 'Encerrada')      AS encerradas
        FROM tbmanifest
    ");
    $status = $stmtStatus->fetch();

    $total         = (int) $status['total'];
    $resolvidas    = (int) $status['respondidas'] + (int) $status['encerradas'];
    $taxaResolucao = $total > 0 ? round($resolvidas / $total * 100, 1) : 0;

    // ── Média de dias para resolver ───────────────────────────
    $stmtMedia = $pdo->query("
        SELECT ROUND(AVG(DATEDIFF(atualizado_em, criado_em)), 1) AS media_dias
        FROM tbmanifest
        WHERE STATUS IN ('Respondida', 'Encerrada')
    ");
    $mediaDias = (float) ($stmtMedia->fetchColumn() ?? 0);

    // ── Por tipo ──────────────────────────────────────────────
    $stmtTipo = $pdo->query("
        SELECT t.descricao AS tipo, COUNT(*) AS total
        FROM tbmanifest m
        JOIN tipos t ON m.IDtipo = t.IDtipo
        GROUP BY m.IDtipo, t.descricao
        ORDER BY total DESC
    ");
    $porTipo = $stmtTipo->fetchAll();

    // ── Por setor ─────────────────────────────────────────────
    $stmtSetor = $pdo->query("
        SELECT
            COALESCE(s.nome, 'Não informado') AS setor,
            COUNT(*) AS total
        FROM tbmanifest m
        LEFT JOIN tbsetores s ON m.IDsetor = s.IDsetor
        GROUP BY m.IDsetor, s.nome
        ORDER BY total DESC
    ");
    $porSetor = $stmtSetor->fetchAll();

    // ── Últimos 30 dias ───────────────────────────────────────
    $stmtDias = $pdo->query("
        SELECT
            DATE_FORMAT(criado_em, '%Y-%m-%d') AS data,
            COUNT(*) AS total
        FROM tbmanifest
        WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(criado_em)
        ORDER BY data ASC
    ");
    $ultimos30 = $stmtDias->fetchAll();

    // ── adm_nome lido da sessão (corrigido) ───────────────────
    // O dashboard usa data.adm_nome para exibir o nome no sidebar
    jsonResponse(true, 'ok', [
        'adm_nome'       => $adminSession['nome'],   // ← BUG CORRIGIDO
        'total'          => $total,
        'abertas'        => (int) $status['abertas'],
        'em_analise'     => (int) $status['em_analise'],
        'respondidas'    => (int) $status['respondidas'],
        'encerradas'     => (int) $status['encerradas'],
        'taxa_resolucao' => $taxaResolucao,
        'media_dias'     => $mediaDias,
        'por_tipo'       => $porTipo,
        'por_setor'      => $porSetor,
        'ultimos_30_dias'=> $ultimos30,
    ]);

} catch (PDOException $e) {
    error_log('[ADM STATS] Erro BD: ' . $e->getMessage());
    jsonResponse(false, 'Erro ao carregar estatísticas.', [], 500);
}
<?php
/**
 * api/adm/manifestacoes.php — Listagem de manifestações para o admin
 * Ouvidoria Escolar
 *
 * GET /api/adm/manifestacoes.php
 * Parâmetros opcionais:
 *   status  → filtrar por status (Aberta, Em análise, Respondida, Encerrada)
 *   tipo    → filtrar por IDtipo
 *   setor   → filtrar por IDsetor
 *   busca   → buscar por protocolo ou trecho da manifestação
 *   pagina  → página atual (default 1)
 *   por_pag → resultados por página (default 20)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/session_check.php';

requireMethod('GET');

// ── Parâmetros de filtro e paginação ──────────────────────────
$status  = $_GET['status']  ?? '';
$tipo    = (int) ($_GET['tipo']    ?? 0);
$setor   = (int) ($_GET['setor']   ?? 0);
$busca   = trim($_GET['busca']     ?? '');
$pagina  = max(1, (int) ($_GET['pagina']  ?? 1));
$porPag  = min(50, max(5, (int) ($_GET['por_pag'] ?? 20)));
$offset  = ($pagina - 1) * $porPag;

// ── Monta WHERE dinamicamente ─────────────────────────────────
$where  = [];
$params = [];

$statusValidos = ['Aberta', 'Em análise', 'Respondida', 'Encerrada'];
if ($status && in_array($status, $statusValidos, true)) {
    $where[]  = 'm.STATUS = :status';
    $params[':status'] = $status;
}

if ($tipo > 0) {
    $where[]  = 'm.IDtipo = :tipo';
    $params[':tipo'] = $tipo;
}

if ($setor > 0) {
    $where[]  = 'm.IDsetor = :setor';
    $params[':setor'] = $setor;
}

if ($busca !== '') {
    $where[]  = '(m.protocolo LIKE :busca OR m.manifest LIKE :busca2)';
    $params[':busca']  = '%' . $busca . '%';
    $params[':busca2'] = '%' . $busca . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $pdo = Database::connect();

    // Total para paginação
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tbmanifest m $whereSql");
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    // Listagem
    $sql = "
        SELECT
            m.IDmanifest,
            m.protocolo,
            m.anonimo,
            m.STATUS,
            m.criado_em,
            m.atualizado_em,
            t.descricao                                  AS tipo,
            s.nome                                       AS setor,
            CASE WHEN m.anonimo = 1 THEN 'Anônimo'
                 ELSE u.nome END                         AS autor,
            a.nome                                       AS atendente,
            LEFT(m.manifest, 120)                        AS resumo
        FROM tbmanifest m
        LEFT JOIN tbusuarios u ON m.IDusu   = u.IDusu
        LEFT JOIN tbadm      a ON m.IDadm   = a.IDadm
        LEFT JOIN tipos      t ON m.IDtipo  = t.IDtipo
        LEFT JOIN tbsetores  s ON m.IDsetor = s.IDsetor
        $whereSql
        ORDER BY
            FIELD(m.STATUS, 'Aberta', 'Em análise', 'Respondida', 'Encerrada'),
            m.criado_em DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $porPag, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $manifestacoes = $stmt->fetchAll();

    jsonResponse(true, 'ok', [
        'manifestacoes' => $manifestacoes,
        'paginacao'     => [
            'total'       => $total,
            'pagina'      => $pagina,
            'por_pagina'  => $porPag,
            'total_pag'   => (int) ceil($total / $porPag),
        ],
    ]);

} catch (PDOException $e) {
    error_log('[ADM MANIFEST] Erro BD: ' . $e->getMessage());
    jsonResponse(false, 'Erro ao carregar manifestações.', [], 500);
}

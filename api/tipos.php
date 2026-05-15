<?php
/**
 * api/tipos.php — Retorna os tipos de manifestação
 * Ouvidoria Escolar
 *
 * GET /api/tipos.php
 * Retorna: { success: true, tipos: [ { IDtipo, descricao } ] }
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/cors.php';

header('Cache-Control: public, max-age=3600'); // cache de 1h — raramente muda

try {
    $pdo  = Database::connect();
    $stmt = $pdo->query('SELECT IDtipo, descricao FROM tipos ORDER BY IDtipo ASC');
    $tipos = $stmt->fetchAll();

    jsonResponse(true, 'ok', ['tipos' => $tipos]);

} catch (PDOException $e) {
    error_log('[TIPOS] Erro BD: ' . $e->getMessage());
    jsonResponse(false, 'Erro ao carregar tipos.', [], 500);
}
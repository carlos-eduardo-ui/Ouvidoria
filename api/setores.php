<?php
/**
 * setores.php — Retorna os setores ativos da escola
 * Ouvidoria Escolar — Grêmio Estudantil
 *
 * GET /api/setores.php
 *
 * Resposta:
 *   { "success": true, "setores": [ { "IDsetor": 1, "nome": "Direção" }, ... ] }
 *
 * Chamado pelo form.js para montar o <select> de setores
 * dinamicamente — sem hardcode no HTML.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=300'); // cache 5 min — setores mudam pouco

require_once __DIR__ . '/config/db.php';

try {
    $pdo  = Database::connect();
    $stmt = $pdo->query(
        'SELECT IDsetor, nome, descricao
           FROM tbsetores
          WHERE ativo = 1
          ORDER BY nome ASC'
    );
    $setores = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'setores' => $setores,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[SETORES] Erro DB: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'setores' => [],
        'message' => 'Erro ao carregar setores.',
    ], JSON_UNESCAPED_UNICODE);
}

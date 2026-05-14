<?php
/**
 * api/adm/me.php — Verifica sessão admin e retorna dados básicos
 * Ouvidoria Escolar
 *
 * GET /api/adm/me.php
 * Retorna 401 se não autenticado, 200 com dados do admin se autenticado.
 * Usado pelo frontend para verificar sessão ao carregar as páginas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/session_check.php';

requireMethod('GET');

jsonResponse(true, 'ok', [
    'IDadm' => $adminSession['IDadm'],
    'nome'  => $adminSession['nome'],
    'email' => $adminSession['email'],
]);
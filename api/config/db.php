<?php
/**
 * api/config/db.php — Wrapper singleton para conexão PDO
 * Ouvidoria Escolar
 *
 * Credenciais lidas exclusivamente do arquivo .env via env.php.
 * NUNCA escreva credenciais diretamente neste arquivo.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {

            $host   = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
            $port   = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
            $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'dbouvidoria';
            $user   = array_key_exists('DB_USER', $_ENV) ? $_ENV['DB_USER'] : (getenv('DB_USER') ?: null);
            $pass   = array_key_exists('DB_PASS', $_ENV) ? $_ENV['DB_PASS'] : (getenv('DB_PASS') ?: '');

            if ($user === null) {
                error_log('[Ouvidoria] Credenciais não configuradas. Defina DB_USER no arquivo .env');
                http_response_code(500);
                die(json_encode(['error' => 'Erro interno de configuração.']));
            }

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
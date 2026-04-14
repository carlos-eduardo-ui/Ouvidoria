<?php
/**
 * config/db.php — Wrapper singleton para conexão PDO
 * Ouvidoria Escolar
 */

class Database
{
    private static string $host   = '127.0.0.1';
    private static string $dbname = 'dbouvidoria';
    private static string $user   = 'root';
    private static string $pass   = '';
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . self::$host
                 . ";dbname=" . self::$dbname
                 . ";charset=utf8mb4";

            self::$instance = new PDO($dsn, self::$user, self::$pass, [
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

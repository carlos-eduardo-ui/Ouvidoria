<?php
/**
 * api/config/env.php — Carrega variáveis do arquivo .env
 * Ouvidoria Escolar
 *
 * Incluído por db.php e cors.php para garantir que as variáveis
 * de ambiente estejam disponíveis independente da ordem de require.
 * Usa flag estática para não reler o arquivo em múltiplos includes.
 */

declare(strict_types=1);

(static function (): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    // Sobe da pasta api/config/ até a raiz do projeto
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!file_exists($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if ($key === '') continue;
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
})();
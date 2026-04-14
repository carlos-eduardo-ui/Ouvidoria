<?php
/**
 * gerar_hash.php — Gera hash bcrypt para cadastro de senha segura
 * Ouvidoria Escolar — Grêmio Estudantil
 *
 * USO NO TERMINAL:
 *   php db/gerar_hash.php SuaSenhaAqui
 *
 * USO NO NAVEGADOR (apenas em ambiente local):
 *   http://localhost/ouvidoria/db/gerar_hash.php?senha=SuaSenhaAqui
 *
 * IMPORTANTE: Delete ou bloqueie este arquivo em produção.
 */

// Bloquear acesso em produção
if (isset($_SERVER['HTTP_HOST']) && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Acesso negado. Este script só pode ser usado localmente.');
}

// Pegar senha via CLI ou GET
$senha = $argv[1] ?? ($_GET['senha'] ?? '');

if (empty($senha)) {
    $uso = PHP_SAPI === 'cli'
        ? "Uso: php gerar_hash.php SuaSenhaAqui\n"
        : "<p>Uso: ?senha=SuaSenhaAqui</p>";
    die($uso);
}

if (strlen($senha) < 8) {
    die("ERRO: A senha deve ter pelo menos 8 caracteres.\n");
}

$hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

if (PHP_SAPI === 'cli') {
    echo "\n";
    echo "Senha:  {$senha}\n";
    echo "Hash:   {$hash}\n";
    echo "\n";
    echo "Cole o hash no migration.sql:\n";
    echo "INSERT INTO tbadm (nome, cargo, email, senha) VALUES\n";
    echo "  ('Grêmio Estudantil', 'Administrador Geral', 'gremio@escola.edu.br', '{$hash}');\n\n";
} else {
    echo "<pre>";
    echo "Senha:  {$senha}\n";
    echo "Hash:   {$hash}\n\n";
    echo "SQL pronto para colar:\n";
    echo htmlspecialchars(
        "INSERT INTO tbadm (nome, cargo, email, senha) VALUES\n" .
        "  ('Grêmio Estudantil', 'Administrador Geral', 'gremio@escola.edu.br', '{$hash}');"
    );
    echo "</pre>";
}
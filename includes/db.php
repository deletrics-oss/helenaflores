<?php
// catalogo/includes/db.php
require_once __DIR__ . "/../config.php";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Em produção, não mostre o erro real para o usuário
    error_log("Erro de conexão DB: " . $e->getMessage());
    http_response_code(500);
    die("<h1>Erro no sistema</h1><p>Não foi possível conectar ao banco de dados. Verifique o arquivo config.php.</p>");
}
?>

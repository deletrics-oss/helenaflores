<?php
// update_schema_newsletter.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Atualizando Banco de Dados (Newsletter) 📧</h1><pre>";

try {
    // Tabela de Assinantes
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Tabela 'newsletter_subscribers' verificada/criada.\n";

} catch (Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n--- Concluído ---</pre>";
?>
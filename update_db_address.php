<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Atualização de Banco de Dados: Endereços</h1>";

try {
    $sql = "CREATE TABLE IF NOT EXISTS `user_addresses` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `name` varchar(50) NOT NULL,
      `recipient_name` varchar(100) NOT NULL,
      `zipcode` varchar(10) NOT NULL,
      `address` varchar(255) NOT NULL,
      `number` varchar(20) NOT NULL,
      `complement` varchar(100) DEFAULT NULL,
      `neighborhood` varchar(100) NOT NULL,
      `city` varchar(100) NOT NULL,
      `state` varchar(2) NOT NULL,
      `is_default` tinyint(1) DEFAULT 0,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "<h2 style='color:green'>✅ Tabela 'user_addresses' criada/verificada com sucesso!</h2>";
    echo "<p>Agora você pode acessar a página 'Meus Endereços' normalmente.</p>";
    echo "<a href='my-addresses.php'>Ir para Meus Endereços</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Erro: " . $e->getMessage() . "</h2>";
}
?>
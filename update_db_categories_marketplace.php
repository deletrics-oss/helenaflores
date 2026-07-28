<?php
/**
 * Atualização do Banco para Mapeamento de Categorias Marketplace
 * 
 * Cria tabela para mapear categorias internas -> IDs do Mercado Livre e Shopee
 */
require_once 'includes/db.php';

echo "<h1>🔧 Criando Tabela de Mapeamento de Categorias...</h1>";
echo "<pre>";

// Cria tabela de mapeamento
$sql = "CREATE TABLE IF NOT EXISTS marketplace_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL COMMENT 'ID da categoria interna',
    marketplace VARCHAR(50) NOT NULL COMMENT 'shopee ou mercadolivre',
    marketplace_category_id VARCHAR(50) NOT NULL COMMENT 'ID da categoria no marketplace',
    marketplace_category_name VARCHAR(255) NULL COMMENT 'Nome para referência',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_mapping (category_id, marketplace),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($sql);
    echo "✅ Tabela marketplace_categories criada!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⏭️ Tabela já existe.\n";
    } else {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}

// Insere alguns mapeamentos de exemplo baseados nas categorias existentes
$categorias = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);

echo "\n📋 Categorias encontradas:\n";
foreach ($categorias as $cat) {
    echo "  - [{$cat['id']}] {$cat['name']}\n";
}

// Exemplos de IDs do Mercado Livre (você pode ajustar)
// Esses IDs são fictícios - você precisa pegar os reais na Central do ML
$exemplos = [
    'Botões' => ['ml' => 'MLB1039', 'shopee' => '100636'],
    'Placas & Interfaces' => ['ml' => 'MLB1648', 'shopee' => '100636'],
    'Joysticks' => ['ml' => 'MLB1039', 'shopee' => '100636'],
    'Cabos' => ['ml' => 'MLB1039', 'shopee' => '100636'],
    'Outros' => ['ml' => 'MLB1039', 'shopee' => '100636'],
];

echo "\n💡 Para adicionar mapeamentos, acesse: admin/marketplace_categories.php\n";

echo "</pre>";
echo "<h2>✅ Concluído!</h2>";
echo "<p><a href='admin/marketplace_categories.php'>📂 Gerenciar Categorias Marketplace</a></p>";
echo "<p><a href='admin/products.php'>👉 Ir para Produtos</a></p>";
?>
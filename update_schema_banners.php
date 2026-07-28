<?php
// update_schema_banners.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Atualizando Banco de Dados (Banners) 🖼️</h1><pre>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        title VARCHAR(100),
        subtitle VARCHAR(255),
        link_url VARCHAR(255),
        display_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Tabela 'banners' criada/verificada.\n";

    // Insert Default Banners if empty
    $count = $pdo->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count == 0) {
        $defaults = [
            ['assets/banners/banner1.png', 'Controles Arcade Profissionais', 'Precisão de campeonato. Acabamento premium.', '?cat=1'],
            ['assets/banners/banner2.png', 'Botões & Iluminação LED', 'Personalize seu setup.', '?cat=2'],
            ['assets/banners/banner3.png', 'Kits DIY Completos', 'Monte sua própria máquina.', '?cat=3']
        ];
        $stmt = $pdo->prepare("INSERT INTO banners (image_path, title, subtitle, link_url, active) VALUES (?, ?, ?, ?, 1)");
        foreach ($defaults as $banner) {
            $stmt->execute($banner);
        }
        echo "✅ Banners padrão inseridos.\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n--- Concluído ---</pre>";
?>
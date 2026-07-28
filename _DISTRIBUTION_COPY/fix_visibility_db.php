<?php
require_once 'includes/db.php';

echo "<h1>🔍 Diagnóstico e Reparo do Banco de Dados</h1>";

$tables = ['products', 'categories'];
$required_cols = [
    'products' => ['show_on_site' => "INT DEFAULT 1", 'allow_export' => "INT DEFAULT 1"],
    'categories' => ['show_on_site' => "INT DEFAULT 1"]
];

foreach ($tables as $table) {
    echo "<h2>Tabela: $table</h2>";
    try {
        $stmt = $pdo->query("DESC $table");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($required_cols[$table] as $col => $def) {
            if (!in_array($col, $cols)) {
                echo "<p style='color:orange;'>⚠️ Coluna '$col' ausente. Criando...</p>";
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
                echo "<p style='color:green;'>✅ Coluna '$col' criada com sucesso.</p>";
            } else {
                echo "<p style='color:green;'>✅ Coluna '$col' já existe.</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Erro ao processar tabela $table: " . $e->getMessage() . "</p>";
    }
}

echo "<hr><p>🍀 Se as colunas acima estão marcadas como verdes, o site deve voltar ao normal.</p>";
echo "<p><a href='index.php'>Voltar ao Início</a> | <a href='admin/products.php'>Ir para Admin</a></p>";

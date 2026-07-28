<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste SimpleXLSXGen</h1>";

$libPath = __DIR__ . '/../includes/SimpleXLSXGen.php';
echo "Tentando incluir: $libPath <br>";

if (!file_exists($libPath)) {
    die("❌ Arquivo não encontrado!");
} else {
    echo "✅ Arquivo encontrado.<br>";
}

require_once $libPath;
echo "✅ Biblioteca incluída.<br>";

if (!class_exists('SimpleXLSXGen')) {
    die("❌ Classe SimpleXLSXGen não existe!");
} else {
    echo "✅ Classe SimpleXLSXGen carregada.<br>";
}

// Teste de geração
try {
    $rows = [
        ['Teste ID', 'Nome'],
        ['1', 'Produto Teste']
    ];
    $xlsx = SimpleXLSXGen::fromArray($rows);
    echo "✅ Objeto criado.<br>";

    // Salvar em disco temporário para não forçar download e ver erro
    $tempFile = __DIR__ . '/test_output.xlsx';
    $xlsx->saveAs($tempFile);
    echo "✅ Arquivo salvo em: $tempFile <br>";
    echo "Sucesso! <a href='test_output.xlsx'>Baixar Teste</a>";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
<?php
require_once __DIR__ . '/../config.php';
session_start();
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Manual do PDV | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .manual-container {
            max-width: 900px;
            margin: 2rem auto;
            background: #1a1e26;
            padding: 2.5rem;
            border-radius: 15px;
            border: 1px solid #333;
            line-height: 1.6;
        }

        h1 {
            color: #f1c40f;
            margin-bottom: 2rem;
            border-bottom: 2px solid #333;
            padding-bottom: 1rem;
        }

        h2 {
            color: #4cc9f0;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .step {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            align-items: flex-start;
        }

        .step-num {
            background: #f1c40f;
            color: #000;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .shortcut {
            background: #333;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: #f1c40f;
            border: 1px solid #444;
        }

        .tip {
            background: rgba(76, 201, 240, 0.1);
            border-left: 4px solid #4cc9f0;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>

<body>
    <div class="manual-container">
        <h1>📑 Manual de Operação - Frente de Loja (PDV)</h1>

        <p>Este sistema foi projetado para vendas rápidas em balcão ou eventos, sincronizando automaticamente o estoque
            com o catálogo online.</p>

        <div class="step">
            <div class="step-num">1</div>
            <div>
                <h2>Busca de Produtos</h2>
                <p>Clique no campo de busca ou pressione <span class="shortcut">F2</span>. Digite o nome do produto ou
                    use um leitor de código de barras no SKU.</p>
                <p>Os resultados aparecerão instantaneamente. Clique no produto para adicioná-lo ao carrinho.</p>
            </div>
        </div>

        <div class="step">
            <div class="step-num">2</div>
            <div>
                <h2>Gestão do Carrinho</h2>
                <p>No lado direito, você verá os itens selecionados. Você pode alterar a quantidade usando os botões
                    <span class="shortcut">+</span> e <span class="shortcut">-</span>.</p>
                <p>O total é atualizado em tempo real no rodapé do carrinho.</p>
            </div>
        </div>

        <div class="step">
            <div class="step-num">3</div>
            <div>
                <h2>Pagamento e Finalização</h2>
                <p>Selecione a forma de pagamento (Dinheiro, PIX, Cartão ou Link Pago).</p>
                <p>Pressione o botão <strong>FINALIZAR VENDA</strong> ou a tecla de atalho <span
                        class="shortcut">F8</span>.</p>
                <div class="tip">
                    <strong>Atenção:</strong> Ao finalizar, o sistema irá deduzir as quantidades do estoque
                    imediatamente e gerar uma entrada no log de movimentações.
                </div>
            </div>
        </div>

        <h2 style="color: #f1c40f;">⌨️ Atalhos de Teclado</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <tr style="border-bottom: 1px solid #333;">
                <td style="padding: 10px;"><span class="shortcut">F2</span></td>
                <td style="padding: 10px;">Foca o cursor no campo de Busca</td>
            </tr>
            <tr style="border-bottom: 1px solid #333;">
                <td style="padding: 10px;"><span class="shortcut">F8</span></td>
                <td style="padding: 10px;">Finaliza a Venda Atual</td>
            </tr>
        </table>

        <div style="margin-top: 3rem; text-align: center;">
            <a href="pos.php" class="btn"
                style="background:#f1c40f; color:#000; padding: 10px 30px; text-decoration:none; border-radius:5px; font-weight:bold;">IR
                PARA O PDV</a>
        </div>
    </div>
</body>

</html>
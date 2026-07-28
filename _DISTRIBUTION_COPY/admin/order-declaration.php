<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = $_GET['id'] ?? 0;
// Fetch Order and User Data
$stmt = $pdo->prepare("SELECT o.*, u.name, u.email, u.phone, u.document, u.city, u.state, u.zipcode, u.address, u.number, u.neighborhood, u.company_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order)
    die("Pedido não encontrado");

$items = $pdo->query("SELECT * FROM order_items WHERE order_id = $id")->fetchAll();

// Fetch Store/Sender Data
$s_file = __DIR__ . '/../includes/site_settings.json';
$store = file_exists($s_file) ? json_decode(file_get_contents($s_file), true) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Declaração de Conteúdo - Pedido #
        <?php echo $id; ?>
    </title>
    <style>
        @page {
            size: A5;
            margin: 5mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            border: 1px solid #000;
            padding: 0;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            background: #fff;
        }

        .section-title {
            background: #eee;
            text-align: center;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 2px 0;
            text-transform: uppercase;
        }

        .grid-half {
            display: flex;
            width: 100%;
        }

        .grid-half>div {
            flex: 1;
            border-right: 1px solid #000;
            padding: 5px;
            box-sizing: border-box;
        }

        .grid-half>div:last-child {
            border-right: 0;
        }

        .field {
            margin-bottom: 4px;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
        }

        .field-label {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            color: #333;
            display: block;
        }

        .field-value {
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
        }

        th {
            background: #eee;
            font-size: 8px;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .declaration-text {
            padding: 10px;
            font-size: 8.5px;
            line-height: 1.3;
            text-align: justify;
            border-top: 1px solid #000;
        }

        .footer-obs {
            padding: 5px 10px;
            font-size: 8px;
            border-top: 1px solid #000;
            font-style: italic;
        }

        @media print {
            .no-print {
                display: none;
            }
        }

        .no-print {
            background: #000;
            color: #fff;
            padding: 10px 20px;
            border: 0;
            cursor: pointer;
            position: fixed;
            top: 10px;
            right: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <button class="no-print" onclick="window.print()">IMPRIMIR AGORA</button>

    <div class="container">
        <div class="header">DECLARAÇÃO DE CONTEÚDO</div>

        <div class="grid-half">
            <!-- REMETENTE -->
            <div>
                <div class="section-title">REMETENTE</div>
                <div class="field">
                    <span class="field-label">NOME:</span>
                    <span class="field-value">
                        <?php echo strtoupper($store['store_name'] ?? 'FIGHT ARCADE'); ?>
                    </span>
                </div>
                <div class="field">
                    <span class="field-label">ENDEREÇO:</span>
                    <span class="field-value">
                        <?php echo strtoupper($store['address'] ?? ''); ?>
                    </span>
                </div>
                <div class="grid-half" style="border:0; padding:0;">
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">CIDADE:</span>
                        <span class="field-value">
                            <?php echo strtoupper($store['city'] ?? 'SÃO PAULO'); ?>
                        </span>
                    </div>
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">UF:</span>
                        <span class="field-value">
                            <?php echo strtoupper($store['state'] ?? 'SP'); ?>
                        </span>
                    </div>
                </div>
                <div class="grid-half" style="border:0; padding:0;">
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">CEP:</span>
                        <span class="field-value">
                            <?php echo $store['zipcode'] ?? ''; ?>
                        </span>
                    </div>
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">CPF/CNPJ:</span>
                        <span class="field-value">
                            <?php echo $store['document'] ?? ''; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- DESTINATÁRIO -->
            <div>
                <div class="section-title">DESTINATÁRIO</div>
                <div class="field">
                    <span class="field-label">NOME:</span>
                    <span class="field-value">
                        <?php echo strtoupper($order['name']); ?>
                    </span>
                </div>
                <div class="field">
                    <span class="field-label">ENDEREÇO:</span>
                    <span class="field-value">
                        <?php echo strtoupper($order['address'] . ", " . $order['number'] . ($order['neighborhood'] ? " - " . $order['neighborhood'] : "")); ?>
                    </span>
                </div>
                <div class="grid-half" style="border:0; padding:0;">
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">CIDADE:</span>
                        <span class="field-value">
                            <?php echo strtoupper($order['city']); ?>
                        </span>
                    </div>
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">UF:</span>
                        <span class="field-value">
                            <?php echo strtoupper($order['state']); ?>
                        </span>
                    </div>
                </div>
                <div class="grid-half" style="border:0; padding:0;">
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">CEP:</span>
                        <span class="field-value">
                            <?php echo $order['zipcode']; ?>
                        </span>
                    </div>
                    <div class="field" style="border:0; padding:0;">
                        <span class="field-label">CPF/CNPJ:</span>
                        <span class="field-value">
                            <?php echo $order['document']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title">IDENTIFICAÇÃO DOS BENS</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width:30px;">ITEM</th>
                    <th>CONTEÚDO</th>
                    <th class="text-center" style="width:50px;">QUANT.</th>
                    <th class="text-right" style="width:80px;">VALOR (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalQty = 0;
                $totalVal = 0;
                foreach ($items as $idx => $item):
                    $totalQty += $item['quantity'];
                    $totalVal += ($item['price'] * $item['quantity']);
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php echo $idx + 1; ?>
                        </td>
                        <td>
                            <?php echo strtoupper($item['product_name']); ?>
                        </td>
                        <td class="text-center">
                            <?php echo $item['quantity']; ?>
                        </td>
                        <td class="text-right">
                            <?php echo number_format($item['price'], 2, ',', '.'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Linhas vazias para preencher se necessário (opcional nos Correios) -->
                <?php for ($i = count($items); $i < 4; $i++): ?>
                    <tr>
                        <td class="text-center">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="text-center">&nbsp;</td>
                        <td class="text-right">&nbsp;</td>
                    </tr>
                <?php endfor; ?>

                <tr>
                    <td colspan="2" class="text-right"><strong>TOTAIS</strong></td>
                    <td class="text-center"><strong>
                            <?php echo $totalQty; ?>
                        </strong></td>
                    <td class="text-right"><strong>
                            <?php echo number_format($totalVal, 2, ',', '.'); ?>
                        </strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right">PESO TOTAL (kg): _________</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">DECLARAÇÃO</div>
        <div class="declaration-text">
            Declaro que não me enquadro no conceito de contribuinte previsto no art. 4º da Lei Complementar nº 87/1996,
            uma vez que não realizo, com habitualidade ou em volume que caracterize intuito comercial, operações de
            circulação de mercadoria, ainda que se iniciem no exterior, ou estou dispensado da emissão da nota fiscal
            por força da legislação tributária vigente, responsabilizando-me, nos termos da lei e a quem de direito, por
            informações inverídicas.<br>
            Declaro que não envio objeto que ponha em risco o transporte aéreo, nem objeto proibido no fluxo postal,
            assumindo responsabilidade pela informação prestada, e ciente de que o descumprimento pode configurar crime,
            conforme artigo 261 do Código Penal Brasileiro. Declaro, ainda, estar ciente da lista de proibições e
            restrições, disponível no site dos Correios.
        </div>

        <div style="padding: 20px 10px 10px; text-align: center;">
            SÃO PAULO,
            <?php echo date('d'); ?> de
            <?php
            $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            echo $meses[date('n') - 1];
            ?> de
            <?php echo date('Y'); ?>
            <br><br><br>
            _______________________________________________________________<br>
            Assinatura do Declarante/Remetente
        </div>

        <div class="footer-obs">
            OBSERVAÇÃO: Constitui crime contra a ordem tributária suprimir ou reduzir tributo, ou contribuição social e
            qualquer acessório (Lei 8.137/90 Art. 1º, V).
        </div>
    </div>

</body>

</html>
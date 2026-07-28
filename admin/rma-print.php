<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'label'; // 'label' or 'declaration'

$stmt = $pdo->prepare("SELECT * FROM rma_tickets WHERE id = ?");
$stmt->execute([$id]);
$rma = $stmt->fetch();

if (!$rma) die("RMA não encontrado.");

// Store Data
$storeName = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'store_name'")->fetchColumn() ?: 'Fight Arcade';
$storeDoc = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'store_document'")->fetchColumn() ?: '';
$storeZip = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'me_from_zipcode'")->fetchColumn() ?: '79002000';
$storeAddr = "Sede Fight Arcade";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Impressão RMA #<?php echo $id; ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 0; padding: 0; }
        .no-print { background: #000; color: #fff; padding: 10px 20px; border: 0; cursor: pointer; position: fixed; top: 10px; right: 10px; border-radius: 5px; font-weight: bold; }
        @media print { .no-print { display: none; } }
        
        /* LABEL STYLE */
        .label-container { width: 10cm; height: 15cm; border: 2px solid #000; padding: 10mm; margin: 0 auto; box-sizing: border-box; }
        .label-section { margin-bottom: 20px; border-bottom: 1px dashed #000; padding-bottom: 15px; }
        .label-title { font-weight: bold; font-size: 10px; text-transform: uppercase; margin-bottom: 5px; }
        .label-name { font-size: 16px; font-weight: 800; margin-bottom: 5px; }
        .label-addr { font-size: 13px; line-height: 1.4; }
        
        /* DECLARATION STYLE */
        .dec-container { width: 100%; border: 1px solid #000; padding: 0; box-sizing: border-box; }
        .dec-header { text-align: center; font-weight: bold; font-size: 16px; border-bottom: 2px solid #000; padding: 10px 0; }
        .dec-section-title { background: #eee; text-align: center; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; text-transform: uppercase; font-size: 10px; }
        .dec-grid { display: flex; width: 100%; }
        .dec-grid > div { flex: 1; border-right: 1px solid #000; padding: 10px; box-sizing: border-box; }
        .dec-grid > div:last-child { border-right: 0; }
        .dec-field { margin-bottom: 6px; }
        .dec-label { font-weight: bold; text-transform: uppercase; font-size: 9px; color: #333; display: block; }
        .dec-value { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background: #eee; font-size: 9px; text-transform: uppercase; }
        .declaration-text { padding: 15px; font-size: 10px; text-align: justify; border-top: 1px solid #000; line-height: 1.4; }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">IMPRIMIR AGORA</button>

    <?php if ($type === 'label'): ?>
        <!-- ETIQUETA DE POSTAGEM -->
        <div class="label-container">
            <div class="label-section" style="border-bottom: 3px solid #000;">
                <div class="label-title">Destinatário</div>
                <div class="label-name"><?php echo strtoupper($rma['customer_name']); ?></div>
                <div class="label-addr">
                    <?php echo strtoupper($rma['address'] . ", " . $rma['number']); ?><br>
                    <?php if($rma['complement']) echo strtoupper($rma['complement']) . "<br>"; ?>
                    <?php echo strtoupper($rma['neighborhood']); ?><br>
                    <strong><?php echo strtoupper($rma['city'] . " - " . $rma['state']); ?></strong><br>
                    CEP: <?php echo $rma['zipcode']; ?>
                </div>
            </div>
            <div class="label-section" style="border:0; margin-top: 40px; opacity: 0.8;">
                <div class="label-title">Remetente</div>
                <div class="label-name" style="font-size: 12px;">DANIEL SOUZA</div>
                <div class="label-addr" style="font-size: 11px;">
                    Rua Cristiano Osorio, 143<br>
                    Penha de França - São Paulo - SP<br>
                    CEP: 03611-060
                </div>
            </div>
            <div style="text-align: center; margin-top: 50px;">
                <div style="font-size: 8px; color: #666;">RMA #<?php echo $id; ?> - Fight Arcade</div>
            </div>
        </div>

    <?php else: ?>
        <!-- DECLARAÇÃO DE CONTEÚDO -->
        <div class="dec-container">
            <div class="dec-header">DECLARAÇÃO DE CONTEÚDO</div>
            <div class="dec-grid">
                <div>
                    <div class="dec-section-title">REMETENTE</div>
                    <div class="dec-field"><span class="dec-label">NOME:</span><span class="dec-value">DANIEL SOUZA</span></div>
                    <div class="dec-field"><span class="dec-label">CPF/CNPJ:</span><span class="dec-value">365.428.828-63</span></div>
                    <div class="dec-field"><span class="dec-label">ENDEREÇO:</span><span class="dec-value">Rua Cristiano Osorio, 143 - São Paulo/SP</span></div>
                </div>
                <div>
                    <div class="dec-section-title">DESTINATÁRIO</div>
                    <div class="dec-field"><span class="dec-label">NOME:</span><span class="dec-value"><?php echo strtoupper($rma['customer_name']); ?></span></div>
                    <div class="dec-field"><span class="dec-label">CPF/CNPJ:</span><span class="dec-value"><?php echo $rma['document']; ?></span></div>
                    <div class="dec-field"><span class="dec-label">ENDEREÇO:</span><span class="dec-value"><?php echo strtoupper($rma['address'].", ".$rma['number']." - ".$rma['city']."/".$rma['state']); ?></span></div>
                </div>
            </div>
            <div class="dec-section-title">IDENTIFICAÇÃO DOS BENS</div>
            <table>
                <thead><tr><th style="width:30px">ITEM</th><th>CONTEÚDO</th><th style="width:50px">QUANT.</th><th style="width:80px">VALOR (R$)</th></tr></thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><?php echo strtoupper($rma['product_name']); ?> (RMA #<?php echo $id; ?>)</td>
                        <td>1</td>
                        <td>30,00</td>
                    </tr>
                    <tr><td colspan="2" style="text-align:right"><strong>TOTAIS</strong></td><td><strong>1</strong></td><td><strong>30,00</strong></td></tr>
                </tbody>
            </table>
            <div class="dec-section-title">DECLARAÇÃO</div>
            <div class="declaration-text">
                Declaro que não me enquadro no conceito de contribuinte previsto no art. 4º da Lei Complementar nº 87/1996, uma vez que não realizo, com habitualidade ou em volume que caracterize intuito comercial, operações de circulação de mercadoria... responsabilizando-me, nos termos da lei, por informações inverídicas.
            </div>
            <div style="padding: 30px; text-align: center;">
                SÃO PAULO, <?php echo date('d/m/Y'); ?><br><br>
                _______________________________________________________________<br>
                Assinatura do Declarante/Remetente
            </div>
        </div>
    <?php endif; ?>
</body>
</html>

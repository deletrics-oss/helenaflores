<?php
// catalogo/export-pdf.php
// A simple printable version that users can "Save as PDF"
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$products = $pdo->query("SELECT p.* FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 AND (p.show_on_site = 1 OR p.show_on_site IS NULL) AND (c.show_on_site = 1 OR c.show_on_site IS NULL OR p.category_id IS NULL) ORDER BY p.name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Catálogo Fight Arcade</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #000;
            padding-bottom: 1rem;
        }

        .item {
            break-inside: avoid;
            border-bottom: 1px solid #ccc;
            padding: 1rem 0;
            display: flex;
            gap: 1rem;
        }

        .img {
            width: 100px;
            height: 100px;
            background: #eee;
            object-fit: contain;
        }

        .info {
            flex: 1;
        }

        .price {
            font-weight: bold;
            font-size: 1.2rem;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="no-print" style="background:#f0f0f0; padding:10px; text-align:center;">
        <button onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer;">IMPRIMIR / SALVAR
            PDF</button>
        <a href="index.php" style="margin-left:20px;">Voltar ao Site</a>
    </div>

    <div class="header">
        <h1>
            <?php echo SITE_NAME; ?>
        </h1>
        <p>Catálogo Completo -
            <?php echo date('d/m/Y'); ?>
        </p>
        <p>WhatsApp:
            <?php echo WHATSAPP_ADMIN; ?>
        </p>
    </div>

    <?php foreach ($products as $p): ?>
        <div class="item">
            <?php if ($p['image_path']): ?>
                <img src="assets/uploads/<?php echo $p['image_path']; ?>" class="img">
            <?php else: ?>
                <div class="img"></div>
            <?php endif; ?>

            <div class="info">
                <h3>
                    <?php echo htmlspecialchars($p['name']); ?>
                </h3>
                <p style="color:#666; font-size:0.9rem;">SKU:
                    <?php echo $p['sku']; ?>
                </p>
                <p>
                    <?php echo htmlspecialchars($p['description']); ?>
                </p>
            </div>

            <div style="text-align:right;">
                <div class="price">R$
                    <?php echo number_format($p['price'], 2, ',', '.'); ?>
                </div>
                <?php if ($p['price_wholesale']): ?>
                    <small>Atacado: R$
                        <?php echo number_format($p['price_wholesale'], 2, ',', '.'); ?>
                    </small><br>
                    <small>(min
                        <?php echo $p['min_wholesale_qty']; ?> un)
                    </small>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</body>

</html>
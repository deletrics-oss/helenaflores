<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner Inteligente | Fight Arcade</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #0a0a0a; color: #fff; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .scanner-container { padding: 20px; max-width: 500px; margin: 0 auto; }
        #reader { border: 2px solid #00ff88 !important; border-radius: 20px; overflow: hidden; background: #000; box-shadow: 0 0 30px rgba(0, 255, 136, 0.2); }
        .result-card { background: #1a1a1a; border-radius: 20px; padding: 20px; margin-top: 20px; display: none; border: 1px solid #333; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .product-info h2 { color: #00ff88; margin-bottom: 5px; }
        .stock-control { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; background: #222; padding: 15px; border-radius: 15px; }
        .btn-qty { width: 60px; height: 60px; border-radius: 50%; border: none; font-size: 24px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-plus { background: #00ff88; color: #000; }
        .btn-minus { background: #ff4444; color: #fff; }
        .btn-qty:active { transform: scale(0.9); }
        .current-stock { font-size: 2rem; font-weight: bold; }
        .status-msg { margin-top: 10px; text-align: center; font-size: 0.9rem; color: #888; }
        #reader__dashboard_section_csr button { background: #00ff88 !important; color: #000 !important; border: none !important; padding: 10px 20px !important; border-radius: 10px !important; font-weight: bold !important; }
    </style>
</head>
<body>

<div class="scanner-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-size:1.5rem;">🚀 Estoque Elite</h1>
        <a href="index.php" style="color:#888; text-decoration:none;">Sair</a>
    </div>

    <div id="reader"></div>

    <div style="margin-top:15px; display:flex; align-items:center; gap:10px; background:#111; padding:10px; border-radius:10px; border:1px solid #333;">
        <label class="switch" style="position:relative; display:inline-block; width:40px; height:20px;">
            <input type="checkbox" id="continuous-scan" style="opacity:0; width:0; height:0;">
            <span class="slider" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#333; transition:.4s; border-radius:20px;"></span>
        </label>
        <span style="font-size:0.8rem; color:#aaa;">Modo Escaneamento Contínuo (Auto +1)</span>
    </div>

    <style>
        #continuous-scan:checked + .slider { background-color: #00ff88; }
        .slider:before { position:absolute; content:""; height:14px; width:14px; left:3px; bottom:3px; background-color:white; transition:.4s; border-radius:50%; }
        #continuous-scan:checked + .slider:before { transform: translateX(20px); }
    </style>

    <div id="result" class="result-card">
        <div class="product-info">
            <span id="p-category" style="font-size:0.8rem; color:#888; text-transform:uppercase;">Categoria</span>
            <h2 id="p-name">Nome do Produto</h2>
            <p id="p-ean" style="color:#555; font-size:0.9rem;">EAN: 0000000000000</p>
        </div>

        <div class="stock-control">
            <button class="btn-qty btn-minus" onclick="updateStock('saida')">-</button>
            <div style="text-align:center;">
                <div class="current-stock" id="p-stock">0</div>
                <div style="font-size:0.7rem; color:#888;">EM ESTOQUE</div>
            </div>
            <button class="btn-qty btn-plus" onclick="updateStock('entrada')">+</button>
        </div>
        <div id="status-msg" class="status-msg">Aguardando ação...</div>
    </div>
</div>

<script>
    let currentProductId = null;
    let gpsCoords = "";

    // Pega GPS para o Audit Log
    navigator.geolocation.getCurrentPosition((pos) => {
        gpsCoords = `${pos.coords.latitude},${pos.coords.longitude}`;
    });

    function onScanSuccess(decodedText, decodedResult) {
        // Pausa o scanner para processar
        html5QrcodeScanner.pause();
        
        // Som de bip profissional
        const audio = new Audio('https://www.soundjay.com/buttons/beep-07a.mp3');
        audio.play();

        fetch(`../api/get_product_by_ean.php?ean=${decodedText}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    currentProductId = data.product.id;
                    document.getElementById('p-name').innerText = data.product.name;
                    document.getElementById('p-category').innerText = data.product.category_name;
                    document.getElementById('p-ean').innerText = "EAN: " + data.product.ean;
                    document.getElementById('p-stock').innerText = data.product.stock;
                    document.getElementById('result').style.display = 'block';
                    
                    // Feedback Tátil
                    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);

                    // AUTO +1 if Continuous Scan is ON
                    if (document.getElementById('continuous-scan').checked) {
                        updateStock('entrada');
                        setTimeout(() => {
                            html5QrcodeScanner.resume();
                        }, 1500);
                    }
                } else {
                    // SE NÃO EXISTIR, CHAMA A IA PARA IDENTIFICAR
                    if(confirm("Produto não encontrado no banco. Deseja que a IA identifique este EAN?")) {
                        document.getElementById('status-msg').innerText = "🤖 Consultando IA...";
                    }
                    html5QrcodeScanner.resume();
                }
            });
    }

    function updateStock(type) {
        if (!currentProductId) return;
        
        const btn = event.target;
        btn.disabled = true;

        fetch('../api/update_stock_fast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentProductId,
                type: type,
                qty: 1,
                gps: gpsCoords
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('p-stock').innerText = data.new_stock;
                document.getElementById('status-msg').innerText = "✅ Movimentação registrada!";
                document.getElementById('status-msg').style.color = "#00ff88";
                if (navigator.vibrate) navigator.vibrate(50);
            }
            btn.disabled = false;
        });
    }

    var html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
    html5QrcodeScanner.render(onScanSuccess);
</script>

</body>
</html>

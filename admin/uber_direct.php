<?php
/**
 * admin/uber_direct.php — Fight Arcade
 * AJAX Handler para Uber Direct (Logística Sob Demanda)
 */
try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/user_auth.php';
    require_once __DIR__ . '/../includes/uber_api.php';
    isAdmin();

    $uber = new UberService($pdo);

    if (isset($_GET['ajax_quote'])) {
        header('Content-Type: application/json');
        
        $oid = (int)($_GET['order_id'] ?? 0);
        if (!$oid) { echo json_encode(['error' => 'Pedido não informado']); exit; }

        $order = $pdo->query("SELECT o.*, u.zipcode, u.address, u.number, u.city, u.state FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
        if (!$order) { echo json_encode(['error' => 'Pedido não encontrado']); exit; }

        // Coordenadas da Loja (Fallback se não houver nas settings)
        $pickup = [
            'address' => 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP',
            'lat'     => -23.5234, // Exemplo
            'lng'     => -46.5432
        ];
        
        // Buscar coordenadas do destino (Via Geocode ou guardado no pedido)
        // Por agora, vamos assumir que precisamos geocodificar ou que o cliente já geocodificou via Lalamove
        // Para simplificar o teste, vamos usar o endereço do cliente
        $dropoff = [
            'address' => $order['address'] . ', ' . $order['number'] . ' - ' . $order['city'] . ', ' . $order['state'],
            'lat'     => $_GET['lat'] ?? 0,
            'lng'     => $_GET['lng'] ?? 0
        ];

        if (!$dropoff['lat'] || !$dropoff['lng']) {
            echo json_encode(['error' => 'Coordenadas de destino não informadas. Geocodifique o endereço primeiro.']);
            exit;
        }

        $quote = $uber->getDeliveryQuote($pickup, $dropoff);
        echo json_encode($quote);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_create'])) {
        header('Content-Type: application/json');
        $quoteId = $_POST['quote_id'] ?? '';
        $oid     = (int)$_POST['order_id'];
        
        // Buscar dados do pedido
        $order = $pdo->query("SELECT o.*, u.name, u.phone, u.address, u.number, u.city, u.state FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
        
        $pickup = ['address' => 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP', 'name' => 'Fight Arcade'];
        $dropoff = [
            'address' => $order['address'] . ', ' . $order['number'] . ' - ' . $order['city'] . ', ' . $order['state'],
            'name'    => $order['name'],
            'notes'   => 'Pedido #' . $oid
        ];
        
        $manifest = [['name' => 'Produtos Fight Arcade', 'quantity' => 1]];

        $result = $uber->createDelivery($quoteId, $pickup, $dropoff, $manifest);
        
        if (isset($result['id'])) {
            $pdo->prepare("UPDATE orders SET shipping_method = 'Uber Direct', me_order_id = ? WHERE id = ?")
                ->execute([$result['id'], $oid]);
            echo json_encode(['success' => true, 'id' => $result['id']]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erro ao criar entrega Uber']);
        }
        exit;
    }

} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

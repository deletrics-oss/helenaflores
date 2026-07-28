<?php
/**
 * api/uber_api_proxy.php — Fight Arcade
 * Proxy para cotações e operações Uber Direct/Eats
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/uber_api.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$uber = new UberService($pdo);

try {
    if ($action === 'quote') {
        $id = $_GET['id'] ?? '';
        $type = $_GET['type'] ?? 'order'; // 'order' ou 'rma'
        
        // Coleta dados do destinatário
        if ($type === 'rma') {
            $stmt = $pdo->prepare("SELECT * FROM rma_tickets WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            $dest = [
                'name' => $data['customer_name'],
                'address' => $data['address'] . ', ' . $data['number'] . ' - ' . $data['city'] . '/' . $data['state'],
                'phone' => $data['phone']
            ];
        } else {
            $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone, u.address, u.number, u.city, u.state FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            $dest = [
                'name' => $data['customer_name'],
                'address' => $data['address'] . ', ' . $data['number'] . ' - ' . $data['city'] . '/' . $data['state'],
                'phone' => $data['phone']
            ];
        }

        // Chamar Uber Direct / Eats Quote API
        // Nota: Isso requer que a conta Uber tenha o escopo 'deliveries' ou 'eats.store'
        // Para fins de demonstração e integração inicial, vamos retornar uma cotação simulada 
        // se a API real ainda estiver em fase de aprovação de escopo.
        
        $token = $uber->getClientToken();
        if (!$token) {
            throw new Exception('Não foi possível obter token da Uber. Verifique a conexão em Configurações.');
        }

        // Simulação de resposta (enquanto o escopo Uber Direct é ativado na conta)
        // Em produção, isso chama: $uber->apiCall('POST', '/v1/deliveries/quote', $token, [...])
        $quotes = [
            [
                'service_type' => 'UBER_FLASH',
                'label' => 'Uber Flash (Moto)',
                'price' => 15.90,
                'estimate' => '25-40 min',
                'id' => 'quote_'.time()
            ],
            [
                'service_type' => 'UBER_DIRECT',
                'label' => 'Uber Direct (Carro)',
                'price' => 24.50,
                'estimate' => '30-50 min',
                'id' => 'quote_direct_'.time()
            ]
        ];

        echo json_encode(['success' => true, 'quotes' => $quotes]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

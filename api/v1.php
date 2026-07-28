<?php
/**
 * api/v1.php — API REST Oficial do Catálogo Fight Arcade
 * 
 * Autenticação via Header: Authorization: Bearer <API_TOKEN>
 * Ou via Query String: ?api_token=<API_TOKEN>
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// Fallback para getallheaders se necessário
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// 1. CARREGAR CONFIGURAÇÕES E TOKEN
$settings_file = __DIR__ . '/../includes/site_settings.json';
$settings = [];
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true) ?? [];
}
$api_token = $settings['api_token'] ?? '';

// 2. AUTENTICAÇÃO
$headers = getallheaders();
$token_received = '';

if (isset($headers['Authorization'])) {
    if (preg_match('/Bearer\s(\S+)/i', $headers['Authorization'], $matches)) {
        $token_received = $matches[1];
    }
} elseif (isset($_GET['api_token'])) {
    $token_received = $_GET['api_token'];
}

if (empty($api_token) || $token_received !== $api_token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado. Token de API inválido ou ausente.']);
    exit;
}

// 3. ROTEAMENTO DE ENDPOINTS
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        if ($method === 'GET') {
            handleGetProducts($pdo);
        } else {
            methodNotAllowed();
        }
        break;

    case 'stock':
        if ($method === 'POST') {
            handlePostStock($pdo);
        } else {
            methodNotAllowed();
        }
        break;

    case 'price':
        if ($method === 'POST') {
            handlePostPrice($pdo);
        } else {
            methodNotAllowed();
        }
        break;

    case 'orders':
        if ($method === 'GET') {
            handleGetOrders($pdo);
        } else {
            methodNotAllowed();
        }
        break;

    case 'customers':
        if ($method === 'GET') {
            handleGetCustomers($pdo);
        } else {
            methodNotAllowed();
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint não encontrado.']);
        break;
}

// --- FUNÇÕES AUXILIARES DE ENDPOINTS ---

function methodNotAllowed() {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método HTTP não permitido para este endpoint.']);
    exit;
}

/**
 * GET /api/v1.php?endpoint=products
 */
function handleGetProducts($pdo) {
    try {
        $active_only = isset($_GET['active']) ? (int)$_GET['active'] : 1;
        
        $sql = "SELECT id, name, sku, price, price_wholesale, cost_price, stock_qty, active, image_path, created_at 
                FROM products";
        if ($active_only) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY id ASC";
        
        $products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar variações
        $variations = $pdo->query("SELECT id, product_id, type, value, price, price_wholesale, cost_price, stock_qty, sku, image_path 
                                   FROM product_variations")->fetchAll(PDO::FETCH_ASSOC);
                                   
        // Agrupar variações por produto
        $variations_by_product = [];
        foreach ($variations as $v) {
            $variations_by_product[$v['product_id']][] = [
                'variation_id' => (int)$v['id'],
                'type' => $v['type'],
                'value' => $v['value'],
                'price' => $v['price'] !== null ? (float)$v['price'] : null,
                'price_wholesale' => $v['price_wholesale'] !== null ? (float)$v['price_wholesale'] : null,
                'cost_price' => (float)$v['cost_price'],
                'stock_qty' => (int)$v['stock_qty'],
                'sku' => $v['sku'],
                'image_path' => $v['image_path']
            ];
        }
        
        $response = [];
        foreach ($products as $p) {
            $pid = $p['id'];
            $response[] = [
                'product_id' => (int)$p['id'],
                'name' => $p['name'],
                'sku' => $p['sku'],
                'price' => (float)$p['price'],
                'price_wholesale' => $p['price_wholesale'] !== null ? (float)$p['price_wholesale'] : null,
                'cost_price' => (float)$p['cost_price'],
                'stock_qty' => (int)$p['stock_qty'],
                'active' => (int)$p['active'] === 1,
                'image_path' => $p['image_path'],
                'created_at' => $p['created_at'],
                'variations' => $variations_by_product[$pid] ?? []
            ];
        }
        
        echo json_encode(['success' => true, 'count' => count($response), 'products' => $response], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao listar produtos: ' . $e->getMessage()]);
    }
}

/**
 * POST /api/v1.php?endpoint=stock
 */
function handlePostStock($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Corpo da requisição JSON inválido.']);
        exit;
    }

    $qty = isset($input['qty']) ? (int)$input['qty'] : null;
    $type = isset($input['type']) ? strtolower($input['type']) : 'set'; // 'set', 'add', 'sub'
    $sku = $input['sku'] ?? '';
    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : null;
    $variation_id = isset($input['variation_id']) ? (int)$input['variation_id'] : null;

    if ($qty === null || $qty < 0 && $type === 'set') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Quantidade (qty) inválida ou ausente.']);
        exit;
    }

    if (!in_array($type, ['set', 'add', 'sub'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de operação (type) inválido. Escolha entre: set, add, sub.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $target_product_id = null;
        $target_variation_id = null;
        $current_stock = 0;
        $is_variation = false;
        $item_name = '';

        // 1. RESOLVER DESTINO (SKU vs IDs)
        if (!empty($sku)) {
            // Buscar em variações primeiro
            $v_stmt = $pdo->prepare("SELECT id, product_id, stock_qty, value FROM product_variations WHERE sku = ?");
            $v_stmt->execute([$sku]);
            $v_res = $v_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($v_res) {
                $target_variation_id = $v_res['id'];
                $target_product_id = $v_res['product_id'];
                $current_stock = (int)$v_res['stock_qty'];
                $is_variation = true;
                
                $p_name = $pdo->query("SELECT name FROM products WHERE id = " . $target_product_id)->fetchColumn();
                $item_name = $p_name . " (" . $v_res['value'] . ")";
            } else {
                // Buscar em produtos
                $p_stmt = $pdo->prepare("SELECT id, stock_qty, name FROM products WHERE sku = ?");
                $p_stmt->execute([$sku]);
                $p_res = $p_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($p_res) {
                    $target_product_id = $p_res['id'];
                    $current_stock = (int)$p_res['stock_qty'];
                    $item_name = $p_res['name'];
                } else {
                    $pdo->rollBack();
                    http_response_code(442);
                    echo json_encode(['success' => false, 'error' => 'SKU não encontrado no sistema.']);
                    exit;
                }
            }
        } elseif ($variation_id) {
            $v_stmt = $pdo->prepare("SELECT product_id, stock_qty, value FROM product_variations WHERE id = ?");
            $v_stmt->execute([$variation_id]);
            $v_res = $v_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($v_res) {
                $target_variation_id = $variation_id;
                $target_product_id = $v_res['product_id'];
                $current_stock = (int)$v_res['stock_qty'];
                $is_variation = true;
                
                $p_name = $pdo->query("SELECT name FROM products WHERE id = " . $target_product_id)->fetchColumn();
                $item_name = $p_name . " (" . $v_res['value'] . ")";
            } else {
                $pdo->rollBack();
                http_response_code(442);
                echo json_encode(['success' => false, 'error' => 'Variation ID não encontrado.']);
                exit;
            }
        } elseif ($product_id) {
            $p_stmt = $pdo->prepare("SELECT stock_qty, name FROM products WHERE id = ?");
            $p_stmt->execute([$product_id]);
            $p_res = $p_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($p_res) {
                $target_product_id = $product_id;
                $current_stock = (int)$p_res['stock_qty'];
                $item_name = $p_res['name'];
            } else {
                $pdo->rollBack();
                http_response_code(442);
                echo json_encode(['success' => false, 'error' => 'Product ID não encontrado.']);
                exit;
            }
        } else {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Identificador do produto ausente. Envie: sku, variation_id ou product_id.']);
            exit;
        }

        // 2. CALCULAR NOVO ESTOQUE
        $new_stock = 0;
        if ($type === 'set') {
            $new_stock = $qty;
        } elseif ($type === 'add') {
            $new_stock = $current_stock + $qty;
        } elseif ($type === 'sub') {
            $new_stock = $current_stock - $qty;
        }
        if ($new_stock < 0) $new_stock = 0;

        // 3. EXECUTAR UPDATE
        if ($is_variation) {
            $upd = $pdo->prepare("UPDATE product_variations SET stock_qty = ? WHERE id = ?");
            $upd->execute([$new_stock, $target_variation_id]);
        } else {
            $upd = $pdo->prepare("UPDATE products SET stock_qty = ? WHERE id = ?");
            $upd->execute([$new_stock, $target_product_id]);
        }

        // 4. REGISTRAR EM MOVIMENTAÇÃO DE ESTOQUE
        $mov_type = ($new_stock >= $current_stock) ? 'in' : 'out';
        $mov_qty = abs($new_stock - $current_stock);
        
        if ($mov_qty > 0) {
            $log = $pdo->prepare("INSERT INTO stock_movements (product_id, variation_id, type, qty, reason) VALUES (?, ?, ?, ?, ?)");
            $log->execute([$target_product_id, $target_variation_id, $mov_type, $mov_qty, 'Atualização via API REST']);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Estoque atualizado com sucesso.',
            'product_id' => $target_product_id,
            'variation_id' => $target_variation_id,
            'item_name' => $item_name,
            'previous_stock' => $current_stock,
            'new_stock' => $new_stock
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao processar atualização de estoque: ' . $e->getMessage()]);
    }
}

/**
 * POST /api/v1.php?endpoint=price
 */
function handlePostPrice($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Corpo da requisição JSON inválido.']);
        exit;
    }

    $sku = $input['sku'] ?? '';
    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : null;
    $variation_id = isset($input['variation_id']) ? (int)$input['variation_id'] : null;
    
    $price = isset($input['price']) ? (float)$input['price'] : null;
    $price_wholesale = isset($input['price_wholesale']) ? (float)$input['price_wholesale'] : null;

    if ($price === null && $price_wholesale === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nenhum preço enviado para atualização. Forneça: price ou price_wholesale.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $target_product_id = null;
        $target_variation_id = null;
        $is_variation = false;

        // RESOLVER DESTINO (SKU vs IDs)
        if (!empty($sku)) {
            // Buscar em variações primeiro
            $v_stmt = $pdo->prepare("SELECT id, product_id FROM product_variations WHERE sku = ?");
            $v_stmt->execute([$sku]);
            $v_res = $v_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($v_res) {
                $target_variation_id = $v_res['id'];
                $is_variation = true;
            } else {
                // Buscar em produtos
                $p_stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
                $p_stmt->execute([$sku]);
                $p_res = $p_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($p_res) {
                    $target_product_id = $p_res['id'];
                } else {
                    $pdo->rollBack();
                    http_response_code(442);
                    echo json_encode(['success' => false, 'error' => 'SKU não encontrado no sistema.']);
                    exit;
                }
            }
        } elseif ($variation_id) {
            $v_stmt = $pdo->prepare("SELECT id FROM product_variations WHERE id = ?");
            $v_stmt->execute([$variation_id]);
            if ($v_stmt->rowCount() > 0) {
                $target_variation_id = $variation_id;
                $is_variation = true;
            } else {
                $pdo->rollBack();
                http_response_code(442);
                echo json_encode(['success' => false, 'error' => 'Variation ID não encontrado.']);
                exit;
            }
        } elseif ($product_id) {
            $p_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $p_stmt->execute([$product_id]);
            if ($p_stmt->rowCount() > 0) {
                $target_product_id = $product_id;
            } else {
                $pdo->rollBack();
                http_response_code(442);
                echo json_encode(['success' => false, 'error' => 'Product ID não encontrado.']);
                exit;
            }
        } else {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Identificador do produto ausente. Envie: sku, variation_id ou product_id.']);
            exit;
        }

        // EXECUTAR UPDATE DE PREÇO
        if ($is_variation) {
            if ($price !== null) {
                $pdo->prepare("UPDATE product_variations SET price = ? WHERE id = ?")->execute([$price, $target_variation_id]);
            }
            if ($price_wholesale !== null) {
                $pdo->prepare("UPDATE product_variations SET price_wholesale = ? WHERE id = ?")->execute([$price_wholesale, $target_variation_id]);
            }
        } else {
            if ($price !== null) {
                $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$price, $target_product_id]);
            }
            if ($price_wholesale !== null) {
                $pdo->prepare("UPDATE products SET price_wholesale = ? WHERE id = ?")->execute([$price_wholesale, $target_product_id]);
            }
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Preços atualizados com sucesso.',
            'product_id' => $target_product_id,
            'variation_id' => $target_variation_id,
            'price_updated' => $price,
            'price_wholesale_updated' => $price_wholesale
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao processar atualização de preços: ' . $e->getMessage()]);
    }
}

/**
 * GET /api/v1.php?endpoint=orders
 */
function handleGetOrders($pdo) {
    try {
        $status = $_GET['status'] ?? '';
        $sql = "SELECT id, user_id, total_amount, payment_method, status, tracking_code, created_at FROM orders";
        $params = [];
        
        if (!empty($status)) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY id DESC LIMIT 100"; // limit to safety
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Order Items
        $response = [];
        foreach ($orders as $o) {
            $oid = $o['id'];
            $items = $pdo->query("SELECT oi.product_id, p.name as product_name, oi.quantity, oi.price as unit_price 
                                  FROM order_items oi 
                                  JOIN products p ON oi.product_id = p.id 
                                  WHERE oi.order_id = $oid")->fetchAll(PDO::FETCH_ASSOC);
                                  
            // Get Customer info
            $c_info = $pdo->query("SELECT name, phone, document, city, state FROM users WHERE id = " . $o['user_id'])->fetch(PDO::FETCH_ASSOC);

            $response[] = [
                'order_id' => (int)$o['id'],
                'total_amount' => (float)$o['total_amount'],
                'payment_method' => $o['payment_method'],
                'status' => $o['status'],
                'tracking_code' => $o['tracking_code'],
                'created_at' => $o['created_at'],
                'customer' => $c_info ?: null,
                'items' => $items
            ];
        }

        echo json_encode(['success' => true, 'count' => count($response), 'orders' => $response], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao listar pedidos: ' . $e->getMessage()]);
    }
}

/**
 * GET /api/v1.php?endpoint=customers
 */
function handleGetCustomers($pdo) {
    try {
        $customers = $pdo->query("SELECT id, name, email, phone, document, current_debt, total_spent, city, state, created_at 
                                  FROM users 
                                  WHERE role = 'customer' 
                                  ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($customers as $c) {
            $response[] = [
                'customer_id' => (int)$c['id'],
                'name' => $c['name'],
                'email' => $c['email'],
                'phone' => $c['phone'],
                'document' => $c['document'],
                'current_debt' => (float)$c['current_debt'],
                'total_spent' => (float)$c['total_spent'],
                'city' => $c['city'],
                'state' => $c['state'],
                'created_at' => $c['created_at']
            ];
        }

        echo json_encode(['success' => true, 'count' => count($response), 'customers' => $response], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao listar clientes: ' . $e->getMessage()]);
    }
}

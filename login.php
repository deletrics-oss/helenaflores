<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// Caleb: Removed global JSON header to support standard POST
// header('Content-Type: application/json');

// Se for GET, redireciona para index
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

try {
    // 1. Sanitização
    $phone_raw = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_NUMBER_INT);
    $phone = preg_replace('/\D/', '', $phone_raw); // Remove não-números
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $company = trim(filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING));
    $source = trim(filter_input(INPUT_POST, 'source', FILTER_SANITIZE_STRING));

    // 2. Validação Básica
    if (empty($phone) || strlen($phone) < 10) {
        throw new Exception('Telefone inválido. Use DDD + Número.');
    }
    if (empty($name)) {
        throw new Exception('Por favor, informe seu nome.');
    }

    // 3. Verifica/Cria Usuário
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = :phone");
    $stmt->execute([':phone' => $phone]);
    $user = $stmt->fetch();

    if ($user) {
        // Usuário existente: Atualiza nome/empresa se fornecidos
        $stmtUp = $pdo->prepare("UPDATE users SET name = :name, company_name = :comp, source = :src, last_login = NOW() WHERE id = :id");
        $stmtUp->execute([
            ':name' => $name,
            ':comp' => $company ?: $user['company_name'],
            ':src' => $source ?: ($user['source'] ?? 'Indefinido'),
            ':id' => $user['id']
        ]);
        $userId = $user['id'];
    } else {
        // Novo Usuário (Lead)
        $dummy_email = $phone . '@lead.fightarcade';
        $stmtIns = $pdo->prepare("INSERT INTO users (name, phone, company_name, source, email, password, is_lead) VALUES (:name, :phone, :comp, :src, :email, :pass, 1)");
        $stmtIns->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':comp' => $company,
            ':src' => $source ?: 'Indefinido',
            ':email' => $dummy_email,
            ':pass' => password_hash($phone, PASSWORD_DEFAULT),
        ]);
        $userId = $pdo->lastInsertId();
    }

    // 4. Cria Sessão
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['phone'] = $phone;
    $_SESSION['is_wholesale'] = true;
    $_SESSION['is_lead'] = ($user ? ($user['is_lead'] ?? 0) : 1);

    // 5. Cria Cookie (30 dias)
    $is_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    if (PHP_VERSION_ID >= 70300) {
        setcookie('b2b_access', $phone, [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('b2b_access', $phone, time() + (86400 * 30), "/; samesite=Lax", "", $is_secure, true);
    }

    // 6. Resposta (AJAX vs Standard)
    session_write_close();

    if (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        // Standard redirect
        $target = !empty($_POST['redirect']) ? $_POST['redirect'] : 'index.php?auth=1';
        header("Location: " . $target);
        exit;
    }

} catch (Exception $e) {
    if (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header("Location: index.php?bg_error=" . urlencode($e->getMessage()));
        exit;
    }
}
?>
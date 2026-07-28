<?php
/**
 * admin/uber_callback.php — Fight Arcade
 * Callback Seguro de Autorização Uber (OAuth 2.0)
 *
 * FUNÇÕES:
 *  1. Verifica integridade do State (Proteção CSRF)
 *  2. Troca o Code pelo Access Token definitivo
 *  3. Persiste o acesso no banco de dados
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/uber_api.php';

// Segurança: Apenas admins podem completar a conexão
isAdmin();

// 1. Tratamento de Erros Retornados pela Uber
if (!empty($_GET['error'])) {
    $errorDesc = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    $_SESSION['error_msg'] = "❌ Uber negou o acesso: $errorDesc";
    header('Location: uber_settings.php');
    exit;
}

// 2. Coleta de Parâmetros
$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state)) {
    $_SESSION['error_msg'] = '❌ Callback inválido: parâmetros ausentes.';
    header('Location: uber_settings.php');
    exit;
}

// 3. Verificação de Segurança (State / CSRF)
$expectedState = $_SESSION['uber_oauth_state'] ?? null;
unset($_SESSION['uber_oauth_state']); // Invalida após o uso

if (!$expectedState || !hash_equals($expectedState, $state)) {
    $_SESSION['error_msg'] = '❌ Falha de segurança: state inválido ou expirado. Tente conectar novamente.';
    header('Location: uber_settings.php');
    exit;
}

// 4. Troca do Código pelo Token Final
try {
    $uber = new UberService($pdo);
    $token = $uber->exchangeCode($code);

    if ($token) {
        $_SESSION['flash_msg'] = '✅ Conta Uber conectada com sucesso! Seu sistema agora está integrado.';
    } else {
        $_SESSION['error_msg'] = '❌ Erro ao trocar código pelo token. Verifique se o Secret está correto.';
    }
} catch (Exception $e) {
    error_log('[UberCallback] Erro Crítico: ' . $e->getMessage());
    $_SESSION['error_msg'] = '❌ Ocorreu um erro interno ao processar a autorização.';
}

header('Location: uber_settings.php');
exit;
?>

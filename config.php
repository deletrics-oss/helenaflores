<?php
// config.php - Helena Flores Configuração Produção
// -----------------------------------------------------
// CONFIGURAÇÃO DO BANCO DE DADOS (Hostinger)
// -----------------------------------------------------
define("DB_SERVER", "localhost");
define("DB_USERNAME", "u788439146_helena1");
define("DB_PASSWORD", 'H3l3n@Flores#2026!');
define("DB_NAME", "u788439146_helena1");

// -----------------------------------------------------
// DYNAMIC BASE_URL AUTO-DETECTION
// -----------------------------------------------------
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = substr($scriptDir, 0, -6);
    }
    $baseUrl = rtrim($scriptDir, '/');
    define("BASE_URL", $baseUrl);
}

define("SITE_NAME", "Helena Flores");
define("WHATSAPP_ADMIN", "5511986727872");
define("SITE_CNPJ", "18.274.066/0001-35");
define("SITE_ADDRESS", "Alameda Jaú, 1777, Jardim Paulista, São Paulo/SP");
define("SITE_FOOTER_COPYRIGHT", "Helena Flores - 2026. Todos os direitos reservados");

// Configurações de Fuso Horário e Erros
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 86400 * 30,
        'path' => '/',
        'domain' => '',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }
    session_start();
}
?>
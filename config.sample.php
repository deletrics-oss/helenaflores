<?php
// config.sample.php - CONFIGURAÇÃO PARA HELENA FLORES / SAAS

// -----------------------------------------------------
// CONFIGURAÇÃO DO BANCO DE DADOS
// -----------------------------------------------------
define("DB_SERVER", "{{DB_HOST}}");
define("DB_USERNAME", "{{DB_USER}}");
define("DB_PASSWORD", "{{DB_PASS}}");
define("DB_NAME", "{{DB_NAME}}");

// -----------------------------------------------------
// CONFIGURAÇÃO GERAL
// -----------------------------------------------------
define("BASE_URL", "{{BASE_URL}}");
define("SITE_NAME", "Helena Flores");
define("WHATSAPP_ADMIN", "5511986727872");
define("SITE_CNPJ", "18.274.066/0001-35");
define("SITE_ADDRESS", "Alameda Jaú, 1777, Jardim Paulista, São Paulo/SP");
define("SITE_FOOTER_COPYRIGHT", "Helena Flores - 2026. Todos os direitos reservados");

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
        session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }
    session_start();
}
?>
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

/**
 * Smart Matching Image Helper: Returns clean relative URL to assets/uploads/
 */
if (!function_exists('get_product_image_url')) {
    function get_product_image_url($image_path, $title = '') {
        if (empty($image_path)) {
            return 'assets/uploads/rose_red.jpg';
        }

        // External URLs (Unsplash)
        if (strpos($image_path, 'http') !== false && strpos($image_path, 'unsplash') !== false) {
            return $image_path;
        }

        // Clean filename
        $filename = basename(parse_url($image_path, PHP_URL_PATH));
        $uploadsDir = __DIR__ . '/assets/uploads/';
        $physicalPath = $uploadsDir . $filename;

        // 1. Exact match on disk
        if (file_exists($physicalPath) && filesize($physicalPath) > 100) {
            return 'assets/uploads/' . rawurlencode($filename);
        }

        // 2. Fuzzy match by slug
        $cleanSlug = preg_replace('/^\d+[-_]/', '', pathinfo($filename, PATHINFO_FILENAME));
        $cleanSlug = strtolower(trim($cleanSlug));

        if (!empty($cleanSlug) && is_dir($uploadsDir)) {
            $dirFiles = scandir($uploadsDir);
            foreach ($dirFiles as $f) {
                if ($f === '.' || $f === '..') continue;
                $fClean = preg_replace('/^\d+[-_]/', '', pathinfo($f, PATHINFO_FILENAME));
                $fClean = strtolower(trim($fClean));

                if (!empty($fClean) && ($fClean === $cleanSlug || strpos($fClean, $cleanSlug) !== false || strpos($cleanSlug, $fClean) !== false)) {
                    return 'assets/uploads/' . rawurlencode($f);
                }
            }
        }

        // 3. Fallback by Title Keywords
        if (preg_match('/cesta|café|cafe/i', $title)) return 'assets/uploads/cesta_cafe.jpg';
        if (preg_match('/girassol/i', $title)) return 'assets/uploads/girassol.jpg';
        if (preg_match('/orquídea|orquidea/i', $title)) return 'assets/uploads/orquidea.jpg';
        if (preg_match('/tulipa/i', $title)) return 'assets/uploads/tulipa.jpg';
        if (preg_match('/ferrero|rocher|kit|presente/i', $title)) return 'assets/uploads/kit_ferrero.jpg';
        if (preg_match('/arranjo|vaso|lírio|lirio/i', $title)) return 'assets/uploads/arranjo_vaso.jpg';
        if (preg_match('/pink|rosé|rose/i', $title)) return 'assets/uploads/rose_pink.jpg';
        if (preg_match('/amarel/i', $title)) return 'assets/uploads/rose_yellow.jpg';

        return 'assets/uploads/' . rawurlencode($filename);
    }
}
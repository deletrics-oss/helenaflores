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
 * Smart Matching Image Helper: Automatically resolves exact or fuzzy image filenames
 */
if (!function_exists('get_product_image_url')) {
    function get_product_image_url($image_path, $title = '') {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        if (empty($image_path)) {
            return $baseUrl . '/assets/uploads/rose_red.jpg';
        }

        // Clean up concatenated URLs or double domain prefixes
        if (strpos($image_path, 'http') !== false) {
            if (preg_match('/(https?:\/\/[^\s"]+\.(?:jpg|jpeg|png|webp))/i', $image_path, $m)) {
                if (strpos($m[1], 'unsplash') !== false) {
                    return $m[1];
                }
            }
            $image_path = basename(parse_url($image_path, PHP_URL_PATH));
        }

        $filename = basename($image_path);
        $uploadsDir = __DIR__ . '/assets/uploads/';
        $physicalPath = $uploadsDir . $filename;

        // 1. Exact match on disk
        if (file_exists($physicalPath) && filesize($physicalPath) > 100) {
            return $baseUrl . '/assets/uploads/' . $filename;
        }

        // 2. Fuzzy match by slug (stripping leading digits 001- to 999-)
        $cleanSlug = preg_replace('/^\d+[-_]/', '', pathinfo($filename, PATHINFO_FILENAME));
        $cleanSlug = strtolower(trim($cleanSlug));

        if (!empty($cleanSlug) && is_dir($uploadsDir)) {
            $dirFiles = scandir($uploadsDir);
            foreach ($dirFiles as $f) {
                if ($f === '.' || $f === '..') continue;
                $fClean = preg_replace('/^\d+[-_]/', '', pathinfo($f, PATHINFO_FILENAME));
                $fClean = strtolower(trim($fClean));

                if (!empty($fClean) && ($fClean === $cleanSlug || strpos($fClean, $cleanSlug) !== false || strpos($cleanSlug, $fClean) !== false)) {
                    return $baseUrl . '/assets/uploads/' . $f;
                }
            }
        }

        // 3. Fallback by Title Keywords
        if (preg_match('/cesta|café|cafe/i', $title)) return $baseUrl . '/assets/uploads/cesta_cafe.jpg';
        if (preg_match('/girassol/i', $title)) return $baseUrl . '/assets/uploads/girassol.jpg';
        if (preg_match('/orquídea|orquidea/i', $title)) return $baseUrl . '/assets/uploads/orquidea.jpg';
        if (preg_match('/tulipa/i', $title)) return $baseUrl . '/assets/uploads/tulipa.jpg';
        if (preg_match('/ferrero|rocher|kit|presente/i', $title)) return $baseUrl . '/assets/uploads/kit_ferrero.jpg';
        if (preg_match('/arranjo|vaso|lírio|lirio/i', $title)) return $baseUrl . '/assets/uploads/arranjo_vaso.jpg';
        if (preg_match('/pink|rosé|rose/i', $title)) return $baseUrl . '/assets/uploads/rose_pink.jpg';
        if (preg_match('/amarel/i', $title)) return $baseUrl . '/assets/uploads/rose_yellow.jpg';

        return $baseUrl . '/assets/uploads/rose_red.jpg';
    }
}
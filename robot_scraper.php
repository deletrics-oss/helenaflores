<?php
/**
 * robot_scraper.php — Helena Flores Catalog Extrator Bot
 * Robô automatizado para extrair TODOS os produtos, fotos, preços e categorias de catálogos WhatsApp Business & Web.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

class CatalogRobotScraper {
    private $pdo;
    private $uploadDir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->uploadDir = __DIR__ . '/assets/uploads/';
        if (!file_exists($this->uploadDir)) {
            @mkdir($this->uploadDir, 0777, true);
        }
    }

    public function scrapeFromUrl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$html || $httpCode !== 200) {
            return ['success' => false, 'error' => "Não foi possível acessar a URL. HTTP Code: {$httpCode}"];
        }

        return $this->parseAndInsert($html, $url);
    }

    public function parseAndInsert($content, $sourceUrl = '') {
        $extracted = [];

        // Check if content is already a JSON array of items
        $jsonDecoded = json_decode($content, true);
        if ($jsonDecoded && is_array($jsonDecoded)) {
            foreach ($jsonDecoded as $item) {
                if (!empty($item['title'])) {
                    $extracted[] = [
                        'title' => trim($item['title']),
                        'price' => $this->cleanPrice($item['price'] ?? 0),
                        'description' => trim($item['description'] ?? ''),
                        'image' => trim($item['image'] ?? ''),
                        'category' => $this->detectCategory($item['title'] . ' ' . ($item['category'] ?? ''))
                    ];
                }
            }
        }

        // WhatsApp Web Catalog Regex Fallback
        if (empty($extracted)) {
            preg_match_all('/\{[^{}]*"title"\s*:\s*"([^"]+)"[^{}]*\}/i', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $item = json_decode($m[0], true);
                if ($item && !empty($item['title'])) {
                    $extracted[] = [
                        'title' => trim($item['title']),
                        'price' => $this->cleanPrice($item['price'] ?? 0),
                        'description' => trim($item['description'] ?? ''),
                        'image' => trim($item['image'] ?? ''),
                        'category' => $this->detectCategory($item['title'])
                    ];
                }
            }
        }

        if (empty($extracted)) {
            return ['success' => false, 'error' => 'Nenhum produto em formato JSON válido foi fornecido.'];
        }

        // Inserção / Atualização de TODOS os produtos no banco MySQL
        $insertedCount = 0;
        $updatedCount  = 0;
        $insertedItems = [];

        foreach ($extracted as $item) {
            $name = trim($item['title']);
            if (empty($name)) continue;

            $slug = $this->createSlug($name);
            $price = floatval($item['price']);
            $desc = trim($item['description']);
            $categoryName = trim($item['category'] ?: 'Rosas Colombianas');
            $imgUrl = trim($item['image']);

            // Curated Image Fallback if image is empty
            if (empty($imgUrl)) {
                $imgUrl = $this->getCuratedFallbackImage($name);
            }

            // Resolve Categoria
            $catId = $this->getOrCreateCategory($categoryName);

            // Processa Imagem se existir
            $localImagePath = null;
            if ($imgUrl) {
                $localImagePath = $this->downloadImage($imgUrl, $slug);
            }

            // Verifica Duplicidade no banco
            $chk = $this->pdo->prepare("SELECT id FROM products WHERE slug = ? OR name = ?");
            $chk->execute([$slug, $name]);
            $existingId = $chk->fetchColumn();

            if (!$existingId) {
                $sku = 'HF-WA-' . strtoupper(substr(md5($name), 0, 6));
                $ins = $this->pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 50, 1)");
                $ins->execute([$catId, $name, $slug, $desc, $sku, $price, $localImagePath]);
                $insertedCount++;
            } else {
                $upd = $this->pdo->prepare("UPDATE products SET price = IF(? > 0, ?, price), image_path = COALESCE(?, image_path), description = IF(LENGTH(?) > 3, ?, description), active = 1 WHERE id = ?");
                $upd->execute([$price, $price, $localImagePath, $desc, $desc, $existingId]);
                $updatedCount++;
            }

            $insertedItems[] = [
                'title' => $name,
                'price' => $price,
                'category' => $categoryName
            ];
        }

        return [
            'success' => true,
            'total_found' => count($extracted),
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'items' => $insertedItems
        ];
    }

    private function detectCategory($title) {
        if (preg_match('/rosa|colombiana/i', $title)) return 'Rosas Colombianas';
        if (preg_match('/cesta|café|cafe/i', $title)) return 'Cestas Personalizadas';
        if (preg_match('/buquê|buque|lily/i', $title)) return 'Buquês de Luxo';
        if (preg_match('/arranjo|vaso|lírio|lirio/i', $title)) return 'Arranjos & Vasos';
        if (preg_match('/kit|presente|bombom|ferreiro|urso|chandon/i', $title)) return 'KITS & Presentes';
        if (preg_match('/orquídea|orquidea|planta|girassol/i', $title)) return 'Orquídeas & Plantas';
        return 'Rosas Colombianas';
    }

    private function getOrCreateCategory($name) {
        $slug = $this->createSlug($name);
        $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE slug = ? OR name = ?");
        $stmt->execute([$slug, $name]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $ins = $this->pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $ins->execute([$name, $slug]);
            $id = $this->pdo->lastInsertId();
        }
        return $id;
    }

    private function downloadImage($url, $slug) {
        if (empty($url) || strpos($url, 'data:image') === 0) return null;
        if (strpos($url, 'http') !== 0) return $url;

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$ext || strlen($ext) > 4) $ext = 'jpg';
        $filename = 'wa_' . $slug . '_' . time() . '.' . $ext;
        $dest = $this->uploadDir . $filename;

        $imgData = @file_get_contents($url);
        if ($imgData && strlen($imgData) > 500) {
            file_put_contents($dest, $imgData);
            return $filename;
        }

        return $url;
    }

    private function getCuratedFallbackImage($title) {
        if (preg_match('/girassol/i', $title)) return 'https://images.unsplash.com/photo-1591886960571-74d43a9d4166?w=800&q=80';
        if (preg_match('/cesta|café|cafe/i', $title)) return 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80';
        if (preg_match('/rosa|colombiana/i', $title)) return 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80';
        if (preg_match('/arranjo|vaso/i', $title)) return 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80';
        if (preg_match('/ferreiro|chocolates|bombom/i', $title)) return 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80';
        return 'https://images.unsplash.com/photo-1567696911980-2eed69a46042?w=800&q=80';
    }

    private function cleanPrice($priceStr) {
        if (is_numeric($priceStr)) return floatval($priceStr);
        $clean = preg_replace('/[^\d,\.]/', '', (string)$priceStr);
        if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else if (strpos($clean, ',') !== false) {
            $clean = str_replace(',', '.', $clean);
        }
        return floatval($clean);
    }

    private function createSlug($text) {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a-' . time() : $text;
    }
}

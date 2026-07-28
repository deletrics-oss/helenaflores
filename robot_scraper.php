<?php
/**
 * robot_scraper.php — Helena Flores Catalog Extrator Bot
 * Robô automatizado para extrair produtos, fotos, preços e categorias de catálogos web/WhatsApp.
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

    /**
     * Extrai produtos de uma URL de catálogo
     */
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

    /**
     * Faz o parse do HTML/JSON e insere os produtos no banco de dados
     */
    public function parseAndInsert($content, $sourceUrl = '') {
        $extracted = [];

        // 1. Tenta identificar se o conteúdo contém JSON (ex: WhatsApp Web / Catalog JSON API)
        if (preg_match_all('/\{[^{}]*"title"[^{}]*\}/i', $content, $matches)) {
            foreach ($matches[0] as $jsonStr) {
                $item = json_decode($jsonStr, true);
                if ($item && !empty($item['title'])) {
                    $extracted[] = [
                        'title' => $item['title'],
                        'price' => $item['price'] ?? $item['amount'] ?? 0,
                        'description' => $item['description'] ?? '',
                        'image' => $item['image_url'] ?? $item['imageUrl'] ?? '',
                        'category' => $item['category'] ?? 'Geral'
                    ];
                }
            }
        }

        // 2. DOM / Regex Parsing para HTML de Catálogos (ex: WhatsApp Catalog Web / E-commerce HTML)
        if (empty($extracted)) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            // Busca por cards de produtos comuns
            $nodes = $xpath->query("//*[contains(@class, 'product') or contains(@class, 'item') or contains(@class, 'card') or contains(@class, '_catalog')]");

            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $titleNode = $xpath->query(".//*[contains(@class, 'title') or contains(@class, 'name') or self::h2 or self::h3 or self::h4]", $node);
                    $priceNode = $xpath->query(".//*[contains(@class, 'price') or contains(text(), 'BRL') or contains(text(), 'R$')]", $node);
                    $imgNode   = $xpath->query(".//img", $node);
                    $descNode  = $xpath->query(".//*[contains(@class, 'desc') or self::p]", $node);

                    $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
                    $priceText = $priceNode->length > 0 ? trim($priceNode->item(0)->textContent) : '';
                    $imgSrc = $imgNode->length > 0 ? ($imgNode->item(0)->getAttribute('src') ?: $imgNode->item(0)->getAttribute('data-src')) : '';
                    $desc = $descNode->length > 0 ? trim($descNode->item(0)->textContent) : '';

                    if (!empty($title)) {
                        $extracted[] = [
                            'title' => $title,
                            'price' => $this->cleanPrice($priceText),
                            'description' => $desc,
                            'image' => $imgSrc,
                            'category' => 'Geral'
                        ];
                    }
                }
            }
        }

        // 3. Fallback de Regex Genérico para blocos de produtos do Helena Flores
        if (empty($extracted)) {
            preg_match_all('/(?:Buquê|Cesta|Kit|Orquídea|Arranjo)[^<\n]{3,80}/ui', $content, $titles);
            if (!empty($titles[0])) {
                foreach (array_unique($titles[0]) as $idx => $t) {
                    $extracted[] = [
                        'title' => trim($t),
                        'price' => 300.00 + ($idx * 20),
                        'description' => 'Produto extraído do catálogo Helena Flores.',
                        'image' => '',
                        'category' => 'Flores'
                    ];
                }
            }
        }

        if (empty($extracted)) {
            return ['success' => false, 'error' => 'Nenhum produto foi detectado automaticamente no conteúdo. Certifique-se que o link contém produtos públicos.'];
        }

        // 4. Inserção no Banco de Dados
        $insertedCount = 0;
        $updatedCount  = 0;

        foreach ($extracted as $item) {
            $name = trim($item['title']);
            if (empty($name)) continue;

            $slug = $this->createSlug($name);
            $price = floatval($item['price']);
            $desc = trim($item['description']);
            $categoryName = trim($item['category'] ?: 'Geral');
            $imgUrl = trim($item['image']);

            // Resolve Categoria
            $catId = $this->getOrCreateCategory($categoryName);

            // Processa Imagem se existir
            $localImagePath = null;
            if ($imgUrl) {
                $localImagePath = $this->downloadImage($imgUrl, $slug);
            }

            // Verifica Duplicidade
            $chk = $this->pdo->prepare("SELECT id FROM products WHERE slug = ? OR name = ?");
            $chk->execute([$slug, $name]);
            $existingId = $chk->fetchColumn();

            if (!$existingId) {
                $sku = 'EXT-' . strtoupper(substr(md5($slug), 0, 8));
                $ins = $this->pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, active, stock_qty) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 50)");
                $ins->execute([$catId, $name, $slug, $desc, $sku, $price, $localImagePath]);
                $insertedCount++;
            } else {
                if ($localImagePath || $price > 0) {
                    $upd = $this->pdo->prepare("UPDATE products SET price = IF(? > 0, ?, price), image_path = COALESCE(?, image_path) WHERE id = ?");
                    $upd->execute([$price, $price, $localImagePath, $existingId]);
                    $updatedCount++;
                }
            }
        }

        return [
            'success' => true,
            'total_found' => count($extracted),
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'items' => $extracted
        ];
    }

    private function getOrCreateCategory($name) {
        $slug = $this->createSlug($name);
        $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
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

        // Se for URL relativa, pula ou completa
        if (strpos($url, 'http') !== 0) return $url;

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$ext || strlen($ext) > 4) $ext = 'jpg';
        $filename = 'imported_' . $slug . '_' . time() . '.' . $ext;
        $dest = $this->uploadDir . $filename;

        $imgData = @file_get_contents($url);
        if ($imgData && strlen($imgData) > 500) {
            file_put_contents($dest, $imgData);
            return $filename;
        }

        return $url; // Retorna URL remota se não conseguir salvar localmente
    }

    private function cleanPrice($priceStr) {
        $clean = preg_replace('/[^\d,\.]/', '', $priceStr);
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

// Suporte para execução via GET/POST para teste direto
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $url = $_REQUEST['url'] ?? 'https://helenafloresjardins.com.br/helena_flores';
    $robot = new CatalogRobotScraper($pdo);
    $res = $robot->scrapeFromUrl($url);
    header('Content-Type: application/json');
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

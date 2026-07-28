<?php
class Scraper
{

    public static function fetch($url)
    {
        $data = [
            'name' => '',
            'description' => '',
            'price' => 0,
            'image' => '',
            'sku' => '',
            'source_url' => $url
        ];

        // Basic CURL Setup
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.google.com/'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html)
            return false;

        // Detect Platform
        if (strpos($url, 'mercadolivre.com.br') !== false) {
            return self::parseMercadoLivre($html, $data);
        }

        // Generic Nuvem Shop Detection (Bigames, Raspgames are Nuvem Shop)
        // They often use "js-price-display" class or "property='og:type' content='product'"
        if (
            strpos($html, 'nuvemshop') !== false || strpos($html, 'js-product-container') !== false ||
            strpos($url, 'bigames.com.br') !== false || strpos($url, 'raspgames.com.br') !== false
        ) {
            $ns = self::parseNuvemShop($html, $data);
            if ($ns)
                return $ns; // Return if successful, otherwise fall to generic
        }

        // Generic Fallback
        return self::parseGeneric($html, $data);
    }

    private static function parseNuvemShop($html, $data)
    {
        // 1. Name
        // Usually in <h1 class="js-product-name">...</h1>
        if (preg_match('/<h1[^>]*class="[^"]*js-product-name[^"]*"[^>]*>(.*?)<\/h1>/i', $html, $m)) {
            $data['name'] = trim(strip_tags($m[1]));
        } elseif (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $html, $m)) {
            // Fallback H1
            $data['name'] = trim(strip_tags($m[1]));
        }

        // 2. Price
        // <span class="js-price-display" id="price_display">R$ 1.234,00</span>
        if (preg_match('/class="[^"]*js-price-display[^"]*"[^>]*>(.*?)<\/span>/i', $html, $m)) {
            $priceRaw = strip_tags($m[1]); // R$ 1.234,00
            $priceRaw = str_replace(['R$', ' ', '.'], '', $priceRaw); // 1,234,00 -> 1234,00
            $priceRaw = str_replace(',', '.', $priceRaw); // 1234.00
            $data['price'] = (float) $priceRaw;
        } elseif (preg_match('/meta property="og:price:amount" content="([\d\.]+)"/', $html, $m)) {
            $data['price'] = (float) $m[1];
        }

        // 3. Image
        if (preg_match('/meta property="og:image" content="(.*?)"/', $html, $m)) {
            $data['image'] = $m[1];
            // Sometimes NuvemShop gives an image with // prefix
            if (strpos($data['image'], '//') === 0)
                $data['image'] = 'https:' . $data['image'];
        }

        // 4. Description
        if (preg_match('/meta property="og:description" content="(.*?)"/', $html, $m)) {
            $data['description'] = $m[1];
        }

        return (!empty($data['name'])) ? $data : false;
    }

    private static function parseMercadoLivre($html, $data)
    {
        // ML uses JSON-LD heavily
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            $json = json_decode($matches[1], true);

            // ML structure varies. usually main entity is Product
            if (isset($json['@type']) && $json['@type'] === 'Product') {
                $data['name'] = $json['name'];
                $data['image'] = $json['image'];
                $data['description'] = substr(strip_tags($json['description'] ?? ''), 0, 500) . '...';
                $data['sku'] = $json['sku'] ?? '';
                if (isset($json['offers']['price'])) {
                    $data['price'] = $json['offers']['price'];
                }
                return $data;
            } elseif (is_array($json)) {
                // Sometimes it's a list of objects
                foreach ($json as $obj) {
                    if (isset($obj['@type']) && $obj['@type'] === 'Product') {
                        $data['name'] = $obj['name'];
                        $data['image'] = is_array($obj['image']) ? $obj['image'][0] : $obj['image'];
                        $data['description'] = $obj['description'] ?? '';
                        $data['sku'] = $obj['sku'] ?? '';
                        $data['price'] = $obj['offers']['price'] ?? 0;
                        return $data;
                    }
                }
            }
        }

        // Fallback to DOM Regex for ML
        preg_match('/<h1 class="ui-pdp-title">(.*?)<\/h1>/', $html, $m_title);
        if (!empty($m_title[1]))
            $data['name'] = strip_tags($m_title[1]);

        preg_match('/<meta property="og:image" content="(.*?)"/', $html, $m_img);
        if (!empty($m_img[1]))
            $data['image'] = $m_img[1];

        return $data;
    }

    private static function parseGeneric($html, $data)
    {
        // OG Tags
        if (preg_match('/<meta property="og:title" content="(.*?)"/i', $html, $m))
            $data['name'] = $m[1];
        if (preg_match('/<meta property="og:description" content="(.*?)"/i', $html, $m))
            $data['description'] = $m[1];
        if (preg_match('/<meta property="og:image" content="(.*?)"/i', $html, $m))
            $data['image'] = $m[1];

        // Title Fallback
        if (!$data['name'] && preg_match('/<title>(.*?)<\/title>/i', $html, $m))
            $data['name'] = $m[1];

        // Check for Cloudflare / Captcha
        if (preg_match('/Just a moment|Access denied|Security Check|Attention Required|Cloudflare/i', $data['name'])) {
            return false;
        }

        // Price attempts (Improved)
        // Look for common price patterns: R$ 1.234,56 or 1234,56
        if (!$data['price'] && preg_match('/R\$\s?([\d\.]+,\d{2})/', $html, $m)) {
            $data['price'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        } elseif (!$data['price'] && preg_match('/content="([\d\.]+)"/i', $html, $m)) {
            // Meta Price often implies currency format
            $val = $m[1];
            if (is_numeric($val))
                $data['price'] = $val;
        }

        // Filter Garbage: If name is too short or generic
        if (strlen($data['name']) < 5 || strpos($data['name'], '404') !== false) {
            return false;
        }

        return $data;
    }
    public static function discoverLinks($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html)
            return [];

        $links = [];
        $domain = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $base = $scheme . '://' . $domain;

        // Extract all AHREF
        if (preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                // Normalize URL
                if (strpos($href, '//') === 0) {
                    $href = $scheme . ':' . $href;
                } elseif (strpos($href, '/') === 0) {
                    $href = $base . $href;
                } elseif (strpos($href, 'http') !== 0) {
                    $href = $base . '/' . $href;
                }

                // Filter Logic
                if (strpos($href, $domain) !== false) {
                    $path = parse_url($href, PHP_URL_PATH);

                    // 1. SPECIFIC: MERCADO LIVRE (Strict Filter)
                    if (strpos($domain, 'mercadolivre') !== false) {
                        // EXCLUDE Technical/Account links
                        if (preg_match('/(registration|login|jms|navigation|addresses-hub|perguntas|opinioes|questoes|anuncios|vendas|compra|resumo|assinaturas|mais-vendidos|lojas-oficiais|promocoes|categoria|ofertas)/i', $href)) {
                            continue;
                        }

                        // MUST look like a product:
                        // 1. Contains "MLB" in path
                        // 2. Contains "/p/" in path
                        if (stripos($href, 'MLB') !== false || stripos($path, '/p/') !== false) {
                            $links[] = $href;
                        }
                        continue;
                    }

                    // 2. NUVEMSHOP / BIGAMES / WOOCOMMERCE Support
                    // Includes /loja/ (Category pages)
                    if (
                        (strpos($path, '/produtos/') !== false && $path !== '/produtos/') ||
                        (strpos($path, '/produto/') !== false && $path !== '/produto/') ||
                        (strpos($path, '/loja/') !== false && $path !== '/loja/')
                    ) {
                        $links[] = $href;
                        continue;
                    }

                    // 3. GENERIC Logic (Mercado Livre etc)
                    if (
                        strlen($path) > 10 &&
                        !preg_match('/(login|register|cart|checkout|conta|minha-conta|search|busca|fale-conosco|politica|termos|category|categoria)/i', $path) &&
                        (preg_match('/\d+/', $path) || substr_count($path, '/') >= 2)
                    ) {
                        $links[] = $href;
                    }
                }
            }
        }

        return array_unique($links);
    }
}
?>
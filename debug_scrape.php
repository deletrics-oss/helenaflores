<?php
require_once __DIR__ . '/includes/Scraper.php';

$url = "https://www.bigames.com.br/produtos/";
echo "Testing URL: $url\n";

// 1. Fetch RAW HTML
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$html = curl_exec($ch);
curl_close($ch);

echo "HTML Length: " . strlen($html) . "\n";

// 2. Test Discovery
$links = Scraper::discoverLinks($url);
echo "Discovered Links Count: " . count($links) . "\n";
print_r($links);

// 3. Test Regex Manually
echo "\n--- Regex Test ---\n";
preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/i', $html, $matches);
echo "Total AHREFs found: " . count($matches[1]) . "\n";

$domain = 'bigames.com.br';
foreach ($matches[1] as $href) {
    if (strpos($href, $domain) !== false && strpos($href, '/produtos/') !== false) {
        echo "Candidate: $href\n";
    }
}
?>
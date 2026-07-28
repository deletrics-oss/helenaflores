<?php
$address = 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP';
$query = urlencode($address . ', Brasil');
$url   = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1&countrycodes=br";
echo "URL: $url\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
curl_close($ch);
print_r($response);
echo "\n";

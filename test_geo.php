<?php
$address = 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP';
$query = urlencode($address . ', Brasil');
$url   = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1&countrycodes=br";
echo "URL: $url\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['User-Agent: FightArcade/1.0 (deletrics@gmail.com)'],
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
curl_close($ch);
print_r($response);
echo "\n";

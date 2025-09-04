<?php
$ch = curl_init('https://127.0.0.1:8001/api/cart');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Cookie: cart_id=01K4A2DMWKKP6BEXKXHHPQASTT'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$info = curl_getinfo($ch);

echo "HTTP Code: $httpCode\n";
echo "URL: " . $info['url'] . "\n";
echo "Response:\n";
echo $response . "\n";

curl_close($ch);
?>

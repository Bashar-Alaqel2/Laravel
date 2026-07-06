<?php
$url2 = 'https://uyvykohckfygsbxbzrpp.supabase.co/storage/v1/object/public/ads/ads/Ru4HlqPYXMzSlQ04S0UPyzRmv88Klr5eUOpNIRuf.jpg';
$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$res2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
echo "\nPublic REST Endpoint 2: Code $code2\n";
echo "Body: " . substr($res2, 0, 50) . "...\n";

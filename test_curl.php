<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'ftp://150.95.25.192:21/');
curl_setopt($ch, CURLOPT_USERPWD, 'myhostserver6902:jFitW3iiEpE5E2yG');
curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_DIRLISTONLY, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
if ($result === false) {
    echo "Error: " . curl_error($ch) . "\n";
} else {
    echo "Success! Dir contents length: " . strlen($result) . "\n";
}
curl_close($ch);

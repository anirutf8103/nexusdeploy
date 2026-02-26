<?php
$ch = curl_init("ftp://invalid.example.com/file.txt");
$mh = curl_multi_init();
curl_multi_add_handle($mh, $ch);

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

var_dump(curl_errno($ch));
echo "Info from curl_multi_info_read:\n";
while ($info = curl_multi_info_read($mh)) {
    var_dump($info['result']);
}
curl_multi_remove_handle($mh, $ch);
curl_close($ch);
curl_multi_close($mh);

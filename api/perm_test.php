<?php
$file = __DIR__ . '/../data/perm_test.txt';
$res = @file_put_contents($file, "TEST");
echo json_encode(["writable" => ($res !== false), "user" => exec('whoami'), "path" => $file]);

<?php
$output = [];
$return_code = -1;
exec('echo HELLO', $output, $return_code);
echo json_encode(['output' => $output, 'return_code' => $return_code]);

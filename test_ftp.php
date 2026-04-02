<?php
$conn = ftp_ssl_connect('150.95.25.192', 21, 5);
if (!$conn) die("ssl_connect failed\n");
echo "ssl_connect ok\n";
$login = ftp_login($conn, 'myhostserver6902', 'jFitW3iiEpE5E2yG');
if (!$login) {
    print_r(error_get_last());
    die("login failed\n");
}
echo "login ok\n";
ftp_close($conn);

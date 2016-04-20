#!/usr/bin/php -q
<?php
exit; // do not release on Prod
date_default_timezone_set("EST");
error_reporting(~E_WARNING);

$log_path = '/data/api/logs';
$log_file = $log_path.'/api_log';

//Create a UDP socket
if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
    $errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);

    die("Couldn't create socket: [$errorcode] $errormsg \n");
}

echo "Socket created \n";

// Bind the source address
if(!socket_bind($sock, "10.0.0.0", 65000)) {
    $errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);

    die("Could not bind socket : [$errorcode] $errormsg \n");
}

echo "Socket bind OK \n";

//Do some communication, this loop can handle multiple clients
while(1) {
    //Receive some data
    $r = socket_recvfrom($sock, $log, 1024, 0, $remote_ip, $remote_port);
    file_put_contents($log_file, "$remote_ip\t$remote_port\t".date("Y/m/d H:i:s")."\t".$log."\n", FILE_APPEND);
}

socket_close($sock);
?>
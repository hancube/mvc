<?php
namespace hc\mvc;

class Log {
    public static $server;
    public static $port;

    public static $start_ms;
    public static $total_ms;

    public static $data;

    public function __construct() {
        self::$start_ms = 0;
        self::$end_ms = 0;
        return true;
    }
    public static function startLog($server, $port) {
        self::$server = $server;
        self::$port = $port;
        self::setStart();
        return true;
    }
    public static function sendLog($data) {
        self::$data = $data;
        self::setTotal();
        $log = self::makeLog();
        self::sendLogToServer($log);
    }

    private static function getCurrentMS() {
        $mt = explode(' ', microtime());
        return $mt[1] * 1000 + round($mt[0] * 1000);
    }
    private static function setStart() {
        self::$start_ms = self::getCurrentMS();
    }
    private static function setTotal() {
        self::$total_ms = self::getCurrentMS() - self::$start_ms;
    }
    private static function getFormatedTotal() {
        return number_format(self::$total_ms, 2);
    }
    private static function makeLog() {
        $tst = self::getFormatedTotal();
        $request_uri = $_SERVER["REQUEST_URI"];
        $user_ip = Security::getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $log = '';
        if (isset(self::$data) && !empty(self::$data)) {
            foreach(self::$data as $key => $val) {
                if ($log != '') $log .= "\t";
                $log .= $val;
            }
        }
        $log .= "\t".$tst;
        $log .= "\t".$request_uri;
        $log .= "\t".$user_ip;
        $log .= "\t".$user_agent;

        return $log;
    }
    private static function sendLogToServer($log) {
        $sock = socket_create(AF_INET, SOCK_DGRAM, 0);
        if ($sock) {
            //echo 'socket_create OK';
        }
        $result = socket_sendto($sock, $log , strlen($log) , 0 , self::$server , self::$port);
        if ($result) {
            //echo 'socket_sendto OK';
        }
    }
}

?>
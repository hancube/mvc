<?php
/**
 * @author Sungok Lim
 */
class Debug {
    public static $last_ttt;
    public static $total_ttt;
    public static $number;
    public function __construct() {
        self::$total_ttt = 0;
        self::$number = 0;
        return true;
    }
    private static function PrintR($arr, $color='#909090', $bg_color='#FFFFFF') {
        echo '<div style="color:'.$color.';background-color:'.$bg_color.'"><pre>';
        print_r($arr);
        echo '</pre></div>';
        return true;
    }
    public static function Error($ext) {
        if (!(DEBUG & DEBUG_ERROR)) return false;
        //self::PrintR($ext->getTraceAsString());
        return true;
    }
    public static function ppp($arr, $color='Silver') {
        if (!(DEBUG & DEBUG_PPP)) return false;
        self::PrintR($arr, $color);
        return true;
    }
    public static function ddd($arr, $color='Silver') {
        if (!(DEBUG & DEBUG_DDD)) return false;
        self::PrintR($arr, $color);
        exit;
    }
    public static function ttt($title) {
        if (!(DEBUG & DEBUG_TTT)) return false;
        $current_ttt = microtime();
        $interval = 0;
        if (isset(self::$last_ttt)) {
            $interval = $current_ttt - self::$last_ttt;
        }
        self::$last_ttt = $current_ttt;
        self::$total_ttt += $interval;
        self::$number++;
        if ($title == 'total') {
            $title = 'Total Server Time: '.number_format(Debug::$total_ttt, 6);
        }
        //self::PrintR(date('Y-m-d H:i:s').' ('.number_format($interval, 6).') '.$title, 'DeepSkyBlue');
        self::PrintR('['.self::$number.'] '.$title, '#006699', '#F6F6F6');

        return true;
    }
}

?>
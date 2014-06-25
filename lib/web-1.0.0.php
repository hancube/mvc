<?php
/**
 * @author HanCube.com
 */

class Web {

    public static $args = array();

    public function __construct() {

    }

    public static function setArgs() {
        global $_GET;
        global $_POST;
        global $_FILES;

        if (isset($_GET)) {
            foreach ($_GET as $key => $val) {
                self::$args[$key] = $val;
            }
        }
        if (isset($_POST)) {
            foreach ($_POST as $key => $val) {
                self::$args[$key] = $val;
            }
    }
        if (isset($_FILES)) {
            foreach ($_FILES as $key => $val) {
                self::$args[$key] = $val;
            }
        }

        return true;
    }
    
    public static function getArgs($var = '') {
        $result = false;
        if (count(self::$args) <= 0) { // make sure only one time execution per session
            self::setArgs();
        }
        if (isset($var) && !empty($var)) {
            if (isset(self::$args[$var])) {
                $result = self::$args[$var];
            }

        }else {
            $result = self::$args;
        }
        return $result;
    }
    public static function getArg($var) {
        return self::getArgs($var);
    }


}

?>

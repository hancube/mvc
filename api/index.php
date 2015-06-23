<?php
if (defined('ROOT')) {
    require_once (ROOT.'/config/config.php');
}else {
    require_once ('./config/config.php');
}
require_once (ROOT.'/lib/controller.php');
require_once (ROOT.'/lib/model.php');

function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = false;

    $arr_ipaddress = explode(',', $ipaddress);
    $ipaddress = trim($arr_ipaddress[0]);

    debug::ttt('index::get_client_ip()');
    debug::ppp('HTTP_CLIENT_IP: '.getenv('HTTP_CLIENT_IP'));
    debug::ppp('HTTP_X_FORWARDED_FOR: '.getenv('HTTP_X_FORWARDED_FOR'));
    debug::ppp('HTTP_X_FORWARDED: '.getenv('HTTP_X_FORWARDED'));
    debug::ppp('HTTP_FORWARDED_FOR: '.getenv('HTTP_FORWARDED_FOR'));
    debug::ppp('REMOTE_ADDR: '.getenv('REMOTE_ADDR'));
    debug::ppp('Client IP: '.$ipaddress);

    return $ipaddress;
}
// Check White List
if (USE_WHITELIST === true) {
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $domain = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $arr_domain = explode('.', $domain);
        $domain2 = count($arr_domain) - 1;
        $domain1 = $domain2 - 1;
        $domain = $arr_domain[$domain1].'.'.$arr_domain[$domain2];
        if (!in_array($domain, $WHITELIST)) {
            Controller::StaticError('INIT_ERRORS', 'ERROR_NOT_ALLOWED_DOMAIN');
        }
    }else {
        // Check IP Range
        if (USE_ALLOWED_IP === true) {
            $ip = get_client_ip();
            if ($ip !== false) {
                $arr_ip_class = explode('.', $ip);
                $ip_ok = false;
                for ($i=0; $i < count($ALLOWED_IPS); $i++) {
                    $arr_allowed_ip_class = explode('.', $ALLOWED_IPS[$i]);
                    if (($arr_ip_class[0] == $arr_allowed_ip_class[0] || $arr_allowed_ip_class[0] == '*')
                        && ($arr_ip_class[1] == $arr_allowed_ip_class[1] || $arr_allowed_ip_class[1] == '*')
                        && ($arr_ip_class[2] == $arr_allowed_ip_class[2] || $arr_allowed_ip_class[2] == '*')
                        && ($arr_ip_class[3] == $arr_allowed_ip_class[3] || $arr_allowed_ip_class[3] == '*')) {
                        $ip_ok = true;
                        break;
                    }
                }
                if ($ip_ok === false) {
                    Controller::StaticError('INIT_ERRORS', 'ERROR_NOT_ALLOWED_IP');
                }
            }else {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NOT_ALLOWED_IP');
            }
        }else {
            Controller::StaticError('INIT_ERRORS', 'ERROR_NOT_ALLOWED_DOMAIN');
        }
    }
}
debug::ttt('Index');

if (DEBUG & DEBUG_ERROR) {
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
}

$controller = Web::getArg('c');
$action = Web::getArg('a');

// Controller & Action
$controller_file = ROOT.'/controllers/'.$controller.'_controller.php';

if (file_exists($controller_file)) {
    require_once ($controller_file);
    $show_error = false;

    $controller_class = ucfirst($controller).'Controller';
    if (class_exists($controller_class)) {
        eval('$ctrl = new '.$controller_class.'();');

        if (method_exists($ctrl, $action)) {
            $ctrl->run();

            if (!$ctrl->isError()) {
                eval('$ctrl->'.$action.'();');
            }
        }else {
            Controller::StaticError('INIT_ERRORS', 'ERROR_NO_ACTION');
        }
    }else {
        Controller::StaticError('INIT_ERRORS', 'ERROR_NO_CONTROLLER_CLASS');
    }
}else {
    Controller::StaticError('INIT_ERRORS', 'ERROR_NO_CONTROLLER_FILE');
}

Debug::ttt('total');

?>
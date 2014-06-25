<?php
if (defined('ROOT')) {
    require_once (ROOT.'/config/config.php');
}else {
    require_once ('./config/config.php');
}

require_once (ROOT.'/lib/controller.php');
require_once (ROOT.'/lib/model.php');

// Check White List
if (USE_WHITELIST === true) {
    $domain = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $arr_domain = explode('.', $domain);
    $domain2 = count($arr_domain) - 1;
    $domain1 = $domain2 - 1;
    $domain = $arr_domain[$domain1].'.'.$arr_domain[$domain2];
    if (!in_array($domain, $WHITELIST)) {
        Controller::StaticError('INIT_ERRORS', 'ERROR_NOT_ALLOWED_DOMAIN');
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

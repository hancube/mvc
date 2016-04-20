<?php
// FRONT CONTROL
header('Access-Control-Allow-Origin: *');

require_once ('config/config.php');
require_once (MVC_LIB.'/lib/security.php');
require_once (MVC_LIB.'/lib/log.php');
require_once (API_ROOT.'/lib/controller.php');
require_once (API_ROOT.'/lib/model.php');

$_GET = hc\mvc\Web::getArgsFromUri($_SERVER['REQUEST_URI']);

if (SERVICE == SERVICE_LIVE) {
    hc\mvc\Log::startLog(APILOG_UDP_SERVER_IP, APILOG_UDP_SERVER_PORT);
}

// Check White List
$sec = new hc\mvc\Security();
$sec->setWhitelist(USE_WHITELIST, $WHITELIST);
$sec->setAllowedIPs(USE_ALLOWED_IP, $ALLOWED_IPS);
$sec->setAuthUsers(USE_AUTH_USERS, $AUTH_USERS);
$allowance = $sec->checkSecurity();
if ($allowance !== true) {
    Controller::StaticError('INIT_ERRORS', 'ERROR_NOT_ALLOWED_ACCESS');
}

hc\mvc\Debug::ttt('Index');

$controller = hc\mvc\Web::getArg('c');
$action = hc\mvc\Web::getArg('a');

if (DEBUG & DEBUG_ERROR) {
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
}

// Controller & Action
$controller_file = API_ROOT.'/controllers/'.$controller.'_controller.php';

if (file_exists($controller_file)) {
    require_once ($controller_file);
    $show_error = false;

    $controller_class = hc\mvc\Web::getClassName($controller);
    $controller_class .= 'Controller';
    hc\mvc\Debug::ppp($controller_class);

    if (class_exists($controller_class)) {
        eval('$ctrl = new '.$controller_class.'();');

        $result = $ctrl->runAction($action);
        if ($result !== true) {
            Controller::StaticError('INIT_ERRORS', $result);
        }
    }else {
        Controller::StaticError('INIT_ERRORS', 'ERROR_NO_CONTROLLER_CLASS');
    }
}else {
    Controller::StaticError('INIT_ERRORS', 'ERROR_NO_CONTROLLER_FILE');
}

// Log
if (USE_APILOG === true && SERVICE == SERVICE_LIVE) {
    if (!isset($ctrl)) {
        $result = 0;
        $error_code = 'ERROR_NO_CONTROLLER_CLASS';
        $data_size = 0;

        $data = array(
            'controller'  => $controller,
            'action'      => $action,
            'result'      => $result,
            'error_code'  => $error_code,
            'data_size'   => $data_size
        );
        hc\mvc\Log::sendLog($data);
    }else {
        $ctrl->log($ctrl->output);
    }
}

hc\mvc\Debug::ttt('total');
?>
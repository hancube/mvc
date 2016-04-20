<?php
date_default_timezone_set("EST");

ini_set('zlib.output_compression',1);
ini_set('zlib.output_compression_level',6);

define('MVC_VERSION', '4.1.3');
define('MVC_CODE_VERSION', '4.1.3');
define('API_FOLDER', 'api');

// Define Domains and each server Hosts
define ('HOST', $_SERVER['HTTP_HOST']);
define ('HOST_LOCAL', 'local.test.com');
define ('HOST_QA', 'qa.test.com');
define ('HOST_LIVE', 'test.com');
$_QA_HOSTS = array(
    'q1.test.com',
    'q2.test.com'
);
$_LIVE_HOSTS = array(
    'www1.test.com',
    'www2.test.com',
    'www3.test.com',
    'www4.test.com',
    'www5.test.com'
);

// Make Web Host
$_QA_WEB_HOSTS = array();
$_LIVE_WEB_HOSTS = array();
foreach ($_QA_HOSTS as $host) array_push($_QA_WEB_HOSTS, $host.':8000');
foreach ($_LIVE_HOSTS as $host) array_push($_LIVE_WEB_HOSTS, $host.':8000');

// SERVICE
define ('SERVICE_LIVE',     1);
define ('SERVICE_QA',  2);
define ('SERVICE_LOCAL',    3);

// Check what Service is using
if (HOST == HOST_LIVE || in_array(HOST, $_LIVE_WEB_HOSTS)) {
    define ('SERVICE', SERVICE_LIVE);
}else if (HOST == HOST_QA || in_array(HOST, $_QA_WEB_HOSTS)) {
    define ('SERVICE', SERVICE_QA);
}else if (HOST == HOST_LOCAL) {
    define ('SERVICE', SERVICE_LOCAL);
}

// Choose the right protocol
if (SERVICE == SERVICE_LOCAL) {
    define ('API_PROTOCOL', 'http');
}else {
    define('API_PROTOCOL', 'https');
}

// DEBUG Levels
define ('DEBUG_ERROR',      1);
define ('DEBUG_PPP',        2);
define ('DEBUG_DDD',        4);
define ('DEBUG_TTT',        8);
define ('DEBUG_SUNG_TEST',  16);

if (SERVICE !== SERVICE_LIVE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
}else {
    error_reporting(E_ALL);
    ini_set('display_errors', 'Off');
}

// Choose the debug level
if (SERVICE !== SERVICE_LIVE && (isset($_GET['debug']) && $_GET['debug'] == '1' || isset($_POST['debug']) && $_POST['debug'] == '1')) {// Restrict Debug on Live Server
//if (isset($_GET['debug']) && $_GET['debug'] == '1' || isset($_POST['debug']) && $_POST['debug'] == '1') { // Allow Debug on Live Server
    define ('DEBUG', DEBUG_ERROR + DEBUG_PPP + DEBUG_DDD + DEBUG_TTT);
}else {
    define ('DEBUG', 0);
}

// Define Paths
if (!defined('WEBROOT')) {
    define ('WEBROOT', $_SERVER['DOCUMENT_ROOT']);
}
if (!defined('API_ROOT')) {
    define ('API_ROOT', $_SERVER['DOCUMENT_ROOT'].'/'.API_FOLDER);
}
if (!defined('API_HOME')) {
    define ('API_HOME', API_PROTOCOL.'://'.HOST.'/'.API_FOLDER);
}
define ('LIB', API_ROOT.'/');
define ('API_LIB', API_ROOT.'/lib');
define ('VENDORS', WEBROOT.'/vendors');
define ('MVC_LIB', VENDORS.'/hancube/mvc-'.MVC_CODE_VERSION);
define ('TMP_PATH', '/tmp');
define ('CURR_URL', API_PROTOCOL.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

// Keeping model->fields
define('DOC_ACTIONS', 'wiki,ref,test');

// Prefix (Important)
$controller = '';
if (isset($_GET['c'])) {
    $controller = $_GET['c'];
}else if (isset($_POST['c'])) {
    $controller = $_POST['c'];
}
$prefix = '';
$cname = '';
if (!empty($controller)) {
    $pos = strpos($controller, '_');
    if ($pos !== false) {
        $prefix = substr($controller, 0, $pos);
        $cname = substr($controller, $pos + 1 - strlen($controller));
    }
}
define ('PREFIX', $prefix);
define ('CNAME', $cname);
if (file_exists('config/config_'.PREFIX.'.php')) {
    require_once('config/config_'.PREFIX.'.php');
}
unset($prefix);
unset($controller);

// Whitelists
define ('USE_WHITELIST', TRUE);
if (!isset($WHITELIST)) {
    $WHITELIST = array(
        'company.com'
    );
}

// Allowed IPs where there's no referer
require_once (MVC_LIB.'/lib/security.php');
define ('USER_IP', hc\mvc\Security::getClientIP());
define ('USE_ALLOWED_IP', TRUE);
if (!isset($ALLOWED_IPS)) {
    $ALLOWED_IPS = array(
        '*.*.*.*',
        '10.*.*.*',
        '127.0.0.1'
    );
}

// define fixed info
switch (SERVICE) {
    case SERVICE_LIVE:
        define ('DB_HOST', '');
        define ('DB_PORT', '3306');
        define ('API_DB_HOST', '');
        define ('API_DB_PORT', '3306');
        break;
    case SERVICE_QA:
        define ('DB_HOST', '');
        define ('DB_PORT', '3306');
        define ('API_DB_HOST', '');
        define ('API_DB_PORT', '3306');
        break;
    case SERVICE_LOCAL:
        define ('DB_HOST', '127.0.0.1');
        define ('DB_PORT', '3306');
        define ('API_DB_HOST', '127.0.0.1');
        define ('API_DB_PORT', '3306');
        break;
}

// Database
if (!defined('DB_NAME')) {
    switch (SERVICE) {
        case SERVICE_LIVE:
            define ('DB_NAME', '');
            define ('DB_USER', '');
            define ('DB_PASS', '');
            break;
        case SERVICE_QA:
            define ('DB_NAME', '');
            define ('DB_USER', '');
            define ('DB_PASS', '');
            break;
        case SERVICE_LOCAL:
            define ('DB_NAME', 'test');
            define ('DB_USER', 'root');
            define ('DB_PASS', 'root');
            break;
    }
}
if (!defined('API_DB_NAME')) {
    switch (SERVICE) {
        case SERVICE_LIVE:
            define ('API_DB_NAME', '');
            define ('API_DB_USER', '');
            define ('API_DB_PASS', '');
            break;
        case SERVICE_QA:
            define ('API_DB_NAME', '');
            define ('API_DB_USER', '');
            define ('API_DB_PASS', '');
            break;
        case SERVICE_LOCAL:
            define ('API_DB_NAME', 'test');
            define ('API_DB_USER', 'root');
            define ('API_DB_PASS', 'root');
            break;
    }
}
// Protocol
if (!defined('API_PROTOCOL')) {
    define ('API_PROTOCOL', 'https');
}

// Language
define ('LANG', 'en');

// Memcached
define ('USE_MEMCACHED', false);
switch (SERVICE) {
    case SERVICE_LIVE:
        $_MEMCACHED_HOST = $_LIVE_HOSTS;
        break;
    case SERVICE_QA:
        $_MEMCACHED_HOST = $_QA_HOSTS;
        break;
    case SERVICE_LOCAL:
        $_MEMCACHED_HOST = array(
            'localhost'
        );
        break;
}
define ('MEMCACHED_PORT', '11211');
define ('MEMCACHED_TIME', 600); // 600:10 mins,1800:half hour,3600:one hour

define('SELECT_LIMIT', 1000);
define ('RETURN_ARGS', false);

// APILOG UDP SERVER Information
define ('USE_APILOG', true);
define ('APILOG_UDP_SERVER_IP', '10.74.109.133');
define ('APILOG_UDP_SERVER_PORT', 65000);


/* Customized Constants */

?>
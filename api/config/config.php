<?php

    date_default_timezone_set("EST");

    define('MVC_VERSION', '3.3.0');
    define('MVC_CODE_VERSION', '3.3.0');
    define('API_FOLDER', 'api');
    define('API_PROTOCOL', 'http');

    // Keeping model->fields
    define('DOC_ACTIONS', 'wiki,ref,test');

    // Whitelists
    define ('USE_WHITELIST', false);
    $WHITELIST = array(
        'alloweddomain.com',
        'localhost:8888'
    );

    // Allowed IPs
    define ('USE_ALLOWED_IP', false);
    $ALLOWED_IPS = array(
        '1.*.*.*'
    );

    // Domain
    define ('DOMAIN', $_SERVER['HTTP_HOST']);
    define ('DOMAIN_LOCAL', 'localhost:8888');
    define ('DOMAIN_SANDBOX', 'test.hancube.com');
    define ('DOMAIN_LIVE', 'hancube.com');

    // SERVICE
    define ('SERVICE_LIVE',     1);
    define ('SERVICE_SANDBOX',  2);
    define ('SERVICE_LOCAL',    3);

    if (DOMAIN == DOMAIN_LIVE) {
        define ('SERVICE', SERVICE_LIVE);
    }else if (DOMAIN == DOMAIN_SANDBOX) {
        define ('SERVICE', SERVICE_SANDBOX);
    }else if (DOMAIN == DOMAIN_LOCAL) {
        define ('SERVICE', SERVICE_LOCAL);
    }

    // Database
    switch (SERVICE) {
        case SERVICE_LOCAL:
            define ('DB_HOST', '');
            define ('DB_PORT', '3306');
            define ('DB_NAME', '');
            define ('DB_USER', '');
            define ('DB_PASS', '');
            break;
        case SERVICE_SANDBOX:
            define ('DB_HOST', '');
            define ('DB_PORT', '3306');
            define ('DB_NAME', '');
            define ('DB_USER', '');
            define ('DB_PASS', '');
            break;
        default :
            define ('DB_HOST', '');
            define ('DB_PORT', '3306');
            define ('DB_NAME', '');
            define ('DB_USER', '');
            define ('DB_PASS', '');
            break;
    }

    // Language
    define ('LANG', 'en');

    // DEBUG
    define ('DEBUG_ERROR',      1);
    define ('DEBUG_PPP',        2);
    define ('DEBUG_DDD',        4);
    define ('DEBUG_TTT',        8);
    define ('DEBUG_SUNG_TEST',  16);
    if (SERVICE !== SERVICE_LIVE && (isset($_GET['debug']) && $_GET['debug'] == 'yes' || isset($_POST['debug']) && $_POST['debug'] == 'yes')) {
        define ('DEBUG', DEBUG_ERROR + DEBUG_PPP + DEBUG_DDD + DEBUG_TTT);
        error_reporting(E_ALL);
    }else {
        define ('DEBUG', 0);
        error_reporting(0);
        ini_set('display_errors', 'Off');
    }

    // Path
    if (!defined('WEBROOT')) {
        define ('WEBROOT', $_SERVER['DOCUMENT_ROOT']);
    }
    if (!defined('ROOT')) {
        define ('ROOT', $_SERVER['DOCUMENT_ROOT'].'/'.API_FOLDER);
    }
    if (!defined('HOME')) {
        define ('HOME', API_PROTOCOL.'://'.DOMAIN.'/'.API_FOLDER);
    }
    define ('LIB', ROOT.'/');
    define ('VENDORS', $_SERVER['DOCUMENT_ROOT'].'/vendors');
    define ('MVC_LIB', VENDORS.'/hancube/mvc-'.MVC_CODE_VERSION);
    define ('TMP_PATH', '/tmp');
    define ('CURR_URL', API_PROTOCOL.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

    // Memcache
    define ('USE_MEMCACHE', false);
    define ('MEMCACHE_HOST', 'localhost');
    define ('MEMCACHE_PORT', '11211');

    define('SELECT_LIMIT', 10000);
    define ('RETURN_ARGS', true);

    /* Customized Constants */

?>
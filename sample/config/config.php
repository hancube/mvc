<?php
    define('MVC_VERSION', '3.1.0');
    define('MVC_CODE_VERSION', '3.1.0');

    // Domain
    define ('DOMAIN', $_SERVER['HTTP_HOST']);
    define ('DOMAIN_LOCAL', 'localhost:8888');
    define ('DOMAIN_LIVE', 'hancube.com');
    define ('DOMAIN_SANDBOX', 'test.hancube.com');

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

    // DEBUG
    define ('DEBUG_ERROR',      1);
    define ('DEBUG_PPP',        2);
    define ('DEBUG_DDD',        4);
    define ('DEBUG_TTT',        8);
    define ('DEBUG_SUNG_TEST',  16);
    if (SERVICE != SERVICE_LIVE && (isset($_GET['debug']) && $_GET['debug'] == 'yes' || isset($_POST['debug']) && $_POST['debug'] == 'yes')) {
        define ('DEBUG', DEBUG_ERROR + DEBUG_PPP + DEBUG_DDD + DEBUG_TTT);
        error_reporting(E_ALL);
    }else {
        define ('DEBUG', 0);
        error_reporting(0);
        ini_set('display_errors', 'Off');
    }

    // Whitelists
    define ('USE_WHITELIST', false);
    $WHITELIST = array(
        'alloweddomain.com'
    );

    // Language
    define ('LANG', 'en');

    // Path
    $folder = getcwd();
    $folder = str_replace($_SERVER['DOCUMENT_ROOT'], '', $folder);
    if (!defined('ROOT')) {
        define ('ROOT', $_SERVER['DOCUMENT_ROOT'].$folder);
    }
    define ('HOME', 'http://'.DOMAIN.$folder);
    define ('VENDORS', $_SERVER['DOCUMENT_ROOT'].'/vendors');
    define ('MVC_LIB', VENDORS.'/hancube/mvc-'.MVC_CODE_VERSION);
    define ('TMP_PATH', '/tmp');

    // Database
    switch (SERVICE) {
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

    // Memcache
    define ('USE_MEMCACHE', false);
    define ('MEMCACHE_HOST', 'localhost');
    define ('MEMCACHE_PORT', '11211');

    define('SELECT_LIMIT', 10000);

?>
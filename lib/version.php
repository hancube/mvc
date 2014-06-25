<?php
switch (MVC_VERSION) {
    case '2.1.8':
        define('DEBUG_VERSION',     '1.0.0');
        define('DB_VERSION',        '1.0.0');
        define('STRING_VERSION',    '1.0.0');
        define('WEB_VERSION',       '1.0.0');
        define('VALIDATE_VERSION',  '1.0.0');

        define('OUTPUT_VERSION',    '1.0.0');
        break;
    case '3.0.0':
    case '3.1.0':
        define('DEBUG_VERSION',     '1.0.0');
        define('DB_VERSION',        '1.0.0');
        define('STRING_VERSION',    '1.0.0');
        define('WEB_VERSION',       '1.0.0');
        define('VALIDATE_VERSION',  '1.0.0');

        define('OUTPUT_VERSION',    '2.0.0');
        break;
}
?>
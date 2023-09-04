<?php
namespace ServerlessFC;

set_include_path(get_include_path() .
    PATH_SEPARATOR . '/code' .
    PATH_SEPARATOR . '/opt/php'.
    PATH_SEPARATOR . '/var/fc/runtime/php7.2');

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . "/src/constant.php";
require dirname(__DIR__) . "/src/util.php";
require dirname(__DIR__) . "/src/client.php";
require dirname(__DIR__) . "/src/http_cgi.php";
require dirname(__DIR__) . "/src/fc_init.php";
require dirname(__DIR__) . "/src/invoke.php";
require dirname(__DIR__) . "/src/http_wrapper.php";

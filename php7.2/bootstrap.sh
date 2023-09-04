#!/bin/bash

export PHP_INI_SCAN_DIR=${PHP_INI_SCAN_DIR:-/code/extension:/var/fc/lang/php7.2/etc:/var/fc/lang/php7.2/etc/conf.d:/opt/php}

exec /var/fc/lang/php7.2/bin/php /var/fc/runtime/php7.2/src/bootstrap.php 2>&1

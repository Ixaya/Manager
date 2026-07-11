#!/bin/bash

export LOG_LEVEL=debug
php_bin=/usr/local/bin/php
public_path=/var/www/html/public

all_args=("$@")

export REQUEST_METHOD=GET
exec /bin/nice -n 10 $php_bin -f $public_path/index.php "${all_args[@]}"

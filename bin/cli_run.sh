#!/bin/sh

#path to php bin
php_bin=/usr/bin/php
#path to website public folder
public_path=/home/example/app/public

all_args=("$@")
$php_bin -f $public_path/index.php ${all_args[@]}

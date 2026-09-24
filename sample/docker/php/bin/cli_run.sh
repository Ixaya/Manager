#!/bin/bash

# `docker exec` inherits the daemon's umask, which can be 0000; files this
# command writes to bind mounts would then be world-writable on the host.
umask 022

php_bin=/usr/local/bin/php
public_path=/var/www/html/public

all_args=("$@")

export REQUEST_METHOD=GET
exec /bin/nice -n 10 $php_bin -f $public_path/index.php "${all_args[@]}"

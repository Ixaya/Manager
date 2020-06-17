#development/production
php_env=development
#path to php bin
php_bin=/usr/bin/php
#path to website public folder
public_path=/home/example/app/public

all_args=("$@")
CI_ENV="$php_env" $php_bin -f $public_path/index.php ${all_args[@]}

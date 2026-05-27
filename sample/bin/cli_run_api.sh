#!/bin/sh

# Path to php binary
php_bin=/usr/bin/php
# Path to website public folder
public_path=/home/example/app/public
# Deprecated in most cases: ENVIRONMENT is handled via .env; only use when a custom env file (e.g. .env.dev) is required
# export CI_ENV=dev

# Force REQUEST_METHOD for CLI execution of REST controllers
method=GET
all_args=()

for arg in "$@"; do
  case "$arg" in
    --method=*)
      method="${arg#*=}"
      ;;
    *)
      all_args+=("$arg")
      ;;
  esac
done

export REQUEST_METHOD="$method"

# Replace this shell with nice + php (saves a process)
exec /usr/bin/nice -n 10 $php_bin -f $public_path/index.php "${all_args[@]}"


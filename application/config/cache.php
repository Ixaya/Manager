<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['adapter']  = mngr_env('CACHE_ADAPTER', 'apc');
$config['backup']   = mngr_env('CACHE_BACKUP', 'file');
$config['key_prefix'] = mngr_env('CACHE_KEY_PREFIX', '');

//Extended
// 'php', 'json', 'json_gzip', 'msgpack'
$config['serialization'] = mngr_env('CACHE_SERIALIZATION', 'php');
$config['default_ttl'] = mngr_env_int('CACHE_DEFAULT_TTL', 600);
$config['enable_logging'] = mngr_env_bool('CACHE_ENABLE_LOGGING', false);


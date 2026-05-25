<?php

defined('BASEPATH') or exit('No direct script access allowed');

$config['adapter']  = mgr_env('CACHE_ADAPTER', 'apc');
$config['backup']   = mgr_env('CACHE_BACKUP', 'file');
$config['key_prefix'] = mgr_env('CACHE_KEY_PREFIX', '');

//Extended
// 'php', 'json', 'json_gzip', 'msgpack'
$config['serialization'] = mgr_env('CACHE_SERIALIZATION', null); //Default is backward compatible
$config['default_ttl'] = mgr_env_int('CACHE_DEFAULT_TTL', 600);
$config['enable_logging'] = mgr_env_bool('CACHE_ENABLE_LOGGING', false);

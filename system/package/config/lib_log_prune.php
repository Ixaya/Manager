<?php

defined('BASEPATH') or exit('No direct script access allowed');

// 0 = stage off. The three delete stages default off on purpose; sample/.env.sample
// carries the recommended values.
$config['app_compress_after_days'] = mgr_env_int('MGR_LOG_PRUNE_APP_COMPRESS_AFTER_DAYS', 7);
$config['app_delete_after_days']   = mgr_env_int('MGR_LOG_PRUNE_APP_DELETE_AFTER_DAYS', 0);
$config['cli_max_size_mb']         = mgr_env_int('MGR_LOG_PRUNE_CLI_MAX_SIZE_MB', 50);
$config['cli_keep']                = mgr_env_int('MGR_LOG_PRUNE_CLI_KEEP', 0);
$config['api_delete_after_days']   = mgr_env_int('MGR_LOG_PRUNE_API_DELETE_AFTER_DAYS', 0);

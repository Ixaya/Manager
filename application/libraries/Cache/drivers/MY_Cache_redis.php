<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

require MGRPATH . "libraries/Cache/drivers/MGR_Cache_redis.php";
class MY_Cache_redis extends MGR_Cache_redis {}

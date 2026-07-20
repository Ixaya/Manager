<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* load the MX_Config class */
require dirname(__FILE__) . "/../../third_party/MX/Config.php";

// Subclassing via MY_Config makes load_class() cache an MX-capable Config
// from the first call. Without it, module config reads only work because
// MX rebinds the global $CFG mid-boot — a swap that never reaches the cache
// when the framework boots inside a function scope (PHPUnit bootstrap).
class MGR_Config extends MX_Config
{
}

<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* load the MX_Lang class */
require dirname(__FILE__) . "/../../third_party/MX/Lang.php";

// Same seam as MGR_Config: via MY_Lang, load_class() caches a module-aware
// Lang from the first call instead of relying on MX's mid-boot global swap,
// which never reaches the cache when the framework boots inside a function
// scope (PHPUnit bootstrap).
class MGR_Lang extends MX_Lang
{
}

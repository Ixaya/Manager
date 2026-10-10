<?php

defined('BASEPATH') or exit('No direct script access allowed');

// require_once, unlike the other shims: framework CLI controllers load this file
// too, and a second require of it is a "Cannot redeclare class" fatal.
require_once MGRPATH . "core/MGR_Cli_Controller.php";

class APP_Cli_Controller extends MGR_Cli_Controller
{
}

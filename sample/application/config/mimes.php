<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Pull in the Global Framework Configuration
|--------------------------------------------------------------------------
*/

$mimes = include MGRPATH . 'config/mimes.php';

// $mimes['foo'] = ['application/x-foo'];

return $mimes;

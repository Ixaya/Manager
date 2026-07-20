<?php

$_SERVER['CI_ENV'] = 'testing';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

// CI3 builds the CLI URI from argv, so phpunit's own flags (--filter,
// --testdox) would become route segments and 404 the boot. Pinning argv
// dispatches the default CLI route (manager/health_checks) instead.
$_SERVER['argv'] = ['index.php'];
$_SERVER['argc'] = 1;

// The boot dispatches the default CLI route (manager/health_checks); its
// echo output belongs to that controller, not the test runner's report.
ob_start();
require_once __DIR__ . '/../public/index.php';
ob_end_clean();

require_once __DIR__ . '/support/CITestCase.php';
require_once __DIR__ . '/support/AuthTestCase.php';

<?php
$_SERVER['CI_ENV'] = 'testing';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

// Adjust this path if needed depending on your folder structure
require_once __DIR__ . '/../../public/index.php';
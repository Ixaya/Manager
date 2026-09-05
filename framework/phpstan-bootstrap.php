<?php
// phpstan-bootstrap.php

// Define the constant with a dummy or relative path so PHPStan understands it
if (!defined('MGRPATH')) {
	define('MGRPATH', dirname(__DIR__) . '/system/'); // system/ is a sibling of framework/, not a child
	define('APPMGRPATH', '../system'); // Adjust to your actual path structure
}

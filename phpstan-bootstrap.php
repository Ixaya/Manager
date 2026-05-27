<?php
// phpstan-bootstrap.php

// Define the constant with a dummy or relative path so PHPStan understands it
if (!defined('MGRPATH')) {
	define('MGRPATH', __DIR__ . '/system/'); // Adjust to your actual path structure
	define('APPMGRPATH', '../system'); // Adjust to your actual path structure
}

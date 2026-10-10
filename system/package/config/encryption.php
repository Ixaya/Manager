<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Master key bytes each cipher needs; a shorter key is silently zero-padded by OpenSSL.
$config['cipher_key_bytes'] = [
	'aes-128' => 16,
	'aes-192' => 24,
	'aes-256' => 32,
];

$config['cipher'] = strtolower(mgr_env('CF_ENCRYPTION_CIPHER', 'aes-128'));

// Opt-in: unset keeps today's behavior for keys already in use.
if (mgr_env('CF_ENCRYPTION_CIPHER', '') !== '') {
	$required_key_bytes = $config['cipher_key_bytes'][$config['cipher']] ?? null;
	if ($required_key_bytes === null) {
		show_error("Encryption: unknown CF_ENCRYPTION_CIPHER '{$config['cipher']}', expected one of " . implode(', ', array_keys($config['cipher_key_bytes'])) . '.');
	}

	$configured_key_bytes = strlen((string) config_item('encryption_key'));
	if ($configured_key_bytes > 0 && $configured_key_bytes < $required_key_bytes) {
		show_error("Encryption: CF_ENCRYPTION_KEY is {$configured_key_bytes} bytes but {$config['cipher']} needs {$required_key_bytes} (" . ($required_key_bytes * 2) . ' hex chars).');
	}
}

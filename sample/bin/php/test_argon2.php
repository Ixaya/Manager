<?php

/**
 * Password Argon2 Hash Benchmark
 * Argon2 is available since PHP 7.2
 *
 * Just upload this script to your server and run it, either through CLI or by calling it in your browser.
 *
 * See Argon2 specs https://password-hashing.net/argon2-specs.pdf chapter 9 Recommended Parameters
 */
// Upper time limit to check
$upperTimeLimit = 800;

$threads = [1, 2];

$time_cost_min = 1;
$time_cost_max = 20;

$memory_cost_min = 1 << 10; // 1 MB
$memory_cost_max = 1 << 18; // 256 MB

$password = 'this_is_just_a_long_string_to_test_on_U8WNZqmz8ZVBNiNTQR8r';

if (php_sapi_name() !== 'cli') echo "<pre>";
echo "\nPassword ARGON2 Benchmark";
echo "\nWill run until the upper limit of {$upperTimeLimit}ms is reached for each thread value";
echo "\n\nTimes are expressed in milliseconds.";

$start = microtime(true);
$hash = password_hash($password, PASSWORD_ARGON2ID);
$time = round((microtime(true) - $start) * 1000);
echo "\n\n\nTime with default settings: {$time}ms";
echo "\nHash = $hash";

foreach ($threads as $thread) {
	echo "\n\n\n=Testing with $thread threads";
	echo "\n m_cost (MB) ";
	for ($m_cost = $memory_cost_min; $m_cost <= $memory_cost_max; $m_cost *= 2) {
		$m_cost_mb = $m_cost / 1024;
		echo '|' . str_pad($m_cost_mb, 5, ' ', STR_PAD_BOTH);
	}
	echo "\n             =====================================================";
	for ($time_cost = $time_cost_min; $time_cost <= $time_cost_max; $time_cost++) {
		echo "\n t_cost=$time_cost    ";
		for ($m_cost = $memory_cost_min; $m_cost <= $memory_cost_max; $m_cost *= 2) {

			$start = microtime(true);
			password_hash($password, PASSWORD_ARGON2ID, [
				'memory_cost' => $m_cost,
				'time_cost'   => $time_cost,
				'threads'     => $thread,
			]);
			$time = round((microtime(true) - $start) * 1000);

			if ($time < $upperTimeLimit) {
				echo '|' . str_pad($time, 5, ' ', STR_PAD_BOTH);
			} else {
				echo '|' . str_pad(">LIM", 5, ' ', STR_PAD_BOTH);
				$m_cost = $memory_cost_max;
				$time_cost = $time_cost_max;
			}
		}
	}
}
echo "\n\n";

if (php_sapi_name() !== 'cli') echo "</pre>";

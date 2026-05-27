<?php
$timeTarget = 0.800; // 350 milliseconds

$cost = 8;
do {
	$cost++;
	$start = microtime(true);
	password_hash("test", PASSWORD_BCRYPT, ["cost" => $cost]);
	$end = microtime(true);

	$diff = $end - $start;
	echo "Test Cost: " . $cost - 1 . "($diff)\n";
} while ($diff < $timeTarget);

echo "Appropriate Cost Found: " . $cost - 1 . "($diff)\n";

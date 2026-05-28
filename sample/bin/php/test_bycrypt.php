<?php
$timeTarget = 800; // 350 milliseconds

$cost = 8;
do {
	$cost++;
	$start = microtime(true);
	password_hash("test", PASSWORD_BCRYPT, ["cost" => $cost]);
	$end = microtime(true);

	$diff = round(($end - $start) * 1000);
	echo "Test Cost: " . $cost - 1 . "($diff)\n";
} while ($diff < $timeTarget);

echo "Appropriate Cost Found: " . $cost - 1 . "($diff)\n";

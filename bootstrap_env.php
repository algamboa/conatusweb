<?php

declare(strict_types=1);

$conatusEnvPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (!is_readable($conatusEnvPath)) {
	return;
}

$lines = file($conatusEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
	return;
}

foreach ($lines as $line) {
	$line = trim($line);
	if ($line === '' || $line[0] === '#') {
		continue;
	}
	$eqPos = strpos($line, '=');
	if ($eqPos === false) {
		continue;
	}

	$name = trim(substr($line, 0, $eqPos));
	$value = trim(substr($line, $eqPos + 1));
	if ($name === '') {
		continue;
	}

	if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
		$quote = $value[0];
		if (substr($value, -1) === $quote) {
			$value = substr($value, 1, -1);
		}
	}

	putenv($name . '=' . $value);
	$_ENV[$name] = $value;
}

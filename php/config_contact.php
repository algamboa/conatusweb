<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap_env.php';

header('Content-Type: application/json; charset=utf-8');

$siteKey = trim((string) getenv('RECAPTCHA_SITE_KEY'));
if ($siteKey === '') {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Falta RECAPTCHA_SITE_KEY en .env',
	], JSON_UNESCAPED_UNICODE);
	exit;
}

echo json_encode([
	'success' => true,
	'siteKey' => $siteKey,
], JSON_UNESCAPED_UNICODE);

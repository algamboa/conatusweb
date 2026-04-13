<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap_env.php';

header('Content-Type: application/json; charset=utf-8');

function t(string $lang, string $key, array $vars = []): string
{
	$messages = [
		'es' => [
			'server_config_missing' => 'Falta configuración del servidor: {name}.',
			'method_not_allowed' => 'Método no permitido.',
			'send_not_validated' => 'No se pudo validar el envío. Intentá nuevamente.',
			'sent_too_fast' => 'El formulario se envió demasiado rápido. Revisá los datos e intentá nuevamente.',
			'rate_limit' => 'Demasiados intentos desde esta conexión. Volvé a intentar en unos {minutes} minutos.',
			'required_fields' => 'Rellena los campos requeridos en el formulario para poder enviar tu solicitud.',
			'invalid_email' => 'El email ingresado no es válido.',
			'field_limit' => 'Alguno de los campos supera el límite permitido.',
			'captcha_invalid' => 'No se pudo validar el captcha. Intentá nuevamente.',
			'send_success' => 'Mensaje enviado con éxito. Pronto nos pondremos en contacto con vos. Gracias!!',
			'send_error' => 'Ups, no se pudo enviar tu mensaje. Intentá nuevamente en unos minutos.',
		],
		'en' => [
			'server_config_missing' => 'Missing server configuration: {name}.',
			'method_not_allowed' => 'Method not allowed.',
			'send_not_validated' => 'The request could not be validated. Please try again.',
			'sent_too_fast' => 'The form was submitted too quickly. Please review your details and try again.',
			'rate_limit' => 'Too many attempts from this connection. Please try again in about {minutes} minutes.',
			'required_fields' => 'Please fill in all required fields before sending your request.',
			'invalid_email' => 'The email address is invalid.',
			'field_limit' => 'One or more fields exceed the allowed length.',
			'captcha_invalid' => 'Captcha validation failed. Please try again.',
			'send_success' => 'Message sent successfully. We will contact you soon. Thank you!',
			'send_error' => 'Oops, your message could not be sent. Please try again in a few minutes.',
		],
	];
	$text = $messages[$lang][$key] ?? $messages['es'][$key] ?? $key;
	foreach ($vars as $name => $value) {
		$text = str_replace('{' . $name . '}', (string) $value, $text);
	}
	return $text;
}

function jsonResponse(bool $success, string $message, string $type = 'error', string $url = ''): void
{
	echo json_encode([
		'success' => $success,
		'error' => !$success,
		'message' => $message,
		'type' => $type,
		'url' => $url,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

function requiredEnv(string $name, string $lang): string
{
	$value = trim((string) getenv($name));
	if ($value === '') {
		jsonResponse(false, t($lang, 'server_config_missing', ['name' => $name]));
	}
	return $value;
}

function clientIp(): string
{
	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
		return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
	if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
		return (string) $_SERVER['REMOTE_ADDR'];
	}
	return '0.0.0.0';
}

function rateLimitBlockMessage(string $ip, string $lang): ?string
{
	$max = (int) requiredEnv('CONTACT_RATE_LIMIT_MAX', $lang);
	$window = (int) requiredEnv('CONTACT_RATE_LIMIT_WINDOW_SECONDS', $lang);
	$max = max(1, min(100, $max));
	$window = max(60, min(86400, $window));

	$dir = dirname(__DIR__) . '/storage/rate-limit';
	if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
		error_log('Conatus rate-limit: no se pudo crear ' . $dir);
		return null;
	}

	$key = hash('sha256', 'contact|' . $ip);
	$file = $dir . '/' . $key . '.json';
	$now = time();
	$attempts = [];

	if (is_readable($file)) {
		$raw = file_get_contents($file);
		$decoded = json_decode($raw ?: '[]', true);
		if (is_array($decoded)) {
			foreach ($decoded as $attempt) {
				if (is_numeric($attempt) && ($now - (int) $attempt) < $window) {
					$attempts[] = (int) $attempt;
				}
			}
		}
	}

	if (count($attempts) >= $max) {
		$minutes = (int) ceil($window / 60);
		return t($lang, 'rate_limit', ['minutes' => $minutes]);
	}

	$attempts[] = $now;
	file_put_contents($file, json_encode($attempts), LOCK_EX);
	return null;
}

function verifyRecaptcha(string $token, string $secret, string $expectedHost): bool
{
	if ($token === '' || $secret === '' || $expectedHost === '') {
		return false;
	}
	$expectedAction = 'contact_form';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
		'secret' => $secret,
		'response' => $token,
		'remoteip' => clientIp(),
	]));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$response = curl_exec($ch);
	curl_close($ch);

	$data = json_decode((string) $response, true);
	if (!is_array($data) || empty($data['success']) || $data['success'] !== true) {
		return false;
	}

	$score = isset($data['score']) && is_numeric($data['score']) ? (float) $data['score'] : 0.0;
	if ($score < 0.5) {
		return false;
	}

	if (!empty($data['action']) && $data['action'] !== $expectedAction) {
		return false;
	}

	if (!empty($data['hostname'])) {
		$host = strtolower((string) $data['hostname']);
		$host = preg_replace('/:\d+$/', '', $host);
		$expected = strtolower(trim($expectedHost));
		$expected = preg_replace('#^https?://#', '', $expected);
		$expected = preg_replace('/:\d+$/', '', $expected);
		$expected = trim($expected, '/');

		$hostNoWww = preg_replace('/^www\./', '', $host);
		$expectedNoWww = preg_replace('/^www\./', '', $expected);
		if ($hostNoWww !== $expectedNoWww && $host !== 'localhost') {
			return false;
		}
	}

	return true;
}

$lang = isset($_POST['lang']) && strtolower((string) $_POST['lang']) === 'en' ? 'en' : 'es';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	jsonResponse(false, t($lang, 'method_not_allowed'));
}

$ip = clientIp();
$hp = isset($_POST['website']) ? trim((string) $_POST['website']) : '';
$startedAt = isset($_POST['started_at']) ? (int) $_POST['started_at'] : 0;
$minSeconds = (int) requiredEnv('CONTACT_MIN_FILL_SECONDS', $lang);
$minSeconds = max(1, min(30, $minSeconds));

if ($hp !== '') {
	jsonResponse(false, t($lang, 'send_not_validated'));
}

if ($startedAt <= 0 || (time() - $startedAt) < $minSeconds) {
	jsonResponse(false, t($lang, 'sent_too_fast'));
}

$rateLimitMessage = rateLimitBlockMessage($ip, $lang);
if ($rateLimitMessage !== null) {
	jsonResponse(false, $rateLimitMessage);
}

$nombre = isset($_POST['nombre']) ? trim((string) $_POST['nombre']) : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$asunto = isset($_POST['subject']) ? trim((string) $_POST['subject']) : '';
$comments = isset($_POST['comments']) ? trim((string) $_POST['comments']) : '';
$token = isset($_POST['token']) ? (string) $_POST['token'] : '';

if ($nombre === '' || $email === '' || $comments === '' || $token === '') {
	jsonResponse(false, t($lang, 'required_fields'));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	jsonResponse(false, t($lang, 'invalid_email'), 'warning');
}
if (mb_strlen($nombre) > 120 || mb_strlen($email) > 254 || mb_strlen($asunto) > 200 || mb_strlen($comments) > 5000) {
	jsonResponse(false, t($lang, 'field_limit'));
}
$recaptchaSecret = requiredEnv('RECAPTCHA_SECRET', $lang);
$recaptchaHostname = requiredEnv('RECAPTCHA_HOSTNAME', $lang);

if (!verifyRecaptcha($token, $recaptchaSecret, $recaptchaHostname)) {
	jsonResponse(false, t($lang, 'captcha_invalid'));
}

$mailTo = requiredEnv('MAIL_TO_ADDRESS', $lang);
$fromAddress = requiredEnv('MAIL_FROM_ADDRESS', $lang);
$fromName = requiredEnv('MAIL_FROM_NAME', $lang);
$smtpHost = requiredEnv('SMTP_HOST', $lang);
$smtpPort = (int) requiredEnv('SMTP_PORT', $lang);
$smtpEncryption = strtolower(requiredEnv('SMTP_ENCRYPTION', $lang));
$smtpUser = requiredEnv('SMTP_USERNAME', $lang);
$smtpPass = requiredEnv('SMTP_PASSWORD', $lang);

$body = '<strong>Nuevo mensaje desde Conatus Web</strong><br><br>';
$body .= '<strong>Nombre:</strong> ' . htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>';
$body .= '<strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>';
$body .= '<strong>Asunto:</strong> ' . htmlspecialchars($asunto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>';
$body .= '<strong>IP:</strong> ' . htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><br>';
$body .= '<strong>Mensaje:</strong><br>' . nl2br(htmlspecialchars($comments, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

try {
	$mail = new PHPMailer(true);
	$mail->isSMTP();
	$mail->Host = $smtpHost;
	$mail->SMTPAuth = true;
	$mail->Username = $smtpUser;
	$mail->Password = $smtpPass;
	if ($smtpEncryption === 'tls' || $smtpEncryption === 'starttls') {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
	} else {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
	}
	$mail->Port = $smtpPort;

	$mail->CharSet = 'UTF-8';
	$mail->setFrom($fromAddress, $fromName);
	$mail->addAddress($mailTo);
	$mail->addReplyTo($email, $nombre);
	$mail->isHTML(true);
	$mail->Subject = 'Mensaje desde la web Conatus';
	$mail->Body = $body;
	$mail->send();

	jsonResponse(true, t($lang, 'send_success'), 'success');
} catch (Exception $e) {
	error_log('Error enviando contacto Conatus: ' . $e->getMessage());
	jsonResponse(false, t($lang, 'send_error'));
}
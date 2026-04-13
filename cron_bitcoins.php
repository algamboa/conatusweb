<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . "/vendor/autoload.php");

function checkThePriceOfBitcoin()
{
    file_put_contents(__DIR__ . '/cron_bitcoins.log', '[' . date('Y-m-d H:i:s') . "] Cron iniciado\n", FILE_APPEND);
    $min = 86000;
    $max = 95000;
    $lockFile = __DIR__ . '/btc_alert.lock';

    try {
        $client = new Client(['timeout' => 10]);

        $response = $client->get(
            'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd'
        );

        $data  = json_decode($response->getBody()->getContents(), true);
        $price = $data['bitcoin']['usd'] ?? null;

        if ($price === null) {
            throw new Exception("Precio BTC no disponible");
        }

        $alreadySent = file_exists($lockFile);

        if ($price <= $min && !$alreadySent) {
            sendEmail("BAJÓ");
            file_put_contents($lockFile, 'low');
        }

        if ($price >= $max && !$alreadySent) {
            sendEmail("SUBIÓ");
            file_put_contents($lockFile, 'high');
        }

        // Reset del lock cuando vuelve a zona normal
        if ($price > $min && $price < $max && $alreadySent) {
            unlink($lockFile);
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    file_put_contents(__DIR__ . '/cron_bitcoins.log', '[' . date('Y-m-d H:i:s') . "] Cron finalizado\n", FILE_APPEND);
}


function sendEmail(string $subject){
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'conatusweb.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = "noreply@conatusweb.com";
        $mail->Password   = "PyBd=[q-7u=+";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom("noreply@conatusweb.com", "Conatus Web");
        $mail->addAddress("algamboa1@gmail.com", 'WEBSITE - CONATUS WEB');

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = "Alerta, el bitcoins " . $subject . ", hay que actuar";

        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}", 1, 'agamboa@conatusweb.com');

        return false;
    }
}

checkThePriceOfBitcoin();

<?php
	define("URL", "https://www.google.com/recaptcha/api/siteverify");
	define("RECAPTCHA", "6LeAc9MaAAAAAEAiK_PqyR4MqmaZwLno9seCXcHE");
	
	function validar_email($email = null){
		//Función que valida si el email es correcto

		$sw = "";
		$patron = "";

		$patron = "^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@([_a-zA-Z0-9-]+\.)*[a-zA-Z0-9-]{2,200}\.[a-zA-Z]{2,6}$";
		$sw = false;

		if(preg_match('/'.$patron.'/', $email)){
			$sw = true;
		}

		return $sw;

	}
	
	function ver_ip()
	{
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
	
	function traer_dispositivo()
	{

		$dispositivo = "";
		$tablet_browser = 0;
		$mobile_browser = 0;
		if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
			$tablet_browser++;
		}
		if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
			$mobile_browser++;
		}
		if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
			$mobile_browser++;
		}
		$mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
		$mobile_agents = array(
			'w3c ', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac',
			'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'inno',
			'ipaq', 'java', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-',
			'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-',
			'newt', 'noki', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox',
			'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar',
			'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-',
			'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp',
			'wapr', 'webc', 'winw', 'winw', 'xda ', 'xda-'
		);
		if (in_array($mobile_ua, $mobile_agents)) {
			$mobile_browser++;
		}
		if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'opera mini') > 0) {
			$mobile_browser++;
			//Check for tablets on opera mini alternative headers
			$stock_ua = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']) ? $_SERVER['HTTP_X_OPERAMINI_PHONE_UA'] : (isset($_SERVER['HTTP_DEVICE_STOCK_UA']) ? $_SERVER['HTTP_DEVICE_STOCK_UA'] : ''));
			if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $stock_ua)) {
				$tablet_browser++;
			}
		}
		if ($tablet_browser > 0) {
			// do something for tablet devices
			$dispositivo = 'tablet';
		} else if ($mobile_browser > 0) {
			// do something for mobile devices
			$dispositivo = 'mobile';
		} else {
			// do something for everything else
			$dispositivo = 'desktop';
		}

		return $dispositivo;
	}

	if(empty($_POST["nombre"]) || empty($_POST["email"]) || empty($_POST["comments"]) || empty($_POST["token"]))
	{
		echo(1);
	}
	else
	{
		$token = $_POST["token"];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, URL);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('secret' => RECAPTCHA, 'response' => $token)));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		$arrResponse = json_decode($response, true);
		if($arrResponse["success"] == '1' && $arrResponse["score"] >= 0.5)
		{
			$nombre = $_POST["nombre"];
			$email = $_POST["email"];
			$asunto = $_POST["asunto"];
			$mensaje = $_POST["comments"];
			$mensaje .= " " . ver_ip();
			$mensaje .= " " . traer_dispositivo();
			if(validar_email($email))
			{
				$titulo = "Mensaje desde la web conatus";
				$cabeceras = 'MIME-Version: 1.0' . "\r\n";
				$cabeceras .= 'Content-type: text/html; charset=utf-8' . "\r\n";
				$cabeceras .= 'From: ' . $nombre . '<'.$email.'>';
				if(mail("agamboa@conatusweb.com", $titulo, $mensaje, $cabeceras))
				{
					echo(3);
				}
				else
				{
					echo(4);
				}
			}
			else
			{
				echo(2);
			}
		}
		else
		{
			echo(5);
		}
	}
?>
<?php
if( !defined( 'ABSPATH')){ exit(); }

/*
https://garantexio.github.io/#347f4bc1c5
*/

if(!class_exists('JWT')){
	class JWT
	{
		function __construct(){
			
		}	
		
		function encode($payload, $key)
		{
			$algo = 'RS256';
			$header = array('typ' => 'JWT', 'alg' => $algo);

			$segments = array();
			$segments[] = $this->urlsafeB64Encode($this->jsonEncode($header));
			$segments[] = $this->urlsafeB64Encode($this->jsonEncode($payload));
			$signing_input = implode('.', $segments);

			$signature = $this->sign($signing_input, $key);
			$segments[] = $this->urlsafeB64Encode($signature);

			return implode('.', $segments);
		}

		function sign($payload, $key)
		{
			$passphrase = '';
			
			$algo = OPENSSL_ALGO_SHA256;
			$key_type = OPENSSL_KEYTYPE_RSA;
			
			$privateKey = openssl_pkey_get_private($key, $passphrase);
			
			if (is_bool($privateKey)) {
				$error = openssl_error_string();
				throw new Exception($error);
			}

			$details = openssl_pkey_get_details($privateKey);
			
			if (!array_key_exists('key', $details) || $details['type'] !== $key_type) {
				throw new Exception("Invalid key provided");
			}
			
			$signature = '';

			if (!openssl_sign($payload, $signature, $privateKey, $algo)) {
				$error = openssl_error_string();
				throw new Exception($error);
			}

			return $signature;
		}

		function jsonDecode($input)
		{
			$obj = json_decode($input);
			if (function_exists('json_last_error') && $errno = json_last_error()) {
				$this->_handleJsonError($errno);
			} else if ($obj === null && $input !== 'null') {
				throw new Exception('Null result with non-null input');
			}
			return $obj;
		}

		function jsonEncode($input)
		{
			$json = json_encode($input);
			if (function_exists('json_last_error') && $errno = json_last_error()) {
				$this->_handleJsonError($errno);
			} else if ($json === 'null' && $input !== null) {
				throw new Exception('Null result with non-null input');
			}
			return $json;
		}

		function urlsafeB64Decode($input)
		{
			$remainder = strlen($input) % 4;
			if ($remainder) {
				$padlen = 4 - $remainder;
				$input .= str_repeat('=', $padlen);
			}
			return base64_decode(strtr($input, '-_', '+/'));
		}

		function urlsafeB64Encode($input)
		{
			return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
		}

		function _handleJsonError($errno)
		{
			$messages = array(
				JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
				JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
				JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
			);
			throw new Exception(
				isset($messages[$errno])
				? $messages[$errno]
				: 'Unknown JSON error: ' . $errno
			);
		}
	}
}

if(!class_exists('AP_Garantex_Crypto_Code')){
	class AP_Garantex_Crypto_Code {
		
		private $m_name = "";
		private $m_id = "";
		private $token = "";
		public $private_key = '';
		public $uid = '';
		public $host = 'garantex.io';
		
		/*
		ERC20 :: usdt
		OMNI :: usdt-omni
		TRON :: usdt-tron 
		*/
		
		function __construct($m_name, $m_id, $private_key='', $uid=''){
			$this->m_name = trim($m_name);
			$this->m_id = trim($m_id);
			$this->private_key = trim($private_key);
			$this->uid = trim($uid);
			$this->set_token();
		}
		
		function set_token(){
			$token = trim($this->token);
			if(!$token){
				
				$request = array('exp' => time() + 3600, 'jti' => bin2hex(random_bytes(12)));
				$class = new JWT();
				$payload = $class->encode($request, base64_decode($this->private_key, true));
				$post_data = array('kid' => $this->uid, 'jwt_token' => $payload);
				
				$json_data = json_encode($post_data);
				
				$headers = array(
					'Content-Type: application/json',
				);
				
				if($ch = curl_init()){
					
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:65.0) Gecko/20100101 Firefox/65.0');
					curl_setopt($ch, CURLOPT_URL, "https://dauth.{$this->host}/api/v1/sessions/generate_jwt");
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
					curl_setopt($ch, CURLOPT_HEADER, false);
					curl_setopt($ch, CURLOPT_TIMEOUT, 20);
					curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
					$ch = apply_filters('curl_ap', $ch, $this->m_name, $this->m_id);
					
					$err  = curl_errno($ch);
					$result = curl_exec($ch);
					
					curl_close($ch);
					
					do_action('save_paymerchant_error', $this->m_name, $this->m_id, $action, $headers, $json_data, $result, $err);
					
					$res = @json_decode($result, true);
					
					if(isset($res['token'])){
						$this->token = $res['token'];
					}	
				}				
			}
		}
		
		function get_balance(){
			$res = $this->request('/api/v2/accounts', array());
			$data = array();
			if(is_array($res) and !isset($res['error'])){
				foreach($res as $re){
					if(isset($re['currency'], $re['balance'])){
						$currency = strtoupper($re['currency']);
						$data[$currency] = is_sum($re['balance']);
					}
				}
			}
			return $data;
		}


		public function make_voucher($amount, $currency, $user_login=''){
			$data = array();
			$data['error'] = 1;
			$data['trans_id'] = 0;
			$data['coupon'] = 0;		
			/*
			["USD","EUR","RUB","BTC","DOGE","DASH","ETH","LTC"]
			*/
			$amount = sprintf("%0.8F",$amount);
			$amount = rtrim($amount,'0');
			$amount = rtrim($amount,'.');
			
			$currency = trim((string)$currency);
			$user_login = trim($user_login);
			
			$req_data = array(
				'amount'=>$amount, 
				'currency' => $currency
			);
			if($user_login){
				$req_data['login'] = $user_login;
			}

			$res = $this->request('/api/v2/depositcodes/create', $req_data);
			if(is_array($res)  and $res['code'] and $res['id']){ 
				$code = trim((string)$res['code']);
			//	if(strstr($code, 'EX-CODE')){ 
					$data['error'] = 0;
					$data['trans_id'] = trim((string)$res['id']);
					$data['coupon'] = $code;
			//	}
			}
			return $data;
		}
	




	
		
		function request($method, $post=array()){

			$json_data = '';
			
			$headers = array(
				"Content-Type: application/json",
				"Authorization: Bearer {$this->token}"
			);			

			if($ch = curl_init()){
				
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:65.0) Gecko/20100101 Firefox/65.0');
				curl_setopt($ch, CURLOPT_URL, 'https://' . $this->host . $method);
				if(is_array($post) and count($post) > 0){
					$json_data = json_encode($post);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
				}
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_TIMEOUT, 20);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
				$ch = apply_filters('curl_ap', $ch, $this->m_name, $this->m_id);
				
				$err  = curl_errno($ch);
				$result = curl_exec($ch);
				
				curl_close($ch);
				
				do_action('save_paymerchant_error', $this->m_name, $this->m_id, $method, $headers, $json_data, $result, $err);
				
				$res = @json_decode($result, true);
		
				return $res;				 
			}	

			return '';
		}		
	}
}
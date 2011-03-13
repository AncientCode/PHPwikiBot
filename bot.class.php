<?php


require dirname(__FILE__).'/stddef.inc';
require_once $inc.'config.php';

/***Exceptions***/
class LoginFailure extends Exception {
	final public function getMessage() {
		return 'LoginFailure: '.$this->message;
	}
}

class PHPwikiBot {
	protected $conf; // current user config
	public $user; // Username
	protected $api_url; // Path to API
	protected $useragent; // Bot's user agent
	protected $wikid; // wiki's id in configuration
	protected $wikiname; // wiki's name
	public $max_lag = 5; // fix slave db's lag problem
	protected $out = true;
	
	public function __construct($user, $slient = false) {
		$this->conf = $GLOBALS['users'][$user]; // Map the user configuration array
		$this->useragent = $GLOBALS['useragent']; // Define the user-agent
		if (PHP_SAPI == 'cli' || PHP_SAPI == 'embed'): define('EOL', PHP_EOL); // we should use the platform's line break for cli
		else: define('EOL', '<br />'.PHP_EOL);// but <br /> with a line break for web
		endif;
		$this->user = $user;
		$this->wikid = $this->conf['wiki'];
		$this->wikiname = $GLOBALS['wiki'][$this->wikid]['name'];
		$this->api_url = $GLOBALS['wiki'][$this->wikid]['api'];
		if ($slient) $this->out = false;
		//if (function_exists('openssl_decrypt')):
			$pass = openssl_decrypt($this->conf['password'], 'AES-128-ECB', $GLOBALS['key']);
		/*else:
			;
		endif;*/
		//var_dump($this->conf, $this->useragent, $this->user, $this->wikid, $this->wikiname, $this->api_url, $pass);
		//echo constant('EOL');
		$this->login($user, $pass);
	}
	
	function __destruct() {
		$this->logout();
	}
	
	
	/**
	 * The login method, used to logon to MediaWiki's API
	 *
	 * @param string $user The username
	 * @param string $pass The password
	 * @return bool true when success
	 * @throws ErrorException when can't login
	 *
	 */
	protected function login($user, $pass) {
		$response = $this->postAPI('action=login&lgname=' . urlencode($user) . '&lgpassword=' . urlencode($pass));
		//var_dump($response);
		if ($response['login']['result'] == 'Success'):
			echo 'Logged in!'.EOL; //Unpatched server, all done. (See bug #23076, April 2010.)
		elseif ($response['login']['result'] == 'NeedToken'):
			//Patched server, going fine
			$token = $response['login']['token'];
			$newresponse = $this->postAPI('action=login&lgname=' . urlencode($user) . '&lgpassword=' . urlencode($pass) . '&lgtoken=' . $token);
			//var_dump($newresponse);
			if ($newresponse['login']['result'] == 'Success') :
				echo 'Logged in!'.EOL; //All done
			else:
				echo 'Forced by server to wait. Automatically trying again.', EOL;
				sleep(10);
				$this->login($user, $pass);
			endif;
		else:
			//Problem
			if (isset($response['login']['wait']) || (isset($response['error']['code']) && $response['error']['code'] == "maxlag")) {
				echo 'Forced by server to wait. Automatically trying again.', EOL;
				sleep(10);
				$this->login($user, $pass);
			} else {
				// die('Login failed: ' . $response . EOL);
				echo 'Debugging Info:', EOL;
				var_dump($response);
				throw new LoginFailure('Can\'t login!');
			}
		endif;
	}
	
	protected function logout() {
		$this->postAPI('action=logout');
	}
	
	protected function postAPI($postdata = '') {
		$ch = curl_init();
		if ($postdata !== '') $postdata .= '&';
		$postdata .= 'format=php';
		//echo $postdata, EOL;
		$cfg = array(
				CURLOPT_URL => $this->api_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_COOKIEJAR => 'cookie.txt',
				CURLOPT_COOKIEFILE => 'cookie.txt',
				CURLOPT_USERAGENT => $this->useragent,
				CURLOPT_POSTFIELDS => $postdata,
				CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8'),
				CURLOPT_HEADER => false,
				);
		curl_setopt_array($ch, $cfg);
		unset($cfg);
		$response = curl_exec($ch);
		if (curl_errno($ch)) return curl_error($ch);
		curl_close($ch);
		//echo $response, EOL;
		return unserialize($response);
	}
}
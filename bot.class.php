<?php
/**
 * PHPwikiBot Main Class File
 * @author Xiaomao
 * @package PHPwikiBot
 * @name PHPwikiBot main class
 */

/**
 * Include some definition and configuration
 */
require dirname(__FILE__).'/stddef.inc';
require_once INC.'config.php';

/***Exceptions***/


/**
 * The exception Login Failure (1xx)
 * 
 * Error Codes:
 * 100 General Failure
 * 
 * @package Exception
 */
class LoginFailure extends Exception {
	final public function getMessage() {
		return 'LoginFailure: '.$this->message;
	}
	final public function getCode() {
		return $this->code + 100;
	}
}


/**
 * The main class for the bot
 *
 * @package PHPwikiBot
 */
class PHPwikiBot {
	/**
	 * @var array Current User Config in the style of config.php
	 */
	protected $conf;
	/**
	 * @var string Username of the bot user on the wiki
	 */
	public $user;
	/**
	 * @var string Absolute path to API
	 */
	protected $api_url;
	/**
	 * @var string Bot's user-agent
	 */
	protected $useragent;
	/**
	 * @var string a key in $wiki array
	 */
	protected $wikid;
	/**
	 * @var string Wiki's full name or a friendly name
	 */
	protected $wikiname;
	/**
	 * Replicate DB's slave some times have trouble syncing, set this to 5
	 * @var int Fix slave DB's lag problem, set to 5
	 */
	public $max_lag = 5; // fix slave db's lag problem
	/**
	 * @var bool Whether to output some unimportant messages
	 */
	protected $out = true;
	
	
	/**
	 * Constructor, initialize the object and login
	 *
	 * @param string $user A username in config.php
	 * @param bool $slient Be quiet
	 * @return void This function throws exception rather than return value
	 * @throws LoginFailure from PHPwikiBot::login
	 *
	 */
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
		try {
			$this->login($user, $pass);
		} catch (LoginFailure $e) {
			throw $e;
		}
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
				throw new LoginFailure('Can\'t login!', 0);
			}
		endif;
	}
	
	
	/**
	 * Logout method, clear all cookies
	 *
	 * @return void There is no such error as can't clear cookies so this was skipped
	 *
	 */
	protected function logout() {
		$this->postAPI('action=logout');
	}
	
	
	
	/**
	 * Perform a post request to the API
	 *
	 * @param string $postdata The data to post in this format a=b&b=c
	 * @return mixed The unserialized data from the API
	 *
	 */
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
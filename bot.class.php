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
require_once INC.'exception.inc';


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
	public $api_url;
	/**
	 * @var string Bot's user-agent
	 */
	public $useragent;
	/**
	 * @var string a key in $wiki array
	 */
	protected $wikid;
	/**
	 * @var string Wiki's full name or a friendly name
	 */
	public $wikiname;
	/**
	 * Replicate DB's slave some times have trouble syncing, set this to 5
	 * @var int Fix slave DB's lag problem, set to 5
	 */
	public $max_lag = 5; // fix slave db's lag problem
	/**
	 * @var bool Whether to output some unimportant messages
	 */
	protected $out = true;
	protected $post;
	protected $get;
	
	/**
	 * Constructor, initialize the object and login
	 *
	 * @param string $user A username in config.php
	 * @param bool $slient Be quiet
	 * @return void This function throws exception rather than return value since constructor doesn't return
	 * @throws LoginFailure from PHPwikiBot::login
	 *
	 */
	public function __construct($user, $slient = false) {
		$this->conf = $GLOBALS['users'][$user]; // Map the user configuration array
		$this->useragent = $GLOBALS['useragent']; // Define the user-agent
		$this->user = $user;
		$this->wikid = $this->conf['wiki'];
		$this->wikiname = $GLOBALS['wiki'][$this->wikid]['name'];
		$this->api_url = $GLOBALS['wiki'][$this->wikid]['api'];
		if ($slient) $this->out = false;
		$this->conninit();
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
	
	/**
	 * Clear the cookies when the script terminates
	 */
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
				throw new LoginFailure('Can\'t Login', 100);
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
	
	public function wiki_info ($type = '') {
		$response = $this->getAPI('action=query&meta=siteinfo');
		if (!is_array($response)) throw new InfoFailure ('Can\'t Get Info', 300);
		if ($type):
			if (isset($response['query']['general'][$type])):
				return $response['query']['general'][$type];
			else:
				throw new InfoFailure ('Not in Gereral Info', 301);
			endif;
		else:
			return $response['query']['general'];
		endif;
	}
	
	/**
	 * Fetch a page from the wiki
	 *
	 * @param string $page The page name
	 * @return string The page content
	 * @throws GetPageFailure when failure
	 *
	 */
	public function get_page($page) {
		$response = $this->getAPI('action=query&prop=revisions&titles='.urlencode($page).'&rvprop=content');
		//var_dump($response);
		if (is_array($response)) {
			$array = $response['query']['pages'];
			//var_dump($array);
			foreach ($array as $v) {
				if (isset($v['missing'])):
					throw new GetPageFailure('Page doesn\'t exist', 201);
				elseif (isset($v['invalid'])):
					throw new GetPageFailure('Page title invaild', 202);
				elseif (isset($v['special'])):
					throw new GetPageFailure('Special Page', 203);
				else:
					if (is_string($v['revisions'][0]['*'])):
						return $v['revisions'][0]['*'];
					else:
						throw new GetPageFailure('Can\'t Fetch Page', 200);
					endif;
				endif;
			}
		} else {
			throw new GetPageFailure('Can\'t Fetch Page', 200);
		}
	}

	/**
	 * Get a page's category
	 *
	 * @param string $page The page name
	 * @return array An array with all categories or false if no category
	 *
	 */
	public function get_page_cat($page) {
		$response = $this->getAPI('action=query&prop=categories&titles='.urlencode($page));
		var_dump($response);
		foreach ($response['query']['pages'] as $key => $value) {
			var_dump($value);
			if (!isset($value['categories'])) return false;
			foreach ($value['categories'] as $key2 => $value2) {
				$cats[] = $value2['title'];
			}
		}
		var_dump($cats);
		return $cats;
	}
	
	/**
	 * Perform a GET request to the API
	 *
	 * @param string $query The query string to pass the the API, without ?
	 * @return mixed The unserialized data from the API
	 *
	 */
	protected function getAPI($query) {
		curl_setopt($this->get, CURLOPT_URL, $this->api_url.'?'.$query.'&maxlag='.$this->max_lag.'&format=php');
		$response = curl_exec($this->get);
		if (curl_errno($this->get)) return curl_error($this->get);
		/*$fh = fopen('test.txt', 'a');
		fwrite($fh, $response);
		fclose($fh);*/
		return unserialize($response);
	}

	/**
	 * Perform a POST request to the API
	 *
	 * @param string $postdata The data to post in this format a=b&b=c
	 * @return mixed The unserialized data from the API
	 *
	 */
	protected function postAPI($postdata = '') {
		if ($postdata !== '') $postdata .= '&';
		$postdata .= 'format=php';
		//echo $postdata, EOL;
		curl_setopt($this->post, CURLOPT_POSTFIELDS, $postdata);
		$response = curl_exec($this->post);
		if (curl_errno($this->post)) return curl_error($this->post);
		//echo $response, EOL;
		return unserialize($response);
	}
	
	
	/**
	 * Initiailze the class property, the $get and $post handle
	 *
	 * @return void No return value
	 *
	 */
	protected function conninit() {
		$this->get = curl_init();
		$this->post = curl_init();
		$cfg = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_COOKIEJAR => 'cookie.txt',
				CURLOPT_COOKIEFILE => 'cookie.txt',
				CURLOPT_USERAGENT => $this->useragent,
				CURLOPT_HEADER => false,
				);
		$post = array(
				CURLOPT_URL => $this->api_url,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8'),
				);
		curl_setopt_array($this->get, $cfg);
		curl_setopt_array($this->post, $cfg);
		curl_setopt_array($this->post, $post);
	}
}
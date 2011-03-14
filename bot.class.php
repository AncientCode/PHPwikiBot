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
require_once dirname(__FILE__).'/stddef.inc';
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
	public $epm;
	protected $post;
	protected $get;
	protected $loglevel = LOG_INFO;
	protected $logh;
	protected $loglevelname = array(
								LG_INFO => 'Info',
								LG_DEBUG => 'Debug Info',
								LG_NOTICE => 'Notice',
								LG_WARN => 'Warning',
								LG_ERROR => 'Error',
								LG_FATAL => 'Fatal Error',
								);
	protected $output_log = true;
	protected $editdetails;
	
	/* Magic Functions */
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
		$this->user = $user; // Username
		$this->wikid = $this->conf['wiki']; // Wiki ID
		$this->wikiname = $GLOBALS['wiki'][$this->wikid]['name'];// Wiki's name
		$this->api_url = $GLOBALS['wiki'][$this->wikid]['api'];// Path to API
		if ($slient) $this->out = false; // Not yet used
		$this->conninit(); // Initialize cURL handles
		$this->loglevel = $GLOBALS['log_level']; // Loglevel
		$this->logh = fopen($GLOBALS['logfile'], 'a');// Open Logfile
		$this->output_log = $GLOBALS['output_log']; // Whether to output logs to stdout/stderr
		$this->epm = 60 / $GLOBALS['wiki'][$this->wikid]['epm']; // Edit per minute
		/**
		 * If the server supports OpenSSL, use encrypted password or else use plain text
		 */
		if (function_exists('openssl_decrypt'))
			$pass = openssl_decrypt($this->conf['password'], 'AES-128-ECB', $GLOBALS['key']);
		else
			$pass = $this->conf['password'];
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
		fclose($this->logh);
	}
	
	function __toString() {
		
	}
	
	
	/**
	 * Unexisted Method are sometimes called, in this case, a 10 Unexist Method is thrown
	 *
	 * @param string $name The name of the function
	 * @param array $arguments Array of arguments
	 * @return void No return value
	 * @throws BotException
	 */
	public function __call($name, $arguments) {
		$this->log('Called unexist method "'.$name.'('.implode(', ', $arguments).'"!', LG_FATAL);
		throw new BotException('Unexist Method', 10);
	}
	
	/* Public Callable Methods */

	/**
	 * Get General wiki info
	 *
	 * @param string $type The name of the setting, leave blank for all
	 * @return mixed Either the value of $type or an array contain all info
	 * @tutorial ./tutorial/gnlwikinfo.txt
	 * 
	 */
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
	public function get_page($page, $internal = false) {
		$response = $this->getAPI('action=query&prop=revisions&titles='.urlencode($page).'&rvprop=content');
		//var_dump($response);
		if (is_array($response)) {
			$array = $response['query']['pages'];
			//var_dump($array);
			foreach ($array as $v) {
				if (isset($v['missing'])):
					if (!$internal)
						$this->log('Page \''.$page.'\' doesn\'t exist!', LG_ERROR);
					throw new GetPageFailure('Page doesn\'t exist', 201);
				elseif (isset($v['invalid'])):
					if (!$internal)
						$this->log('Page title \''.$page.'\' is invalid!', LG_ERROR);
					throw new GetPageFailure('Page title invaild', 202);
				elseif (isset($v['special'])):
					if (!$internal)
						$this->log('Page \''.$page.'\' is a special page!', LG_ERROR);
					throw new GetPageFailure('Special Page', 203);
				else:
					if (is_string($v['revisions'][0]['*'])):
						return $v['revisions'][0]['*'];
					else:
						$this->log('Can\' fetch page \''.$page.'\' for some reason!', LG_ERROR);
						throw new GetPageFailure('Can\'t Fetch Page', 200);
					endif;
				endif;
			}
		} else {
			$this->log('Can\' fetch page \''.$page.'\' for some reason!', LG_ERROR);
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
		//var_dump($response);
		foreach ($response['query']['pages'] as $key => $value) {
			var_dump($value);
			if (!isset($value['categories'])) return false;
			foreach ($value['categories'] as $key2 => $value2) {
				$cats[] = $value2['title'];
			}
		}
		//var_dump($cats);
		return $cats;
	}
	
	/**
	 * Creates a page
	 *
	 * @param string $page Page title
	 * @param string $text New Text
	 * @param string $summary Edit Summary
	 * @param bool $minor Minor Edit
	 * @param bool $force Force Edit
	 * @return bool Return true on success
	 * @throws EditFailure
	 */
	public function create_page($page, $text, $summary, $minor = false, $force = false) {
		$response = $this->getAPI('action=query&prop=info|revisions&intoken=edit&titles=' . urlencode($page));
		$this->editdetails = $response['query']['pages'];
		if (!isset($this->editdetails[-1])) throw new EditFailure('Page Exists', 420);
		$bot = false;
		if (isset($this->conf['bot']) && $this->conf['bot'] == true) $bot = true;
		try {
			$this->put_page($page, $text, $summary, $minor, $bot);
			return true;
		} catch (EditFailure $e) {
			throw $e;
		}
		$this->editdetails = null;
	}
	
	/**
	 * Modifies a page
	 *
	 * @param string $page Page title
	 * @param string $text New Text
	 * @param string $summary Edit Summary
	 * @param bool $minor Minor Edit
	 * @param bool $force Force Edit
	 * @return bool Return true on success
	 * @throws EditFailure
	 */
	public function edit_page($page, $text, $summary, $minor = false, $force = false) {
		$response = $this->getAPI('action=query&prop=info|revisions&intoken=edit&titles=' . urlencode($page));
		$this->editdetails = $response['query']['pages'];
		if (isset($this->editdetails[-1])) throw new EditFailure('Page Doesn\'t Exist', 421);
		$bot = false;
		if (isset($this->conf['bot']) && $this->conf['bot'] == true) $bot = true;
		try {
			$this->put_page($page, $text, $summary, $minor, $bot);
			return true;
		} catch (EditFailure $e) {
			throw $e;
		}
		$this->editdetails = null;
	}
	
	/* Internal Methods */
	/**
	 * Change a page's content
	 *
	 * @param string $name Page Name
	 * @param string $newtext Page Content
	 * @param string $summary Edit Summary
	 * @param bool $minor Minor Edit
	 * @param bool $bot Bot Edit
	 * @param string $force Force Edit
	 * @return bool Return true on success
	 * @throws EditFailure
	 *
	 */
	protected function put_page($name, $newtext, $summary, $minor = false, $bot = true, $force = false) {
		foreach ($this->editdetails as $key => $value) {
			$token = urlencode($value["edittoken"]);
			$sts = $value["starttimestamp"];
			if (isset($this->editdetails[-1])) {
				$ts = $sts;
				$extra = "&createonly=yes";
			} else {
				$ts = $value["revisions"][0]["timestamp"];
				$extra = "&nocreate=yes";
			}
		}
		$newtext = urlencode($newtext);
		try {
			$rawoldtext = $this->get_page($name, true);
		} catch (GetPageFailure $e) {
			if ($e->getCode() == 201)
				$rawoldtext = '';
			else
				throw $e;
		}
		$oldtext = urlencode($rawoldtext);
		$summary = urlencode($summary);
		//$md5 = md5($newtext);
		
		if ($newtext == $oldtext) 
			//the new content is the same, nothing changes
			throw new EditFailure('Same Content', 401);
		
		if ($newtext == '' && !$force) 
			//the new content is void, nothing changes
			throw new EditFailure('Blank Content', 402);
		
		$post = "title=$name&action=edit&basetimestamp=$ts&starttimestamp=$sts&token=$token&summary=$summary$extra&text=$newtext";
		if ($bot) {
			if (!$this->allowBots($rawoldtext)) throw new EditFailure('Forbidden', 403);
			$post .= '&bot=yes';
		}
		if ($minor)
			$post .= '&minor=yes';
		else
			$post .= '&notminor=yes';
		
		$response = $this->postAPI($post);
		if (isset($response['edit']['result']) && $response['edit']['result'] == 'Success') {
			$this->log('Successfully edited page ' . $response['edit']['title'], LG_INFO);
			sleep($this->epm);
			return true;
		/*Being worked on to throw the right exception*/
		} elseif (isset($response['error'])) {
			$this->log('[' . $response['error']['code'] . '] ' . $response['error']['info'], LG_ERROR);
			throw EditFailure('Edit Failure', 400);
		} else {
			echo "Error - " . $response["edit"]["result"] . "&nbsp;<br />\n";
			throw EditFailure('Edit Failure', 400);
		}
	}
	
	/**
	* The login method, used to logon to MediaWiki's API
	*
	* @param string $user The username
	* @param string $pass The password
	* @return bool true when success
	* @throws LoginFailure when can't login
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
	
	/**
	 * Log to file and stdout(for html)/stderr(cli)
	 *
	 * @param string $msg Error to log
	 * @param int $level On of the error constants
	 * @return void No return value
	 *
	 */
	protected function log($msg, $level = LG_INFO) {
		//var_dump($msg, $level, $this->loglevel);
		if ($level >= $this->loglevel) {
			$msg = date('Y-m-d H:i:s').' - '.$this->loglevelname[$level].': '.$msg;
			if ( $this->output_log ) {
				if(CLI && LOG_TO_STDERR) {
					if ($level < LG_WARN && !WIN32)
						fwrite(STDERR, "\033[31m$msg\033[0m".EOL);
					else
						fwrite(STDERR, $msg.EOL);
				} else {
					if ($level < LOG_WARN)
						echo "\033[31m$msg\033[0m".EOL;
					else
						echo $msg.EOL;
				}
			}
			fwrite($this->logh, $msg.PHP_EOL);
		}
	}
	
	protected function allowBots($text) {
		if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?' . preg_quote($this->user, '/') . '.*?)\}\}/iS', $text))
			return false;
		return true;
	}
}
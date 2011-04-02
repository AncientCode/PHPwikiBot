<?php
/**
 * PHPwikiBot Main Class File
 * @author Xiaomao
 * @package PHPwikiBot
 * @name PHPwikiBot main class
 * @license http://www.gnu.org/licenses/gpl.html GPLv3+
 */

/**
 * Include some definition and configuration
 */
require_once dirname(__FILE__).'/stddef.inc';
require_once INC.'cfgparse.php';
require_once INC.'exception.inc';


/**
 * The main class for the bot
 *
 * @package PHPwikiBot
 */
class PHPwikiBot {
	/**
	 * Current User Config in config.yml
	 * @var array 
	 */
	protected $conf;
	/**
	 * Username of the bot user on the wiki
	 * @var string 
	 */
	public $user;
	/**
	 * Absolute path to API
	 * @var string 
	 */
	public $api_url;
	/**
	 * Bot's user-agent
	 * @var string 
	 */
	public $useragent;
	/**
	 * A key in $wiki array
	 * @var string
	 */
	protected $wikid;
	/**
	 * Wiki's full name or a friendly name
	 * @var string
	 */
	public $wikiname;
	/**
	 * Replicate DB's slave some times have trouble syncing, set this to 5
	 * @var int
	 */
	public $max_lag = 5; // fix slave db's lag problem
	/**
	 * Whether to output some unimportant messages
	 * @var bool 
	 */
	protected $out = true;
	/**
	 * Frequency of edit
	 * @var float
	 */
	public $epm;
	/**
	 * cURL POST handle
	 * @var resource 
	 */
	protected $post;
	/**
	 * cURL GET handle
	 * @var resource 
	 */
	protected $get;
	/**
	 * Log level
	 * @var int 
	 */
	protected $loglevel = LOG_INFO;
	/**
	 * fopen() handle of the log file
	 * @var resource 
	 */
	protected $logh;
	/**
	 * friendly name of log level
	 * @var array
	 */
	protected $loglevelname = array(
								LG_INFO => 'Info',
								LG_DEBUG => 'Debug Info',
								LG_NOTICE => 'Notice',
								LG_WARN => 'Warning',
								LG_ERROR => 'Error',
								LG_FATAL => 'Fatal Error',
								);
	/**
	 * Whether to output the log to STDOUT/STDERR
	 * @var array
	 */
	protected $output_log = true;
	/**
	 * Data to pass to put_page() from create_page() or edit_page()
	 * @var array
	 */
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
		/** Configuration Mapping */
		$this->conf = $GLOBALS['users'][$user]; // Map the user configuration array
		$this->useragent = $GLOBALS['useragent']; // Define the user-agent
		$this->wikid = $this->conf['wiki']; // Wiki ID
		$this->wikiname = $GLOBALS['wiki'][$this->wikid]['name'];// Wiki's name
		$this->api_url = $GLOBALS['wiki'][$this->wikid]['api'];// Path to API
		$this->epm = 60 / $GLOBALS['wiki'][$this->wikid]['epm']; // Edit per minute
		$this->user = $this->conf['name']; // Username
		/**
		 * If the server supports OpenSSL, use encrypted password or else use plain text
		 */
		if (function_exists('openssl_decrypt'))
			$pass = openssl_decrypt($this->conf['password'], 'AES-128-ECB', $GLOBALS['key']); // Password
		else
			$pass = $this->conf['password']; // Password
		if ($slient) $this->out = false; // Not yet used
		/** Log */
		$this->loglevel = $GLOBALS['log_level']; // Loglevel
		$this->logh = fopen($GLOBALS['logfile'], 'a');// Open Logfile
		$this->output_log = $GLOBALS['output_log']; // Whether to output logs to stdout/stderr
		/** Initialize */
		$this->conninit(); // Initialize cURL handles
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
	
	/**
	 * Convert the object to string
	 *
	 * @return string Basic info about this object
	 *
	 */
	function __toString() {
		$name = $this->user;
		$wikid = $this->wikid;
		$wikiname = $this->wikiname;
		$useragent = $this->useragent;
		$api = $this->api_url;
		if (function_exists('openssl_decrypt')) $crypt = 'yes';
		else $crypt = 'no';
		echo <<<EOD
Username: $name
Encrypted Password: $crypt
Wiki ID: $wikid
Wiki Name: $wikiname
User Agent: $useragent

EOD;
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
		$response = $this->postAPI('action=query&prop=categories&titles='.urlencode($page));
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
	 * Get all page in a category
	 *
	 * @param string $category Name of Category
	 * @param int $limit Number of page to fetch before it stops
	 * @param string $start Start from page name
	 * @param string $ns Namespace, 'all' for all
	 * @return array Array of page
	 *
	 */
	public function category($category, $limit = 500, $start = '', $ns = 'all') {
		$query = 'action=query&list=categorymembers&cmtitle=' . urlencode('Category:' . $category) . '&cmlimit=' . $limit;
		if ($ns != 'all')
			$query .= '&cmnamespace=' . $ns;
		if ($start != '')
			$query .= '&cmcontinue=' . urlencode($start);
		$result = $this->postAPI($query);
		$cm = $result['query']['categorymembers'];
		$pages = array();
		$j = count($cm);
		for ($i = 0; $i < $j; ++$i)
			$pages[] = $cm[$i]['title'];
		if (isset($result['query-continue']['categorymembers']['cmcontinue'])) {
			$next = $result['query-continue']['categorymembers']['cmcontinue'];
			if ($next != '') {
				array_push($pages, $next);
			}
		}
		return $pages;
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
		$response = $this->postAPI('action=query&prop=info|revisions&intoken=edit&titles=' . urlencode($page));
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
		$response = $this->postAPI('action=query&prop=info|revisions&intoken=edit&titles=' . urlencode($page));
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
	
	/**
	 * Move a page
	 *
	 * @param string $from The source page
	 * @param string $to The destination
	 * @param string $reason Reason for moving
	 * @param bool $talk Move talk page
	 * @param bool $sub Move subpages
	 * @param bool $redirect Create a redirect form $from to $to
	 * @return bool Return ture on sucess
	 * @throws MoveFailure
	 */
	public function move_page($from, $to, $reason = '', $talk = true, $sub = true, $redirect = true) {
		$response = $this->postAPI('action=query&prop=info&intoken=move&titles=' . urlencode($from));
		//var_dump($response);
		foreach ($response['query']['pages'] as $v) {
			if (isset($v['invalid'])) throw new ProtectFailure('Invalid Title', 507);
			$token = $v['movetoken'];
		}
		$query = 'action=move&from='.urlencode($from).'&to='.urlencode($to).'&token='.urlencode($token).'&reason='.urlencode($reason);
		if (!$redirect)
			$query .= '&noredirect';
		if ($talk)
			$query .= '&movetalk';
		if ($sub)
			$query .= '&movesubpages';
		$response = $this->postAPI($query);
		//var_dump($response);
		if (isset($response['error'])) {
			switch ($response['error']['code']):
				case 'articleexists': // 501 Destination Exists
					throw new MoveFailure('Destination Exists', 501);
					break;
				case 'protectedpage':
				case 'protectedtitle':
				case 'immobilenamespace': // 502 Protected
					throw new MoveFailure('Protected', 502);
					break;
				case 'cantmove':
				case 'cantmovefile':
				case 'cantmove-anon': // 503 Forbidden
					throw new MoveFailure('Forbidden', 503);
					break;
				case 'filetypemismatch': // 504 Extension Mismatch
					throw new MoveFailure('Extension Mismatch', 504);
					break;
				case 'nonfilenamespace': // 504 Wrong Namespace
					throw new MoveFailure('Wrong Namespace', 505);
					break;
				case 'selfmove': // 506 Self Move
					throw new MoveFailure('Self Move', 506);
					break;
				default:
					throw new MoveFailure('Move Failure', 500);
			endswitch;
		}
		return true;
	}
	
	/**
	 * Deletes a page
	 *
	 * @param string $page Page to delete
	 * @param string $reason Reason of deleting
	 * @return bool True when success
	 * @throws DeleteFailure
	 *
	 */
	public function del_page($page, $reason = '') {
		$response = $this->postAPI('action=query&prop=info&intoken=delete&titles=' . urlencode($page));
		//var_dump($response);
		if (isset($response['warnings']['info']['*']) && strstr($response['warnings']['info']['*'], 'not allowed'))
			throw new DeleteFailure('Forbidden', 603);
		foreach ($response['query']['pages'] as $v) {
			if (isset($v['invalid'])) throw new ProtectFailure('Invalid Title', 604);
			$token = $v['deletetoken'];
		}
		$query = 'action=delete&title='.urlencode($page).'&token='.urlencode($token).'&reason='.urlencode($reason);
		$response = $this->postAPI($query);
		if (isset($response['error'])) {
			switch ($response['error']['code']):
				case 'cantdelete':
				case 'missingtitle':
					$this->log('Failed to delete '.$page.' with error 601 No Such Page', LG_ERROR);
					throw new DeleteFailure('No Such Page', 601);
					break;
				case 'blocked':
				case 'autoblocked': // 402 Blocked
					$this->log('Failed to delete '.$page.' with error 602 Blocked', LG_ERROR);
					throw new DeleteFailure('Blocked', 602);
					break;
				case 'permissiondenied':
				case 'protectedtitle':
				case 'protectedpage':
				case 'protectednamespace': // 603 Forbidden
					$this->log('Failed to delete '.$page.' with error 603 Forbidden', LG_ERROR);
					throw new DeleteFailure('Forbidden', 603);
					break;
				default:
					$this->log('Failed to delete '.$page.' with error 600 Delete Failure', LG_ERROR);
					throw new DeleteFailure('Delete Failure', 600);
			endswitch;
		}
		return true;
	}
	
	/**
	 * Undeletes a page with all revisions
	 *
	 * @param string $page Page name to undelete
	 * @param string $reason Reason of undeleting
	 * @return bool Return true on success
	 * @throws UndeleteFailure
	 */
	public function undel_page($page, $reason = '') {
		$response = $this->postAPI('action=query&prop=info&intoken=edit&titles=xxxxxxxx');
		//var_dump($response);
		foreach ($response['query']['pages'] as $v)
			$token = $v['edittoken'];
		//var_dump($token);
		$query = 'action=undelete&title='.urlencode($page).'&token='.urlencode($token).'&reason='.urlencode($reason);
		$response = $this->postAPI($query);
		//var_dump($response);
		if (isset($response['error'])) {
			switch ($response['error']['code']) {
				case 'cantdelete':
					$this->log('Failed to undelete '.$page.' with error 901 Not Deleted', LG_ERROR);
					throw new UndeleteFailure('No Such Page', 901);
					break;
				case 'blocked':
				case 'autoblocked': // 402 Blocked
					$this->log('Failed to undelete '.$page.' with error 902 Blocked', LG_ERROR);
					throw new UndeleteFailure('Blocked', 902);
					break;
				case 'permissiondenied':
				case 'protectedtitle':
				case 'protectedpage':
				case 'protectednamespace': // 603 Forbidden
					$this->log('Failed to undelete '.$page.' with error 903 Forbidden', LG_ERROR);
					throw new UndeleteFailure('Forbidden', 903);
					break;
				case 'invalidtitle':
					$this->log('Failed to undelete '.$page.' with error 904 Invaild Title', LG_ERROR);
					throw new UndeleteFailure('Invaild Title', 904);
					break;
				default:
					$this->log('Failed to undelete '.$page.' with error 900 Delete Failure', LG_ERROR);
					throw new UneleteFailure('Delete Failure', 900);
			}
		}
		return true;
	}
	
	/**
	 * Blocks a user
	 *
	 * @param string $name Username
	 * @param string $reason Reason for blocking
	 * @param string $exp A realtive(e.g. 2 days) or absolute(yyyymmddhhmmss)
	 * @param bool $nocreate Block the IP from creating acounts
	 * @param bool $auto Block the user's registration IP and any other IP the user tries to logon
	 * @param bool $noemail Blocks the user's ability to send emails
	 * @return bool True on success
	 * @throws BlockFailure
	 */
	public function block ($name, $reason = '', $exp = 'never', $nocreate = false, $auto = false, $noemail = true) {
		$resp = $this->postAPI('action=query&prop=info&intoken=block&titles=User:'.$name);
		//var_dump($resp);
		if (isset($resp['warnings']['info']['*']) && strstr($resp['warnings']['info']['*'], 'not allowed')) {
			$this->log('Failed to block user '.$name.' with error 1003 Forbidden', LG_ERROR);
			throw new BlockFailure('Forbidden', 1003);
		}
		foreach ($resp['query']['pages'] as $v)
			$token = $v['blocktoken'];
		//echo $token;
		$query = 'action=block&user='.urlencode($name).'&expiry='.urlencode($exp).'&token='.urlencode($token);
		if ($reason)
			$query .= '&reason='.$reason;
		else
			$query .= '&reason='.urlencode('I Hate '.$name);
		if ($auto)
			$query .= '&autoblock';
		if ($nocreate)
			$query .= '&nocreate';
		if ($noemail)
			$query .= '&noemail';
		$resp = $this->postAPI($query);
		//var_dump($resp);
		if (isset($response['error'])) {
			switch ($response['error']['code']) {
				case 'alreadyblocked':
					$this->log('Failed to block user '.$name.' with error 1001 Already Blocked', LG_ERROR);
					throw new BlockFailure('1001 Already Blocked', 1001);
					break;
				case 'blocked':
				case 'autoblocked':
					$this->log('Failed to block user '.$name.' with error 1002 Blocked', LG_ERROR);
					throw new BlockFailure('Blocked', 1002);
					break;
				case 'permissiondenied':
				case 'cantblock':
				case 'cantblock-email':
				case 'rangedisabled':
					$this->log('Failed to block user '.$name.' with error 1003 Forbidden', LG_ERROR);
					throw new BlockFailure('Forbidden', 1003);
					break;
				case 'invalidexpiry':
				case 'pastexpiry':
				case 'invalidrange':
					$this->log('Failed to block user '.$name.' with error 1004 Invaild Expiry', LG_ERROR);
					throw new BlockFailure('Invaild Expiry', 1004);
					break;
				case 'invaliduser':
				case 'invalidip':
					$this->log('Failed to block user '.$name.' with error 1005 Invaild User/IP', LG_ERROR);
					throw new BlockFailure('Invaild User/IP', 1005);
					break;
				default:
					$this->log('Failed to block user '.$name.' with error 1000 Block Failure', LG_ERROR);
					throw new BlockFailure('Block Failure', 1000);
			}
		}
		return true;
	}
	
	/**
	 * Unlocks a user
	 *
	 * @param string $name Username
	 * @param string $reason Reason for unblocking
	 * @return bool True on success
	 * @throws BlockFailure
	 */
	public function unblock ($name, $reason = '') {
		$resp = $this->postAPI('action=query&prop=info&intoken=unblock&titles=User:'.$name);
		//var_dump($resp);
		if (isset($resp['warnings']['info']['*']) && strstr($resp['warnings']['info']['*'], 'not allowed')) {
			$this->log('Failed to unblock user '.$name.' with error 1003 Forbidden', LG_ERROR);
			throw new BlockFailure('Forbidden', 1003);
		}
		foreach ($resp['query']['pages'] as $v)
			$token = $v['blocktoken'];
		//echo $token;
		$query = 'action=unblock&user='.urlencode($name).'&token='.urlencode($token);
		if ($reason)
			$query .= '&reason='.$reason;
		else
			$query .= '&reason='.urlencode('Sorry '.$name);
		$resp = $this->postAPI($query);
		//var_dump($resp);
		if (isset($response['error'])) {
			switch ($response['error']['code']) {
				case 'blocked':
				case 'autoblocked':
					$this->log('Failed to unblock user '.$name.' with error 1002 Blocked', LG_ERROR);
					throw new BlockFailure('Blocked', 1002);
					break;
				case 'permissiondenied':
				case 'cantunblock':
					$this->log('Failed to unblock user '.$name.' with error 1003 Forbidden', LG_ERROR);
					throw new BlockFailure('Forbidden', 1003);
					break;
				case 'cantunblock':
					$this->log('Failed to unblock user '.$name.' with error 1007 Not Blocked', LG_ERROR);
					throw new BlockFailure('Not Blocked', 1007);
					break;
				default:
					$this->log('Failed to unblock user '.$name.' with error 1000 Unblock Failure', LG_ERROR);
					throw new BlockFailure('Unblock Failure', 1000);
			}
		}
		return true;
	}
	
	/**
	 * Protects a page
	 *
	 * @param string $page Page title to protect
	 * @param string $edit all=everyone autoconfirmed=Autoconfirmed Users sysop=Administrators
	 * @param string $move all=everyone autoconfirmed=Autoconfirmed Users sysop=Administrators
	 * @param string $reason Reason of protection
	 * @param string $editexp Edit protecting expiry in format yyyymmddhhmmss
	 * @param string $movexp Move protecting expiry in format yyyymmddhhmmss
	 * @param bool $cascade Whether to enable cascade protection, i.e. protect all transcluded tamplates
	 * @return bool Return true on success
	 * @throws ProtectFailure
	 */
	public function protect_page($page, $edit, $move, $reason = '', $editexp = 'never', $movexp = 'never', $cascade = false) {
		$response = $this->postAPI('action=query&prop=info&intoken=protect&titles=' . urlencode($page));
		//var_dump($response);
		if (isset($response['warnings']['info']['*']) && strstr($response['warnings']['info']['*'], 'not allowed'))
			throw new ProtectFailure('Forbidden', 703);
		foreach ($response['query']['pages'] as $v) {
			if (isset($v['invalid'])) throw new ProtectFailure('Invalid Title', 704);
			$token = $v['protecttoken'];
		}
		$query = 'action=protect&title='.urlencode($page).'&token='.urlencode($token);
		if ($reason)
			$query .= '&reason='.urlencode($reason);
		$query .= '&protections=edit='.$edit.'|move='.$move;
		$query .= '&expiry='.$editexp.'|'.$movexp;
		if ($cascade) $query .= '&cascade';
		$response = $this->postAPI($query);
		//var_dump($response);
		if (isset($response['error'])) {
			switch ($response['error']['code']) {
				case 'missingtitle-createonly':
					$this->log('Failed to protect '.$page.' with error 701 No Such Page', LG_ERROR);
					throw new ProtectFailure('No Such Page', 701);
					break;
				case 'blocked':
				case 'autoblocked': // 702 Blocked
					$this->log('Failed to protect '.$page.' with error 702 Blocked', LG_ERROR);
					throw new ProtectFailure('Blocked', 702);
					break;
				case 'cantedit':
				case 'permissiondenied':
				case 'protectednamespace': // 703 Forbidden
					$this->log('Failed to protect '.$page.' with error 703 Forbidden', LG_ERROR);
					throw new ProtectFailure('Forbidden', 703);
					break;
				case 'invalidexpiry': // 705 Invaild Expiry
					$this->log('Failed to protect '.$page.' with error 705 Invaild Expiry', LG_ERROR);
					throw new ProtectFailure('Invaild Expiry', 705);
					break;
				case 'pastexpiry': // 706 Past Expiry
					$this->log('Failed to protect '.$page.' with error 706 Past Expiry', LG_ERROR);
					throw new ProtectFailure('Past Expiry', 706);
					break;
				case 'protect-invalidlevel': // 707 Invaild Level
					$this->log('Failed to protect '.$page.' with error 707 Invaild Level', LG_ERROR);
					throw new ProtectFailure('Invaild Level', 707);
					break;
				default:
					$this->log('Failed to protect '.$page.' with error 700 Protect Failure', LG_ERROR);
					throw new ProtectFailure('Protect Failure', 700);
			}
		}
		return true;
	}
	
	/**
	 * Protects a non-exist page
	 *
	 * @param string $page Page title to protect
	 * @param string $perm Permission:all=everyone autoconfirmed=Autoconfirmed Users sysop=Administrators
	 * @param string $reason Reason of protection
	 * @param string $exp Protecting expiry in format yyyymmddhhmmss
	 * @return bool Return true on success
	 * @throws ProtectFailure
	 */
	public function protect_title($page, $perm, $reason = '', $exp = 'never') {
		$response = $this->postAPI('action=query&prop=info&intoken=protect&titles=' . urlencode($page));
		//var_dump($response);
		if (isset($response['warnings']['info']['*']) && strstr($response['warnings']['info']['*'], 'not allowed'))
			throw new ProtectFailure('Forbidden', 703);
		foreach ($response['query']['pages'] as $v) {
			if (isset($v['invalid'])) throw new ProtectFailure('Invalid Title', 704);
			$token = $v['protecttoken'];
		}
		$query = 'action=protect&title='.urlencode($page).'&token='.urlencode($token);
		if ($reason)
			$query .= '&reason='.urlencode($reason);
		$query .= '&protections=create='.$perm;
		$query .= '&expiry='.$exp;
		$response = $this->postAPI($query);
		//var_dump($response);
		if (isset($response['error'])) {
			switch ($response['error']['code']) {
				case 'create-titleexists':
					$this->log('Failed to protect '.$page.' with error 708 Page Exists', LG_ERROR);
					throw new ProtectFailure('Page Exists', 708);
					break;
				case 'blocked':
				case 'autoblocked': // 702 Blocked
					$this->log('Failed to protect '.$page.' with error 702 Blocked', LG_ERROR);
					throw new ProtectFailure('Blocked', 702);
					break;
				case 'cantedit':
				case 'permissiondenied':
				case 'protectednamespace': // 703 Forbidden
					$this->log('Failed to protect '.$page.' with error 703 Forbidden', LG_ERROR);
					throw new ProtectFailure('Forbidden', 703);
					break;
				case 'invalidexpiry': // 705 Invaild Expiry
					$this->log('Failed to protect '.$page.' with error 705 Invaild Expiry', LG_ERROR);
					throw new ProtectFailure('Invaild Expiry', 705);
					break;
				case 'pastexpiry': // 706 Past Expiry
					$this->log('Failed to protect '.$page.' with error 706 Past Expiry', LG_ERROR);
					throw new ProtectFailure('Past Expiry', 706);
					break;
				case 'protect-invalidlevel': // 707 Invaild Level
					$this->log('Failed to protect '.$page.' with error 707 Invaild Level', LG_ERROR);
					throw new ProtectFailure('Invaild Level', 707);
					break;
				default:
					$this->log('Failed to protect '.$page.' with error 700 Protect Failure', LG_ERROR);
					throw new ProtectFailure('Protect Failure', 700);
			}
		}
		return true;
	}	
	
	/**
	 * Upload a local file or a remote file using URL
	 *
	 * @param string $src Source, may be a local file or an URI with a warpper
	 * @param string $target Target file name
	 * @param string $comment Upload comment
	 * @param string $text File page content
	 * @return array an array with file data
	 * @throws UploadFailure
	 */
	function upload($src, $target, $comment = '', $text = '') {
		$response = $this->postAPI('action=query&prop=info&intoken=edit&titles=xxxxxxxx');
		//var_dump($response);
		foreach ($response['query']['pages'] as $v)
			$token = $v['edittoken'];
		//echo $token.EOL;
		if ($this->is_url($src)) {
			$i = get_headers($src);
			//var_dump($i);
			if ($i[0]{9} != 2 and $i[0]{9} != 3) throw new UploadFailure('Can\'t Fetch File', 801);
			$query = 'action=upload&url='.urlencode($src).'&token='.urlencode($token).'&filename='.urlencode($target);
			if ($comment) $query .= '&comment='.urlencode($comment);
			if ($text) $query .= '&text='.urlencode($text);
			$response = $this->postAPI($query);
			//var_dump($response);
		} else {
			if (!is_readable($src))	throw new UploadFailure('Can\'t Read File', 802);
			$query = array(
					'action'	=> 'upload',
					'file'		=> "@$src",
					'token'		=> $token,
					'filename'	=> $target,
					'format'	=> 'php'
					);
			//var_dump($query['file']);
			if ($comment) $query['comment'] = $comment;
			if ($text) $query['text'] = $text;
			$ch = curl_init();
			$cfg = array(
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_COOKIEJAR => 'cookie.txt',
					CURLOPT_COOKIEFILE => 'cookie.txt',
					CURLOPT_USERAGENT => $this->useragent,
					CURLOPT_HEADER => false,
					CURLOPT_URL => $this->api_url,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $query,
					CURLOPT_HTTPHEADER => array('Content-Type: multipart/form-data'),
					);
			curl_setopt_array($ch, $cfg);
			$response = curl_exec($ch);
			if (curl_errno($this->post)) var_dump(curl_error($ch));
			curl_close($ch);
			$response = unserialize($response);
		}
		if (isset($response['error'])) {
			switch ($response['error']['code']) {
				case 'empty-file':
					$this->log('Failed to upload '.$src.' with error 801 Can\'t Fetch File', LG_ERROR);
					throw new UploadFailure('Can\'t Fetch File', 801);
					break;
				case 'permissiondenied':
					$this->log('Failed to upload '.$src.' with error 803 Forbidden', LG_ERROR);
					throw new UploadFailure('Forbidden', 803);
				case 'blocked':
				case 'autoblocked': // 804 Blocked
					$this->log('Failed to upload '.$src.' with error 804 Blocked', LG_ERROR);
					throw new UploadFailure('Blocked', 804);
					break;
				default:
					$this->log('Failed to upload '.$src.' with error 800 Upload Failure', LG_ERROR);
					throw new UploadFailure('Upload Failure', 800);
			}
		}
		if ($response['upload']['result'] == 'Success'):
			$j = $response['upload']['imageinfo'];
			$this->log('Uploaded '.$src.' to '.$j['descriptionurl'], LG_INFO);
			$i = array(
					'timestamp'	=> $j['timestamp'],
					'width'		=> $j['width'],
					'height'	=> $j['height'],
					'url'		=> $j['url'],
					'page'		=> $j['descriptionurl'],
					'mime'		=> $j['mime'],
					'sha1'		=> $j['sha1'],
					);
			return $i;
		endif;
		throw new UploadFailure('Upload Failure', 800);
	}
	
	/**
	 * Emails A user
	 *
	 * @param string $user The User name
	 * @param string $subject Email Subject
	 * @param string $text Content of email
	 * @param bool $cc Send a copy the the sender
	 * @return bool True on success
	 * @throws EmailFailure
	 */
	public function email ($user, $subject, $text, $cc) {
		$response = $this->postAPI('action=query&prop=info&intoken=email&titles=User%3A' . urlencode($user));
		//var_dump($response);
		if (isset($response['warnings']['info']['*']) && strstr($response['warnings']['info']['*'], 'not allowed'))
			throw new ProtectFailure('Forbidden', 703);
		foreach ($response['query']['pages'] as $v) {
			if (isset($v['invalid'])) throw new ProtectFailure('Invalid Title', 704);
			$token = $v['emailtoken'];
		}
		//echo $token;
		$query = 'action=emailuser&target='.urlencode($user).'&subject='.urlencode($subject).'&text='.urlencode($text).'&token='.urlencode($token);
		if ($cc) $query .= '&ccme';
		$resp = $this->postAPI($query);
		//var_dump($resp);
		if (isset($resp['error'])) switch ($resp['error']['code']) {
			case 'noemail':
			case 'usermaildisabled':
				$this->log('Failed to email '.$user.' with error 1101 Don\'t want email', LG_ERROR);
				throw new EmailFailure('Don\'t want email', 1101);
				break;
			case 'permissiondenied':
				$this->log('Failed to email '.$user.' with error 1103 Forbidden', LG_ERROR);
				throw new EmailFailure('Forbidden', 1103);
			case 'blocked':
			case 'autoblocked':
			case 'blockedfrommail':
				$this->log('Failed to email '.$user.' with error 1102 Blocked', LG_ERROR);
				throw new EmailFailure('Blocked', 1102);
				break;
			default:
				$this->log('Failed to email '.$user.' with error 1100 Email Failure', LG_ERROR);
				throw new EmailFailure('Email Failure', 1100);
		}
		if ($resp['emailuser']['result'] == 'Success') return true;
		$this->log('Failed to email '.$user.' with error 1100 Email Failure', LG_ERROR);
		throw new EmailFailure('Email Failure', 1100);
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
			$token = urlencode($value['edittoken']);
			$sts = $value['starttimestamp'];
			if (isset($this->editdetails[-1])) {
				$ts = $sts;
				$extra = '&createonly=yes';
			} else {
				$ts = $value['revisions'][0]['timestamp'];
				$extra = '&nocreate=yes';
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
		
		if ($newtext == $oldtext) {
			//the new content is the same, nothing changes
			$this->log('401 Same Content, can\'t update!!!', LG_ERROR);
			throw new EditFailure('Same Content', 401);
		}
		if ($newtext == '' && !$force) {
			//the new content is void, nothing changes
			$this->log('402 Blank Content, use $force!!!', LG_ERROR);
			throw new EditFailure('Blank Content', 402);
		}
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
			switch ($response['error']['code']):
				case 'cantcreate':
				case 'permissiondenied':
				case 'noedit': // 403 Forbidden
					throw new EditFailure('Forbidden', 403);
					break;
				case 'blocked':
				case 'autoblocked': // 404 Blocked
					throw new EditFailure('Blocked', 404);
					break;
				case 'protectedtitle':
				case 'protectedpage':
				case 'protectednamespace':
					throw new EditFailure('Protected', 405);
					break;
				case 'badmd5':
					throw new EditFailure('MD5 Failed', 406);
					break;
				default:
					throw new EditFailure('Edit Failure', 400);
			endswitch;
		} else {
			$this->log('[' . $response['edit']['result'] . '] ' . $response['error']['info'], LG_ERROR);
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
		//var_dump($response);
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
		$header = array(
			'Connection: keep-alive',
			'Keep-Alive: 300'
			); 
		$cfg = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_COOKIEJAR => 'cookie.txt',
				CURLOPT_COOKIEFILE => 'cookie.txt',
				CURLOPT_USERAGENT => $this->useragent,
				CURLOPT_HEADER => false,
				CURLOPT_ENCODING => 'gzip,deflate',
				CURLOPT_HTTPHEADER => $header,
				);
		$header[] = 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8';
		$post = array(
				CURLOPT_URL => $this->api_url,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => $header,
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
	
	
	/**
	 * See if bots are allowed to edit the page
	 *
	 * @param string $text The content of the page
	 * @return bool Returns true if the bot is allowed
	 *
	 */
	protected function allowBots($text) {
		if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?' . preg_quote($this->user, '/') . '.*?)\}\}/iS', $text))
			return false;
		return true;
	}
	
	/**
	 * Check if $url is a valid URL, doesn't check if it returns an error when requesting
	 *
	 * @param string $url URL to check
	 * @return bool True if $url is really a URL or false when it's some what not using HTTP(S) or FTP
	 *
	 */
	protected function is_url($url) {
		return (bool)preg_match('/^(http|https|ftp):\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\':+!]*([^<>\"])*$/', $url);
	}
}
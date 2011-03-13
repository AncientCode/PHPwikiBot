<?php
/**
 * PHPwikiBot Configuration File Generator
 * @author Xiaomao
 * @package PHPwikiBot
 * @name confgen.php
 */
require dirname(__FILE__).'/stddef.inc';
// Date
$date = date('Y-m-d h:m:s T');

// Header
$file = <<<EOD
<?php
/**
 * PHPwikiBot Configuration File
 * @author Xiaomao
 * @package PHPwikiBot
 * @name config.php
 * @version $date
 */

EOD;

function input($msg, $default = '') {
	echo $msg, ' [', $default, ']: ';
	return trim(fgets(STDIN));
}

$ua = input('User-Agent of bot', 'PHPwikiBot/'.MB_VERSION);
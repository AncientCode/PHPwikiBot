<?php
/**
 * PHPwikiBot Configuration File Generator
 * @author Xiaomao
 * @package PHPwikiBot
 * @name Configuration Generator
 */
/**
 * Standard Definitions
 */
require_once dirname(__FILE__).'/stddef.inc';
// Date
$date = date('Y-m-d h:m:s T');

// Header
$file = <<<EOD
<?php
/**
 * PHPwikiBot Configuration File
 * Generated with confgen.php
 * @author Xiaomao
 * @package PHPwikiBot
 * @name Configuration File
 * @version $date
 */


EOD;

/**
 * Get the users input into the return value,
 * print a message and handle the default value
 *
 * @param string $msg Message to the user without ': '
 * @param string $default Default value, string please
 * @return string The users input
 *
 */
function input($msg, $default = '') {
	echo $msg, ' [', $default, ']: ';
	$input = trim(fgets(STDIN));
	if ($input === '') {
		return $default;
	} else {
		return $input;
	}
}

$ua = input('User-Agent of bot', 'PHPwikiBot/'.PWB_VERSION);
$file .= '$useragent = \''.$ua.'\';'.PHP_EOL;
echo 'Choose a way to store the key, a string(0) or a statment that returns a key(1, use this is you are skilled enough)',PHP_EOL;
do {
	$keytype = input('Choose a type, 0 or 1', '0');
	if ($keytype == 0) {
		
	} elseif ($keytype == 1) {
		
	}
} while ($keytype != 0 && $keytype != 1);

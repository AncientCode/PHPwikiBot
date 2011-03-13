<?php
/**
 * Generate a encrypted password for config.php
 * 
 * @author Xiaomao
 * @package PHPwikiBot
 * @name Password Encoder
 */

/** Get the configuration File */
if (is_readable(dirname(__FILE__).'/config.php')):
	require dirname(__FILE__).'/config.php';
else:
	echo <<<'END'
Can't find a configureation file, type in your own key
Key: 
END;
	$key = trim(fgets(STDIN));
endif;
if (!isset($key)) die('Please set $key in config.php');

/**
 * Asking user for a password without displaying it
 *
 * @param string $prompt The prompt to use
 * @return string Returns the password in plain text or false on failure
 *
 */
function prompt_silent($prompt = 'Enter Password: ') {
	if (substr(PHP_OS, 0, 3) == 'WIN'):
		echo $prompt;
		$dir = dirname(__FILE__);
		$out = `cmd /c $dir\pass.bat`;
		echo PHP_EOL;
		return $out;
	else:
		$command = '/usr/bin/env bash -c \'echo OK\'';
		if (rtrim(shell_exec($command)) !== 'OK') {
			trigger_error("Can't invoke bash");
			return false;
		}
		$command = '/usr/bin/env bash -c \'read -s -p "' . addslashes($prompt) . '" mypassword && echo $mypassword\'';
		$password = rtrim(shell_exec($command));
		echo PHP_EOL;
		return $password;
	endif;
}

$pass1 = prompt_silent('Enter the password: ');
$pass2 = prompt_silent('Enter the password again: ');
if ($pass1 == $pass2):
	$arr = explode("\n", $pass1);
	echo openssl_encrypt($arr[0], 'AES-128-ECB', $key), PHP_EOL;
else:
	echo 'Error: The two password must match!!!'.PHP_EOL;
	die(2);
endif;
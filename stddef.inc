<?php
/**
 * PHPwikiBot Definitions
 * @author Xiaomao
 * @package PHPwikiBot
 * @name Standard Definitions
 */

/**Definitions**/
define('INC', dirname(__FILE__).'/');
define('PWB_VERSION', 0.1);
if (PHP_SAPI == 'cli' || PHP_SAPI == 'embed'): 
	define('EOL', PHP_EOL); // we should use the platform's line break for cli
else:
	define('EOL', '<br />'.PHP_EOL);// but <br /> with a line break for web
endif;
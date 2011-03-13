<?php
/**
 * PHPwikiBot Definitions
 * @author Xiaomao
 * @package PHPwikiBot
 * @name Standard Definitions
 */

/**Definitions**/

/**
 * @const The bot's location
 */
define('INC', dirname(__FILE__).'/');

/**
 * @const PHPwikiBot's version
 */
define('PWB_VERSION', 0.1);

/**
 * Defines the end of line
 * If it's command line or embed SAPI, we use the system's EOL
 * If it's web, i.e. othe web server SAPI, we use <br / > + system's EOL
 * @const end of line character
 * @ignore
 */
if (PHP_SAPI == 'cli' || PHP_SAPI == 'embed'): 
	define('EOL', PHP_EOL); // we should use the platform's line break for cli
else:
	define('EOL', '<br />'.PHP_EOL);// but <br /> with a line break for web
endif;
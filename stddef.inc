<?php
/**
 * PHPwikiBot Definitions
 * @author Xiaomao
 * @package PHPwikiBot
 * @name Standard Definitions
 * @license http://www.gnu.org/licenses/gpl.html GPLv3+
 */

/**Definitions**/

/**
 * The bot's location
 */
define('INC', dirname(__FILE__).'/');

/**
 * PHPwikiBot's version
 */
define('PWB_VERSION', 0.1);

/** Command line or HTML */
define('CLI', (PHP_SAPI == 'cli' || PHP_SAPI == 'embed'));

/**
 * Defines the end of line
 * If it's command line or embed SAPI, we use the system's EOL
 * If it's HTML, i.e. othe web server SAPI, we use <br / > plus system's EOL
 */
define('EOL', CLI ? PHP_EOL : '<br />'.PHP_EOL);

/** Windows Specific Functions */
define('WIN32', (substr(PHP_OS, 0, 3) == 'WIN'));

/* Error levels */
/**Debug level*/
define('LG_DEBUG', 0);
/**Information level*/
define('LG_INFO', 1);
/**Notice level*/
define('LG_NOTICE', 2);
/**Warning level*/
define('LG_WARN', 3);
/**Error level*/
define('LG_ERROR', 4);
/**Fatal Error level*/
define('LG_FATAL', 5);

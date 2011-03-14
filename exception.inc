<?php
/**
 * PHPwikiBot Exceptions
 * @author Xiaomao
 * @package Exception
 * @name Exception Definitions
 */


/**
 * BotException returns has additional function getException() which returns the exception in HTTP header style
 * e.g. 100 Can't Login
 * @package Exception
 */
class BotException extends Exception {
	
	/**
	 * getException method returns the exception in HTTP header style
	 * @return string An HTTP header style error code
	 */
	final public function getException() {
		return $this->code.' '.$this->message;
	}
}
/**
 * The exception Login Failure (1xx)
 * 
 * Error Codes:
 * 100 Can't Login
 * 
 * @package Exception
 */
class LoginFailure extends BotException {}

/**
 * The exception Get Page Failure (2xx)
 * 
 * Error Codes:
 * 200 Can't Fetch Page;
 * 201 Page doesn't exist;
 * 202 Page title invaild;
 * 203 Special Page
 * 
 * @package Exception
 */
class GetPageFailure extends BotException {}

/**
 * The exception Get Information Failure (3xx)
 * 
 * Error Codes:
 * 300 Can't Get Info;
 * 301 Not in Gereral Info
 *
 */
class InfoFailure extends BotException {}

//class LoginFailure extends BotException {}*/


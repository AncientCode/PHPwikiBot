<?php
/**
 * PHPwikiBot Exceptions
 * @author Xiaomao
 * @package Execption
 * @name Exception Definitions
 */

class BotException extends Exception {
	final public function getException() {
		return $this->code.' '.$this->message;
	}
}
/**
 * The exception Login Failure (1xx)
 * 
 * Error Codes:
 * 100 General Failure
 * 
 * @package Exception
 */
class LoginFailure extends BotException {}

/**
 * The exception Get Page Failure (2xx)
 * 
 * Error Codes:
 * 200 General Failure
 * 201 Page doesn't exist
 * 202 Page title invaild
 * 203 Special Page
 * 
 * @package Exception
 */
class GetPageFailure extends BotException {}
//class LoginFailure extends BotException {}*/


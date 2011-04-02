<?php
/**
 * PHPwikiBot Exceptions
 * @author Xiaomao
 * @package Exception
 * @name Exception Definitions
 * @license http://www.gnu.org/licenses/gpl.html GPLv3+
 */


/**
 * BotException returns has additional function getException() which returns the exception in HTTP header style
 * e.g. 100 Can't Login. Also include some errors:
 * + 10 Unexist Method
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
 * + 100 Can't Login
 * 
 * @package Exception
 */
class LoginFailure extends BotException {}

/**
 * The exception Get Page Failure (2xx)
 * 
 * Error Codes:
 * + 200 Can't Fetch Page
 * + 201 Page doesn't exist
 * + 202 Page title invaild
 * + 203 Special Page
 * 
 * @package Exception
 */
class GetPageFailure extends BotException {}

/**
 * The exception Get Information Failure (3xx)
 * 
 * Error Codes:
 * + 300 Can't Get Info
 * + 301 Not in Gereral Info
 *
 * @package Exception
 */
class InfoFailure extends BotException {}


/**
 * The exception Edit Failure (4xx)
 * 
 * Error Codes:
 * + 400 Edit Failure
 * + 401 Same Content
 * + 402 Blank Content
 * + 403 Forbidden
 * + 404 Blocked
 * + 405 Protected
 * + 406 MD5 Failed
 * + 420 Page Exists
 * + 421 Page Doesn't Exist
 *
 * @package Exception
 */
class EditFailure extends BotException {}

/**
 * The exception Move Failure (5xx)
 * 
 * Error Codes:
 * + 500 Move Failure
 * + 501 Destination Exists
 * + 502 Protected
 * + 503 Forbidden
 * + 504 Extension Mismatch
 * + 505 Wrong Namespace
 * + 506 Self Move
 * + 507 Invaild Title
 *
 * @package Exception
 */
class MoveFailure extends BotException {}

/**
 * The exception Delete Failure (6xx)
 * 
 * Error Codes:
 * + 600 Delete Failure
 * + 601 No Such Page
 * + 602 Blocked
 * + 603 Forbidden
 * + 604 Invaild Title
 *
 * @package Exception
 */
class DeleteFailure extends BotException {}

/**
 * The exception Protect Failure (7xx)
 * 
 * Error Codes:
 * + 700 Protect Failure
 * + 701 No Such Page
 * + 702 Blocked
 * + 703 Forbidden
 * + 704 Invaild Title
 * + 705 Invaild Expiry
 * + 706 Past Expiry
 * + 707 Invaild Level
 * + 708 Page Exists
 *
 * @package Exception
 */
class ProtectFailure extends BotException {}

/**
 * The exception Upload Failure (8xx)
 * 
 * Error Codes:
 * + 800 Upload Failure
 * + 801 Can't Fetch File
 * + 802 Can't Read File
 * + 803 Forbidden
 * + 804 Blocked
 *
 * @package Exception
 */
class UploadFailure extends BotException {}

/**
 * The exception Delete Failure (9xx)
 * 
 * Error Codes:
 * + 900 Undelete Failure
 * + 901 Not Deleted
 * + 902 Blocked
 * + 903 Forbidden
 * + 904 Invaild Title
 *
 * @package Exception
 */
class UndeleteFailure extends BotException {}

/**
 * The exception Block Failure (10xx)
 * 
 * Error Codes:
 * + 1000 Block Failure
 * + 1001 Already Blocked
 * + 1002 Blocked
 * + 1003 Forbidden
 * + 1004 Invaild Expiry
 * + 1005 Invaild User/IP
 * + 1006 Unblock Failure
 * + 1007 Not Blocked
 *
 * @package Exception
 */
class BlockFailure extends BotException {}

/**
 * The exception Email Failure (11xx)
 * 
 * Error Codes:
 * + 1100 Email Failure
 * + 1101 Don't want email
 * + 1102 Blocked
 * + 1103 Forbidden
 *
 * @package Exception
 */
class EmailFailure extends BotException {}

//class LoginFailure extends BotException {}*/


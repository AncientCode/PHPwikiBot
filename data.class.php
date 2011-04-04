<?php
/**
 * PHPwikiBot Data Classes File
 * @author Xiaomao
 * @package DataObjects
 * @name PHPwikiBot data classes
 * @license http://www.gnu.org/licenses/gpl.html GPLv3+
 */


/**
 * One single page on the wiki with only one revision
 * @package DataObjects
 */
class WikiPage {
	/**
	 * Namespace
	 * @var int
	 */
	public $ns;
	/**
	 * Namespace user-friendly name
	 * @var string
	 */
	public $nsname;
	/**
	 * Page content of current revision
	 * @var string
	 */
	public $text;
	/**
	 * Page ID
	 * @var int
	 */
	public $id;
	/**
	 * Page title
	 * @var string 
	 */
	public $title;
}

/**
 * A class for one exported pages
 * @package DataObjects
 */
class ExportedPage {
	/**
	 * XML Export
	 * @var string
	 */
	public $xml;
	/**
	 * Page id
	 * @var int
	 */
	public $id;
	/**
	 * Page Namespace ID
	 * @var int
	 */
	public $ns;
	/**
	 * Page title
	 * @var string
	 */
	public $title;
}

/**
 * Upload File Data
 * @package DataObjects
 */
class UploadData {
	/**
	 * Timestamp of upload
	 * @var string
	 */
	public $timestamp;
	/**
	 * Image Width
	 * @var int
	 */
	public $width;
	/**
	 * Image Height
	 * @var int
	 */
	public $height;
	/**
	 * Image URL
	 * @var string
	 */
	public $url;
	/**
	 * Description Page
	 * @var string
	 */
	public $page;
	/**
	 * MIME type
	 * @var string
	 */
	public $mime;
	/**
	 * File SHA1
	 * @var string
	 */
	public $sha1;
}
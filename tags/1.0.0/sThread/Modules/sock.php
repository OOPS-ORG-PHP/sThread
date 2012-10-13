<?php
/**
 * sThread SOCK module for port knocking
 * 
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @filesource
 */
Class sThread_SOCK {
	// {{{ properteis
	static public $clearsession = false;
	static public $port = 12345;

	const SOCK_REQUEST  = 1;
	const SOCK_CLOSE    = 2;
	// }}}

	// {{{ (void) sThread_SOCK::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_SOCK::init (void)
	function init () {
		self::$clearsession = false;
	}
	// }}}

	// {{{ (int) sThread_SOCK::check_buf_status ($status)
	function check_buf_status ($status) {
		switch ($status) {
			case 0 :
			case self::SOCK_REQUEST :
				return Vari::EVENT_READY_SEND;
				break;
			case self::SOCK_CLOSE :
				return Vari::EVENT_READY_CLOSE;
				break;
			default :
				return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_SOCK::call_status ($status, $call = false)
	function call_status ($status, $call = false) {
		switch ($status) {
			case self::SOCK_REQUEST :
				$r = 'SOCK_REQUEST';
				break;
			default:
				$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_SOCK::change_status (&$sess, $key)
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::SOCK_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_SOCK::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::SOCK_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_SOCK::clear_session ($key) {
	/*
	 * self::$clearsession == false 일 경우, clear_session method
	 * 는 존재하지 않아도 된다.
	 */
	function clear_session ($key) {
		return;
	}
	// }}}

	/*
	 * Handler Definition
	 * handler name is get sThread_MODULE::call_status API
	 */

	// {{{ (string) sThread_SOCK::sock_request (&$sess, $key)
	function sock_request (&$sess, $key) {
		return "\r\n";
	}
	// }}}
}

?>

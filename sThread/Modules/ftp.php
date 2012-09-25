<?php
/**
 * sThread FTP module
 * 
 * This is supported only until login process
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
Class sThread_FTP {
	// {{{ properteis
	static public $clearsession = false;
	static public $port = 21;

	const FTP_BANNER      = 1;
	const FTP_SENDUSER    = 2;
	const FTP_USERBANNER  = 3;
	const FTP_SENDPASS    = 4;
	const FTP_COMFIRMAUTH = 5;
	/*
	 * When processing has error, call TYPE_QUIT method automatically
	 * by parent::seocketClose API If TYPE_QUIT method is defined
	 */
	const FTP_QUIT        = 6;
	const FTP_CLOSE       = 7;
	// }}}

	// {{{ (void) sThread_FTP::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_FTP::init (void)
	function init () {
		self::$clearsession = false;
		self::$port = 21;
	}
	// }}}

	// {{{ (int) sThread_FTP::check_buf_status ($status)
	function check_buf_status ($status) {
		switch ($status) {
			case 0 :
			case self::FTP_BANNER :
				return Vari::EVENT_READY_RECV;
				break;
			case self::FTP_SENDUSER :
				return Vari::EVENT_READY_SEND;
				break;
			case self::FTP_USERBANNER :
				return Vari::EVENT_READY_RECV;
				break;
			case self::FTP_SENDPASS :
				return Vari::EVENT_READY_SEND;
				break;
			case self::FTP_COMFIRMAUTH :
				return Vari::EVENT_READY_RECV;
				break;
			case self::FTP_QUIT :
				return Vari::EVENT_READY_SEND;
				break;
			case self::FTP_CLOSE :
				return Vari::EVENT_READY_CLOSE;
				break;
			default :
				return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_FTP::call_status ($status, $call = false)
	function call_status ($status, $call = false) {
		switch ($status) {
			case self::FTP_BANNER :
				$r = 'FTP_BANNER';
				break;
			case self::FTP_SENDUSER :
				$r = 'FTP_SENDUSER';
				break;
			case self::FTP_USERBANNER :
				$r = 'FTP_USERBANNER';
				break;
			case self::FTP_SENDPASS :
				$r = 'FTP_SENDPASS';
				break;
			case self::FTP_COMFIRMAUTH :
				$r = 'FTP_COMFIRMAUTH';
				break;
			case self::FTP_QUIT:
				$r = 'FTP_QUIT';
				break;
			default:
				$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_FTP::change_status (&$sess, $key)
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::FTP_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_FTP::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::FTP_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_FTP::clear_session ($key) {
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

	// {{{ (boolean) sThread_FTP::ftp_banner (&$sess, $key, $recv)
	function ftp_banner (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		if ( ! preg_match ('/^220 /', $sess->recv[$key]) )
			return false;

		$sess->recv[$key] = '';
		return true;
	} // }}}

	// {{{ (string) function ftp_senduser (&$sess, $key)
	function ftp_senduser (&$sess, $key) {
		list ($host, $port, $type) = $sess->addr[$key];
		$opt = $sess->opt[$key];

		if ( ! $opt->user ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				'[FTP] Configration error: ftp user does not set!'
			);
			return false;
		}

		return 'User ' . $opt->user . "\r\n";
	} // }}}

	// {{{ (boolean) function ftp_comfirmauth (&$sess, $key, $recv)
	function ftp_userbanner (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		if ( ! preg_match ('/^([0-9]{3}) /', $sess->recv[$key]) )
			return false;

		if ( ! preg_match ('/^331 /', $sess->recv[$key]) ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				'[FTP] Unknown command: '
			);
			$sess->recv[$key] = '';
			return null;
		}

		$sess->recv[$key] = '';
		return true;
	} // }}}

	// {{{ function ftp_sendpass (&$ses, $key)
	function ftp_sendpass (&$ses, $key) {
		return 'Pass ' . $ses->opt[$key]->pass . "\r\n";
	} // }}}

	// {{{ (boolean) function ftp_comfirmauth (&$sess, $key, $recv)
	function ftp_comfirmauth (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		if ( ! preg_match ('/^[0-9]{3} /m', $sess->recv[$key]) ) {
			return false;
		}

		if ( preg_match ('/^(530|500) /m', $sess->recv[$key], $m) ) {
			$err = ($m[1] == 530) ? 'Unmatched password' : 'Unknown command';
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				'[FTP] Protocol error: ' . $err
			);
			return null;
		}

		$sess->recv[$key] = '';
		return true;
	} // }}}

	// {{{ (string) sThread_FTP::ftp_quit (&$sess, $key)
	function ftp_quit (&$sess, $key) {
		return "quit\r\n";
	}
	// }}}
}

?>

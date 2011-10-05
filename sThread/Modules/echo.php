<?php
/*
 * sThread ECHO module
 * 
 * $Id$
 * Don't support utp protocol
 *
 */
Class sThread_ECHO {
	// {{{ properteis
	static public $clearsession = false;
	static public $port = 7;

	const ECHO_REQUEST  = 1;
	const ECHO_RESPONSE = 2;
	const ECHO_CLOSE    = 3;
	// }}}

	// {{{ (void) sThread_ECHO::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_ECHO::init (void)
	function init () {
		self::$clearsession = false;
		self::$port = 11211;
	}
	// }}}

	// {{{ (int) sThread_ECHO::check_buf_status ($status)
	function check_buf_status ($status) {
		switch ($status) {
			case 0 :
			case self::ECHO_REQUEST :
				return Vari::EVENT_READY_SEND;
				break;
			case self::ECHO_RESPONSE :
				return Vari::EVENT_READY_RECV;
				break;
			case self::ECHO_CLOSE :
				return Vari::EVENT_READY_CLOSE;
				break;
			default :
				return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_ECHO::call_status ($status, $call = false)
	function call_status ($status, $call = false) {
		switch ($status) {
			case self::ECHO_REQUEST :
				$r = 'ECHO_REQUEST';
				break;
			case self::ECHO_RESPONSE :
				$r = 'ECHO_RESPONSE';
				break;
			default:
				$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_ECHO::change_status (&$sess, $key)
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::ECHO_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_ECHO::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::ECHO_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_ECHO::clear_session ($key) {
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

	// {{{ (string) sThread_ECHO::echo_request (&$sess, $key)
	function echo_request (&$sess, $key) {
		return "echo data\r\n";
	}
	// }}}

	// {{{ (boolean) sThread_ECHO::echo_response (&$sess, $key, $recv)
	function echo_response (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		//if ( ! preg_match ('/data$/', $sess->recv[$key]) )
		if ( $sess->recv[$key] != "echo data\r\n" )
			return false;

		$sess->recv[$key] = '';
		return true;
	}
}

?>

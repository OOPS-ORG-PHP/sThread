<?php
/*
 * sThread MEMCACHED module
 * 
 * $Id$
 * This is don't support binary data protocol!
 *
 */
Class sThread_MEMCACHED {
	// {{{ properteis
	static public $clearsession = false;
	static public $port = 11211;

	const MEMCACHED_REQUEST  = 1;
	const MEMCACHED_RESPONSE = 2;
	const MEMCACHED_CLOSE    = 3;
	// }}}

	// {{{ (void) sThread_MEMCACHED::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_MEMCACHED::init (void)
	function init () {
		self::$clearsession = false;
		self::$port = 11211;
	}
	// }}}

	// {{{ (int) sThread_MEMCACHED::check_buf_status ($status)
	function check_buf_status ($status) {
		switch ($status) {
			case 0 :
			case self::MEMCACHED_REQUEST :
				return Vari::EVENT_READY_SEND;
				break;
			case self::MEMCACHED_RESPONSE :
				return Vari::EVENT_READY_RECV;
				break;
			case self::MEMCACHED_CLOSE :
				return Vari::EVENT_READY_CLOSE;
				break;
			default :
				return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_MEMCACHED::call_status ($status, $call = false)
	function call_status ($status, $call = false) {
		switch ($status) {
			case self::MEMCACHED_REQUEST :
				$r = 'MEMCACHED_REQUEST';
				break;
			case self::MEMCACHED_RESPONSE :
				$r = 'MEMCACHED_RESPONSE';
				break;
			default:
				$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_MEMCACHED::change_status (&$sess, $key)
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::MEMCACHED_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_MEMCACHED::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::MEMCACHED_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_MEMCACHED::clear_session ($key) {
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

	// {{{ (string) sThread_MEMCACHED::memcached_request (&$sess, $key)
	function memcached_request (&$sess, $key) {
		return "stats\r\n";
	}
	// }}}

	// {{{ (boolean) sThread_MEMCACHED::memcached_response (&$sess, $key, $recv)
	function memcached_response (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		if ( strncmp ($sess->recv[$key], 'STAT pid ', 9) ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				'Protocol error: Invalid response'
			);
			return null;
		}

		if ( ! preg_match ('/(END|ERROR)$/', trim ($sess->recv[$key])) )
			return false;

		if ( preg_match ('/ERROR$/', trim ($sess->recv[$key])) ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				'Protocol error: Protocol Error'
			);
			return null;
		}

		$sess->recv[$key] = '';
		return true;
	}
}

?>

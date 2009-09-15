<?php
Class sThread_HTTP {
	// {{{ properties
	static public $clearsession = true;
	static public $port = 80;
	static public $uri  = '/robots.txt';
	//static public $uri   = '/index.php';
	static public $agent = 'pear.oops.org::sThread HTTP module';
	static private $sess;

	const HTTP_REQUEST  = 1;
	const HTTP_RESPONSE = 2;
	const HTTP_CLOSE    = 3;
	// }}}

	// {{{ (void) __construct (void)
	function __construct () {
		self::init ();

		$this->clearsession = &self::$clearsession;
		$this->port  = &self::$port;
		$this->uri   = &self::$uri;
		$this->agent = &self::$agent;
		$this->sess  = &self::$sess;
	}
	// }}}

	function init () {
		self::$sess = (object) array (
			'returnCode' => array (),
			'chunked'    => array (),
			'length'     => array (),
			'header'     => array (),
			'data'       => array (),
		);
	}

	// {{{ (int) sThread_HTTP::check_buf_status ($status)
	function check_buf_status ($status) {
		switch ($status) {
			case 0 : 						/* Vari::EVENT_CONNECT */
			case self::HTTP_REQUEST :
				return Vari::EVENT_READY_SEND;
				break;
			case self::HTTP_RESPONSE :
				return Vari::EVENT_READY_RECV;
				break;
			case self::HTTP_CLOSE :
				return Vari::EVENT_READY_CLOSE;
				break;
			default :
				return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_HTTP::call_status ($status, $call = false)
	function call_status ($status, $call = false) {
		switch ($status) {
			case self::HTTP_REQUEST :
				$r = 'HTTP_REQUEST';
				break;
			case self::HTTP_RESPONSE :
				$r = 'HTTP_RESPONSE';
				break;
			default:
				$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_HTTP::change_status (&$sess, $key)
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::HTTP_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_HTTP::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::HTTP_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_HTTP::clear_session ($key) {
	function clear_session ($key) {
		$target = array (
			'returnCode', 'chunked', 'length', 'header', 'data',
		);

		foreach ( $target as $val ) {
			$dest = &self::$sess->$target;
			if ( isset ($dest[$key]) )
				unset ($dest[$key]);
		}
	}
	// }}}

	/*
	 * Handler Definition
	 * handler name is get sThread_MODULE::call_status API
	 */

	// {{{ (void) sThread_HTTP::http_request (&$sess, $key)
	function http_request (&$sess, $key) {
		list ($host, $port, $type) = $sess->addr[$key];

		$uri = self::$uri;
		return "GET {$uri} HTTP/1.1\r\n" .
				"Host: {$host}\r\n" .
				"Accpt: *.*\r\n" .
				'User-Agent: ' . self::$agent . "\r\n" .
				"Connection: close\r\n" .
				"\r\n";
	}
	// }}}

	// {{{ (boolean) sThread_HTTP::http_response (&$sess, $key, $recv)
	function http_response (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		/*
		 * Check HTTP Return Code
		 */
		if ( ! isset (self::$sess->returnCode[$key]) ) {
			if ( ! preg_match ('!^HTTP/[0-9]\.[0-9] ([0-9]{3})!', $recv, $matches) ) {
				Vari::$res->status[$key] = array (
					"{$host}:{$port}",
					false,
					'Protocol error: Not http protocol'
				);
				self::clear_session ($key);
				return null;
			}

			if ( $matches[1] != '200' ) {
				Vari::$res->status[$key] = array (
					"{$host}:{$port}",
					false,
					"Protocol error: Return code is not 200 (Return {$matches[1]})"
				);
				self::clear_session ($key);
				return null;
			}

			self::$sess->returnCode[$key] = $matches[1];
		}

		$headerSet = false;
		if ( ! isset (self::$sess->header[$key]) &&
			 ($pos = strpos ($sess->recv[$key], "\r\n\r\n")) !== false ) {
			$headerSet = true;
			self::parse_header ($key, trim (substr ($sess->recv[$key], 0, $pos)));
			$data = substr ($sess->recv[$key], $pos + 4);

			if ( isset (self::$sess->header[$key]->Transfer_Encoding) &&
				 self::$sess->header[$key]->Transfer_Encoding == "chunked" ) {
				self::$sess->chunked[$key] = true;
				self::$sess->length[$key] = 0;
				self::chunked_data ($key, $data);
			} else {
				self::$sess->length[$key] = (integer) self::$sess->header[$key]->Content_Length;
				self::$sess->data[$key] = $data;
			}
		}

		if ( isset (self::$sess->data[$key]) && $headerSet !== true ) {
			if ( self::$sess->chunked[$key] === true )
				self::chunked_data ($key, $recv);
			else
				self::$sess->data[$key] .= $recv;
		}

		/*
		 * Procotol complete case
		 */
		$exit = false;
		// case chunnked encoding
		if ( self::$sess->chunked[$key] === true ) {
			if ( preg_match ("/\r\n+0(\r\n)+$/", $recv) ) {
				/*
				echo "!!!!!!!!!!!!!!!!!!!!!-----------------\n";
				echo self::$sess->data[$key] . "\n";
				echo "1. " . strlen (self::$sess->data[$key]) . "\n";
				echo "2. " . self::$sess->length[$key] . "\n";
				echo "!!!!!!!!!!!!!!!!!!!!!-----------------\n";
				file_put_contents ('./b', self::$sess->data[$key]);
				 */
				$datalen = strlen (self::$sess->data[$key]);
				$sesslen = self::$sess->length[$key];
				if ( $datalen != $sesslen ) {
					Vari::$res->status[$key] = array (
						"{$host}:{$port}",
						false,
						"Protocol error: Contents Length different (D: $datalen <-> S:$sesslen)"
					);
					return null;
				}
				$exit = true;
			}
		} else {
			if ( strlen (self::$sess->data[$key]) == self::$sess->length[$key] )
				$exit = true;
		}

		if ( $exit === true ) {
			self::clear_session ($key);
			$sess->recv[$key] = '';
			return true;
		}

		return false;
	}
	// }}}

	/*
	 * User define method
	 */
	// {{{ (void) sThread_HTTP::parse_header ($v)
	private function parse_header ($key, $v) {
		$s = explode ("\r\n", $v);
		foreach ( $s as $line ) {
			if ( ! preg_match ('/([^:]+): (.+)/', $line, $matches) )
				continue;
			$matches[1] = str_replace ('-', '_', $matches[1]);
			self::$sess->header[$key]->{$matches[1]} = $matches[2];
		}
	}
	// }}}

	// {{{ (int) sThread_HTTP::chunked_length ($v)
	private function chunked_length ($v) {
		return hexdec (trim ($v));
	}
	// }}}

	// {{{ (void) sThread_HTTP::chunked_data ($key, $v)
	private function chunked_data ($key, $v) {
		if ( preg_match ("/^([0-9a-z ]{1,3})(?:\r\n)(.*)$/s", $v, $matches) ) {
			self::$sess->length[$key] += self::chunked_length ($matches[1]);
			$v = $matches[2];
		}

		while ( ($pos = strpos ($v, "\r\n")) !== false ) {
			$chunkeds = substr ($v, $pos + 2, 5);
			if ( preg_match ("/([0-9a-z ]{1,3})\r\n/", $chunkeds, $matches) ) {
				self::$sess->length[$key] += self::chunked_length ($matches[1]);
				self::$sess->data[$key] .= substr ($v, 0, $pos);
				$v = substr ($v, $pos + strlen ($matches[1]) + 4);
				continue;
			}

			self::$sess->data[$key] .= substr ($v, 0, $pos);
			$v = substr ($v, $pos + 2);
		}

		self::$sess->data[$key] .= $v;
	}
	// }}}
}

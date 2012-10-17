<?php
/**
 * sThread HTTP module
 *
 * HTTP protocol을 테스트 한다. (https는 지원하지 않는다.)
 *
 * 점검시에, 반환값이 200이 아닐경우 실패로 결과를 보내며, response
 * header의 content-length와 실제 받은 데이터 사이즈가 동일해야 정상
 * 처리로 판단을 한다.
 *
 * chunked 전송의 경우 chuned된 데이터를 파싱을 해서 사이즈가 맞는지
 * 비교를 한다.
 *
 * <b>* 경고!</b>
 * 4KB 이하의 문서에 사용하는 것을 권장한다. 만약 100K가 넘는 문서라면
 * event_buffer_read 크기를 40960 정도로 증가 하는 것이 좋다.
 *
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

/**
 * sThread HTTP module
 *
 * HTTP protocol을 테스트 한다. (https는 지원하지 않는다.)
 *
 * 점검시에, 반환값이 200이 아닐경우 실패로 결과를 보내며, response
 * header의 content-length와 실제 받은 데이터 사이즈가 동일해야 정상
 * 처리로 판단을 한다.
 *
 * chunked 전송의 경우 chuned된 데이터를 파싱을 해서 사이즈가 맞는지
 * 비교를 한다.
 *
 * HTTP 모듈에 사용할 수 있는 모듈 option은 다음과 같다.
 *
 * <ul>
 *     <ol>uri:</ol>   URI
 *     <ol>host:</ol>  HTTP/1.1 Host header 값
 *     <ol>agent:</ol> User Agent 값
 * </ul>
 *
 * 예제:
 * <code>
 *   sThread::execute ('domain.com:80:http|uri=>/robot.txt,host=>www.domaing.com', 2, 'tcp');
 * </code>
 *
 * <b>* 경고!</b>
 * 4KB 이하의 문서에 사용하는 것을 권장한다. 만약 100K가 넘는 문서라면
 * event_buffer_read 크기를 40960 정도로 증가 하는 것이 좋다.
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_HTTP {
	// {{{ Base properties
	/**#@+
	 * @access public
	 */
	/**
	 * 이 변수의 값이 true로 셋팅이 되면, clear_session
	 * method를 만들어 줘야 한다. 반대로 false 상태에서는
	 * clear_session method가 존재하지 않아도 상관이 없다.
	 *
	 * @var bool
	 */
	static public $clearsession = true;
	/**
	 * HTTP 모듈이 사용하는 기본 포트 번호
	 * @var int
	 */
	static public $port = 80;

	const HTTP_REQUEST  = 1;
	const HTTP_RESPONSE = 2;
	const HTTP_CLOSE    = 3;
	/**#@-*/
	// }}}

	// {{{ Per module properties
	/**#@+
	 * @access private
	 */
	static private $uri  = '/robots.txt';
	//static private $uri   = '/index.php';
	static private $agent = 'pear.oops.org::sThread HTTP module';
	static private $sess;
	/**#@-*/
	// }}}

	// {{{ (void) __construct (void)
	/**
	 * Class OOP 형식 초기화 메소드
	 * @access public
	 * @return object
	 */
	function __construct () {
		self::init ();

		$this->clearsession = &self::$clearsession;
		$this->port  = &self::$port;
		$this->uri   = &self::$uri;
		$this->agent = &self::$agent;
		$this->sess  = &self::$sess;
	}
	// }}}

	// {{{ (void) init (void)
	/**
	 * HTTP 모듈을 초기화 한다.
	 *
	 * @access public
	 * @return void
	 */
	function init () {
		self::$sess = (object) array (
			'returnCode' => array (),
			'chunked'    => array (),
			'length'     => array (),
			'header'     => array (),
			'data'       => array (),
		);
	}
	// }}}

	// {{{ (int) sThread_HTTP::check_buf_status ($status)
	/**
	 * 현재 상태가 event read 상태인지 event write 상태인지
	 * 를 판단한다.
	 *
	 * @access public
	 * @return int
	 * @param  int    현재 status
	 */
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
	/**
	 * 현재의 status(integer) 또는 현재 status의 handler 이름을
	 * 반환한다.
	 *
	 * @access public
	 * @return int|string
	 * @param  int        현재 status
	 * @param  boolean    true로 설정했을 경우 현재 status의 handler
	 *                    이름을 반환한다.
	 */
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
	/**
	 * 세션의 상태를 단계로 변경한다.
	 *
	 * @access public
	 * @param  boolean 변경한 상태가 마지막 단계일 경우 false를
	 *                 반환한다.
	 * @param  object  sThread 세션 변수 reference
	 * @param  int     세션 키
	 */
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::HTTP_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_HTTP::set_last_status (&$sess, $key)
	/**
	 * 세션의 상태를 마지막 단계로 변경한다.
	 *
	 * @access public
	 * @param  object sThread 세션 변수 reference
	 * @param  int    세션 키
	 */
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::HTTP_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_HTTP::clear_session ($key) {
	/**
	 * session에서 사용한 변수(self::$sess)의 값을 정리한다.
	 *
	 * self::$clearsession == false 일 경우, clear_session method
	 * 는 존재하지 않아도 된다.
	 *
	 * @access public
	 * @return void
	 * @param  int    세션 키
	 */
	function clear_session ($key) {
		$target = array (
			'returnCode', 'chunked', 'length', 'header', 'data',
		);

		foreach ( $target as $val ) {
			$dest = &self::$sess->$val;
			if ( isset ($dest[$key]) )
				unset ($dest[$key]);
		}
	}
	// }}}

	/*
	 * Handler 정의
	 *
	 * Handler는 call_status 메소드에 정의된 값들 중
	 * Vari::EVENT_UNKNOWN를 제외한 모든 status의 constant string을
	 * 소문자로해서 만들어야 한다.
	 *
	 * Handler 이름은 sThread_MODULE::call_status 메소드를
	 * 통해서 구할 수 있다.
	 *
	 * handler는 다음의 구조를 가지며, 실제로 전송을 하거나 받는
	 * 것은 libevent가 한다.
	 *
	 * write handler:
	 *       handler_name (&$ses, $key)
	 *
	 *       write handler는 실제로 전송을 하지 않고 전송할
	 *       데이터를 생성해서 반환만 한다.
	 *
	 * read handler:
	 *       handler_name (&$sess, $key, $recv) 
	 *
	 *       read handler의 반환값은 다음과 같이 지정을 해야 한다.
	 *
	 *       true  => 모든 전송이 완료
	 *       false => 전송 받을 것이 남아 있음
	 *       null  => 전송 중 에러 발생
	 *
	 *       이 의미는 sThread가 read handler에서 결과값에 따라
	 *       true는 다음 단계로 전환을 하고, false는 현 status를
	 *       유지하며, null의 경우 connection을 종료를 한다.
	 */

	// {{{ (void) sThread_HTTP::http_request (&$sess, $key)
	/**
	 * HTTP 요청 데이터를 반환
	 *
	 * @access public
	 * @return void
	 * @param  object 세션 object
	 * @param  int    세션 키
	 */
	function http_request (&$sess, $key) {
		list ($host, $port, $type) = $sess->addr[$key];
		$opt = $sess->opt[$key];

		$uri = isset ($opt->uri) ? $opt->uri : self::$uri;
		$hostHeader = isset ($opt->host) ? $opt->host : $host;
		$agent = isset ($opt->agent) ? $opt->agent : self::$agent;

		return "GET {$uri} HTTP/1.1\r\n" .
				"Host: {$hostHeader}\r\n" .
				"Accpt: *.*\r\n" .
				"User-Agent: {$agent}\r\n" .
				"Connection: close\r\n" .
				"\r\n";
	}
	// }}}

	// {{{ (boolean) sThread_HTTP::http_response (&$sess, $key, $recv)
	/**
	 * 서버의 응답을 확인
	 *
	 * @access public
	 * @return bool|null 결과 값은 다음과 같다.
	 *     <ul>
	 *         <li>true:  모든 전송이 완료</li>
	 *         <li>false: 전송할 것이 남아 있음. readcallback에서
	 *                    false를 받으면 status를 유지한다.</li>
	 *         <li>null:  전송 중 에러 발생</li>
	 *     </ul>
	 * @param  object 세션 object
	 * @param  int    세션 키
	 * @param  mixed  read callback에서 전송받은 누적 데이터
	 */
	function http_response (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		# remove extra option
		$type = preg_replace ('/\|.*/', '', $type);
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
	// {{{ private (void) sThread_HTTP::parse_header ($v)
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

	// {{{ private (int) sThread_HTTP::chunked_length ($v)
	private function chunked_length ($v) {
		return hexdec (trim ($v));
	}
	// }}}

	// {{{ private (void) sThread_HTTP::chunked_data ($key, $v)
	private function chunked_data ($key, $v) {
		if ( preg_match ("/^([0-9a-z][0-9a-z ]{0,3})\r\n(.*)$/s", $v, $matches) ) {
			self::$sess->length[$key] += self::chunked_length ($matches[1]);
			$v = $matches[2];
		}

		while ( ($pos = strpos ($v, "\r\n")) !== false ) {
			$chunkeds = substr ($v, $pos + 2, 6);
			if ( preg_match ("/^([0-9a-z][0-9a-z ]{0,3})\r\n/", $chunkeds, $matches) ) {
				self::$sess->length[$key] += self::chunked_length ($matches[1]);
				self::$sess->data[$key] .= substr ($v, 0, $pos);
				$v = substr ($v, $pos + strlen ($matches[1]) + 4);
				continue;
			}

			self::$sess->data[$key] .= substr ($v, 0, $pos + 2);
			$v = substr ($v, $pos + 2);
		}

		//self::$sess->data[$key] = preg_replace ("/([^\r]\n)\r\n$/", '\\1', self::$sess->data[$key]);
		self::$sess->data[$key] = preg_replace ("/\r\n$/", '', self::$sess->data[$key]);
		self::$sess->data[$key] .= $v;
	}
	// }}}
}

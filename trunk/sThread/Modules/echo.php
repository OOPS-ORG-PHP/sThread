<?php
/**
 * sThread ECHO module
 *
 * 에코 프로코톨을 검사.
 *
 * UDP protocol에 대한 검사는 정확성을 보장하지 못한다.
 *
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2014 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @filesource
 */

/**
 * ECHO module Class
 *
 * 에코 프로코톨을 검사.
 *
 * UDP protocol에 대한 검사는 정확성을 보장하지 못한다.
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2014 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_ECHO {
	// {{{ Base properteis
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
	static public $clearsession = false;
	/**
	 * ECHO 모듈이 사용하는 기본 포트 번호
	 * @var int
	 */
	static public $port = 7;

	const ECHO_REQUEST  = 1;
	const ECHO_RESPONSE = 2;
	const ECHO_CLOSE    = 3;
	/**#@-*/
	// }}}

	// {{{ (void) sThread_ECHO::__construct (void)
	/**
	 * Class OOP 형식 초기화 메소드
	 * @access public
	 * @return sThread_ECHO
	 */
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_ECHO::init (void)
	/**
	 * echo 모듈을 초기화 한다.
	 *
	 * @access public
	 * @return void
	 */
	function init () {
		self::$clearsession = false;
	}
	// }}}

	// {{{ (int) sThread_ECHO::check_buf_status ($status)
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
	/**
	 * 세션의 상태를 단계로 변경한다.
	 *
	 * @access public
	 * @param  boolean  변경한 상태가 마지막 단계일 경우 false를
	 *                  반환한다.
	 * @param  stdClass sThread 세션 변수 reference
	 * @param  int      세션 키
	 */
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::ECHO_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_ECHO::set_last_status (&$sess, $key)
	/**
	 * 세션의 상태를 마지막 단계로 변경한다.
	 *
	 * @access public
	 * @param  stdClass sThread 세션 변수 reference
	 * @param  int      세션 키
	 */
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::ECHO_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_ECHO::clear_session ($key) {
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
		return;
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

	// {{{ (string) sThread_ECHO::echo_request (&$sess, $key)
	/**
	 * 요청할 데이터 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function echo_request (&$sess, $key) {
		return "echo data\r\n";
	}
	// }}}

	// {{{ (boolean) sThread_ECHO::echo_response (&$sess, $key, $recv)
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
	 * @param  stdClass 세션 object
	 * @param  int    세션 키
	 * @param  mixed  read callback에서 전송받은 누적 데이터
	 */
	function echo_response (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;

		//if ( ! preg_match ('/data$/', $sess->recv[$key]) )
		if ( $sess->recv[$key] != "echo data\r\n" )
			return false;

		if ( Vari::$result === true )
			$sess->data[$key] = base64_encode (self::$sess->data[$key]);
		unset ($sess->recv[$key]);

		return true;
	}
	// }}}
}

?>

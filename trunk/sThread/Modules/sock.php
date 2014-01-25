<?php
/**
 * sThread SOCK module for port knocking
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
 * 포트 노킹을 위한 sThread SOCK 모듈
 *
 * 포트만 열었다가 종료한다.
 * 
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2014 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_SOCK {
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
	 * SOCK 모듈이 사용하는 기본 포트 번호
	 * @var int
	 */
	static public $port = 12345;

	const SOCK_REQUEST  = 1;
	const SOCK_CLOSE    = 2;
	/**#@-*/
	// }}}

	// {{{ (void) sThread_SOCK::__construct (void)
	/**
	 * Class OOP 형식 초기화 메소드
	 * @access public
	 * @return sThread_SOCK
	 */
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_SOCK::init (void)
	/**
	 * sock 모듈을 초기화 한다.
	 *
	 * @access public
	 * @return void
	 */
	function init () {
		self::$clearsession = false;
	}
	// }}}

	// {{{ (int) sThread_SOCK::check_buf_status ($status)
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

	// {{{ (bool) sThread_SOCK::change_status (&$sess, $key)
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

		if ( $sess->status[$key] === self::SOCK_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_SOCK::set_last_status (&$sess, $key)
	/**
	 * 세션의 상태를 마지막 단계로 변경한다.
	 *
	 * @access public
	 * @param  stdClass sThread 세션 변수 reference
	 * @param  int      세션 키
	 */
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::SOCK_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_SOCK::clear_session ($key) {
	/**
	 * session에서 사용한 변수(self::$sess)의 값을 정리한다.
	 *
	 * self::$clearsession == false 일 경우, clear_session method
	 * 는 존재하지 않아도 된다.
	 *
	 * @access public
	 * @return void
	 * @param  int     세션 키
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

	// {{{ (string) sThread_SOCK::sock_request (&$sess, $key)
	/**
	 * 요청할 문자를 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function sock_request (&$sess, $key) {
		return "\r\n";
	}
	// }}}
}

?>

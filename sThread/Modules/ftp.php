<?php
/**
 * sThread FTP module
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
 * FTP module Class
 * 
 * 이 모듈은 로그인 인증까지만 테스트 한다. 데이터 커넥션
 * 부분은 구현이 되어 있지 않다. 역시 ssl은 지원하지 않는다.
 *
 * FTP 모듈에 사용할 수 있는 모듈 option은 다음과 같다.
 *
 * <ul>
 *     <li><b>user:</b> 로그인 유저</li>
 *     <li><b>pass:</b> 로그인 암호</li>
 * </ul>
 *
 * 예제:
 * <code>
 *   sThread::execute ('domain.com:21:ftp|user=>username,pass=password', 2, 'udp');
 * </code>
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2014 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_FTP {
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
	static public $clearsession;
	/**
	 * FTP 모듈이 사용하는 protocol
	 * @var string
	 */
	static public $protocol;
	/**
	 * FTP 모듈이 사용하는 기본 포트 번호
	 * @var int
	 */
	static public $port;

	const FTP_BANNER      = 1;
	const FTP_SENDUSER    = 2;
	const FTP_USERBANNER  = 3;
	const FTP_SENDPASS    = 4;
	const FTP_COMFIRMAUTH = 5;
	/**
	 * 에러가 발생했을 경우, FTP_QUIT 메소드가 정의가 되어있으면,
	 * parent::socketColose에 의해서 자동으로 FTP_QUIT이 호출이
	 * 된다.
	 */
	const FTP_QUIT        = 6;
	const FTP_CLOSE       = 7;
	/**#@-*/
	// }}}

	// {{{ (void) sThread_FTP::__construct (void)
	/**
	 * Class OOP 형식 초기화 메소드
	 * @access public
	 * @return sThread_FTP
	 */
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
		$this->protocol     = &self::$protocol;
	}
	// }}}

	// {{{ (void) sThread_FTP::init (void)
	/**
	 * FTP 모듈을 초기화 한다.
	 *
	 * @access public
	 * @return void
	 */
	function init () {
		self::$clearsession = false;
		self::$port         = 21;
		self::$protocol     = 'tcp';
	}
	// }}}

	// {{{ (int) sThread_FTP::check_buf_status ($status)
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

		if ( $sess->status[$key] === self::FTP_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_FTP::set_last_status (&$sess, $key)
	/**
	 * 세션의 상태를 마지막 단계로 변경한다.
	 *
	 * @access public
	 * @param  stdClass sThread 세션 변수 reference
	 * @param  int      세션 키
	 */
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::FTP_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_FTP::clear_session ($key) {
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

	// {{{ (boolean) sThread_FTP::ftp_banner (&$sess, $key, $recv)
	/**
	 * 전송 받은 서버 배너 확인
	 *
	 * @access public
	 * @return bool|null
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 * @param  mixed  read callback에서 전송받은 누적 데이터
	 */
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
	/**
	 * 전송할 유저 정보 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdCLass 세션 object
	 * @param  int      세션 키
	 */
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
	/**
	 * 유저 정보 전송 결과 확인
	 *
	 * @access public
	 * @return bool|null
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 * @param  mixed  read callback에서 전송받은 누적 데이터
	 */
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
	/**
	 * 인증 정보 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function ftp_sendpass (&$ses, $key) {
		return 'Pass ' . $ses->opt[$key]->pass . "\r\n";
	} // }}}

	// {{{ (boolean) function ftp_comfirmauth (&$sess, $key, $recv)
	/**
	 * 인증 결과 확인
	 *
	 * @access public
	 * @return bool|null
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 * @param  mixed  read callback에서 전송받은 누적 데이터
	 */
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
	/**
	 * 종료 명령 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function ftp_quit (&$sess, $key) {
		return "quit\r\n";
	}
	// }}}
}

?>

<?php
/**
 * Common Variables of Single Thread Monitoring<br>
 * File: sThread/Vari.php
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @filesource
 */

/**
 * sThread package에서 사용하는 공통 변수와 변수 관련
 * 메소드를 제공
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class Vari {
	// {{{ properties
	/**#@+
	 * @access public
	 */
	/**
	 * 세션 관련 정보 저장
	 * @var    object
	 */
	static public $sess;
	/**
	 * 옵션값 저장
	 * @var    object
	 */
	static public $opt;
	/**
	 * 처리 결과값을 저장
	 * @var    object
	 */
	static public $res;
	/**
	 * 처리 시간 정보
	 * @var    object
	 */
	static public $time;
	/**#@-*/

	const DEBUG1 = 1;
	const DEBUG2 = 2;
	const DEBUG3 = 3;

	//const EVENT_CONNECT     = 0;
	const EVENT_ERROR_CLOSE = -1;
	const EVENT_READY_SEND  = 0;
	const EVENT_READY_RECV  = 1;
	const EVENT_SEND_DONE   = 1;
	const EVENT_READY_CLOSE = 1000;
	const EVENT_UNKNOWN     = false;
	// }}}

	// {{{ (void) Vari::clear (void)
	function clear () {
		$res = array ('total', 'success', 'failure', 'status');
		$sess = array ('addr', 'opt', 'sock', 'status', 'event', 'recv', 'send', 'ctime', 'ptime');
		$time = array ('cstart', 'cend', 'pstart', 'pend');

		foreach ( $res as $key )
			if ( isset (self::$res->$key) )
				unset (self::$res->$key);

		foreach ( $sess as $key )
			if ( isset (self::$sess->$key) )
				unset (self::$sess->$key);

		foreach ( $time as $key )
			if ( isset (self::$time->$key) )
				unset (self::$time->$key);

		Vari::$res = (object) array (
			'total'   => 0,
			'success' => 0,
			'failure' => 0,
			'status'  => array ()
		);

		Vari::$sess = (object) array (
			'addr'   => array (),
			'opt'    => array (),
			'sock'   => array (),
			'status' => array (),
			'event'  => array (),
			'recv'   => array (), // recieve data buffer
			'send'   => array (), // socket write complete flag. set 1, complete
			'ctime'  => array (), // Connection time. It can't believe! :-(
			'ptime'  => array (), // Processing time
		);

		Vari::$time = (object) array (
			'cstart' => array (),
			'cend'   => array (),
			'pstart' => array (),
			'pend'   => array ()
		);
	}
	// }}}

	// {{{ (void) Vari::objectCopy (&$r, $obj)
	/**
	 * 2번째 파라미터에 주어진 값을 다른 메모리 주소에
	 * cpoy하여 1번째 변수에 할당한다.
	 *
	 * @access public
	 * @return void
	 * @param  mixed 빈 변수
	 * @param  mixed 원본 변수
	 */
	function objectCopy (&$r, $obj) {
		switch (($type = gettype ($obj))) {
			case 'object' :
			case 'array' :
				foreach ( $obj as $key => $val ) {
					switch (($subtype = gettype ($val))) {
						case 'array' :
						case 'object' :
							if ( $type == 'array' ) {
								$r[$key] = null;
								self::objectCopy ($r[$key], $val);
							} else {
								$r->$key = null;
								self::objectCopy ($r->$key, $val);
							}
							break;
						default:
							if ( $type == 'array' )
								$r[$key] = self::varCopy ($val);
							else
								$r->$key = self::varCopy ($val);
					}
				}
				break;
			default :
				$r = self::varCopy ($val);
		}
	}
	// }}}

	// {{{ (mixed) Vari::varCopy ($var)
	/**
	 * 입력된 값을 다른 주소의 메모리에 copy하여 반환
	 *
	 * @access public
	 * @return mixed
	 * @param  mixed
	 */
	function varCopy ($var) {
		return $var;
	}
	// }}}

	// {{{ (string) Vari::chkTime ($o, $n)
	/**
	 * 두 microtime 의 차이를 계산
	 *
	 * @access public
	 * @return string
	 * @param  string 먼저 측정된 microtime() 의 결과값
	 * @param  string 나중에 측정된 microtime() 의 결과값
	 * @param  int    보정할 millisencond 값
	 */
	function chkTime ($o, $n, $t = 0) {
		$o = self::hmicrotime ($o);
		$n = self::hmicrotime ($n);

		if ( $t > 0 )
			$t /= 1000;
		else if ( $t < 0 ) {
			$t *= -1;
			$t /= 1000000;
			$t *= -1;
		}

		$r = $n - $o + $t;;

		#echo "############## $o\n";
		#echo "############## $n\n";
		#echo "############## $t\n";
		#echo "############## $r\n";

		if ( $r > 1 )
			return sprintf ('%.3f sec', $r);

		return sprintf ('%.3f msec', $r * 1000);
	}
	// }}}

	/*
	 * Private methods.
	 */

	// {{{ private (float) Vari::hmicrotime ($t)
	/**
	 * microtime 의 결과값을 sec로 보정
	 *
	 * @access private
	 * @return float
	 * @param  string microtime() 의 결과 값
	 */
	private function hmicrotime ($t) {
		if ( preg_match ('/^([^ ]+)[ \t](.+)/', $t, $matches) )
			return (float) $matches[2] + (float) $matches[1];
		return $t;
	}
	// }}}
}
?>

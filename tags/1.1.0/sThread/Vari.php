<?php
/**
 * Common Variables of Single Thread Monitoring<br>
 * File: sThread/Vari.php
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015 OOPS.ORG
 * @license     BSD License
 * @version     $Id: Vari.php 95 2015-02-17 06:35:51Z oops $
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
 * @copyright   (c) 2015 OOPS.ORG
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
	 * @var    stdClass
	 */
	static public $sess;
	/**
	 * 옵션값 저장
	 * @var    stdClass
	 */
	static public $opt;
	/**
	 * 처리 결과값을 저장
	 * @var    stdClass
	 */
	static public $res;
	/**
	 * 처리 시간 정보
	 * @var    stdClass
	 */
	static public $time;
	/**
	 * Action의 결과를 반환 받을 것인지 여부
	 * @var    boolean
	 *
	 */
	static public $result;
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

	// {{{ (void) Vari::clear ($b = false)
	/**
	 * @access public
	 * @return void
	 * @param  bool   (optional) true로 설정하면 필요없는 member만
	 *                정리한다. false의 경우, Vari Class의 모든
	 *                멤버를 초기화 한다. 기본값 false
	 */
	static function clear ($b = false) {
		if ( $b !== true )
			self::objectUnset (self::$res);
		self::objectUnset (self::$sess);
		self::objectUnset (self::$time);

		if ( $b !== true ) {
			Vari::$res = (object) array (
				'total'   => 0,
				'success' => 0,
				'failure' => 0,
				'status'  => array ()
			);
		}

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
			'time'   => array ()  // Total time
		);

		Vari::$time = (object) array (
			'cstart' => array (),
			'cend'   => array (),
			'pstart' => array (),
			'pend'   => array ()
		);
	}
	// }}}

	// {{{ (void) Vari::objectUnset (&$r)
	/**
	 * 주어진 파라미터가 object 또는 array일 경우 소속
	 * 멤버들을 unset 시킨다.
	 *
	 * 자기 자신을 unset 하지는 못하므로, 이 함수 호출 후에
	 * 직접 unset을 해 줘야 한다.
	 *
	 * @access public
	 * @return void
	 * @param  mixed unset할 array 또는 object
	 */
	static function objectUnset (&$r) {
		switch (($type = gettype ($r))) {
			case 'object' :
			case 'array' :
				foreach ( $r as $key => $val ) {
					switch (($subtype = gettype ($val))) {
						case 'array' :
						case 'object' :
							if ( $type == 'array' ) {
								self::objectUnset ($r[$key]);
								unset ($r[$key]);
							} else {
								self::objectUnset ($r->$key);
								unset ($r->$key);
							}
							break;
						default:
							if ( $type == 'array' )
								unset ($r[$key]);
							else
								unset ($r->$key);
					}
				}
				break;
		}
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
	static function chkTime ($o, $n, $t = 0) {
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

	// {{{ (string) Vari::timeCalc ($a)
	/**
	 * 배열로 주어진 시간의 합을 반환
	 *
	 * @access public
	 * @return string
	 * @param  array
	 */
	static function timeCalc ($a) {
		if ( ! is_array ($a) )
			return '0 msec';

		foreach ( $a as $t ) {
			if ( preg_match ('/(.*)[\s]+msec/', $t, $matches) )
				$time += $matches[1] * 1000;
			else
				$time += $matches[1] * 1000000;

		}

		if ( $time >= 1000000 ) {
			$unit = 'sec';
			$div = 1000000;
		} else {
			$unit = 'msec';
			$div = 1000;
		}

		return sprintf ('%.3f %s', $time / $div, $unit);
	}
	// }}}

	// {{{ (string) Vari::binaryDecode ($bin, $ret = false)
	/**
	 * binary data를 hex data로 변환
	 *
	 * @access public
	 * @return void|string
	 * @param  string binary data
	 * @param  boot   (optional) true로 설정하면 결과값을 반환한다.
	 *                기본값을 false로 바로 출력을 한다.
	 */
	function binaryDecode (&$bin, $ret = false) {
		$len = strlen ($bin);
		for ( $i=0; $i<$len; $i++ ) {
			if ( ($i % 8) == 0 )
				$r .= ( ($i % 16) == 0 ) ? "\n" : ' ';

			$r .= sprintf ("%02x ", ord ($bin[$i]));
		}

		if ( ! $ret )
			echo $r . "\n";

		$bin = $r;
	}
	// }}}

	// {{{ (string) Vari::objectInit (&$obj)
	/**
	 * 주어진 변수가 empty이거나 object가 아닐경우
	 * standard object로 선언한다.
	 *
	 * @access public
	 * @return void
	 * @param  mixed
	 */
	static function objectInit (&$obj) {
		if ( empty ($obj) || ! is_object ($obj) )
			$obj = new stdClass;
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
	static private function hmicrotime ($t) {
		if ( preg_match ('/^([^ ]+)[ \t](.+)/', $t, $matches) )
			return (float) $matches[2] + (float) $matches[1];
		return $t;
	}
	// }}}
}
?>

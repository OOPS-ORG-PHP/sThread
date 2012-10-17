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
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @filesource
 */
Class Vari {
	// {{{ properties
	/**
	 * 세션 관련 정보 저장
	 *
	 * @access public
	 * @var    object
	 */
	static public $sess;
	/**
	 * 옵션값 저장
	 *
	 * @access public
	 * @var    object
	 */
	static public $opt;
	/**
	 * 처리 결과값을 저장
	 *
	 * @access public
	 * @var    object
	 */
	static public $res;

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
}
?>

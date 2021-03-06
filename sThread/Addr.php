<?php
/**
 * Parsing API of Host Address format<br>
 * File: sThread/Addr.php
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @filesource
 */

/**
 * include ePrint package
 */
require_once 'ePrint.php';

/**
 * Host 주소 형식 파싱 API
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_Address {
	// {{{ (string) sThread_Address::parse ($buf)
	/**
	 * sThread로 전달된 주소및 옵션의 유효성 검증 및 파싱
	 *
	 * @access public
	 * @return string
	 * @param  string
	 */
	static function parse ($buf) {
		$extra = '';
		if ( preg_match ('/^(.+)\|(.+)$/', $buf, $matches) ) {
			$buf = $matches[1];
			$extra = $matches[2];
		}
		$v = explode (':', $buf);
		$size = count ($v);

		if ( $size < 2 || $size > 3 ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 )
				ePrint::ePrintf ('Error: Argument format is must \'host:port[:module]\' : ' . $buf);
			return false;
		}

		$new_array[0] = $v[0];
		switch ($size) {
			case 3 :
				// 포트가 마지막으로 올 경우, 포트와 모듈의 자리 변경
				if ( is_numeric ($v[1]) ) {
					$new_array[1] = $v[1];
					$new_array[2] = $v[2];
				} else {
					$new_array[1] = $v[2];
					$new_array[2] = $v[1];
				}

				// 포트는 정확하고, 모듈 이름이 잘못되었을 경우
				// 보정해 주는 것이 맞을까??
				//if ( ! sThread_Module::port ($new_array[2]) )
				//	$new_array[2] = sThread_Module::type ($new_array[1]);
				break;
			case 2 :
				if ( is_numeric (trim ($v[1])) ) {
					// 포트가 주어졌을 경우 , 포트를 이용하여 어떤
					// 모듈인지를 확인
					$new_array[1] = $v[1];
					$new_array[2] = sThread_Module::type ($v[1]);
				} else {
					// 포트가 주어지지 않았을 경우, 해당 모듈의 포트를
					// 사용
					$new_array[1] = sThread_Module::port ($v[1]);
					$new_array[2] = $v[1];
				}
				break;
		}

		// 모듈이 지원되지 않는 형식이거나, 포트를 알 수 없을 경우, 에러
		if ( ! $new_array[1] || ! $new_array[2] ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 ) {
				if ( ! $new_array[1] && $new_array[2] )
					ePrint::ePrintf ('Error: %s Unsupport module %s', array ($new_array[0], $new_array[2]));
				else if ( $new_array[1] && ! $new_array[2] )
					ePrint::ePrintf ('Error: %s Unsupport port %d', array ($new_array[0], $new_array[1]));
				else
					ePrint::ePrintf ('Error: Argument format is must \'host:port[:module]\'');
			}
			return false;
		}

		// 모듈을 지원하지 않는 경우 에러
		if ( ! sThread_Module::port ($new_array[2]) ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 )
				ePrint::ePrintf ('Error: Can\'t find %s module', $new_array[2]);
			return false;
		}

		$r = join (':', $new_array);
		if ( $extra )
			$r .= '|' . $extra;

		return $r;
	} //  }}}

	// {{{ (object) sThread_Address::extraOption (&$type)
	/**
	 * sThread로 주어진 주소값에서 옵션을 분리
	 *
	 * @access public
	 * @return stdClass
	 * @param  string
	 */
	static function extraOption (&$type) {
		if ( ! preg_match ('/^(.+)\|(.+)$/', $type, $matches) )
			return false;

		$type = $matches[1];
		$buf = explode (',', $matches[2]);

		$r = new stdClass;
		foreach ( $buf as $val ) {
			if ( ! preg_match ('/^(.+)=>(.+)/', $val, $matches) )
				continue;

			$key = trim ($matches[1]);
			$r->$key = trim ($matches[2]);
		}

		return $r;
	} // }}}
}
?>

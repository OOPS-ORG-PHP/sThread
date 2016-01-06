<?php
/**
 * Logging Class of Single Thread Monitoring<br>
 * File: sThread/Log.php
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
 * sThread Log Class
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_Log {
	// {{{ properties
	/**
	 * Log 파일 이름
	 * @access public
	 * @var    string
	 */
	static public $fname;
	/**
	 * Log 파일 postfix
	 *
	 * @access public
	 * @var    stirng
	 */
	static public $format = 'Ymd';
	/**
	 * 로그 저장 동작 여부
	 * <ul>
	 *     <li>0  -> 실패한 로그만 저장</li>
	 *     <li>1  -> 모든 로그 저장</li>
	 *     <li>-1 -> 로그 저장 안함</li>
	 * </ul>
	 *
	 * @access public
	 * @var    int
	 */
	static public $type = 0;
	// }}}

	// {{{ (void) sThread_Log::save ($key, $recv)
	/**
	 * 로그를 파일에 저장
	 *
	 * @access public
	 * @return void
	 * @param  int    세션 키
	 * @param  string 로그 내용
	 */
	static function save ($key, $recv) {
		$res = &Vari::$res->status[$key];

		// no logging
		if ( self::$type < 0 )
			return;

		if ( self::$type != 1 && $res[1] === true )
			return;

		if ( ! trim (self::$fname) )
			return;

		$format = date ('Ym');
		$logdate = date ('Ymd H:i:s');

		$logfile = self::$fname . '.' . $format;

		$logdir = dirname ($logfile);
		if ( ! is_dir ($logdir) || ! is_writeable ($logfile) )
			ePrint::dPrintf (Vari::DEBUG1, "Error: Can't write %n", $logfile);

		$log = sprintf ("[%s] %s %s\n", $logdate, $res[0], $res[2]);
		file_put_contents ($logfile, $log, FILE_APPEND);

		if ( $res[1] === false && $recv ) {
			ob_start ();
			ePrint::echoi ($recv, 4);
			$err = ob_get_contents ();
			ob_end_clean ();
			file_put_contents ($logfile, $err, FILE_APPEND);
		}
	}
	// }}}
}
?>

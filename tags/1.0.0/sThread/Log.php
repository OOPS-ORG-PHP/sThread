<?php
/**
 *
 * Logging Class of Single Thread Monitoring<br>
 * File: sThread/Log.php
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
Class sThread_Log {
	// {{{ properties
	static public $fname;
	static public $format = 'Ymd';

	/*
	 * type 0  -> only falied logging
	 *      1  -> all logging
	 *      -1 -> no logging
	 */
	static public $type = 0;
	// }}}

	function save ($key, $recv) {
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
}
?>
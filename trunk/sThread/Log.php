<?php
/**
 *
 * Logging Class of Single Thread Monitoring
 * File: sThread/Log.php
 *
 * Copyright (c) 1997-2009 JoungKyun.Kim
 *
 * LICENSE: BSD license
 *
 * @category    Network
 * @package     sThread
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2009 OOPS.ORG
 * @license     BSD License
 * @version     CVS: $Id: Log.php,v 1.1 2009-10-14 08:17:44 oops Exp $
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_Log {
	// {{{ properties
	static public $fname;
	static public $format = 'Ymd';

	/*
	 * type 0 -> only falied log
	 *      1 -> all log
	 */
	static public $type = 0;
	// }}}

	function save ($key, $recv) {
		$res = &Vari::$res->status[$key];

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
			ob_end_flush ();
			file_put_contents ($logfile, $err, FILE_APPEND);
		}
	}
}
?>

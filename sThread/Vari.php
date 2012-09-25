<?php
/**
 *
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
Class Vari {
	// {{{ properties
	static public $sess;
	static public $opt;
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
}
?>

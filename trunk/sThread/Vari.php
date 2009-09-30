<?php
/**
 *
 * Common Variables of Single Thread Monitoring
 * File: sThread/Vari.php
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
 * @version     CVS: $Id: Vari.php,v 1.3 2009-09-30 18:19:37 oops Exp $
 * @link        http://pear.oops.org/package/sThread
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

<?php
error_reporting (E_ALL & ~E_NOTICE);
/*
 * For over PHP 5.3
 */
if ( version_compare (PHP_VERSION, '5.3.0') >= 0 ) {
	//error_reporting (E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	date_default_timezone_set ('Asia/Seoul');
}

if ( ! extension_loaded ('libevent') ) {
	dl ('libevent.so');
}

require_once 'sThread.php';

$s = new sThread;
$s->async = false;
ePrint::$debugLevel = 0;

/*
 * Host Format
 * SERVER:PORT:MODULE
 * SERVER:PORT
 * SERVER:PORT:MODULE|EXTRA_OPTION
 * SERVER:PORT|EXTRA_OPTION
 *
 * Extra Option Format
 * var1=>val1,var2=>val2,var3=>val3...
 */

$host = 'test.domain.com:80';
/*
 * sThread::execute ('host:port', timeout);
 */
$s->execute ($host, 1);
print_r (Vari::$res);

unset ($host);
$host = array (
	'test.domain;.com:80', /* use http module */
	'test10.domain.com:227', /* need port 227 module */
	'test11.domain.com:21', /* need port 21 module */
	'test111.domain.com:80,http|uri=>/index.jsp', /* use http module with httpd option */
);

$s->excute ($host, 1);
print_r (Vari::$res);
?>
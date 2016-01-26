<?php
if ( version_compare (PHP_VERSION, '5.4.0', '>=') )
	error_reporting (E_ALL & ~E_NOTICE & ~E_STRICT);
else {
	error_reporting (E_ALL & ~E_NOTICE);
	if ( ! extension_loaded ('libevent') ) {
		dl ('libevent.so');
	}
}

/*
 * For over PHP 5.3
 */
if ( version_compare (PHP_VERSION, '5.3.0', '>=') ) {
	//error_reporting (E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	date_default_timezone_set ('Asia/Seoul');
}

/*
 * for libevent2 warning messages
 */
fclose (STDERR);
$STDERR = fopen('/dev/dull', 'wb');

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

$host = 'chosun.com:80';
/*
 * sThread::execute ('host:port', timeout);
 */
$s->execute ($host, 1);
print_r (Vari::$res);

unset ($host);
$host = array (
	'google.com:80', /* use http module */
	'ftp.kaist.ac.kr:21|user=>anonymous,pass=>user@domain.com', /* need port 21 module */
	'chosun.com:80:http|uri=>/robots.txt', /* use http module with httpd option */
	'daum.net:11211', /* use memcache module */
);

$s->execute ($host, 1);
print_r (Vari::$res);

Vari::clear ();
unset ($host);
$host = 'google-public-dns-a.google.com:53|query=>kldp.org';
$s->execute ($host, 1, 'udp');
print_r (Vari::$res);

?>

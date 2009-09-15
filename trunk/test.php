<?php
if ( ! extension_loaded ('libevent') ) {
	dl ('libevent.so');
}

$s = new sThread;

$host = 'test.domain.com:80';
/*
 * sThread::execute ('host:port', timeout);
 */
$s->execute ($host, 1);
print_r (Vari::$res);

unset ($host);
$host = array (
	'test.domain;.com:80',
	'test10.domain.com:227',
	'test11.domain.com:21',
);

$s->excute ($host, 1);
print_r (Vari::$res);
?>

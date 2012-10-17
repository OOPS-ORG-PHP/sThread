<?php

require_once 'ePrint.php';
require_once 'sThread/Vari.php';
require_once 'sThread/Module.php';
require_once 'sThread/Addr.php';

function readCallback ($buf, $arg) {
	return sThread::readCallback ($buf, $arg);
}

function writeCallback ($buf, $arg) {
	return sThread::writeCallback ($buf, $arg);
}

function exceptionCallback ($buf, $arg) { }

Class sThread {
	const MAJOR_VERSION = 0;
	const MINOR_VERSION = 0;
	const PATCH_VERSION = 1;
	const VERSION = '0.0.1';

	static public $mod;

	function __construct () {
		self::init ();
		$this->mod     = &self::$mod;
	}

	function init () {
		Vari::$res = (object) array (
			'total'   => 0,
			'success' => 0,
			'failure' => 0,
			'status'  => array ()
		);

		Vari::$sess = (object) array (
			'addr'   => array (),
			'sock'   => array (),
			'status' => array (),
			'event'  => array (),
			'recv'   => array (), // recieve data buffer
			'send'   => array (), // socket write complete flag. set 1, complete
		);

		Module::init ();
		self::$mod = &Module::$obj;
	}

	function execute ($hosts, $tmout = 1) {
		if ( ! is_array ($hosts) )
			$hosts = array ($hosts);

		$sess = &Vari::$sess;
		$res  = &Vari::$res;

		$base = event_base_new ();

		$key = 0;
		foreach ( $hosts as $line ) {
			$res->total++;
			$line = rtrim ($line);
			if ( ($newline = Address::parse ($line, $key)) === false ) {
				list ($host, $port) = explode (':', $line);
				$res->failure++;
				$res->status[$key] = array ("{$host}:{$port}", false, "Address parsing error");
				$key++;
				continue;
			}
			$sess->addr[$key] = explode (':', $newline);
			list ($host, $port, $type) = explode (':', $newline);
			$addr = "tcp://{$host}:{$port}";

			$sess->sock[$key] = @stream_socket_client ($addr, $errno, $errstr, $tmout);
			usleep (100);

			if ( ! is_resource ($sess->sock[$key]) ) {
				if ( ePrint::$debugLevel >= Vari::DEBUG1 )
					ePrint::ePrintf ("%s:%d (%d) Failed socket create: %s",
								array ($host, $port, $key, $errstr));
				$res->failure++;
				$res->status[$key] = array ("{$host}:{$port}", false, "Failed socket create: {$errstr}");
				$key++;
				continue;
			}

			ePrint::dPrintf (Vari::DEBUG1, "[%-15s] %s:%d (%d) Socket create success\n",
						$sess->sock[$key], $host, $port, $key);

			$sess->status[$key] = 1;
			$sess->send[$key] = Vari::EVENT_READY_SEND;
			stream_set_timeout ($sess->sock[$key], $tmout, 0);
			stream_set_blocking ($sess->sock[$key], 0);

			$sess->event[$key] = event_buffer_new (
					$sess->sock[$key],
					"readCallback",
					"writeCallback",
					"exceptionCallback", array ($key)
			);
			event_buffer_timeout_set ($sess->event[$key], $tmout, $tmout);
			event_buffer_base_set ($sess->event[$key], $base);

			if ( self::currentStatus ($key) === Vari::EVENT_READY_RECV )
				event_buffer_enable ($sess->event[$key], EV_READ);
			else
				event_buffer_enable ($sess->event[$key], EV_WRITE);

			$key++;
		}

		event_base_loop ($base);
		self::clearEvent ();
		event_base_free ($base);
	}

	function readCallback ($buf, $arg) {
		list ($key) = $arg;
		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		list ($host, $port, $type) = $sess->addr[$key];

		ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Read Callback\n",
				$sess->sock[$key], $host, $port);

		if ( self::currentStatus ($key) !== Vari::EVENT_READY_RECV )
			return true;

		if ( ($handler = self::getCallname ($key)) === false ) {
			fclose ($sess->sock[$key]);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Recieve %s call\n",
					$sess->sock[$key], $host, $port, $handler);

		$buffer = event_buffer_read ($buf, 4096);
		ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Recieved data\n",
				$sess->sock[$key], $host, $port);
		if ( ePrint::$debugLevel >= Vari::DEBUG3 ) {
			$msg = rtrim ($buffer);
			ePrint::echoi ($msg ? $msg . "\n" : "NONE\n", 8);
		}

		$recvR = self::$mod->$type->$handler ($sess, $key, $buffer);

		// {{{ 전송이 완료되지 않은 경우
		if ( $recvR !== true ) {
			// protocol level error
			if ( $recvR === null ) {
				$res->failure++;
				$res->status[$key] = array ("{$host}:{$port}", false, 'Protocol error');

				fclose ($sess->sock[$key]);
				event_buffer_free ($sess->event[$key]);
				return true;
			}
		}
		// }}}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Complete %s call\n",
					$sess->sock[$key], $host, $port, $handler);
	
		/*
		 * Unknown status or Regular connection end
		 */
		if ( ($is_rw = self::nextStatus ($key)) === false ) {
			fclose ($sess->sock[$key]);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		return true;
	}

	function writeCallback ($buf, $arg) {
		list ($key) = $arg;
		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		list ($host, $port, $type) = $sess->addr[$key];

		if ( $sess->send[$key] === Vari::EVENT_SEND_DONE ) {
			$sess->send[$key] = Vari::EVENT_READY_SEND;
			/*
			 * Unknown status or Regular connection end
			 */
			if ( ($is_rw = self::nextStatus ($key)) === false ) {
				fclose ($sess->sock[$key]);
				event_buffer_free ($sess->event[$key]);
			}
			return true;
		}

		ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Write Callback\n",
				$sess->sock[$key], $host, $port);

		if ( self::currentStatus ($key) !== Vari::EVENT_READY_SEND )
			return true;


		if ( ($handler = self::getCallname ($key)) === false ) {
			fclose ($sess->sock[$key]);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Send %s call\n",
					$sess->sock[$key], $host, $port, $handler);

		$send = self::$mod->$type->$handler ($sess, $key);

		if ( event_buffer_write ($buf, $send, strlen ($send)) === false ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 )
				ePrint::ePrintf ("[%-15s] Error: %s:%d Send error",
					array ($sess->sock[$key], $host, $port));
			$res->failure++;
			$res->status[$key] = array ("{$host}:{$port}", false, "{$handler} Send error");
			fclose ($sess->sock[$key]);
			event_buffer_free ($sess->event[$key]);
			return true;
		}
		ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Send data\n", $sess->sock[$key], $host, $port);
		if ( ePrint::$debugLevel >= Vari::DEBUG3 ) {
			$msg = rtrim ($send);
			ePrint::echoi ($send . "\n", 8);
		}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Complete %s call\n",
					$sess->sock[$key], $host, $port, $handler);

		$sess->send[$key] = Vari::EVENT_SEND_DONE;
	}

	function getCallname ($key) {
		$sess = &Vari::$sess;
		list ($host, $port, $type) = $sess->addr[$key];
		$event = self::$mod->$type->call_status ($sess->status[$key], true);
		if ( $event === false ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 )
				ePrint::ePrintf ("[%-15s] Error: %s 'Unknown status => %d",
					array ($sess->sock[$key], $sess->addr[$key], $sess->status[$key]));
			$res->failure++;
			$res->status[$key] = array ("{$host}:{$port}", false, "Unknown Status: {$sess->status[$key]}");
			return false;
		}

		return $event;
	}

	function currentStatus ($key, $next = false) {
		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		list ($host, $port, $type) = $sess->addr[$key];

		if ( $next !== false ) {
			self::$mod->$type->change_status ($sess, $key);
			$sess->recv[$key] = '';
		}
		$is_rw = self::$mod->$type->check_buf_status ($sess->status[$key]);

		/*
		 * $is_wr가 sThread::SHARED_READY_CLOSE 상태가 되면, 모든 작업이
		 * 완료된 case 이므로 socket을 닫는다. $is_rw 가 false일 경우,
		 * 알 수 없는 상태이므로 종료 한다.
		 */
		if ( $is_rw === Vari::EVENT_READY_CLOSE || $is_rw === false ) {
			if ( $is_rw === false ) {
				if ( ePrint::$debugLevel >= Vari::DEBUG1 )
					ePrint::ePrintf ("[%-15s] Error: %s 'Unknown status => %d",
						array ($sess->sock[$key], $sess->addr[$key], $sess->status[$key]));
				$res->failure++;
				$res->status[$key] = array ("{$host}:{$port}", false, "Unknown Status: {$sess->status[$key]}");
			} else {
				ePrint::dPrintf (Vari::DEBUG1, "[%-15s] %s:%d Socket close\n",
							$sess->sock[$key], $host, $port);
				$res->success++;
				$res->status[$key] = array ("{$host}:{$port}", true, 'Success');
			}

			return false;
		}

		if ( $next !== false ) {
			switch ($is_rw) {
				case Vari::EVENT_READY_SEND :
					ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Enable write event\n",
								$sess->sock[$key], $host, $port);
					event_buffer_enable ($sess->event[$key], EV_WRITE);
					event_buffer_disable ($sess->event[$key], EV_READ);
					break;
				default :
					ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Enable read event\n",
								$sess->sock[$key], $host, $port);
					event_buffer_enable ($sess->event[$key], EV_READ);
					event_buffer_disable ($sess->event[$key], EV_WRITE);
			}
		}

		return $is_rw;
	}

	function nextStatus ($key) {
		return self::currentStatus ($key, true);
	}

	function clearEvent () {
		$sess = &Vari::$sess;

		foreach ( $sess->status as $key => $val ) {
			list ($host, $port, $type) = $sess->addr[$key];

			if ( self::$mod->$type->check_buf_status ($val) !== Vari::EVENT_READY_CLOSE ) {
				list ($host, $port, $type) = Vari::$sess->addr[$key];
				Vari::$res->failure++;
				Vari::$res->status[$key] = array ("{$host}:{$port}", false, 'Protocol timeout');
				if ( is_resource ($sess->event[$key]) )
					event_buffer_free ($sess->event[$key]);
			}
		}
	}
}
?>
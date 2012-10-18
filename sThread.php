<?php
/**
 * Project: sThread :: Single Thread Monitoring Agent API<br>
 * File: sThread.php
 *
 * sThread package는 async 방식의 L7 layer 모니터링 에이전트
 * 이다.
 *
 * sThread package는 libevent를 이용하여 Multi session과
 * 동시에 여러가지 protocol을 처리할 수 있도록 설계가 있다.
 *
 * 모듈 구조를 지원하므로, 필요한 모듈을 직접 만들어 추가할 수
 * 있다.
 *
 * @category    Network
 * @package     sThread
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @since       File available since relase 1.0.0
 * @filesource
 */

// {{{ 기본 include package
/**
 * include ePrint package
 */
require_once 'ePrint.php';
/**
 * sThread에서 사용하는 공통 변수 관리 Class
 */
require_once 'sThread/Vari.php';
/**
 * sThread에서 사용하는 모듈 관리 Class
 */
require_once 'sThread/Module.php';
/**
 * sThread에서 사용하는 주소 관리 Class
 */
require_once 'sThread/Addr.php';
/**
 * sThread의 logging Class
 */
require_once 'sThread/Log.php';
// }}}

// {{{ libevent call functions
/**
 * libevent에서 사용되는 read callback wrapper 함수
 *
 * 실제로 frontend에서는 사용할 일이 없다.
 *
 * @return bool
 * @param  resource libevent resource
 * @param  array    callback argument
 */
function sThread_readCallback ($buf, $arg) {
	return sThread::readCallback ($buf, $arg);
}

/**
 * libevent에서 사용되는 write callback wrapper 함수
 *
 * 실제로 frontend에서는 사용할 일이 없다.
 *
 * @return bool
 * @param  resource libevent resource
 * @param  array    callback argument
 */
function sThread_writeCallback ($buf, $arg) {
	return sThread::writeCallback ($buf, $arg);
}

/**
 * libevent에서 사용되는 예외 callback wrapper 함수
 *
 * 이 함수는 아무런 작동을 하지 않는다.
 *
 * @return null
 * @param  resource libevent resource
 * @param  array    callback argument
 */
function sThread_exceptionCallback ($buf, $arg) { }
// }}}

/**
 * sThread 패키지의 메인 Class
 *
 * sThread package는 async 방식의 L7 layer 모니터링 에이전트
 * 이다.
 *
 * sThread package는 libevent를 이용하여 Multi session과
 * 동시에 여러가지 protocol을 처리할 수 있도록 설계가 있다.
 *
 * 모듈 구조를 지원하므로, 필요한 모듈을 직접 만들어 추가할 수
 * 있다.
 *
 * @category    Network
 * @package     sThread
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread {
	// {{{ properties
	// {{{ sThread constants
	/**#@+
	 * @access public
	 */
	/**
	 * sThread Major version 번호
	 */
	const MAJOR_VERSION = 1;
	/**
	 * sThread Minor version 번호
	 */
	const MINOR_VERSION = 0;
	/**
	 * sThread Patch 레벨 번호
	 */
	const PATCH_VERSION = 1;
	/**
	 * sThread 버전
	 */
	const VERSION = '1.0.1';
	/**#@-*/
	// }}}

	/**#@+
	 * @access public
	 */
	/**
	 * sThread_Module 패키지에서 등록한 모듈 object
	 * @var object
	 */
	static public $mod;
	/**
	 * libevent 동작을 sync로 할지 async로 할지 여부.
	 * 기본값 'false'
	 * @var boolean
	 */
	static public $async;
	/**
	 * 저장할 로그 파일 이름
	 * @var string
	 */
	static public $logfile;
	/**
	 * 저장할 로그 파일이름 postfix.
	 * data function의 format으로 지정해야 한다.
	 * @var string
	 */
	static public $logformat;
	/**
	 * 로그 저장 여부
	 * <ul>
	 *     <li>0  -> 실패한 로그만 저장</li>
	 *     <li>1  -> 모든 로그 저장</li>
	 *     <li>-1 -> 로그 저장 안함</li>
	 * </ul>
	 *
	 * @var int
	 */
	static public $logtype;
	/**
	 * 실행 결과 데이터 보존 여부
	 * @var boolean
	 */
	static public $result = false;
	/**#@-*/

	/**#@+
	 * @access private
	 */
	/**
	 * 소켓 연결/읽기/쓰기 타임아웃
	 * @var    int
	 */
	static private $tmout;

	/**
	 * socket create interval
	 * @var    int
	 */
	static private $timer = 200;
	/**#@-*/
	// }}}

	// {{{ (object) sThread::__construct (void)
	/**
	 * OOP 스타일의 sThread Class 초기화
	 *
	 * @access public
	 * @return object
	 */
	function __construct () {
		self::init ();
		$this->mod     = &self::$mod;
		$this->tmout   = &self::$tmout;
		$this->async   = &self::$async;
		$this->result  = &self::$result;

		# sThread_LOG varibles
		$this->logfile   = &sThread_Log::$fname;
		$this->logformat = &sThread_Log::$format;
		$this->logtype   = &sThread_Log::$type;
	}
	// }}}

	// {{{ (void) sThread::init ($mod_no_init = false)
	/**
	 * sThread Class를 초기화 한다.
	 *
	 * @access public
	 * @return void
	 * @param  bool (optional) true로 설정을 하면 sThread_Module
	 *              class를 호출 하지 않는다. 기본값 false.
	 */
	function init ($mod_no_init = false) {
		Vari::clear ();

		if ( $mod_no_init === false ) {
			sThread_Module::init ();
			self::$mod = &sThread_Module::$obj;
		}

		self::$async  = false;
		self::$result = false;
		Vari::$result = &self::$result;

		# sThread_LOG varibles
		self::$logfile   = &sThread_Log::$fname;
		self::$logformat = &sThread_Log::$format;
		self::$logtype   = &sThread_Log::$type;
	}
	// }}}

	// {{{ (void) sThread::execute ($host, $tmout = 1, $protocol = 'tcp')
	/**
	 * sThread를 실행한다.
	 *
	 * 실행 후 결과값은 Vari::$res 와 Vari::$sess 의 값을 확인하면
	 * 된다.
	 *
	 * @access public
	 * @return void
	 * @param  mixed  연결 정보.<br>
	 *         host의 형식은 기본적으로 문자열 또는 배열을 사용할 수 있다.
	 *
	 *         주소 형식은 기본적으로 도메인이름 또는 IP주소와 PORT 번호로
	 *         구성이 된다.
	 *
	 *         <code>
	 *         $host = 'test.domaint.com:80';
	 *         </code>
	 *
	 *         sThread package의 장점은 여러개의 실행을 동시에 할 수 있다는
	 *         것이므로, 여러개의 주소를 한번에 넘길 경우에는 배열을 이용한다.
	 *
	 *         <code>
	 *         $host = array (
	 *             'test.domain.com:80',
	 *             'test1.domain.com:53',
	 *             'test16.domain.com:80'
	 *         );
	 *         </code>
	 * @param  int    소켓 연결/읽기/쓰기 타임아웃
	 * @param  string tcp 또는 udp. 기본값 tcp.
	 */
	function execute ($hosts, $tmout = 1, $protocol = 'tcp') {
		if ( ! is_array ($hosts) )
			$hosts = array ($hosts);

		self::$tmout = $tmout;

		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		$time = &Vari::$time;

		$key = 0;
		foreach ( $hosts as $line ) {
			$res->total++;
			$line = rtrim ($line);
			if ( ($newline = sThread_Address::parse ($line, $key)) === false ) {
				list ($host, $port) = explode (':', $line);
				$res->failure++;
				$sess->status[$key] = Vari::EVENT_ERROR_CLOSE;
				$res->status[$key] = array ("{$host}:{$port}", false, "Address parsing error");
				$key++;
				continue;
			}
			$sess->opt[$key] = sThread_Address::extraOption ($newline);
			$sess->addr[$key] = explode (':', $newline);
			$sess->proto[$key] = $protocol;

			self::explodeAddr ($host, $port, $type, $newline);
			$addr = "{$protocol}://{$host}:{$port}";

			$time->cstart[$key] = microtime ();
			$async = (self::$async === true) ?
					STREAM_CLIENT_CONNECT|STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT;
			$sess->sock[$key] = @stream_socket_client (
				$addr, $errno, $errstr, self::$tmout, $async
			);
			usleep (self::$timer);

			if ( self::$async !== true && ! is_resource ($sess->sock[$key]) ) {
				if ( ePrint::$debugLevel >= Vari::DEBUG1 )
					ePrint::ePrintf ("%s:%d (%d) Failed socket create: %s",
								array ($host, $port, $key, $errstr));
				$res->failure++;
				self::$mod->$type->set_last_status ($sess, $key);
				$res->status[$key] = array ("{$host}:{$port}", false, "Failed socket create: {$errstr}");
				$key++;
				self::timeResult ($key);
				continue;
			}
			$time->cend[$key] = microtime ();

			ePrint::dPrintf (Vari::DEBUG1, "[%-15s] %s:%d (%d) Socket create success\n",
						$sess->sock[$key], $host, $port, $key);

			$sess->status[$key] = 1;
			$sess->send[$key] = Vari::EVENT_READY_SEND;
			stream_set_timeout ($sess->sock[$key], self::$tmout, 0);
			stream_set_blocking ($sess->sock[$key], 0);
			$key++;
		}

		$base = event_base_new ();
		foreach ( $sess->sock as $key => $val ) {
			if ( ! is_resource ($val) )
				continue;

			$time->pstart[$key] = microtime ();
			$sess->event[$key] = event_buffer_new (
					$sess->sock[$key],
					'sThread_readCallback',
					'sThread_writeCallback',
					'sThread_exceptionCallback', array ($key)
			);
			event_buffer_timeout_set ($sess->event[$key], self::$tmout, self::$tmout);
			event_buffer_base_set ($sess->event[$key], $base);

			if ( self::currentStatus ($key) === Vari::EVENT_READY_RECV )
				event_buffer_enable ($sess->event[$key], EV_READ);
			else
				event_buffer_enable ($sess->event[$key], EV_WRITE);
		}

		event_base_loop ($base);
		self::clearEvent ();
		event_base_free ($base);
		// 필요 없는 정보 정리
		Vari::clear (true);
	}
	// }}}

	// {{{ (bool) sThread::readCallback ($buf, $arg)
	/**
	 * libevent read callback
	 *
	 * 이 메소드는 외부에서 사용할 필요가 없다. private로
	 * 지정이 되어야 하나, event_buffer_new api가 class method를
	 * callback 함수로 사용을 하지 못하게 되어 있어, 외부 wrapper
	 * 를 통하여 호출을 하기 때문에 public으로 선언이 되었다.
	 *
	 * @access public
	 * @return boolean
	 * @param  resource libevent resource
	 * @param  array    callback argument
	 */
	function readCallback ($buf, $arg) {
		list ($key) = $arg;
		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		self::explodeAddr ($host, $port, $type, $sess->addr[$key]);

		ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Read Callback\n",
				$sess->sock[$key], $host, $port);

		if ( self::currentStatus ($key) !== Vari::EVENT_READY_RECV )
			return true;

		if ( ($handler = self::getCallname ($key)) === false ) {
			self::socketClose ($key);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Recieve %s call\n",
					$sess->sock[$key], $host, $port, $handler);

		while ( strlen ($_buf = event_buffer_read ($buf, 8192)) > 0 )
			$buffer .= $_buf;

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
				self::$mod->$type->set_last_status ($sess, $key);
				self::socketClose ($key);
				event_buffer_free ($sess->event[$key]);
				return true;
			}
			return;
		}
		// }}}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Complete %s call\n",
					$sess->sock[$key], $host, $port, $handler);
	
		/*
		 * Unknown status or Regular connection end
		 */
		if ( ($is_rw = self::nextStatus ($key)) === false ) {
			self::socketClose ($key);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		return true;
	}
	// }}}

	// {{{ (bool) sThread::writeCallback ($buf, $arg)
	/**
	 * libevent write callback
	 *
	 * 이 메소드는 외부에서 사용할 필요가 없다. private로
	 * 지정이 되어야 하나, event_buffer_new api가 class method를
	 * callback 함수로 사용을 하지 못하게 되어 있어, 외부 wrapper
	 * 를 통하여 호출을 하기 때문에 public으로 선언이 되었다.
	 *
	 * @access public
	 * @return boolean
	 * @param  resource libevent resource
	 * @param  array    callback argument
	 */
	function writeCallback ($buf, $arg) {
		list ($key) = $arg;
		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		list ($host, $port, $type) = $sess->addr[$key];
		self::explodeAddr ($host, $port, $type, $sess->addr[$key]);

		if ( $sess->send[$key] === Vari::EVENT_SEND_DONE ) {
			$sess->send[$key] = Vari::EVENT_READY_SEND;
			/*
			 * Unknown status or Regular connection end
			 */
			if ( ($is_rw = self::nextStatus ($key)) === false ) {
				self::socketClose ($key);
				event_buffer_free ($sess->event[$key]);
			}
			return true;
		}

		ePrint::dPrintf (Vari::DEBUG3, "[%-15s] %s:%d Write Callback\n",
				$sess->sock[$key], $host, $port);

		if ( self::currentStatus ($key) !== Vari::EVENT_READY_SEND )
			return true;


		if ( ($handler = self::getCallname ($key)) === false ) {
			self::socketClose ($key);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Send %s call\n",
					$sess->sock[$key], $host, $port, $handler);

		$send = self::$mod->$type->$handler ($sess, $key);

		// make error for send packet
		if ( $send === false ) {
			$res->failure++;
			self::$mod->$type->set_last_status ($sess, $key);
			self::socketClose ($key);
			event_buffer_free ($sess->event[$key]);
			return true;
		}

		if ( event_buffer_write ($buf, $send, strlen ($send)) === false ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 )
				ePrint::ePrintf ("[%-15s] Error: %s:%d Send error",
					array ($sess->sock[$key], $host, $port));
			$res->failure++;
			self::$mod->$type->set_last_status ($sess, $key);
			$res->status[$key] = array ("{$host}:{$port}", false, "{$handler} Send error");
			self::socketClose ($key);
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
	// }}}

	/*
	 * Private functions
	 *
	// {{{ private (string) sThread::getCallname ($key)
	/**
	 * 현재 상태의 세션 handler 이름을 반환
	 *
	 * @access private
	 * @return string|false
	 * @param  int  세션 키
	 */
	private function getCallname ($key) {
		$sess = &Vari::$sess;
		self::explodeAddr ($host, $port, $type, $sess->addr[$key]);
		$event = self::$mod->$type->call_status ($sess->status[$key], true);
		if ( $event === false ) {
			if ( ePrint::$debugLevel >= Vari::DEBUG1 )
				ePrint::ePrintf ("[%-15s] Error: %s:%d 'Unknown status => %d (%s)",
					array ($sess->sock[$key], $host, $port, $sess->status[$key], $type));
			$res->failure++;
			self::$mod->$type->set_last_status ($sess, $key);
			$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				"Unknown Status: {$sess->status[$key]} ({$type})"
			);
			return false;
		}

		return $event;
	}
	// }}}

	// {{{ private (int) sThread::currentStatus ($key, $next = false)
	/**
	 * 현재의 처리 단계를 반환
	 *
	 * @access private
	 * @return int 
	 * @param  int    세션 키
	 * @param  boolean true로 설정이 되면 처리 단계를 다음으로 전환한다.
	 */
	private function currentStatus ($key, $next = false) {
		$sess = &Vari::$sess;
		$res  = &Vari::$res;
		self::explodeAddr ($host, $port, $type, $sess->addr[$key]);

		if ( $next !== false ) {
			self::$mod->$type->change_status ($sess, $key);
			$sess->recv[$key] = '';
		}
		$is_rw = self::$mod->$type->check_buf_status ($sess->status[$key]);

		/*
		 * $is_wr가 sThread::EVENT_READY_CLOSE 상태가 되면, 모든 작업이
		 * 완료된 case 이므로 socket을 닫는다. $is_rw 가 false일 경우,
		 * 알 수 없는 상태이므로 종료 한다.
		 */
		if ( $is_rw === Vari::EVENT_READY_CLOSE || $is_rw === false ) {
			if ( $is_rw === false ) {
				if ( ePrint::$debugLevel >= Vari::DEBUG1 )
					ePrint::ePrintf ("[%-15s] Error: %s:%d 'Unknown status => %d (%s)'",
						array ($sess->sock[$key], $host, $port, $sess->status[$key], $type));
				$res->failure++;
				self::$mod->$type->set_last_status ($sess, $key);
				$res->status[$key] = array (
					"{$host}:{$port}",
					false,
					"Unknown Status: {$sess->status[$key]} ({$type})"
				);
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
	// }}}

	// {{{ private (void) sThread::explodeAddr (&$host, &$port, &$type, $v)
	/**
	 * 주소 형식 값에서 주소, 분리, 모듈 형식을 분리해 낸다.
	 *
	 * @access private
	 * @return void
	 * @param  reference     주소 값을 저장할 변수 reference
	 * @param  reference     포트 값을 저장할 변수 reference
	 * @param  reference     모듈 이름을 저장할 변수 reference
	 * @param  array|string  분리되지 않은 문자열 or 배열
	 */
	private function explodeAddr (&$host, &$port, &$type, $v) {
		list ($host, $port, $type) = is_array ($v) ? $v : explode (':', $v);
		//$type = preg_replace ('/\|.*/', '', $type);
	}
	// }}}

	// {{{ private (int) sThread::nextStatus ($key)
	/**
	 * 다음 처리단계로 전환
	 *
	 * @access private
	 * @return int
	 * @param  int    세션 키
	 */
	private function nextStatus ($key) {
		return self::currentStatus ($key, true);
	}
	// }}}

	// {{{ private (void) sThread::clearEvent (void)
	/**
	 * 각 세션에서 사용한 변수들 정리 및 소켓 close
	 *
	 * @access private
	 * @return void
	 */
	private function clearEvent () {
		$sess = &Vari::$sess;

		foreach ( $sess->status as $key => $val ) {
			self::explodeAddr ($host, $port, $type, $sess->addr[$key]);

			if ( $val !== Vari::EVENT_ERROR_CLOSE &&
				 self::$mod->$type->check_buf_status ($val) !== Vari::EVENT_READY_CLOSE ) {
				list ($host, $port, $type) = Vari::$sess->addr[$key];
				Vari::$res->failure++;
				if ( $val == 1 )
					Vari::$res->status[$key] = array ("{$host}:{$port}", false, 'Connection timeout');
				else
					Vari::$res->status[$key] = array ("{$host}:{$port}", false, 'Protocol timeout');

				if ( self::$mod->$type->clearsession === true )
					self::$mod->$type->clear_session ($key);

				self::socketClose ($key);
				if ( is_resource ($sess->event[$key]) )
					event_buffer_free ($sess->event[$key]);
			}
		}

		if ( ! empty (self::$mod->$type) )
			self::$mod->$type->init();
	}
	// }}}

	// {{{ private (void) sThread::socketClose ($key)
	/**
	 * @access private
	 * @return void
	 * @param  int  세션 키
	 */
	private function socketClose ($key) {
		$sess = &Vari::$sess;

		list ($host, $port, $type) = $sess->addr[$key];

		if ( Vari::$res->status[$key][1] === false ) {
			$handler = $type . '_quit';
			if ( method_exists (self::$mod->$type, $handler) ) {
				ePrint::dPrintf (Vari::DEBUG2, "[%-15s] %s:%d Quit call\n",
							$sess->sock[$key], $host, $port);

				$send = self::$mod->$type->$handler ($sess, $key);
				@fwrite ($sess->sock[$key], $send, strlen ($send));

				ePrint::dPrintf (
					Vari::DEBUG3,
					"[%-15s] %s:%d Send data\n",
					$sess->sock[$key], $host, $port);

				if ( ePrint::$debugLevel >= Vari::DEBUG3 ) {
					$msg = rtrim ($send);
					ePrint::echoi ($send . "\n", 8);
				}
			}
		}

		sThread_Log::save ($key, $sess->recv[$key]);

		if ( is_resource ($sess->sock[$key]) )
			fclose ($sess->sock[$key]);

		self::timeResult ($key);
	}
	// }}}

	// {{{ private sThread::timeResult ($key)
	/**
	 * 수행 시간을 정리
	 *
	 * @access private
	 * @return void
	 * @param  int  세션 키
	 */
	private function timeResult ($key) {
		$time = &Vari::$time;
		$sess = &Vari::$sess;

		if ( isset ($time->pstart[$key]) )
			$time->pend[$key] = microtime ();

		$sess->ctime[$key] = Vari::chkTime ($time->cstart[$key], $time->cend[$key]);
		$sess->ptime[$key] = Vari::chkTime ($time->pstart[$key], $time->pend[$key]);

		unset (Vari::$time->cstart[$key]);
		unset (Vari::$time->cend[$key]);
		unset (Vari::$time->pstart[$key]);
		unset (Vari::$time->pend[$key]);
	}
	// }}}
}
?>

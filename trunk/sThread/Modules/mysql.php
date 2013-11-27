<?php
/**
 * sThread MYSQL module
 * 
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2013 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @see         http://forge.mysql.com/wiki/MySQL_Internals_ClientServer_Protocol MySQL Internals ClientServer Protocol
 * @filesource
 */

/**
 * MYSQL module Class
 * 
 * MYSQL 모듈에 사용할 수 있는 모듈 option은 다음과 같다.
 *
 * <ul>
 *     <li><b>user:</b>     로그인 유저</li>
 *     <li><b>pass:</b>     로그인 암호</li>
 *     <li><b>database:</b> 질의할 데이터베이스 이름</li>
 *     <li><b>query:</b>    쿼리 문자열</li>
 *     <li><b>charset:</b>  클라이언트 문자셋</li>
 * </ul>
 *
 * 예제:
 * <code>
 *   sThread::execute ('domain.com:3306:mysql|query=>select count(*) FROM test', 2, 'tcp');
 * </code>
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_Module
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2013 OOPS.ORG
 * @license     BSD License
 * @link        http://pear.oops.org/package/sThread
 * @see         http://forge.mysql.com/wiki/MySQL_Internals_ClientServer_Protocol MySQL Internals ClientServer Protocol
 */
Class sThread_MYSQL {
	// {{{ Base properteis
	/**#@+
	 * @access public
	 */
	/**
	 * 이 변수의 값이 true로 셋팅이 되면, clear_session
	 * method를 만들어 줘야 한다. 반대로 false 상태에서는
	 * clear_session method가 존재하지 않아도 상관이 없다.
	 *
	 * @var bool
	 */
	static public $clearsession = true;
	/**
	 * MYSQL 모듈이 사용하는 기본 포트 번호
	 * @var int
	 */
	static public $port = 3306;

	const MYSQL_BANNER    = 1;
	const MYSQL_SENDAUTH  = 2;
	const MYSQL_HANDSHAKE = 3;
	const MYSQL_SENDQUERY = 4;
	const MYSQL_QUERYRES  = 5;
	/**
	 * 에러가 발생했을 경우, MYSQL_QUIT 메소드가 정의가 되어있으면,
	 * parent::socketColose에 의해서 자동으로 MYSQL_QUIT이 호출이
	 * 된다.
	 */
	const MYSQL_QUIT      = 6;
	const MYSQL_CLOSE     = 7;
	/**#@-*/
	// }}}

	// {{{ Per module properteis
	/**#@+
	 * @access private
	 */
	const MYSQL_COM_QUIT    = 0x01;
	const MYSQL_COM_INIT_DB = 0x02;
	const MYSQL_COM_QUERY   = 0x03;

	const MYSQL_RET_ERROR = 0x00;
	const MYSQL_RET_OK    = 0x01;
	const MYSQL_RET_QUERY = 0x02;
	const MYSQL_RET_EOF   = 0x03;

	/* user define variable */
	static private $server;
	static private $qstatus;
	static private $columnno;
	static private $columnid;
	static private $rowid;
	static private $column;
	static private $r;
	/**#@-*/
	// }}}

	// {{{ (void) sThread_MYSQL::__construct (void)
	/**
	 * Class OOP 형식 초기화 메소드
	 * @access public
	 * @return sThread_MYSQL
	 */
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_MYSQL::init (void)
	/**
	 * mysql 모듈을 초기화 한다.
	 *
	 * @access public
	 * @return void
	 */
	function init () {
		self::$clearsession = true;
		self::$port         = 3306;

		self::$server       = array ();
		self::$qstatus      = array ();
		self::$columnno     = array ();
		self::$columnid     = array ();
		self::$rowid        = array ();
		self::$column       = array ();
		self::$r			= array ();
	}
	// }}}

	// {{{ (int) sThread_MYSQL::check_buf_status ($status)
	/**
	 * 현재 상태가 event read 상태인지 event write 상태인지
	 * 를 판단한다.
	 *
	 * @access public
	 * @return int
	 * @param  int    현재 status
	 */
	function check_buf_status ($status) {
		switch ($status) {
		case 0 :
		case self::MYSQL_BANNER :
			return Vari::EVENT_READY_RECV;
			break;
		case self::MYSQL_SENDAUTH:
			return Vari::EVENT_READY_SEND;
			break;
		case self::MYSQL_HANDSHAKE:
			return Vari::EVENT_READY_RECV;
			break;
		case self::MYSQL_SENDQUERY:
			return Vari::EVENT_READY_SEND;
			break;
		case self::MYSQL_QUERYRES:
			return Vari::EVENT_READY_RECV;
			break;
		case self::MYSQL_QUIT:
			return Vari::EVENT_READY_SEND;
			break;
		case self::MYSQL_CLOSE :
			return Vari::EVENT_READY_CLOSE;
			break;
		default :
			return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_MYSQL::call_status ($status, $call = false)
	/**
	 * 현재의 status(integer) 또는 현재 status의 handler 이름을
	 * 반환한다.
	 *
	 * @access public
	 * @return int|string
	 * @param  int        현재 status
	 * @param  boolean    true로 설정했을 경우 현재 status의 handler
	 *                    이름을 반환한다.
	 */
	function call_status ($status, $call = false) {
		switch ($status) {
		case self::MYSQL_BANNER :
			$r = 'MYSQL_BANNER';
			break;
		case self::MYSQL_SENDAUTH:
			$r = 'MYSQL_SENDAUTH';
			break;
		case self::MYSQL_HANDSHAKE:
			$r = 'MYSQL_HANDSHAKE';
			break;
		case self::MYSQL_SENDQUERY:
			$r = 'MYSQL_SENDQUERY';
			break;
		case self::MYSQL_QUERYRES:
			$r = 'MYSQL_QUERYRES';
			break;
		case self::MYSQL_QUIT:
			$r = 'MYSQL_QUIT';
			break;
		default:
			$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_MYSQL::change_status (&$sess, $key)
	/**
	 * 세션의 상태를 단계로 변경한다.
	 *
	 * @access public
	 * @param  boolean  변경한 상태가 마지막 단계일 경우 false를
	 *                  반환한다.
	 * @param  stdClass sThread 세션 변수 reference
	 * @param  int      세션 키
	 */
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::MYSQL_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_MYSQL::set_last_status (&$sess, $key)
	/**
	 * 세션의 상태를 마지막 단계로 변경한다.
	 *
	 * @access public
	 * @param  stdClass sThread 세션 변수 reference
	 * @param  int      세션 키
	 */
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::MYSQL_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_MYSQL::clear_session ($key) {
	/**
	 * session에서 사용한 변수(self::$sess)의 값을 정리한다.
	 *
	 * self::$clearsession == false 일 경우, clear_session method
	 * 는 존재하지 않아도 된다.
	 *
	 * @access public
	 * @return void
	 * @param  int    세션 키
	 */
	function clear_session ($key) {
		Vari::objectUnset (self::$server);
		Vari::objectUnset (self::$qstatus);
		Vari::objectUnset (self::$columnno);
		Vari::objectUnset (self::$columnid);
		Vari::objectUnset (self::$rowid);
		Vari::objectUnset (self::$column);
		Vari::objectUnset (self::$r);
		return;
	}
	// }}}

	/*
	 * Handler 정의
	 *
	 * Handler는 call_status 메소드에 정의된 값들 중
	 * Vari::EVENT_UNKNOWN를 제외한 모든 status의 constant string을
	 * 소문자로해서 만들어야 한다.
	 *
	 * Handler 이름은 sThread_MODULE::call_status 메소드를
	 * 통해서 구할 수 있다.
	 *
	 * handler는 다음의 구조를 가지며, 실제로 전송을 하거나 받는
	 * 것은 libevent가 한다.
	 *
	 * write handler:
	 *       handler_name (&$ses, $key)
	 *
	 *       write handler는 실제로 전송을 하지 않고 전송할
	 *       데이터를 생성해서 반환만 한다.
	 *
	 * read handler:
	 *       handler_name (&$sess, $key, $recv) 
	 *
	 *       read handler의 반환값은 다음과 같이 지정을 해야 한다.
	 *
	 *       true  => 모든 전송이 완료
	 *       false => 전송 받을 것이 남아 있음
	 *       null  => 전송 중 에러 발생
	 *
	 *       이 의미는 sThread가 read handler에서 결과값에 따라
	 *       true는 다음 단계로 전환을 하고, false는 현 status를
	 *       유지하며, null의 경우 connection을 종료를 한다.
	 */

	// {{{ (bool) function mysql_banner (&$sess, $key, $recv)
	/**
	 * MySQL banner 확인
	 *
	 * @access public
	 * @return bool|null
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 * @param  mixed    read callback에서 전송받은 누적 데이터
	 */
	function mysql_banner (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];

		$sess->recv[$key] .= $recv;
		if ( strlen ($sess->recv[$key]) < 5 )
			return false;

		$server->length = self::packet_length ($sess->recv[$key]) + 4;
		$server->packet_number = ord ($sess->recv[$key][3]);

		if ( $server->packet_number !== 0 ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				"Protocol error: Invalid Banner Packet Number : {$server->packet_number}"
			);
			Vari::binaryDecode ($sess->recv[$key], true);
			return null;
		}

		if ( strlen ($sess->recv[$key]) != $server->length )
			return false;

		$sess->recv[$key] = substr ($sess->recv[$key], 4);
		self::parse_handshake ($sess->recv[$key], self::$server[$key]);
		return true;
	} // }}}

	// {{{ (binary) function mysql_sendauth (&$sess, $key)
	/**
	 * 전송할 인증 데이터 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function mysql_sendauth (&$sess, $key) {
		list ($host, $port, $type) = $sess->addr[$key];
		$opt = $sess->opt[$key];

		self::$server[$key]->user     = $opt->user;
		self::$server[$key]->passwd   = $opt->pass;
		self::$server[$key]->database = $opt->database;
		self::$server[$key]->query    = $opt->query;
		if ( isset ($opt->charset) )
			self::$server[$key]->charset  = $opt->charset;

		return self::send_authenication (self::$server[$key]);
	} // }}}

	// {{{ (bool) function mysql_handshake (&$sess, $key, $recv)
	/**
	 * MySQL 인증 결과 확인 및 handshake 확인
	 *
	 * @access public
	 * @return bool|null
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 * @param  mixed  read callback에서 전송받은 누적 데이터
	 */
	function mysql_handshake (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];

		$sess->recv[$key] .= $recv;
		if ( strlen ($sess->recv[$key]) < 5 )
			return false;

		$length = self::packet_length ($sess->recv[$key]) + 4;
		$packet_number = ord ($sess->recv[$key][3]);

		if ( $packet_number !== 2 ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				"Protocol error: Invalid Handshake Packet Number : {$packet_number}"
			);
			Vari::binaryDecode ($sess->recv[$key], true);
			return null;
		}

		if ( strlen ($sess->recv[$key]) != $length )
			return false;

		$sess->recv[$key] = substr ($sess->recv[$key], 4);
		if ( self::parse_result ($r, $sess->recv[$key]) != self::MYSQL_RET_OK ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				"Protocol error: Authenication Error: {$r->errno} : {$r->message}"
			);
			fwrite ($sess->sock[$key], self::quit_packet (), 5);
			Vari::binaryDecode ($sess->recv[$key], true);
			return null;
		}

		return true;
	}
	// }}}

	// {{{ (binary) function mysql_sendquery (&$sess, $key)
	/**
	 * 전송할 쿼리 데이터 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function mysql_sendquery (&$sess, $key) {
		return self::query_packet (self::MYSQL_COM_QUERY, self::$server[$key]->query);
	} // }}}

	// {{{ (bool) function mysql_queryres (&$sess, $key, $recv)
	/**
	 * MySQL Query 전송 결과 확인
	 *
	 * @access public
	 * @return bool|null
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 * @param  mixed    read callback에서 전송받은 누적 데이터
	 */
	function mysql_queryres (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		$sess->recv[$key] .= $recv;
		if ( strlen ($sess->recv[$key]) < 5 )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];

		self::init_variable (self::$qstatus[$key]);
		self::init_variable (self::$columnno[$key]);
		self::init_variable (self::$columnid[$key]);

		while ( true ) {
			$length = self::packet_length ($sess->recv[$key]);
			$packet_number = ord ($sess->recv[$key][3]);

			if ( $packet_number !== (self::$qstatus[$key] + 1) ) {
				Vari::$res->status[$key] = array (
					"{$host}:{$port}",
					false,
					"Protocol error: Invalid Query Result Packet Number : {$packet_number}"
				);
				Vari::binaryDecode ($sess->recv[$key], true);
				return null;
			}

			$buf = substr ($sess->recv[$key], 4, $length);
			if ( strlen ($buf) != $length )
				return false;

			// Result Set Header Packet
			if ( self::$qstatus[$key] == 0 ) {
				if ( self::parse_result ($r, $buf) != self::MYSQL_RET_QUERY ) {
					Vari::$res->status[$key] = array (
						"{$host}:{$port}",
						false,
						"Protocol error: : Invalid Query Result Type"
					);
					fwrite ($sess->sock[$key], self::quit_packet (), 5);
					Vari::binaryDecode ($sess->recv[$key], true);
					return null;
				}
				self::$columnno[$key] = self::length_coded_binary ($buf);
				self::$qstatus[$key]++;

				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
				continue;
			}

			// Column Descriptors
			if ( self::$qstatus[$key] > 0 && self::$qstatus[$key] < self::$columnno[$key] + 1 ) {
				self::$column[self::$columnid[$key]]->catalog = self::length_coded_string ($buf);
				self::$column[self::$columnid[$key]]->db = self::length_coded_string ($buf);
				self::$column[self::$columnid[$key]]->table = self::length_coded_string ($buf);
				self::$column[self::$columnid[$key]]->org_table = self::length_coded_string ($buf);
				self::$column[self::$columnid[$key]]->name = self::length_coded_string ($buf);
				self::$column[self::$columnid[$key]]->old_name = self::length_coded_string ($buf);
				self::$column[self::$columnid[$key]]->filler = ord ($buf[0]);
				self::$column[self::$columnid[$key]]->charsetnr = unpack ('S', substr ($buf, 1, 2));
				self::$column[self::$columnid[$key]]->length = unpack ('L', substr ($buf, 3, 4));
				self::$column[self::$columnid[$key]]->type = '0x' . dechex (ord ($buf[7]));
				self::$column[self::$columnid[$key]]->flag = substr ($buf, 8, 2);
				self::$column[self::$columnid[$key]]->decimals = $buf[10];
				self::$column[self::$columnid[$key]]->filler = unpack ('S', substr ($buf, 11, 2));
				$buf = substr ($buf, 13);
				self::$column[self::$columnid[$key]++]->default = self::length_coded_binary ($buf);

				self::$qstatus[$key]++;

				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
				continue;
			}

			// EOF Packet: end of Field Packets
			if ( self::$qstatus[$key] == (self::$columnno[$key] + 1) ) {
				if ( self::parse_result ($r, $buf) != self::MYSQL_RET_EOF ) {
					Vari::$res->status[$key] = array (
						"{$host}:{$port}",
						false,
						"Protocol error: : Invalid EOF Type of Packet"
					);
					fwrite ($sess->sock[$key], self::quit_packet (), 5);
					Vari::binaryDecode ($sess->recv[$key], true);
					return null;
				}

				self::$qstatus[$key]++;
				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
				continue;
			}

			// Row Data Packets: row contents
			if ( self::$qstatus[$key] > (self::$columnno[$key] + 1) ) {
				//
				// end of packet
				// ----------------------------------------------------------------
				if ( self::parse_result ($r, $buf) == self::MYSQL_RET_EOF ) {
					//print_r (self::$r);
					unset ($sess->recv[$key]);
					if ( Vari::$result === true )
						Vari::objectCopy ($sess->data[$key], self::$r);
					return true;
				}
				// ----------------------------------------------------------------
				//

				self::init_variable (self::$rowid[$key]);

				for ( $i=0; $i<self::$columnno[$key]; $i++ ) {
					$colname = self::$column[$i]->name->data;
					self::$r[self::$rowid[$key]]->$colname = self::length_coded_string ($buf)->data;
				}
				self::$rowid[$key]++;
				self::$qstatus[$key]++;
				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
			}
		}

		return false;
	} // }}}

	// {{{ (binary) function mysql_quit (&$sess, $key) {
	/**
	 * 전송할 종료 데이터 반환
	 *
	 * @access public
	 * @return void
	 * @param  stdClass 세션 object
	 * @param  int      세션 키
	 */
	function mysql_quit (&$sess, $key) {
		return self::quit_packet ();
	} // }}}

	/*
	 * ********************************************************************************
	 * User define functions
	 * ********************************************************************************
	 */
	// {{{ private (void) sThread_MYSQL::init_variabe (&$v)
	private function init_variable (&$v) {
		if ( ! $v && ! is_numeric ($v) )
			$v = 0;
	}
	// }}}

	// {{{ private (void) sThread_MYSQL::hexview ($buf, $len, $t = false)
	private function hexview ($buf, $len, $t = false) {
		for ( $i=0; $i<$len; $i++ )
			printf ("%3d -> 0x%2x\n", $i+1, ($t === false) ? ord($buf[$i]) : $buf[$i]);
	} // }}}

	// {{{ private (void) sThread_MYSQL::hexview_dump ($buf, $len, $t = false)
	private function hexview_dump ($buf, $len, $t = false) {
		for ( $i=0; $i<$len; $i++ ) {
			if ( $i >= 8 && ($i % 8) == 0 ) {
				echo "  ";
				if ( ($i % 16) == 0 )
					echo "\n";
			}
			printf ("%02x ", ($t === false) ? ord($buf[$i]) : $buf[$i]);
		}
		echo "\n";
	} // }}}

	// {{{ private (void) sThread_MYSQL::scramble (&$to, $salt, $passwd)
	private function scramble (&$to, $salt, $passwd) {
		$pass1 = sha1 ($passwd, true);
		$pass2 = sha1 ($pass1, true);
		$pass3 = sha1 ($salt . $pass2, true);
		$to = $pass3 ^ $pass1;
	} // }}}

	// {{{ private (integer) sThread_MYSQL::packet_length (&$buf)
	private function packet_length (&$buf) {
		$r = unpack ('l', substr ($buf, 0, 3) . "\0");
		return $r[1];
	} // }}}

	// {{{ private (object) sThread_MYSQL::length_coded_string (&$buf)
	private function length_coded_string (&$buf) {
		$r->length = ord ($buf[0]);
		$r->data = substr ($buf, 1, $r->length);

		$buf = substr ($buf, $r->length + 1);

		return $r;
	} // }}}

	// {{{ private (binary) sThread_MYSQL::length_coded_binary (&$buf)
	private function length_coded_binary (&$buf) {
		$v = ord ($buf[0]);
		$next = 1;

		switch ($v) {
		case 254 : // unsigned 64bit
			$low = unpack ('V', substr ($buf, 1, 4));
			$high = unpack ('V', substr ($buf, 5, 4));
			$v = $high[1] << 32;
			$v |= $low[1];
			$next += 8;
			break;
		case 253 : // unsigned 24bit
			$tmp = unpack ('L', substr ($buf, 1, 3) . "\0");
			$v = $tmp[1];
			$next += 3;
			break;
		case 252 : // unsigned 16bit
			$tmp = unpack ('S', substr ($buf, 1, 2) );
			$v = $tmp[1];
			$next += 2;
			break;
		case 251 : // NULL of database
			break;
			$v = 'NULL';
		default :
			// self
		}
		$buf = substr ($buf, $next);
		return $v;
	} // }}}

	/*
	 * Parsings
	 */

	// {{{ private (void) sThread_MYSQL::parse_handshake ($buffer, &$server)
	private function parse_handshake ($buffer, &$server) {
		$server->protocol_version = ord ($buffer[0]);

		$server_version_length = $handshake_length - 44;
		// for mysql 5.5
		if ( preg_match ('/mysql_native_password/', $buffer) )
			$server_version_length -= 22;
		$server->server_version = substr ($buffer, 1, $server_version_length - 1);
		$buffer = substr ($buffer, $server_version_length);
		$buf = unpack ('L', $buffer);
		$server->thread_number = ord ($buf[1]);
		$server->scramble_buff = substr ($buffer, 4, 8);

		$buffer = substr ($buffer, 13);
		$buf = unpack ('Scapa/clang/Sstatus', $buffer);
		$server->server_capabilities = $buf['capa'];
		$server->charset = ord($buf['lang']);
		$server->server_status = $buf['status'];

		$buffer =  substr ($buffer, 18, 12);
		$server->scramble_buff .= $buffer;
	} // }}}

	// {{{ private (binary) sThread_MYSQL::send_authenication (&$client)
	private function send_authenication (&$client) {
		if ( ! $client->user )
			return false;

		# client_flag
		$s = pack ('c*', 0x8d, 0xa6, 0x03, 0x00);
		# max_packaet_size
		$s .= pack('c*', 0x00, 0x00, 0x00, 0x01);
		# charset_number (use latin1 -> 51, euckr -> 19 or 0x13, utf8 -> 33)
		$s .= pack ('c', $client->charset);
		# padding
		$s .= pack ('x23');
		# user
		$s .= $client->user . pack ('x');
		# password
		self::scramble ($pass, $client->scramble_buff, $client->passwd);
		# length of $pass is always 20 byte
		$s .= pack ('c', 0x14) . $pass;
		$s .= $client->database . pack ('x1');
		$send = pack ('Sxc', strlen ($s), ++$client->packet_number) . $s;

		return $send;
	} // }}}

	// {{{ private (integer) sThread_MYSQL::parse_result (&$r, $buf)
	private function parse_result (&$r, $buf) {
		/*
		 * OK Packet           0x00
		 * Error Packet        0xff
		 * Result Set Packet   1-250 (first byte of Length-Coded Binary)
		 * Field Packet        1-250 (first byte of Length-Coded Binary)
		 * Row Data Set Packet 1-250 (first byte of Length-Coded Binary)
		 * EOF Packet          0xfe
		 */
		$r->field_count = ord ($buf[0]);

		switch ($r->field_count) {
			case 0 :
				// 0x00 OK packet
				$buf = substr ($buf, 1);

				# affected rows
				$r->affected_rows = self::length_coded_binary ($buf);
				# insert id
				$r->insert_id = self::length_coded_binary ($buf);
				# server status
				$tmp = unpack ('S', substr ($buf, 0, 2));
				$r->server_status = $tmp[1];
				$tmp = unpack ('S', substr ($buf, 2, 2));
				$r->warning_count = $tmp[1];
				$buf = substr ($buf, 4);
				$r->msg = self::length_coded_string ($buf);

				return self::MYSQL_RET_OK;
				break;
			case 254 :
				// Oxfe EOF packet
				$buf = substr ($buf, 1);

				$tmp = unpack ('S', substr ($buf, 0, 2));
				$r->warning_count = $tmp[1];
				$tmp = unpack ('S', substr ($buf, 2, 2));
				$r->server_status = $tmp[1];

				return self::MYSQL_RET_EOF;
				break;
			case 255 :
				// 0xff Error packet
				$buf = substr ($buf, 1);

				$tmp = unpack ('v', substr ($buf, 0, 2));
				$r->errono = $tmp[1];
				$r->sqlstate_mark = $buf[2];
				$r->sqlsate = substr ($buf, 3, 5);
				$r->message = substr ($buf, 8);

				return self::MYSQL_RET_ERROR;
				break;
			default:
				return self::MYSQL_RET_QUERY;
		}
	} // }}}

	// {{{ private (binary) sThread_MYSQL::query_packet ($type, $arg)
	private function query_packet ($type, $arg) {
		$arg = preg_replace ('/;$/', '', $arg);

		$s = pack ('c', $type);
		$s .= $arg;

		$send = pack ('Sxc', strlen ($s), 0x00) . $s;
		return $send;
	} // }}}

	// {{{ private (binary) sThread_MYSQL::quit_packet ()
	private function quit_packet () {
		$send = pack ('c*', 0x01, 0x00, 0x00, 0x00, 0x01);
		return $send;
	} // }}}
}

?>

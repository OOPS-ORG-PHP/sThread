<?php
/*
 * sThread MYSQL module
 * 
 * $Id: mysql.php,v 1.1 2010-02-08 14:06:06 oops Exp $
 * See also http://forge.mysql.com/wiki/MySQL_Internals_ClientServer_Protocol
 *
 */
Class sThread_MYSQL {
	// {{{ properteis
	static public $clearsession = true;
	static public $port = 3306;

	const MYSQL_BANNER    = 1;
	const MYSQL_SENDAUTH  = 2;
	const MYSQL_HANDSHAKE = 3;
	const MYSQL_SENDQUERY = 4;
	const MYSQL_QUERYRES  = 5;
	const MYSQL_QUIT      = 6;

	const MYSQL_CLOSE     = 7;


	const MYSQL_COM_QUIT    = 0x01;
	const MYSQL_COM_INIT_DB = 0x02;
	const MYSQL_COM_QUERY   = 0x03;

	const MYSQL_RET_ERROR = 0x00;
	const MYSQL_RET_OK    = 0x01;
	const MYSQL_RET_QUERY = 0x02;
	const MYSQL_RET_EOF   = 0x03;

	/* user define variable */
	static private $server;
	static private $qstatus = 0;
	static private $columnno = 0;
	static private $columnid = 0;
	static private $rowid;
	static private $column;
	static private $r;

	// }}}

	// {{{ (void) sThread_MYSQL::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
	}
	// }}}

	// {{{ (void) sThread_MYSQL::init (void)
	function init () {
		self::$clearsession = true;
		self::$port         = 3306;

		self::$server       = '';
		self::$qstatus      = 0;
		self::$columnno     = 0;
		self::$columnid     = 0;
		self::$rowid        = 0;
		self::$column       = array ();
		self::$r			= array ();
	}
	// }}}

	// {{{ (int) sThread_MYSQL::check_buf_status ($status)
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
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::MYSQL_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_MYSQL::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::MYSQL_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_MYSQL::clear_session ($key) {
	/*
	 * self::$clearsession == false 일 경우, clear_session method
	 * 는 존재하지 않아도 된다.
	 */
	function clear_session ($key) {
		self::$server = '';
		self::$qstatus  = 0;
		self::$columnno = 0;
		self::$columnid = 0;
		self::$rowid    = 0;
		self::$column   = array ();
		self::$r        = array ();
		return;
	}
	// }}}

	/*
	 * Handler Definition
	 * handler name is get sThread_MODULE::call_status API
	 */

	// {{{ (bool) function mysql_banner (&$sess, $key, $recv)
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
			return null;
		}

		if ( strlen ($sess->recv[$key]) != $server->length )
			return false;

		$sess->recv[$key] = substr ($sess->recv[$key], 4);
		self::parse_handshake ($sess->recv[$key], self::$server);
		return true;
	} // }}}

	// {{{ (binary) function mysql_sendauth (&$sess, $key)
	function mysql_sendauth (&$sess, $key) {
		list ($host, $port, $type) = $sess->addr[$key];
		$opt = self::extraOption ($type);

		self::$server->user     = $opt->user;
		self::$server->passwd   = $opt->pass;
		self::$server->database = $opt->database;
		self::$server->query    = $opt->query;

		return self::send_authenication (self::$server);
	} // }}}

	// {{{ (bool) function mysql_handshake (&$sess, $key, $recv)
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
			return null;
		}

		return true;
	}
	// }}}

	// {{{ (binary) function mysql_sendquery (&$sess, $key)
	function mysql_sendquery (&$sess, $key) {
		return self::query_packet (self::MYSQL_COM_QUERY, self::$server->query);
	} // }}}

	// {{{ (bool) function mysql_queryres (&$sess, $key, $recv)
	function mysql_queryres (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		$sess->recv[$key] .= $recv;
		if ( strlen ($sess->recv[$key]) < 5 )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];

		while ( true ) {
			$length = self::packet_length ($sess->recv[$key]);
			$packet_number = ord ($sess->recv[$key][3]);

			if ( $packet_number !== (self::$qstatus + 1) ) {
				Vari::$res->status[$key] = array (
					"{$host}:{$port}",
					false,
					"Protocol error: Invalid Query Result Packet Number : {$packet_number}"
				);
				return null;
			}

			$buf = substr ($sess->recv[$key], 4, $length);
			if ( strlen ($buf) != $length )
				return false;

			// Result Set Header Packet
			if ( self::$qstatus == 0 ) {
				if ( self::parse_result ($r, $buf) != self::MYSQL_RET_QUERY ) {
					Vari::$res->status[$key] = array (
						"{$host}:{$port}",
						false,
						"Protocol error: : Invalid Query Result Type"
					);
					fwrite ($sess->sock[$key], self::quit_packet (), 5);
					return null;
				}
				self::$columnno = self::length_coded_binary ($buf);
				self::$qstatus++;

				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
				continue;
			}

			// Column Descriptors
			if ( self::$qstatus > 0 && self::$qstatus < self::$columnno + 1 ) {
				self::$column[self::$columnid]->catalog = self::length_coded_string ($buf);
				self::$column[self::$columnid]->db = self::length_coded_string ($buf);
				self::$column[self::$columnid]->table = self::length_coded_string ($buf);
				self::$column[self::$columnid]->org_table = self::length_coded_string ($buf);
				self::$column[self::$columnid]->name = self::length_coded_string ($buf);
				self::$column[self::$columnid]->old_name = self::length_coded_string ($buf);
				self::$column[self::$columnid]->filler = ord ($buf[0]);
				self::$column[self::$columnid]->charsetnr = unpack ('S', substr ($buf, 1, 2));
				self::$column[self::$columnid]->length = unpack ('L', substr ($buf, 3, 4));
				self::$column[self::$columnid]->type = '0x' . dechex (ord ($buf[7]));
				self::$column[self::$columnid]->flag = substr ($buf, 8, 2);
				self::$column[self::$columnid]->decimals = $buf[10];
				self::$column[self::$columnid]->filler = unpack ('S', substr ($buf, 11, 2));
				$buf = substr ($buf, 13);
				self::$column[self::$columnid++]->default = self::length_coded_binary ($buf);

				self::$qstatus++;

				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
				continue;
			}

			// EOF Packet: end of Field Packets
			if ( self::$qstatus == (self::$columnno + 1) ) {
				if ( self::parse_result ($r, $buf) != self::MYSQL_RET_EOF ) {
					Vari::$res->status[$key] = array (
						"{$host}:{$port}",
						false,
						"Protocol error: : Invalid EOF Type of Packet"
					);
					fwrite ($sess->sock[$key], self::quit_packet (), 5);
					return null;
				}

				self::$qstatus++;
				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
				continue;
			}

			// Row Data Packets: row contents
			if ( self::$qstatus > (self::$columnno + 1) ) {
				//
				// end of packet
				// ----------------------------------------------------------------
				if ( self::parse_result ($r, $buf) == self::MYSQL_RET_EOF ) {
					//print_r (self::$r);
					return true;
				}
				// ----------------------------------------------------------------
				//

				for ( $i=0; $i<self::$columnno; $i++ ) {
					$colname = self::$column[$i]->name->data;
					self::$r[self::$rowid]->$colname = self::length_coded_string ($buf)->data;
				}
				self::$rowid++;
				self::$qstatus++;
				$sess->recv[$key] = substr ($sess->recv[$key], $length + 4);
			}
		}

		return false;
	} // }}}

	// {{{ (binary) function mysql_quit (&$sess, $key) {
	function mysql_quit (&$sess, $key) {
		return self::quit_packet ();
	} // }}}

	/*
	 * ********************************************************************************
	 * User define functions
	 * ********************************************************************************
	 */
	// {{{ (object) sThread_MYSQL::extraOption (&$type)
	function extraOption (&$type) {
		if ( ! preg_match ('/^(.+)\|(.+)$/', $type, $matches) )
			return false;

		$type = $matches[1];
		$buf = explode (',', $matches[2]);

		$r = (object) array ();
		foreach ( $buf as $val ) {
			if ( ! preg_match ('/^(.+)=>(.+)/', $val, $matches) )
				continue;

			$key = trim ($matches[1]);
			$r->$key = trim ($matches[2]);
		}

		return $r;
	}
	// }}}

	// {{{ (void) function hexview ($buf, $len, $t = false)
	function hexview ($buf, $len, $t = false) {
		for ( $i=0; $i<$len; $i++ )
			printf ("%3d -> 0x%2x\n", $i+1, ($t === false) ? ord($buf[$i]) : $buf[$i]);
	} // }}}

	// {{{ (void) function hexview ($buf, $len, $t = false)
	function hexview_dump ($buf, $len, $t = false) {
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

	// {{{ (void) function scramble (&$to, $salt, $passwd)
	function scramble (&$to, $salt, $passwd) {
		$pass1 = sha1 ($passwd, true);
		$pass2 = sha1 ($pass1, true);
		$pass3 = sha1 ($salt . $pass2, true);
		$to = $pass3 ^ $pass1;
	} // }}}

	// {{{ (integer) function packet_length (&$buf)
	function packet_length (&$buf) {
		$r = unpack ('l', substr ($buf, 0, 3) . "\0");
		return $r[1];
	} // }}}

	// {{{ (object) function length_coded_string (&$buf)
	function length_coded_string (&$buf) {
		$r->length = ord ($buf[0]);
		$r->data = substr ($buf, 1, $r->length);

		$buf = substr ($buf, $r->length + 1);

		return $r;
	} // }}}

	// {{{ (binary) function length_coded_binary (&$buf)
	function length_coded_binary (&$buf) {
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

	// {{{ (void) function parse_handshake ($buffer, &$server)
	function parse_handshake ($buffer, &$server) {
		$server->protocol_version = ord ($buffer[0]);

		$server_version_length = $handshake_length - 44;
		$server->server_version = substr ($buffer, 1, $server_version_length - 1);
		$buffer = substr ($buffer, $server_version_length);
		$buf = unpack ('L', $buffer);
		$server->thread_number = ord ($buf[1]);
		$server->scramble_buff = substr ($buffer, 4, 8);

		$buffer = substr ($buffer, 13);
		$buf = unpack ('Scapa/clang/Sstatus', $buffer);
		$server->server_capabilities = $buf['capa'];
		$server->server_language = ord($buf['lang']);
		$server->server_status = $buf['status'];

		$buffer =  substr ($buffer, 18, 12);
		$server->scramble_buff .= $buffer;
	} // }}}

	// {{{ (binary) function send_authenication (&$client)
	function send_authenication (&$client) {
		if ( ! $client->user )
			return false;

		# client_flag
		$s = pack ('c*', 0x8d, 0xa6, 0x03, 0x00);
		# max_packaet_size
		$s .= pack('c*', 0x00, 0x00, 0x00, 0x01);
		# charset_number (use latin1, euckr -> 19 or 0x13)
		$s .= pack ('c', $client->server_language);
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

	// {{{ (integer) function parse_result (&$r, $buf)
	function parse_result (&$r, $buf) {
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

	// {{{ (binary) function query_packet ($type, $arg)
	function query_packet ($type, $arg) {
		$arg = preg_replace ('/;$/', '', $arg);

		$s = pack ('c', $type);
		$s .= $arg;

		$send = pack ('Sxc', strlen ($s), 0x00) . $s;
		return $send;
	} // }}}

	// {{{ (binary) function quit_packet ()
	function quit_packet () {
		$send = pack ('c*', 0x01, 0x00, 0x00, 0x00, 0x01);
		return $send;
	} // }}}
}

?>

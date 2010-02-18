<?php
/*
 * sThread DNS module
 * See also http://www.freesoft.org/CIE/RFC/1035/39.htm
 *
 * $Id: dns.php,v 1.2 2010-02-18 05:22:49 oops Exp $
 */
Class sThread_DNS {
	static public $clearsession = true;
	static public $port = 53;

	const DNS_REQUEST  = 1;
	const DNS_RESPONSE = 2;
	const DNS_CLOSE    = 3;

	// {{{ properties
	const OP_QUERY  = 0x00;
	const OP_IQUERY = 0x01;
	const OP_STATUS = 0x02;

	const QTYPE_A     = 1;
	const QTYPE_NS    = 2;
	const QTYPE_CNAME = 5;
	const QTYPE_SOA   = 6;
	const QTYPE_PTR   = 12;
	const QTYPE_HINFO = 13;
	const QTYPE_MX    = 15;

	const QCLASS_IN   = 1;
	const QCLASS_CH   = 3;
	const QCLASS_HS   = 4;

	static private $header_id;
	static private $flag;
	static private $header_member = array ('id', 'flags', 'noq', 'noans', 'noauth', 'noadd');
	static private $dnserr;
	static public $recv;
	// }}}

	// {{{ (void) sThread_DNS::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
		$this->recv         = &self::$recv;
	}
	// }}}

	// {{{ (void) sThread_DNS::init (void)
	function init () {
		self::$clearsession = true;
		self::$port = 53;
	}
	// }}}

	// {{{ (int) sThread_DNS::check_buf_status ($status)
	function check_buf_status ($status) {
		switch ($status) {
			case 0 :
			case self::DNS_REQUEST :
				return Vari::EVENT_READY_SEND;
				break;
			case self::DNS_RESPONSE :
				return Vari::EVENT_READY_RECV;
				break;
			case self::DNS_CLOSE :
				return Vari::EVENT_READY_CLOSE;
				break;
			default :
				return Vari::EVENT_UNKNOWN;
		}
	}
	// }}}

	// {{{ (string) sThread_DNS::call_status ($status, $call = false)
	function call_status ($status, $call = false) {
		switch ($status) {
			case self::DNS_REQUEST :
				$r = 'DNS_REQUEST';
				break;
			case self::DNS_RESPONSE :
				$r = 'DNS_RESPONSE';
				break;
			default:
				$r = Vari::EVENT_UNKNOWN;
		}

		if ( $call !== false && $r !== Vari::EVENT_UNKNOWN )
			$r = strtolower ($r);

		return $r;
	}
	// }}}

	// {{{ (boolean) sThread_DNS::change_status (&$sess, $key)
	function change_status (&$sess, $key) {
		++$sess->status[$key];

		if ( $sess->status[$key] === self::DNS_CLOSE )
			return false;

		return true;
	}
	// }}}

	// {{{ (void) sThread_DNS::set_last_status (&$sess, $key)
	function set_last_status (&$sess, $key) {
		$sess->status[$key] = self::DNS_CLOSE;
	}
	// }}}

	// {{{ (boolean) sThread_DNS::clear_session ($key) {
	/*
	 * self::$clearsession == false 일 경우, clear_session method
	 * 는 존재하지 않아도 된다.
	 */
	function clear_session ($key) {
		self::$header_id = '';
		self::$flag = '';
		self::$dnserr = '';
		self::$recv = '';
		return;
	}
	// }}}

	/*
	 * Handler Definition
	 * handler name is get sThread_MODULE::call_status API
	 */

	// {{{ (string) sThread_DNS::dns_request (&$sess, $key)
	function dns_request (&$sess, $key) {
		list ($host, $port, $type) = $sess->addr[$key];
		$opt = $sess->opt[$key];


		if ( ! $opt->query ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				"[DNS] Query domain is null"
			);
			return false;
		}

		if ( ! $opt->record )
			$opt->record = 'A';

		switch ($opt->record) {
			case 'A' :
				$opt->record = self::QTYPE_A;
				break;
			case 'MX' :
				$opt->record = self::QTYPE_MX;
				break;
			case 'PTR' :
				$opt->record = self::QTYPE_PTR;
				break;
			case 'NS' :
				$opt->record = self::QTYPE_NS;
				break;
			case 'CNAME' :
				$opt->record = self::QTYPE_CNAME;
				break;
			default :
				self::$dnserr = sprintf ('[DNS] Invalid query type : "%s"', $opt->record);
				Vari::$res->status[$key] = array (
					"{$host}:{$port}",
					false,
					self::$dnserr
				);
				return false;
		}

		$send = self::query ($opt->query, $opt->record);
		if ( $send === false ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				self::$dnserr
			);
			return false;
		}

		return $send->data;
	}
	// }}}

	// {{{ (boolean) sThread_DNS::dns_response (&$sess, $key, $recv)
	function dns_response (&$sess, $key, $recv) {
		if ( ! $recv )
			return false;

		list ($host, $port, $type) = $sess->addr[$key];
		$sess->recv[$key] .= $recv;
		$rlen = strlen ($sess->recv[$key]);

		// at least, dns header size is 32byte
		if ( $rlen < 32 )
			return false;

		self::$recv->data = $sess->recv[$key];
		self::$recv->length = $rlen;

		$r = self::recv_header ($sess->recv[$key]);
		if ( $r === false ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				self::$dnserr
			);
			return null;
		}

		if ( self::$recv->header->flags->rcode != 'NOERROR' ) {
			$err = sprintf ('[DNS] Return RCODE flag "%s"', self::$recv->header->flags->rcode);
			Vari::$res->status[$key] = array ("{$host}:{$port}", false, $err);
			return null;
		}

		if ( self::$recv->header->noans == 0 ) {
			$err = '[DNS] No return result';
			Vari::$res->status[$key] = array ("{$host}:{$port}", false, $err);
			return null;
		}

		$sess->recv[$key] = '';
		return true;
	} // }}}


	/*
	 * ********************************************************************************
	 * User define functions
	 * ********************************************************************************
	 */

	/*
	 * Debugging API
	 */
	// {{{ {void) function print_query_packet ($packet)
	function print_query_packet ($packet, $return = false) {
		for ( $i=0; $i<strlen ($packet); $i++ ) {
			if ( ($i % 8) == 0 ) {
				if ( ($i % 16) == 0 )
					if ( $return )
						$r .= "\n";
					else
						echo "\n";
				else
					if ( $return )
						$r .= " ";
					else
						echo '  ';
			}
			if ( $return )
				$r .= sprintf ("%02x ", ord ($packet[$i]));
			else
				printf ("%02x ", ord ($packet[$i]));
		}

		if ( $return )
			return $r;

		echo "\n";
	} // }}}

	/*
	 * DNS packet header
	 */
	// {{{ (binary|string) function random_id ($encode = false)
	function random_id ($encode = false) {
		$id = mt_rand (0, 65535);
		return $encode ? pack ('n', $id) : $id;
	} // }}}

	// (bin string) function make_opcode ($type) {{{
	//
	// Query            0
	// Inverse query    1
	// Status           2
	// Reserved         3-15
	function make_opcode ($type) {
		if ( $type > 0x0f ) {
			self::$dnserr = sprintf ('[DNS] unknown opcode type %d', $type);
			return false;
		}

		switch ($type) {
			case self::OP_QUERY :
				return '0000';
				break;
			case self::OP_IQUERY :
				return '0001';
				break;
			case self::OP_STATUS :
				return '0010';
				break;
			default :
				self::$dnserr = sprintf ('[DNS] unknown opcode type %d', $type);
				return false;
		}
	} // }}}

	// {{{ function query_type ($v, $convert = false)
	// if $convert set false, don't convert to decimal
	function query_type ($v, $convert = true) {
		if ( $convert ) {
			$buf = unpack ('n', $v);
			$v = $buf[1];
		}

		switch ($v) {
			case self::QTYPE_A :
				return 'A';
			case self::QTYPE_NS :
				return 'NS';
			case self::QTYPE_CNAME :
				return 'CNAME';
			case self::QTYPE_PTR :
				return 'PTR';
			case self::QTYPE_SOA :
				return 'SOA';
			case self::QTYPE_HINFO:
				return 'HINFO';
			case self::QTYPE_MX:
				return 'MX';
			default:
				self::$dnserr = sprintf ('[DNS] unknown query type 0x%02x', $v);
				return 'Unknown';
		}
	} // }}}

	// {{{ function query_class ($v, $convert = false)
	// if $convert set true, convert binary code
	function query_class ($v, $convert = true) {
		if ( $convert ) {
			$buf = unpack ('n', $v);
			$v = $buf[1];
		}

		switch ($v) {
			case self::QCLASS_IN :
				return 'IN';
			case self::QCLASS_CH :
				return 'CH';
			case self::QCLASS_HS :
				return 'HS';
			default :
				self::$dnserr = sprintf ('[DNS] 0x%02x is %s query class.', $v, $v ? 'unknown' : 'reserved');
				return false;
		}
	} // }}}

	/* {{{ (void) function make_header (&$buf)
	 * DNS Header Packet structure
	 *
	 * 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
	 * +-------------------------------------------------------------+
	 * |      Identification          |            flags             |
	 * +------------------------------+------------------------------+
	 * |   number of questions        |       number of answer RRs   |
	 * +------------------------------+------------------------------+
	 * | number of authority RRs      |    number of additional RRs  |
	 * +------------------------------+------------------------------+
	 *
	 * type => OP_QUERY      nomal query
	 *         OP_IQUERY     inverse query
	 *         OP_STATUS     status query
	 */
	function make_header (&$buf) {
		self::$header_id = self::random_id (true);

		$buf = self::$header_id;      // Identification
		$buf .= pack ('n', 0x0100);   // flags
		$buf .= pack ('n', 1);        // number of questions
		$buf .= pack ('x6');          // rest
	} // }}}

	// {{{ (binary) function dns_string ($v)
	function dns_string ($domain) {
		$p = explode ('.', $domain);
		$len = count ($p);

		if ( ! is_array ($p) )
			return null;

		$r = '';
		foreach ( $p as $n ) {
			$nlen = strlen ($n);
			$r .= chr ($nlen);
			$r .= $n;
		}

		return $r . "\0";
	} // }}}

	// {{{ (void) function make_question (&$buf, $domain)
	function make_question (&$buf, $domain) {
		$buf .= self::dns_string ($domain);
	} // }}}

	// {{{ (object|boolean) function query ($domain, $type)
	function query ($domain, $type) {
		self::make_header ($buf);

		if ( preg_match ('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $domain) )
			$query_type = self::OP_IQUERY;
		else
			$query_type = self::OP_QUERY;

		if ( $query_type === self::OP_IQUERY ) {
			$ip = explode ('.', $domain);
			if ( count ($ip) !== 4 ) {
				self::$dnserr = sprintf ('[DNS] %s is invalid ip format', $domain);
				return false;
			}

			foreach (array_reverse ($ip) as $v ) {
				if ( $v > 255 ) {
					self::$dnserr = sprintf ('[DNS] %s is invalid ip format', $domain);
					return false;
				}
				$arpa .= $v . '.';
			}
			$arpa .= 'in-addr.arpa';
		}

		self::make_question ($buf, isset ($arpa) ? $arpa : $domain);
		$buf .= pack ('n', $type);
		$buf .= pack ('n', self::QCLASS_IN);

		$header->data = $buf;
		$header->length  = strlen ($buf);

		return $header;
	} // }}}

	// {{{ (string) function recv_flags_opcode ($v)
	function recv_flags_opcode ($v) {
		switch ($v) {
			case '0000' :
				return 'QUERY';
				break;
			case '0001' :
				return 'IQUERY';
				break;
			case '0010' :
				return 'STATUS';
				break;
			default :
				self::$dnserr = sprintf ('[DNS} unknown opcode type %02x', bindec ($v));
				return false;
		}
	} // }}}

	// {{{ (void) function recv_flags_rcode ($v)
	function recv_flags_rcode ($v) {
		$v = bindec ($v);

		switch ($v) {
			case 0 :
				return 'NOERROR';
				break;
			case 1 :
				return 'FORMAT ERROR';
				break;
			case 2 :
				return 'SERVER FAILURE';
				break;
			case 3 :
				return 'NAME ERROR';
				break;
			case 4 :
				return 'NOT IMPLEMENTED';
				break;
			case 5 :
				return 'REFUSED';
				break;
			default :
				// reserved for future use
				return 'RESERVED';
		}
	} // }}}

	/* (object) {{{ function recv_header_flags ($v)
	 *
	 * flag 분석
	 * -- 16-bits Flags --
	 * +----+--------+----+----+----+----+--------+-------+
	 * | QR | opcode | AA | TC | RD | RA | (zero) | rcode |
	 * +----+--------+----+----+----+----+--------+-------+
	 *   1      4      1    1    1    1      3        4     (bits)
	 */
	function recv_header_flags ($v) {
		$buf = &self::$recv->header->flags;
		$v = decbin ($v);
		$buf->qr = $v[0];
		$buf->opcode = self::recv_flags_opcode (substr ($v, 1, 4));
		$buf->aa = $v[5];
		$buf->tc = $v[6];
		$buf->rd = $v[7];
		$buf->ra = $v[8];
		$buf->rcode = self::recv_flags_rcode (substr ($v, 12));

		return $buf;
	} // }}}

	// {{{ (boolean) function recv_header ($v)
	function recv_header ($v) {
		if ( strlen ($v) < 12 ) {
			self::$dnserr = '[DNS] Recived header is over 12 characters';
			return false;
		}

		if ( self::$header_id !== substr ($v, 0, 2) ) {
			self::$dnserr = sprintf (
				'[DNS] Don\'t match packet id (send: 0x%04x, recv 0x%04x)',
				ord (self::$header_id), substr ($v, 0, 2)
			);

			return false;
		}

		self::$recv->header->data = substr ($v, 0, 12);
		self::$recv->header->length = 12;
		$buf = unpack ('n*', self::$recv->header->data);

		for ( $i=0; $i<6; $i++ ) {
			if ( self::$header_member[$i] == 'flags' ) {
				self::$recv->header->{self::$header_member[$i]}->data = $buf[$i+1];
				self::recv_header_flags ($buf[$i+1]);
			} else
				self::$recv->header->{self::$header_member[$i]} = $buf[$i+1];
		}

		return true;
	} // }}}

	// {{{ (object) function length_coded_string (&$v)
	function length_coded_string (&$v) {
		$len = ord ($v[0]);
		$r->length = $len + 1;

		if ( $len === 0 ) {
			$v = substr ($v, 1);
			return null;
		}

		if ( strlen ($v) < $len + 1 )
			return false;

		$r->data = substr ($v, 1, $len);
		$v = substr ($v, $len + 1);

		return $r;
	} // }}}

	// {{{ (void) function recv_question ($v)
	function recv_question ($v) {
		$v = substr ($v, 12);
		$z = $v;
		self::$recv->question->length = 0;

		while ( ($rr = self::length_coded_string ($z)) != null ) {
			if ( $rr === false )
				return false;
			self::$recv->question->qname .= $rr->data . '.';
			self::$recv->question->length += $rr->length;
		}

		self::$recv->question->type = self::query_type (substr ($z, 0, 2));
		self::$recv->question->length += 2;
		self::$recv->question->class = self::query_class (substr ($z, 2, 2));
		self::$recv->question->length += 2;
		self::$recv->question->data = substr ($v, 0, self::$recv->question->length);
	} // }}}

	// {{{ (void) function recv_rdata (&$v)
	function recv_rdata (&$v) {
		$buf = self::$recv->data;
		$vlen = $v->rdlen;

		switch ($v->type) {
			case 'A' :
				$ip = unpack ('N', $v->rdata);
				$v->rdata = long2ip ($ip[1]);
				break;
			case 'MX' :
				$mx = unpack ('n', $v->rdata[0] . $v->rdata[1]);
				$v->mx = $mx[1];
				$v->rdata = substr ($v->rdata, 2);
				$vlen -= 2;
			case 'PTR' :
			case 'CNAME' :
				$rdata = '';
				for ( $i=0; $i<$vlen; $i++ ) {
					$len = ord ($v->rdata[$i]);
					if ( $len == 0 )
						break;

					if ( $len == 0xc0 ) {
						$pos = ord ($v->rdata[$i+1]);
						$rlen = ord ($buf[$pos]);

						while ( $rlen != 0 ) {
							$rdata .= substr ($buf, $pos + 1, $rlen) . '.';
							$pos += $rlen + 1;
							$rlen = ord ($buf[$pos]);

							if ( $rlen == 0xc0 ) {
								$pos = ord ($buf[$pos + 1]);
								$rlen = ord ($buf[$pos]);
							}
						}
						$i += 2;
					} else {
						$rdata .= substr ($v->rdata, $i + 1, $len) . '.';
						$i += $len;
					}
				}
				$v->rdata = $rdata;
				break;
			case 'SOA' :
				$rdata = '';
				$soa_count = 0;
				for ( $i=0; $i<$v->rdlen; $i++ ) {
					$len = ord ($v->rdata[$i]);
					if ( $len == 0 ) {
						if ( $soa_count++ == 1 )
							break;

						$rdata .= ' ';
						continue;
					}

					if ( $len == 0xc0 ) {
						$pos = ord ($v->rdata[$i+1]);
						$rlen = ord ($buf[$pos]);

						while ( $rlen != 0 ) {
							$rdata .= substr ($v->rdata, $pos + 1, $rlen) . '.';
							$pos += $rlen + 1;
							$rlen = ord ($buf[$pos]);

							if ( $rlen == 0xc0 ) {
								$pos = ord ($buf[$pos + 1]);
								$rlen = ord ($buf[$pos]);
							}
						}
						$i += 2;
					} else {
						$rdata .= substr ($v->rdata, $i + 1, $len) . '.';
						$i += $len;
					}
				}

				$soa_field = unpack ('N4', substr ($v->rdata, $i + 1));
				$v->rdata = $rdata;
				foreach ( $soa_field as $vv )
					$v->rdata .= ' ' . $vv;

				break;
		}
	} // }}}

	// {{{ (void) function recv_resource ($v)
	function recv_resource ($v) {
		$start = self::$recv->header->length + self::$recv->question->length + 1;
		self::$recv->resource->data = substr ($v, $start);
		self::$recv->resource->length = strlen (self::$recv->resource->data);

		$res = &self::$recv->resource;
		$buf = $res->data;
		$header = &self::$recv->header;
		$question = &self::$recv->question;

		$idx = 0; // position of resource
		$idn = 0; // value of position of resource
		$pnt = 0; // position of question

		$limit = $header->noans ? $header->noans : 1;
		for ( $i=0; $i<$limit; $i++ ) {
			$idn = ord ($buf[$idx]);

			if ( $idn == 0xc0 ) {
				$pnt = ord ($buf[++$idx]);
				$qpnt = $pnt - $header->length;

				$qbuf = substr ($question->data, $qpnt);
			} else {
				$qbuf = substr ($buf, $idx);
			}

			unset ($rr);
			while ( ($rr = self::length_coded_string ($qbuf)) != null ) {
				if ( $rr === false )
					return false;
				$res->v[$i]->name .= $rr->data . '.';
				$res->v[$i]->length += $rr->length;
			}

			if ( $idn != 0xc0 )
				$idx += $res->v[$i]->length;

			$rdata = unpack ('n2type/N1ttl/n1rdlen', substr ($buf, $idx + 1));
			$res->v[$i]->type = self::query_type ($rdata['type1'], false);
			$res->v[$i]->class = self::query_class ($rdata['type2'], false);
			$res->v[$i]->ttl = $rdata['ttl'];
			$res->v[$i]->rdlen = $rdata['rdlen'];
			$res->v[$i]->rdata = substr ($buf, $idx + 10 + 1, $res->v[$i]->rdlen);

			self::recv_rdata ($res->v[$i]);
			$buf = substr ($buf, $idx + 10 + $res->v[$i]->rdlen + 1);
			$idx = 0;
		}
	} // }}}

	// {{{ (void) function recv_parse ($v)
	function recv_parse ($v) {
		self::$recv->data = $v;
		self::$recv->length = strlen ($v);

		self::recv_header ($v);
		self::recv_question ($v);
		self::recv_resource ($v);
		print_r (self::$recv);
	} // }}}
}

?>

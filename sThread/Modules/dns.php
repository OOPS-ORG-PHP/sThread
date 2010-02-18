<?php
/*
 * sThread DNS module
 * See also http://www.freesoft.org/CIE/RFC/1035/39.htm
 *
 * $Id: dns.php,v 1.4 2010-02-18 06:39:07 oops Exp $
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

	static private $header_member = array ('id', 'flags', 'noq', 'noans', 'noauth', 'noadd');
	static public $dns;
	// }}}

	// {{{ (void) sThread_DNS::__construct (void)
	function __construct () {
		self::init ();
		$this->clearsession = &self::$clearsession;
		$this->port         = &self::$port;
		$this->dns          = &self::$dns;
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
		self::$dns = array ();
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
				$err = sprintf ('[DNS] Invalid query type : "%s"', $opt->record);
				Vari::$res->status[$key] = array (
					"{$host}:{$port}", false, $err
				);
				return false;
		}

		$send = self::query ($key, $opt->query, $opt->record);
		if ( $send === false ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}", false, self::$dns[$key]->err
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

		self::$dns[$key]->recv->data = $sess->recv[$key];
		self::$dns[$key]->recv->length = $rlen;

		$r = self::recv_header ($key, $sess->recv[$key]);
		if ( $r === false ) {
			Vari::$res->status[$key] = array (
				"{$host}:{$port}",
				false,
				self::$dns[$key]->err
			);
			return null;
		}

		if ( self::$dns[$key]->recv->header->flags->rcode != 'NOERROR' ) {
			$err = sprintf ('[DNS] Return RCODE flag "%s"', self::$dns[$key]->recv->header->flags->rcode);
			Vari::$res->status[$key] = array ("{$host}:{$port}", false, $err);
			return null;
		}

		if ( self::$dns[$key]->recv->header->noans == 0 ) {
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
				return sprintf ('Unknown: 0x%02x', $v);
		}
	} // }}}

	// {{{ (void) function query_class ($v, $convert = false)
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
				if ( $v )
					return sprintf ('Unknown: 0x%02x', $v);
				else
					return sprintf ('Reserved: 0x%02x', $v);
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
	function make_header ($key, &$buf) {
		self::$dns[$key]->header_id = self::random_id (true);

		$buf = self::$dns[$key]->header_id;      // Identification
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
	function query ($key, $domain, $type) {
		self::make_header ($key, $buf);

		if ( preg_match ('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $domain) )
			$query_type = self::OP_IQUERY;
		else
			$query_type = self::OP_QUERY;

		if ( $query_type === self::OP_IQUERY ) {
			$ip = explode ('.', $domain);
			if ( count ($ip) !== 4 ) {
				self::$dns[$key]->err = sprintf ('[DNS] %s is invalid ip format', $domain);
				return false;
			}

			foreach (array_reverse ($ip) as $v ) {
				if ( $v > 255 ) {
					self::$dns[$key]->err = sprintf ('[DNS] %s is invalid ip format', $domain);
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
				return sprintf ('Unknown: %02x', bindec (substr ($v, 1, 4)));
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

	/* (void) {{{ function recv_header_flags ($key, $v)
	 *
	 * flag 분석
	 * -- 16-bits Flags --
	 * +----+--------+----+----+----+----+--------+-------+
	 * | QR | opcode | AA | TC | RD | RA | (zero) | rcode |
	 * +----+--------+----+----+----+----+--------+-------+
	 *   1      4      1    1    1    1      3        4     (bits)
	 */
	function recv_header_flags ($key, $v) {
		$buf = &self::$dns[$key]->recv->header->flags;
		$v = decbin ($v);
		$buf->qr = $v[0];
		$buf->opcode = self::recv_flags_opcode (substr ($v, 1, 4));
		$buf->aa = $v[5];
		$buf->tc = $v[6];
		$buf->rd = $v[7];
		$buf->ra = $v[8];
		$buf->rcode = self::recv_flags_rcode (substr ($v, 12));
	} // }}}

	// {{{ (boolean) function recv_header ($key, $v)
	function recv_header ($key, $v) {
		if ( strlen ($v) < 12 ) {
			self::$dns[$key]->err = '[DNS] Recived header is over 12 characters';
			return false;
		}

		if ( self::$dns[$key]->header_id !== substr ($v, 0, 2) ) {
			$header_id = unpack ('n', self::$dns[$key]->header_id);
			$recv_id   = unpack ('n', substr ($v, 0, 2));
			self::$dns[$key]->err = sprintf (
				'[DNS] Don\'t match packet id (send: 0x%04x, recv 0x%04x)',
				$header_id[1], $recv_id[1]
			);

			return false;
		}

		self::$dns[$key]->recv->header->data = substr ($v, 0, 12);
		self::$dns[$key]->recv->header->length = 12;
		$buf = unpack ('n*', self::$dns[$key]->recv->header->data);

		for ( $i=0; $i<6; $i++ ) {
			if ( self::$header_member[$i] == 'flags' ) {
				self::$dns[$key]->recv->header->{self::$header_member[$i]}->data = $buf[$i+1];
				self::recv_header_flags ($key, $buf[$i+1]);
			} else
				self::$dns[$key]->recv->header->{self::$header_member[$i]} = $buf[$i+1];
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

	// {{{ (void) function recv_question ($key, $v)
	function recv_question ($key, $v) {
		$v = substr ($v, 12);
		$z = $v;
		$ques = &self::$dns[$key]->recv->question;
		$ques->length = 0;

		while ( ($rr = self::length_coded_string ($z)) != null ) {
			if ( $rr === false )
				return false;
			$ques->qname .= $rr->data . '.';
			$ques->length += $rr->length;
		}

		$ques->type = self::query_type (substr ($z, 0, 2));
		$ques->length += 2;
		$ques->class = self::query_class (substr ($z, 2, 2));
		$ques->length += 2;
		$ques->data = substr ($v, 0, $ques->length);
	} // }}}

	// {{{ (void) function recv_rdata ($key, &$v)
	function recv_rdata ($key, &$v) {
		$buf = self::$dns[$key]->recv->data;
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

	// {{{ (void) function recv_resource ($key, $v)
	function recv_resource ($key, $v) {
		$header   = &self::$dns[$key]->header;
		$question = &self::$dns[$key]->question;

		$start = $header->length + $question->length + 1;
		self::$dns[$key]->recv->resource->data = substr ($v, $start);
		self::$dns[$key]->recv->resource->length = strlen (self::$dns[$key]->recv->resource->data);

		$res = &self::$dns[$key]->recv->resource;
		$buf = $res->data;

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

			self::recv_rdata ($key, $res->v[$i]);
			$buf = substr ($buf, $idx + 10 + $res->v[$i]->rdlen + 1);
			$idx = 0;
		}
	} // }}}

	// {{{ (void) function recv_parse ($v)
	function recv_parse ($key, $v) {
		self::$dns[$key]->recv->data = $v;
		self::$dns[$key]->recv->length = strlen ($v);

		self::recv_header ($key, $v);
		self::recv_question ($key, $v);
		self::recv_resource ($key, $v);
		//print_r (self::$dns[$key]->recv);
	} // }}}
}

?>

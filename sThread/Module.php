<?php
/**
 *
 * Single Thread Monitoring Module API
 * File: sThread/Module.php
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
 * @version     CVS: $Id: Module.php,v 1.6 2009-09-30 18:19:37 oops Exp $
 * @link        http://pear.oops.org/package/sThread
 */
Class sThread_Module {
	// {{{ properties
	static public $obj;
	static public $port;
	static private $moddir = '/sThread/Modules';
	// }}}

	// {{{ (void) __construct (void)
	function __construct () {
		self::init ();
		$this->obj = &self::$obj;
		$this->port = &self::$port;
		$this->moddir = &self::$moddir;
	}
	// }}}

	// {{{ (void) MODULE::init (void)
	/*
	 * ./module 디렉토리에 있는 모듈들을 읽어서 $obj에 등록.
	 * $port에는 module을 키로 하여 모듈 포트를 저장.
	 */
	function init () {
		$mods_r = explode (':', ini_get ('include_path'));
		foreach ( $mods_r as $_mods ) {
			if ( is_dir ($_mods . self::$moddir) ) {
				self::$moddir = $_mods . self::$moddir;
				break;
			}
		}
		$mods = @glob (self::$moddir . '/*.php', GLOB_MARK|GLOB_BRACE);

		if ( ($env = getenv ('STHREAD_MODULES')) !== false ) {
			if ( is_dir ($env) ) {
				$user_mod = @glob ($env . '/*.php', GLOB_MARK|GLOB_BRACE);
				if ( is_array ($user_mod) )
					$mods = array_merge ($mods, $user_mod);
			}
		}

		if ( count ($mods) == 0 ) {
			ePrint::ePrintf ('Error: Availble module is not found');
			exit (1);
		}

		foreach ( $mods as $mod ) {
			require_once "$mod";
			$mod = basename ($mod, '.php');
			$class = 'sThread_' . strtoupper ($mod);
			if ( ! class_exists ($class, false) ) {
				ePrint::ePrintf (
					'Warning: %s is not sThread module structure (%s class not found)',
					array ($mod . '.php', $class)
				);
				continue;
			}
			self::$obj->$mod = new $class;
			self::$port[$mod] = self::$obj->$mod->port;
		}
	}
	// }}}

	// {{{ (int) MODULE::port ($type)
	/*
	 * 모듈 이름으로 사용할 포트를 반환
	 */
	function port ($type) {
		if ( ! $type )
			return false;
		return self::$port[$type];
	}
	// }}}

	// {{{ (string) MODULE::type ($port)
	/*
	 * 포트 번호를 입력 받아, 해당 포트에 대한 모듈 이름을 반환
	 */
	function type ($port) {
		if ( ! $port )
			return false;

		foreach ( self::$port as $key => $val )
			if ( $val == $port )
				return $key;
		return false;
	}
	// }}}
}

sThread_Module::init ();
$mod = &sThread_Module::$obj;
?>

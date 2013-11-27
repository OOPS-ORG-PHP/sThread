<?php
/**
 * Single Thread Monitoring Module API<br>
 * File: sThread/Module.php
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2013 OOPS.ORG
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/sThread
 * @filesource
 */

/**
 * sThread package의 모듈을 관리하는 Class
 *
 * @category    Network
 * @package     sThread
 * @subpackage  sThread_CORE
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2013 OOPS.ORG
 * @license     BSD License
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
		#$this->obj = &self::$obj;
		#$this->port = &self::$port;
		#$this->moddir = &self::$moddir;
	}
	// }}}

	// {{{ (void) sThread_Module::init (void)
	/**
	 * sThread의 모듈을 등록한다.
	 *
	 * get_ini('include_path') 의 경로 하위의 sThread/Moudles
	 * 디렉토리에 있는 모듈을 self::$obj 에 등록을 한다.
	 *
	 * STHREAD_MODULES라는 환경변수가 있을 경우 이 환경변수에
	 * 지정된 디렉토리에 있는 모듈들도 같이 등록을 한다.
	 *
	 * self::$port에는 module 이름을 키로 하여 모듈 포트를
	 * 저장한다.
	 *
	 * @access public
	 * @return void
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
			if ( ! is_object (self::$obj) )
				self::$obj = new stdClass;
			self::$obj->$mod = new $class;
			self::$port[$mod] = self::$obj->$mod->port;
		}
	}
	// }}}

	// {{{ (int) sThread_Moduel::port ($type)
	/**
	 * 모듈 이름으로 사용할 포트를 반환
	 *
	 * @access public
	 * @return int
	 * @param  string 모듈 이름
	 */
	function port ($type) {
		if ( ! $type )
			return false;
		return self::$port[$type];
	}
	// }}}

	// {{{ (string) sThread_Module::type ($port)
	/**
	 * 포트 번호를 입력 받아, 해당 포트에 대한 모듈 이름을 반환
	 *
	 * @access public
	 * @return string
	 * @param  int    포트 번호
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

/**
 * sThread_Module 을 구동
 */
sThread_Module::init ();
$mod = &sThread_Module::$obj;
?>

<?php
Class sThread_Module {
	// {{{ properties
	static public $obj;
	static public $port;
	static private $moddir = './module';
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
		$mods = glob (self::$moddir . '/*.php', GLOB_MARK|GLOB_BRACE);
		foreach ( $mods as $mod ) {
			require_once "$mod";
			$mod = basename ($mod, '.php');
			$class = strtoupper ($mod);
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

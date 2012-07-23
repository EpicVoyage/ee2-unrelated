<?php
class external {
	public $set = 'no';
	function run() {
		$internal = new internal();
		$internal->find();
	}

	function test() {
		echo 'hello: '.$this->set.'<br />';
		return;
	}
}

class internal {
	function __construct() {
		$parent = $this->_get_channel_instance();
		$parent->set = 'yes';
	}

	public function find() {
		$parent = $this->_get_channel_instance();
		$parent->test();
		return;
	}

	private function _get_channel_instance($class = 'external') {
		$trace = debug_backtrace(defined('DEBUG_BACKTRACE_PROVIDE_OBJECT') ? DEBUG_BACKTRACE_PROVIDE_OBJECT : true);
		$ret = null;

		foreach ($trace as $index => &$t) {
			if (isset($t['class']) && ($t['class'] == $class)) {
				$ret = &$t['object'];
				break;
			}
		}

		return $ret;
	}
}

$external = new external();
$external->run();
?>

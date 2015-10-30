<?php
	namespace Relay\Utils;

	use Relay\Conf\Config;

	/**
	 * @author Simon Skrødal
	 * @date   16/09/15
	 * @time   17:11
	 */
	class Utils {
		public static function log($text) {
			if(Config::get('utils')['debug']) {
				$trace  = debug_backtrace();
				$caller = $trace[1];
				error_log($caller['class'] . '->' . $caller['function'] . '::' . $caller['line'] . ': ' . $text);
			}
		}
	}
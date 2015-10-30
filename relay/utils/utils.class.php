<?php
	namespace Relay\Utils;

	use Relay\Conf\Config;
/**
 * @author Simon Skrødal
 * @date 16/09/15
 * @time 17:11
 */

class Utils {
	public static function log($text, $class = '', $function = '', $line = '') {
		if(Config::get('utils')['debug'])
			error_log($class . '->' . $function . '::' . $line . ': ' . $text);
	}
} 
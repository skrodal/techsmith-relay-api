<?php
	namespace Relay\Utils;

	use Relay\Conf\Config;
/**
 * @author Simon Skrødal
 * @date 16/09/15
 * @time 17:11
 */

class Utils {
	public static function log($text, $line, $function) {
		if(Config::get('utils')['debug'])
			error_log($function . '(' . $line . '): ' . $text);
	}
} 
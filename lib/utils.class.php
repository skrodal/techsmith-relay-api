<?php
/**
 * @author Simon Skrødal
 * @date 16/09/15
 * @time 17:11
 */

class Utils {
	static $DEBUG = true;

	public static function log($text, $line, $function) {
		if(Utils::DEBUG) {
			error_log($function . '(' . $line . '): ' . $text);
		}
	}
} 
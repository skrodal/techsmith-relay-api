<?php
/**
 * @author Simon Skrødal
 * @date 16/09/15
 * @time 17:11
 */

class Utils {

	public static function log($text, $line, $function) {
		error_log($function . '(' . $line . '): ' . $text);
	}
} 
<?php

	/**
	 * @author Simon Skrødal
	 * @date   30/10/2015
	 * @time   10:27
	 */
	namespace Relay\Config;

	class Config {
		protected static $config = array();

		public static function get($name, $default = null)
		{
			return isset(self::$config[$name]) ? self::$config[$name] : $default;
		}

		public static function add($parameters = array())
		{
			self::$config = array_merge(self::$config, $parameters);
		}
	}
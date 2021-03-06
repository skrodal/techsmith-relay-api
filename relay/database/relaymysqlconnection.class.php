<?php
	namespace Relay\Database;

	use Relay\Conf\Config;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;


	class RelayMySQLConnection {

		private $config;

		function __construct($configKey) {
			// Get connection conf
			$this->config = $this->_loadConfig($configKey);
		}

		public function db_connect() {
			$mysqli = new \mysqli($this->config['db_host'], $this->config['db_user'], $this->config['db_pass'], $this->config['db_name']);
			//
			if($mysqli->connect_errno) {
				Response::error(503, "503 Service Unavailable (DB connection failed): " . $mysqli->connect_error);
			}

			Utils::log("MySQL DB CONNECTED: " . json_encode( $mysqli->get_charset() ));

			return $mysqli;
		}

		public function getConfig($key){
			return $this->config[$key];
		}

		private function _loadConfig($configKey){
			$this->config = file_get_contents(Config::get('auth')[$configKey]);
			// Sanity
			if($this->config === false) { Response::error(404, 'Not Found: MySQL config [' . $configKey . ']'); }
			// DB details
			return json_decode($this->config, true);
		}

	}


<?php
	namespace Relay\Database;

	use Relay\Conf\Config;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	/**
	 * Handles DB Connection and queries
	 *
	 * @author Simon Skrodal
	 * @since  July 2016
	 */
	class RelayMySQLConnection {

		private $connection, $config;

		function __construct() {
			// Get connection conf
			$this->config = $this->getConfig();
		}

		public function getConfig() {
			$this->config = file_get_contents(Config::get('auth')['relay_mysql_presdelete']);
			// Sanity
			if($this->config === false) {
				Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: MySQL config.');
			}
			// MySQL connection and info config
			return json_decode($this->config, true);
		}

		/**
		 * @param $sql
		 *
		 * @return array
		 */
		public function query($sql) {
			//
			$this->connection = $this->getConnection();
			// Run query
			if(!$result = $this->connection->query($sql)) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB query failed (MySQL): ' . $this->connection->error);
			}
			// Response
			$response = array();
			//
			Utils::log("MySQL rows returned: " . $result->num_rows);
			// Loop rows and add to response array
			while($row = $result->fetch_assoc()) {
				$response[] = $row;
			}
			// Free the query result
			$result->free_result();
			// Close link
			$this->closeConnection();
			//
			return $response;
		}

		/**
		 *    Open MySQL connection
		 */
		private function getConnection() {
			Response::error(503, $this->config);
			$mysqli = new \mysqli($this->config['db_host'], $this->config['db_user'], $this->config['db_pass'], $this->config['db_name']);
			//
			if($mysqli->connect_errno) {
				Response::error(503, "503 Service Unavailable (DB connection failed (MySQL): " . $this->config["db_name"]);
			}

			Utils::log("MySQL DB CONNECTED");

			return $mysqli;
		}

		/**
		 *    Close MySQL connection
		 */
		private function closeConnection() {
			if($this->connection !== false) {
				mysqli_close($this->connection);
			}
			Utils::log("MySQL DB CLOSED");
		}
	}

<?php
	namespace Relay\Database;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;
	use Relay\Conf\Config;
	/**
	 * Handles DB Connection and queries
	 *
	 * @author Simon Skrodal
	 * @since  August 2015
	 */

	ini_set('mssql.charset', 'UTF-8');

	class RelaySQLConnection {

		private $connection, $config;

		function __construct() {
			// Get connection conf
			$this->config = $this->getConfig();
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
			$query = mssql_query($sql, $this->connection);
			// On error
			if($query === false) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB query failed (SQL).');
			}
			// Response
			$response = array();
			//
			Utils::log("Rows returned: " . mssql_num_rows($query));
			// Loop rows and add to response array
			if(mssql_num_rows($query) > 0) {
				while($row = mssql_fetch_assoc($query)) {
					$response[] = $row;
					// Utils::log(print_r($row, true), __CLASS__ , __FUNCTION__, __LINE__);
				}
			}
			// Free the query result
			mssql_free_result($query);
			// Close link
			$this->closeConnection();

			//
			return $response;
		}

		public function employeeProfileId() {
			return (int)$this->config['employeeProfileId'];
		}

		public function studentProfileId() {
			return (int)$this->config['studentProfileId'];
		}

		/**
		 *    Open MSSQL connection
		 */
		private function getConnection() {
			//
			$connection = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
			//
			if(!$connection) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed (SQL).');
			}
			//
			if(!mssql_select_db($this->config['db'])) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB table connection failed (SQL).');
			}

			Utils::log("DB CONNECTED");
			return $connection;
		}

		/**
		 *    Close MSSQL connection
		 */
		private function closeConnection() {
			if($this->connection !== false) {
				mssql_close($this->connection);
			}
			Utils::log("DB CLOSED");
		}

		private function getConfig(){
			$this->config = file_get_contents(Config::get('auth')['relay_sql']);
			// Sanity
			if($this->config === false) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: SQL config.'); }
			// Connect username and pass
			return json_decode($this->config, true);
		}
	}
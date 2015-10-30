<?php
	namespace Relay\Database;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;
	/**
	 * Handles DB Connection and queries
	 *
	 * @author Simon Skrodal
	 * @since  August 2015
	 */

	ini_set('mssql.charset', 'UTF-8');

	class RelaySQLConnection {

		private $connection, $config;

		function __construct($config) {
			$this->config = file_get_contents(Config::get('auth')['relay_sql']);
			// Sanity
			if($this->config === false) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: SQL config.'); }
			// Connect username and pass
			$this->config = json_decode($this->config, true);
		}

		/**
		 * @param $sql
		 *
		 * @return array
		 */
		public function query($sql) {
			//
			$this->connect();
			// Run query
			$query = mssql_query($sql, $this->connection);
			// On error
			if($query === false) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB query failed (SQL).');
			}
			// Response
			$response = array();
			//
			Utils::log("Rows returned: " . mssql_num_rows($query), __LINE__, __FUNCTION__);
			// Loop rows and add to response array
			if(mssql_num_rows($query) > 0) {
				while($row = mssql_fetch_assoc($query)) {
					$response[] = $row;
					// Utils::log(print_r($row, true), __LINE__, __FUNCTION__);
				}
			}
			// Free the query result
			mssql_free_result($query);
			// Close link
			$this->close();

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
		private function connect() {
			//
			$this->connection = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
			//
			if(!$this->connection) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed (SQL).');
			}
			//
			if(!mssql_select_db($this->config['db'])) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB table connection failed (SQL).');
			}

			Utils::log("DB CONNECTED", __LINE__, __FUNCTION__);
		}

		/**
		 *    Close MSSQL connection
		 */
		private function close() {
			if($this->connection !== false) {
				mssql_close($this->connection);
			}
			Utils::log("DB CLOSED", __LINE__, __FUNCTION__);
		}
	}
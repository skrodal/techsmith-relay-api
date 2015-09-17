<?php
	/**
	 * Handles DB Connection and queries
	 *
	 * @author Simon Skrodal
	 * @since  August 2015
	 */

	ini_set('mssql.charset', 'UTF-8');

	class RelayDB {
		private $conn, $config;

		function __construct($config) {
			$this->config = $config;
		}
		/**
		 *
		 */
		public function query($sql) {
			//
			$this->connect();
			// Run query
			$query = mssql_query($sql, $this->conn);
			// On error
			if($query === false) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB query failed.');
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
			return (int)$this->config->employeeProfileId;
		}
		public function studentProfileId() {
			return (int)$this->config->studentProfileId;
		}

		/**
		 *    Open MSSQL connection
		 */
		private function connect() {
			//
			$this->conn = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
			//
			if(!$this->conn) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed.');
			}
			//
			if(!mssql_select_db($this->config['db'])) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB table connection failed.');
			}

			Utils::log("DB CONNECTED", __LINE__, __FUNCTION__);
		}

		/**
		 *    Close MSSQL connection
		 */
		private function close() {
			if($this->conn !== false) {
				mssql_close($this->conn);
			}
			Utils::log("DB CLOSED", __LINE__, __FUNCTION__);
		}
	}
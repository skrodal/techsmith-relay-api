<?php
	namespace Relay\Database;

	use Relay\Conf\Config;
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

		function __construct() {
			// Get connection conf
			$this->config = $this->getConfig();
			$this->connection = NULL;
		}

		private function getConfig() {
			$this->config = file_get_contents(Config::get('auth')['relay_sql']);
			// Sanity
			if($this->config === false) {
				Response::error(404, 'Not Found: SQL config.');
			}
			// Connect username and pass
			return json_decode($this->config, true);
		}


		public function query($sql) {
			$this->connection = $this->getConnection();
			try{
				$response = array();
				Utils::log("Rows returned: " . $query->rowCount());
				while($row = $this->connection->query($sql, \PDO::FETCH_ASSOC)) {
					$response[] = $row;
				}
				$this->closeConnection();
				return $response;
			}catch(\PDOException $e){
				Response::error(500, 'DB query failed (SQL): ' . $e->getMessage());
			}
		}

		/**
		 * 29.09.2016: Rewrite class to use PDO (mssql is deprecated starting with PHP7)
		 * @return \PDO
		 */
		private function getConnection() {
			if(!is_null($this->connection))return $this->connection;
			$connection = NULL;
			$host = $this->config['host'];
			$db   = $this->config['db'];
			$user = $this->config['user'];
			$pass = $this->config['pass'];
			try {
				$connection = new \PDO("mssql:host=$host;dbname=$db;charset=UTF8", $user, $pass);
				$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				Utils::log("DB CONNECTED");
				return $connection;
			} catch(\PDOException $e) {
				Response::error(500, 'DB connection failed (SQL): ' . $e->getMessage());
			}
		}

		private function closeConnection() {
			$this->connection = NULL;
			Utils::log("DB CLOSED");
		}

		/**
		 * @param $sql
		 *
		 * @return array
		 */
		public function _query($sql) {
			//
			$this->connection = $this->getConnection();
			// Run query
			$query = mssql_query($sql, $this->connection);
			// On error
			if($query === false) {
				Response::error(500, 'DB query failed (SQL).');
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
		 *    Close MSSQL connection
		 */
		private function _closeConnection() {
			if($this->connection !== false) {
				mssql_close($this->connection);
			}
			Utils::log("DB CLOSED");
		}

		/**
		 *    Open MSSQL connection
		 */
		private function _getConnection() {
			//
			$connection = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
			//
			if(!$connection) {
				Response::error(500, 'DB connection failed (SQL).');
			}
			//
			if(!mssql_select_db($this->config['db'])) {
				Response::error(500, 'DB table connection failed (SQL).');
			}

			Utils::log("DB CONNECTED");

			return $connection;
		}
	}
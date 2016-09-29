<?php
	namespace Relay\Database;

	use Relay\Conf\Config;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;
	use PDO;
	use PDOException;

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
		public function employeeProfileId() {
			return (int)$this->config['employeeProfileId'];
		}

		public function studentProfileId() {
			return (int)$this->config['studentProfileId'];
		}

		###
		# 29.09.2016: Alternative DB implementation using PDO used.
		# mssql is DEPRECATED PHP7, using PDO gives forward compatability.
		###

		/**
		 *
		 * @param $sql
		 * @return array
		 */
		public function query($sql) {
			$this->connection = $this->getConnection();
			try{
				$response = array();
				$query = $this->connection->query($sql, PDO::FETCH_ASSOC);
				Utils::log("Rows returned: " . $query->rowCount());
				foreach($query as $row){
					$response[] = $row;
				}
				$query->closeCursor();
				$this->closeConnection();
				return $response;
			}catch(PDOException $e){
				Response::error(500, 'DB query failed (SQL): ' . $e->getMessage());
			}
		}

		/**
		 * @return PDO
		 */
		private function getConnection() {
			if(!is_null($this->connection))return $this->connection;
			$connection = NULL;
			$host = $this->config['host'];
			$db   = $this->config['db'];
			$user = $this->config['user'];
			$pass = $this->config['pass'];
			try {
				//$connection = new PDO("mssql:host=$host;dbname=$db;charset=UTF8", $user, $pass);
				$connection = new PDO("dblib:host=$host;dbname=$db;charset=UTF8", $user, $pass);
				//$connection = new PDO("sqlsrv:Server=$host;Database=$db", $user, $pass);
				//odbc:DRIVER=FreeTDS;SERVERNAME=mssql;DATABASE=
				$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				Utils::log("DB CONNECTED");
				return $connection;
			} catch(PDOException $e) {
				Response::error(500, 'DB connection failed (SQL): ' . $e->getMessage());
			}
		}

		/**
		 *
		 */
		private function closeConnection() {
			$this->connection = NULL;
			Utils::log("DB CLOSED");
		}


		###
		# 29.09.2016: OLD mssql DB implementation below. Got some random connection errors with it, and
		# it is also DEPRECATED in PHP7. Hence, testing with PDO implementation above for a while.
		#
		###

		/**
		 *
		 * @param $sql
		 * @return array
		 */
		public function _query($sql) {
			$this->connection = $this->getConnection();
			// Run query
			$query = mssql_query($sql, $this->connection);
			if($query === false) { Response::error(500, 'DB query failed (SQL).'); }
			$response = array();
			Utils::log("Rows returned: " . mssql_num_rows($query));
			// Loop rows and add to response array
			if(mssql_num_rows($query) > 0) {
				while($row = mssql_fetch_assoc($query)) {
					$response[] = $row;
					// Utils::log(print_r($row, true), __CLASS__ , __FUNCTION__, __LINE__);
				}
			}
			mssql_free_result($query);
			$this->closeConnection();
			return $response;
		}
		/**
		 *    Close MSSQL connection
		 */
		private function _closeConnection() {
			if($this->connection !== false) {mssql_close($this->connection);}
			Utils::log("DB CLOSED");
		}
		/**
		 *    Open MSSQL connection
		 */
		private function _getConnection() {
			$connection = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
			if(!$connection) { Response::error(500, 'DB connection failed (SQL).'); }
			if(!mssql_select_db($this->config['db'])) { Response::error(500, 'DB table connection failed (SQL).'); }
			Utils::log("DB CONNECTED");
			return $connection;
		}
	}
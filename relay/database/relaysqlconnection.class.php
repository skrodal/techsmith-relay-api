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

	}
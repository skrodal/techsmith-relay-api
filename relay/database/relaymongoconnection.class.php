<?php
	namespace Relay\Database;

	use MongoClient;
	use MongoCollection;
	use MongoConnectionException;
	use Relay\Utils\Response;
	use Relay\Conf\Config;
	use Relay\Utils\Utils;

	/**
	 * @author Simon Skrødal
	 * @date   20/10/2015
	 * @time   14:28
	 */
	class RelayMongoConnection {
		private $connection, $db, $config;

		public function __construct() {
			// Get connection conf
			$this->config = $this->getConfig();
			// Set DB
			$this->db = $this->config['db'];
			//
			$this->connection = $this->getConnection();
		}

		public function find($collection, $criteria){
			return $this->connection->selectDB($this->db)->selectCollection($collection)->find($criteria);
		}

		public function count($collection, $criteria){
			return $this->connection->selectDB($this->db)->selectCollection($collection)->find($criteria)->count();
		}

		public function countAll($collection){
			return $this->connection->selectDB($this->db)->selectCollection($collection)->count();
		}





		private function getConnection(){
			try {
				return new MongoClient("mongodb://" . $this->config['user'] . ":" . $this->config['pass'] . "@127.0.0.1/" . $this->db);
			} catch (MongoConnectionException $e){
				Utils::log($e->getMessage());
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed (MongoDB).');
			}
		}

		private function getConfig(){
			$this->config = file_get_contents(Config::get('auth')['relay_mongo']);
			// Sanity
			if($this->config === false) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: MongoDB config.'); }
			// Connect username and pass
			return json_decode($this->config, true);
		}

	}
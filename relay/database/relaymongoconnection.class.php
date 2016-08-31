<?php
	namespace Relay\Database;

	use MongoClient;
	use MongoConnectionException;
	use MongoCursorException;
	use Relay\Utils\Response;
	use Relay\Conf\Config;
	use Relay\Utils\Utils;

	/**
	 * @author Simon Skrødal
	 * @date   20/10/2015
	 * @time   14:28
	 */
	class RelayMongoConnection {
		// Mongo
		private $connection, $db;
		//
		private $config;

		public function __construct() {
			// Get connection conf
			$this->config = $this->getConfig();
			// MongoClient
			$this->connection = $this->getConnection();
			// Set Client DB
			$this->db = $this->connection->selectDB( $this->config['db'] );
		}

		public function find($collection, $criteria){
			$response = [];
			try {
				// Get cursor
				$cursor = $this->db->selectCollection($collection)->find($criteria);
				// Iterate the cursor
				foreach($cursor as $document) {
					// Push document (array) into response array
					array_push($response, $document);
				}
				// Close the cursor (apparently recommended)
				$cursor->reset();
				return $response;
			} catch (MongoCursorException $e){
				Response::error(500, 'DB cursor error (MongoDB).');
			}
		}

		public function findOne($collection, $criteria){
			try {
				return $this->db->selectCollection($collection)->findOne($criteria);
			} catch (MongoCursorException $e){
				Response::error(500, 'DB cursor error (MongoDB).');
			}
		}

		public function findAll($collection){
			$response      = [];
			try {
				// Get cursor
				$cursor = $this->db->selectCollection($collection)->find();
				// Iterate the cursor
				foreach($cursor as $document) {
					// Push document (array) into response array
					array_push($response, $document);
				}
				// Close the cursor (apparently recommended)
				$cursor->reset();
				return $response;
			} catch (MongoCursorException $e){
				Response::error(500, 'DB cursor error (MongoDB).');
			}
		}

		public function count($collection, $criteria){
			return $this->db->selectCollection($collection)->find($criteria)->count();
		}

		public function countAll($collection){
			return $this->db->selectCollection($collection)->count();
		}


		private function getConnection(){
			try {
				return new MongoClient("mongodb://" . $this->config['user'] . ":" . $this->config['pass'] . "@127.0.0.1/" . $this->config['db']);
			} catch (MongoConnectionException $e){
				Utils::log($e->getMessage());
				Response::error(500, 'DB connection failed (MongoDB).');
			}
		}

		private function getConfig(){
			$this->config = file_get_contents(Config::get('auth')['relay_mongo']);
			// Sanity
			if($this->config === false) { Response::error(404, 'Not Found: MongoDB config.'); }
			// Connect username and pass
			return json_decode($this->config, true);
		}

	}
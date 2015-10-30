<?php
	namespace Relay\Database;

	use MongoClient;
	use MongoCollection;
	use MongoConnectionException;
	use Relay\Utils\Response;
	use Relay\Conf\Config;

	/**
	 * @author Simon Skrødal
	 * @date   20/10/2015
	 * @time   14:28
	 */
	class RelayMongoConnection {
		private $config;
		private $database;
		public $collection;

		public function __construct($collection) {
			$this->config = file_get_contents(Config::get('auth')['relay_mongo']);
			// Sanity
			if($this->config === false) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: MongoDB config.'); }
			// Connect username and pass
			$this->config = json_decode($this->config, true);
/*
			try {
				$authString = sprintf('mongodb://%s:%s@%s/%s',
					getenv('MONGO_USERNAME'),
					getenv('MONGO_PASSWORD'),
					getenv('MONGO_HOST'),
					getenv('MONGO_DATABASE'));

				$mongoClient    = new MongoClient($authString);
				$this->database = $mongoClient->selectDB(getenv('MONGO_DATABASE'));
			} catch(MongoConnectionException $e) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed (mongodb).');
				// die('Error connecting to MongoDB server: ' . $e->getMessage() . PHP_EOL);
			}
*/
			$this->collection = new MongoCollection($this->database, $collection);

			return $this->collection;
		}
		public function findDocument($criteria) {
			return $this->collection->find($criteria);
		}

		public function findOne($criteria) {
			return $this->collection->findOne($criteria);
		}

	}
<?php
	namespace Relay\Tests;

	use Relay\Database\RelayMongoConnection;

	/**
	 * @author Simon Skrødal
	 * @date   02/11/2015
	 * @time   13:42
	 */
	class MongoTest {

		function __construct() {

		}

		public function memoryTest() {
			$result = [];
			$old = memory_get_usage();
			$relayMongoConnection = new RelayMongoConnection();
			$new = memory_get_usage();
			array_push($result,  "Memory consumption after connection: " . ($new - $old) . "bytes");

			$old = memory_get_usage();
			$relayMongoConnection->findOne('presentations', ['username' => 'simon@uninett.no']);
			$new = memory_get_usage();
			array_push($result, "Memory consumption after findOne: " . ($new - $old) . "bytes");

			$old = memory_get_usage();
			$relayMongoConnection->findAll('presentations');
			$new = memory_get_usage();
			array_push($result, "Memory consumption after findAll: " . $this->bytes2mb($new - $old) . "MB");
			return $result;
		}

		private function bytes2mb($bytes){
			return $bytes / 1000 / 1000;
		}

	}
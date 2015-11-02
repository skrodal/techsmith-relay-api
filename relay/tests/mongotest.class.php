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
			array_push($result,  "Memory after collection: " . ($new - $old));

			$old = memory_get_usage();
			$arr =$relayMongoConnection->findOne('users', ['username' => 'simon@uninett.no']);
			$new = memory_get_usage();
			array_push($result, "Memory after findOne: " . ($new - $old));

			$old = memory_get_usage();
			$cursor = $relayMongoConnection->findAll('presentations');
			$arr = iterator_to_array($cursor);
			$new = memory_get_usage();
			array_push($result, "Memory after find: " . ($new - $old));

			$cursor->reset();
			return $result;
		}



	}
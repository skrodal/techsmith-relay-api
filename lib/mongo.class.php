<?php
	namespace UNINETT\RelayAPI;
	/**
	 * @author Simon Skrødal
	 * @date   20/10/2015
	 * @time   14:28
	 */
	class Mongo {

		public static function insert($collection, $document){
			// Connect
			$mongoClient = new \MongoClient();
			// Select/create a database (test) and collection (testCollection)
			$collection = $mongoClient->relaydb->$collection;
			$collection->insert($document);
		}
		/**
		 * @param $id
		 * @param $document
		 */
		public static function update($id, $document) {
			// Connect
			$mongoClient = new \MongoClient();
			// Select/create a database (test) and collection (testCollection)
			$collection = $mongoClient->relaydb->users;
			$collection->update(array("_id" => $id), $document, array("upsert" => true));
		}

	}
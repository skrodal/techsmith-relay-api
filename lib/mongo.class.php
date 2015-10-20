<?php
	namespace UNINETT\RelayAPI;
	/**
	 * @author Simon Skrødal
	 * @date   20/10/2015
	 * @time   14:28
	 */
	class Mongo {

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
<?php
	namespace Relay\Api;

	use Relay\Auth\FeideConnect;
	use Relay\Database\RelayMongoConnection;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	/**
	 * Serves API routes requesting data from UNINETTs TechSmith RelaySQL Harvesting Service.
	 *
	 * The harvester stores all consolidated information in MongoDB.
	 *
	 * @author  Simon Skrødal
	 * @see:    https://github.com/skrodal/relay-mediasite-harvest
	 * @date    29/10/2015
	 * @time    15:24
	 */

	class RelayMongo {
		private $relayMongoConnection, $relaySQL, $feideConnect;

		function __construct(RelaySQL $rs, FeideConnect $fc) {
			//
			$this->relayMongoConnection = new RelayMongoConnection();
			$this->relaySQL = $rs;
			$this->feideConnect = $fc;
		}

		/**
		 * Same as $this->relaySQL->getGlobalUserCount(), really... wonder which is faster... -> TODO
		 *
		 * @return int
		 */
		public function getGlobalUserCount() {
			return $this->relayMongoConnection->countAll('users');
		}

		public function test(){
			// Simple test to get all presentations *on disk* for a specific user.
			$response = [];
			$criteria = ['username' => 'simon@uninett.no'];
			$test = $this->relayMongoConnection->find("presentations", $criteria);
			// Iterate the cursor
			foreach($test as $document){
				// Push document (array) into response array
				array_push($response, $document);
			}
			// Close the cursor (apparently recommended)
			$test->reset();
			// Response
			// Response::result(array('status' => true, 'data' => $response ));
			return $response;
		}
	}
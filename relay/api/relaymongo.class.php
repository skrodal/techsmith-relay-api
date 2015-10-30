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

			foreach($cursor as $item){
				$return[$i] = array(
					'_id'=>$item['_id'],
					'nCode'=>$item['nCode'],
					'pId'=>$item['pId'],
					'nText'=>$item['nText'],
					'longText'=>$item['longText'],
					'nStatus'=>$item['nStatus'],
					'nVType'=>$item['nVType'],
					'pushDate'=>$item['pushDate'],
					'updateFlag'=>$item['updateFlag'],
					'counter' => $i
				);
				$i++;
			}
			$response = [];
			$test = $this->relayMongoConnection->find("presentations", ['username' => 'simon@uninett.no']);
			foreach($test as $document){
				array_push($response, json_decode($document));
			}
			$test->reset();
			Response::result(array('status' => true, 'data' => $response ));
		}

		public function getGlobalUserCount() {
			//return $this->relayMongoConnection->collection->;
		}
	}
<?php
	namespace Relay\Api;

	use Relay\Auth\FeideConnect;
	use Relay\Database\RelayMongoConnection;
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

			Utils::log($this->relayMongoConnection->find("presentations", ['username' => 'simon@uninett.no']));
		}

		public function getGlobalUserCount() {
			//return $this->relayMongoConnection->collection->;
		}
	}
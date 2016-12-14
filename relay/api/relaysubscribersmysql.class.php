<?php

	namespace Relay\Api;

	use Relay\Database\RelayMySQLConnection;

	/**
	 * Serves API routes pertaining to subscribers (table managed by relay-register service)
	 *
	 * Talks with a MySQL DB table built for this purpose.
	 *
	 * NOTE! This class is currently only providing the org access list (inc. affiliation access) as provided by
	 * the relay-register service. See relaysql.class.php - function getOrgs() for info on how detailed orginfo is
	 * fetched from various services.
	 *
	 * @author Simon Skrodal
	 * @since  December 2016
	 */
	class RelaySubscribersMySQL {
		private $relayMySQLConnection = false;
		private $sqlConn, $tableSubscribers, $dataporten, $relay;
		private $configKey = 'relay_mysql_subscribers';

		function __construct(Relay $relay) {
			$this->dataporten = $relay->dataporten();
			$this->relay      = $relay;
		}

		/**
		 * All subscribers in table (also inactive)
		 * @return array
		 */
		public function getSubscribers() {
			$this->init();
			$result = $this->sqlConn->query("SELECT * FROM $this->tableSubscribers");

			return $this->_sqlResultToArray($result);
		}

		private function init() {
			if(!$this->relayMySQLConnection) {
				$this->relayMySQLConnection = new RelayMySQLConnection($this->configKey);
				$this->tableSubscribers     = $this->relayMySQLConnection->getConfig('db_table_subscribers');
				$this->sqlConn              = $this->relayMySQLConnection->db_connect();
			}
		}

		private function _sqlResultToArray($result) {
			$response = array();
			// Loop returned rows and create a response
			while($row = $result->fetch_assoc()) {
				array_push($response, $row);
			}

			return $response;
		}
	}
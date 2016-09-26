<?php

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Database\RelayMySQLConnection;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	/**
	 * Serves API routes pertaining to presentation hits (collected by service relay-iis-logparser).
	 *
	 * Talks with a MySQL DB table built for this purpose.
	 *
	 * @author Simon Skrodal
	 * @since  September 2016
	 */
	class RelayPresHitsMySQL {
		private $sql, $tableHits, $tableDaily, $dataporten, $feideUserName;
		private $configKey = 'relay_mysql_preshits';

		function __construct(Dataporten $dataporten) {
			//
			$relayMySQLConnection = new RelayMySQLConnection($this->configKey);
			$this->sql            = $relayMySQLConnection->db_connect();
			$this->tableHits      = $relayMySQLConnection->getConfig('db_table_hits');
			$this->tableDaily      = $relayMySQLConnection->getConfig('db_table_daily');
			$this->dataporten     = $dataporten;
			$this->feideUserName  = $this->dataporten->userName();
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/presentations/hits/*/
		#
		public function getDailyHitsAll() {
			$result = $this->sql->query("SELECT * FROM $this->tableDaily");
			return $this->_sqlResultToArray($result);
		}

		public function getDailyHitsByYear($year) {
			$year = intval($this->sql->real_escape_string($year));
			$result = $this->sql->query("SELECT * FROM $this->tableDaily WHERE YEAR(log_date) = $year");
			return $this->_sqlResultToArray($result);
		}

		#
		# ORG ENDPOINTS
		#
		# /org/[org]/presentations/hits/*/
		#


		#
		# USER (ME) ENDPOINTS (requires user-scope)
		#
		# /me/presentations/hits/*/


		// Hits for all presentations
		public function getHitsPresentationsMe() {
			$result = $this->sql->query("SELECT * FROM $this->tableHits WHERE username = '$this->feideUserName'");
			return $this->_sqlResultToArray($result);
		}

		
		//
		private function _sqlResultToArray($result) {
			$response = array();
			// Loop returned rows and create a response
			while($row = $result->fetch_assoc()) {
				array_push($response, $row);
			}

			return $response;
		}

	}
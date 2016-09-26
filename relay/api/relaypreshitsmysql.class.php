<?php

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Database\RelayMySQLConnection;
	use Relay\Utils\Response;

	/**
	 * Serves API routes pertaining to presentation hits (collected by service relay-iis-logparser).
	 *
	 * Talks with a MySQL DB table built for this purpose.
	 *
	 * @author Simon Skrodal
	 * @since  September 2016
	 */
	class RelayPresHitsMySQL {
		private $sql, $tableHits, $tableDaily, $dataporten, $feideUserName, $relayMongo;
		private $configKey = 'relay_mysql_preshits';

		function __construct(Dataporten $dataporten, RelayMongo $relayMongo) {
			//
			$relayMySQLConnection = new RelayMySQLConnection($this->configKey);
			$this->sql            = $relayMySQLConnection->db_connect();
			$this->tableHits      = $relayMySQLConnection->getConfig('db_table_hits');
			$this->tableDaily     = $relayMySQLConnection->getConfig('db_table_daily');
			$this->dataporten     = $dataporten;
			$this->relayMongo     = $relayMongo;
			$this->feideUserName  = $this->dataporten->userName();
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/presentations/hits/*/
		#

		/**
		 * All daily records in table
		 * @return array
		 */
		public function getDailyHitsAll() {
			$result = $this->sql->query("SELECT * FROM $this->tableDaily");

			return $this->_sqlResultToArray($result);
		}

		/**
		 * All daily records in table from year $year
		 *
		 * @param $year
		 * @return array
		 */
		public function getDailyHitsByYear($year) {
			$year   = intval($this->sql->real_escape_string($year));
			$result = $this->sql->query("SELECT * FROM $this->tableDaily WHERE YEAR(log_date) = $year");

			return $this->_sqlResultToArray($result);
		}

		public function getHitsByOrgAnonymised() {
			// Sorted list of org names (org.no)
			$orgs = $this->relayMongo->getOrgs();
			$response = [];
			//
			foreach($orgs as $index => $org){
				$result = $this->sql->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org%'");
				$hits = $result->fetch_assoc();
				$response[] = $hits['hits'] ? $hits['hits'] : 0;
			}
			return $response;
		}

		#
		# ADMIN ENDPOINTS
		#
		# /admin/presentations/hits/
		#

		/**
		 * Total number of hits per org.
		 * @return array
		 */
		public function getHitsByOrgAdmin() {
			// Sorted list of org names (org.no)
			$orgs = $this->relayMongo->getOrgs();
			$response = [];
			//
			foreach($orgs as $index => $org){
				$result = $this->sql->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org%'");
				$hits = $result->fetch_assoc();
				$response[$org] = $hits['hits'] ? $hits['hits'] : 0;
			}
			return $response;
		}

		#
		# ORG ENDPOINTS
		#
		# /org/[org]/presentations/hits/*/
		#
		public function getOrgTotalHits($org) {
			//TODO
		}

		public function getOrgPresentationHitsByUser($org){
			// TODO
		}

		#
		# USER (ME) ENDPOINTS (requires user-scope)
		#
		# /me/presentations/hits/*/

		/**
		 * Get an array with all [ presentation paths : hits ] belonging to this user
		 * @return array
		 */
		public function getHitsMe() {
			$username = '%' . str_replace("@","%",$this->feideUserName) . '%';
			$result = $this->sql->query("SELECT * FROM $this->tableHits WHERE path LIKE '$username'");
			return $this->_sqlResultToArray($result);
		}


		private function _sqlResultToArray($result) {
			$response = array();
			// Loop returned rows and create a response
			while($row = $result->fetch_assoc()) {
				array_push($response, $row);
			}

			return $response;
		}

		// ---------------------------- UTILS ----------------------------

		/**
		 * Prevent orgAdmin to request data for other orgs than what he belongs to.
		 *
		 * @param $orgName
		 */
		function verifyOrgAccess($orgName) {
			// If NOT superadmin AND requested org data is not for home org
			if(!$this->dataporten->isSuperAdmin() && strcasecmp($orgName, $this->dataporten->userOrg()) !== 0) {
				Response::error(401, '401 Unauthorized (request mismatch org/user). ');
			}
		}


		// ---------------------------- ./UTILS ----------------------------

	}
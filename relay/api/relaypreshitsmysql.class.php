<?php

	namespace Relay\Api;

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
		private $relayMySQLConnection = false;
		private $sqlConn, $tableHits, $tableDaily, $tableInfo, $dataporten, $feideUserName, $firstRecordTimestamp, $relay;
		private $configKey = 'relay_mysql_preshits';

		function __construct(Relay $relay) {
			$this->dataporten    = $relay->dataporten();
			$this->feideUserName = $this->dataporten->userName();
			$this->relay         = $relay;
		}

		public function getTotalHits() {
			$this->init();
			$result                      = $this->sqlConn->query("SELECT SUM(hits) AS 'hits' FROM $this->tableDaily");
			$hits                        = $result->fetch_assoc();
			$response                    = [];
			$response['hits']            = $hits['hits'];
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();

			return $response;
		}

		private function init() {
			if(!$this->relayMySQLConnection) {
				$this->relayMySQLConnection = new RelayMySQLConnection($this->configKey);
				$this->tableHits            = $this->relayMySQLConnection->getConfig('db_table_hits');
				$this->tableDaily           = $this->relayMySQLConnection->getConfig('db_table_daily');
				$this->tableInfo            = $this->relayMySQLConnection->getConfig('db_table_info');
				$this->sqlConn              = $this->relayMySQLConnection->db_connect();
				$this->firstRecordTimestamp = $this->getFirstRecordedTimestamp();
			}
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/presentations/hits/*/
		#

		/**
		 *
		 * @return bool
		 */
		private function getFirstRecordedTimestamp() {
			$this->init();
			$result    = $this->sqlConn->query("SELECT conf_val AS 'timestamp' from $this->tableInfo WHERE conf_key = 'first_record_timestamp'");
			$timestamp = $result->fetch_assoc();

			return $timestamp['timestamp'] ? $timestamp['timestamp'] : false;
		}

		/**
		 * All daily records in table
		 * @return array
		 */
		public function getDailyHitsAll() {
			$this->init();
			$result = $this->sqlConn->query("SELECT * FROM $this->tableDaily");

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

		/**
		 * All daily records in table from year $year
		 *
		 * @param $year
		 *
		 * @return array
		 */
		public function getDailyHitsByYear($year) {
			$this->init();
			$year   = intval($this->sqlConn->real_escape_string($year));
			$result = $this->sqlConn->query("SELECT * FROM $this->tableDaily WHERE YEAR(log_date) = $year");

			return $this->_sqlResultToArray($result);
		}

		/**
		 * All daily records in table in the last $days
		 *
		 * @param $days
		 *
		 * @return array
		 */
		public function getDailyHitsByDays($days) {
			$this->init();
			$days   = intval($this->sqlConn->real_escape_string($days));
			$result = $this->sqlConn->query("SELECT * FROM $this->tableDaily WHERE log_date >= date(now()) - INTERVAL $days DAY");

			return $this->_sqlResultToArray($result);
		}

		#
		# ADMIN ENDPOINTS
		#
		# /admin/presentations/hits/
		#

		/**
		 * Array with number of hits per org (only org is missing/anonymised). Array items are also
		 * shuffled/randomised before returned.
		 * @return bool
		 */
		public function getOrgsTotalHitsAnonymised() {
			$this->init();
			// Sorted list of org names (org.no)
			$orgs                        = $this->relay->mongo()->getOrgs();
			$response                    = [];
			$response['hits']            = [];
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			//
			foreach($orgs as $index => $org) {
				$result             = $this->sqlConn->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org%'");
				$hits               = $result->fetch_assoc();
				$response['hits'][] = $hits['hits'] ? $hits['hits'] : "0";
			}
			shuffle($response['hits']);

			return $response;
		}

		#
		# ORG ENDPOINTS
		#
		# /org/[org]/presentations/hits/*/
		#

		/**
		 * Total number of hits per org.
		 * @return array
		 */
		public function getOrgsTotalHits() {
			$this->init();
			// Sorted list of org names (org.no)
			$orgs                        = $this->relayMongo->getOrgs();
			$response                    = [];
			$response['hits']            = [];
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			//
			foreach($orgs as $index => $org) {
				$result                 = $this->sqlConn->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org'");
				$hits                   = $result->fetch_assoc();
				$response['hits'][$org] = $hits['hits'] ? $hits['hits'] : 0;
			}

			return $response;
		}

		public function getOrgTotalHits($org) {
			$this->init();
			$result                      = $this->sqlConn->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org'");
			$hits                        = $result->fetch_assoc();
			$response                    = [];
			$response['hits']            = $hits['hits'] ? $hits['hits'] : 0;
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();

			return $response;
		}

		/**
		 * List of all users at org and the total number of hits each user has had on their content.
		 * Also includes total and first logged timestamp.
		 *
		 * @param $org
		 *
		 * @return array
		 */
		public function getOrgTotalHitsByUser($org) {
			$this->init();

			$result = $this->sqlConn->query("SELECT username, SUM(hits) AS 'hits' FROM $this->tableHits WHERE username LIKE '%$org' GROUP BY username");
			$response                    = [];
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			$response['total_hits']      = 0;
			$users                       = [];
			while($row = $result->fetch_assoc()) {
				$users[$row['username']] = $row['hits'];
				$response['total_hits'] += $row['hits'];
			}
			$response['users'] = $users;

			return $response;
		}

		/**
		 * Not made available via route - used by mongo to merge hits with presentations
		 *
		 * @param $org
		 *
		 * @return array
		 */
		public function getOrgPresentationsHits($org) {
			$this->init();
			$result   = $this->sqlConn->query("SELECT path, hits FROM $this->tableHits WHERE username LIKE '%$org'");
			$response = [];
			while($row = $result->fetch_assoc()) {
				$response[$row['path']] = $row['hits'];
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
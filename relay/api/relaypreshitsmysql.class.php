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
	class RelayPresHitsMySQL extends Relay {
		private $relayMySQLConnection = false;
		private $sql, $tableHits, $tableDaily, $tableInfo, $dataporten, $feideUserName, $relayMongo, $firstRecordTimestamp;
		private $configKey = 'relay_mysql_preshits';

		function __construct() {
			$this->dataporten     = parent::dataporten();
			$this->relayMongo     = parent::mongo();
			$this->feideUserName  = $this->dataporten->userName();
		}

		private function init(){
			if (!$this->relayMySQLConnection){
				$this->relayMySQLConnection = new RelayMySQLConnection($this->configKey);
				$this->tableHits            = $this->relayMySQLConnection->getConfig('db_table_hits');
				$this->tableDaily           = $this->relayMySQLConnection->getConfig('db_table_daily');
				$this->tableInfo            = $this->relayMySQLConnection->getConfig('db_table_info');
				$this->sql                  = $this->relayMySQLConnection->db_connect();
				$this->firstRecordTimestamp = $this->getFirstRecordedTimestamp();
			}
		}

		/**
		 *
		 * @return bool
		 */
		private function getFirstRecordedTimestamp(){
			$this->init();
			$result = $this->sql->query("SELECT conf_val AS 'timestamp' from $this->tableInfo WHERE conf_key = 'first_record_timestamp'");
			$timestamp = $result->fetch_assoc();
			return $timestamp['timestamp'] ? $timestamp['timestamp'] : false;
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
			$this->init();
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
			$this->init();
			$year   = intval($this->sql->real_escape_string($year));
			$result = $this->sql->query("SELECT * FROM $this->tableDaily WHERE YEAR(log_date) = $year");
			return $this->_sqlResultToArray($result);
		}

		/**
		 * All daily records in table in the last $days
		 *
		 * @param $days
		 * @return array
		 */
		public function getDailyHitsByDays($days) {
			$this->init();
			$days   = intval($this->sql->real_escape_string($days));
			$result = $this->sql->query("SELECT * FROM $this->tableDaily WHERE log_date >= date(now()) - INTERVAL $days DAY");
			return $this->_sqlResultToArray($result);
		}
		/**
		 * Array with number of hits per org (only org is missing/anonymised). Array items are also
		 * shuffled/randomised before returned.
		 * @return bool
		 */
		public function getOrgsTotalHitsAnonymised() {
			$this->init();
			// Sorted list of org names (org.no)
			$orgs = $this->relayMongo->getOrgs();
			$response = [];
			$response['hits'] = [];
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			//
			foreach($orgs as $index => $org){
				$result = $this->sql->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org%'");
				$hits = $result->fetch_assoc();
				$response['hits'][] = $hits['hits'] ? $hits['hits'] : "0";
			}
			shuffle($response['hits']);
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
		public function getOrgsTotalHits() {
			$this->init();
			// Sorted list of org names (org.no)
			$orgs = $this->relayMongo->getOrgs();
			$response = [];
			$response['hits'] = [];
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			//
			foreach($orgs as $index => $org){
				$result = $this->sql->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org%'");
				$hits = $result->fetch_assoc();
				$response['hits'][$org] = $hits['hits'] ? $hits['hits'] : 0;
			}
			return $response;
		}

		#
		# ORG ENDPOINTS
		#
		# /org/[org]/presentations/hits/*/
		#
		public function getOrgTotalHits($org) {
			$this->init();
			$result = $this->sql->query("SELECT SUM(hits) AS 'hits' FROM $this->tableHits WHERE path LIKE '%$org%'");
			$hits = $result->fetch_assoc();
			$response = [];
			$response['hits'] = $hits['hits'] ? $hits['hits'] : 0;
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			return $response;
		}

		/**
		 * List of all users at org and the total number of hits each user has had on their content.
		 *
		 * @param $org
		 * @return array
		 */
		public function getOrgTotalHitsByUser($org){
			$this->init();
			$result = $this->sql->query("SELECT username, sum(hits) AS 'hits' FROM $this->tableHits WHERE username LIKE '%$org%' GROUP BY username");
			$users = [];
			while($row = $result->fetch_assoc()) {
				$users[$row['username']] = $row['hits'];
			}
			$response = [];
			$response['users'] = $users;
			$response['first_timestamp'] = $this->getFirstRecordedTimestamp();
			return $response;
		}

		#
		# USER (ME) ENDPOINTS (requires user-scope)
		#
		# /me/presentations/hits/*/

		/**
		 * Get an indexed array with one obj per presentation path (hits, timestamp, username)
		 * @return array
		 */
		public function getHitsMe($feideUserName = NULL) {
			$feideUserName = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;
			// Hits table does not ever use the ampersand in username (as username was generated from the presentation path)
			$username = str_replace("@","",$feideUserName);
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->tableHits WHERE username LIKE '$username'");
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
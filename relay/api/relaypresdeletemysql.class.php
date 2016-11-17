<?php

	namespace Relay\Api;

	use Relay\Database\RelayMySQLConnection;

	/**
	 * Serves API `user-scope` routes requesting a user presentation to be deleted/undeleted.
	 *
	 * Talks with a MySQL DB table built for this purpose.
	 *
	 * @author Simon Skrodal
	 * @since  July 2016
	 */
	class RelayPresDeleteMySQL {
		private $relayMySQLConnection = false;
		private $sql, $table_name, $dataporten, $feideUserName, $relay;
		private $configKey = 'relay_mysql_presdelete';

		function __construct(Relay $relay) {
			$this->dataporten    = $relay->dataporten();
			$this->feideUserName = $this->dataporten->userName();
			$this->relay         = $relay;
		}

		/**
		 *
		 * @param $org
		 *
		 * @return array
		 */
		public function getDeletedPresentationsOrgCount($org) {
			$this->init();
			// Only checking on moved (i.e. unavailable)
			$result = $this->sql->query("SELECT COUNT(*) AS count FROM $this->table_name WHERE username LIKE '%$org' AND moved = 1");

			return !empty($result) ? $result['count'] : [];
		}

		private function init() {
			if(!$this->relayMySQLConnection) {
				$this->relayMySQLConnection = new RelayMySQLConnection($this->configKey);
				$this->table_name           = $this->relayMySQLConnection->getConfig('db_table_name');
				$this->sql                  = $this->relayMySQLConnection->db_connect();
			}
		}


		#
		# ADMIN ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /admin/presentations/deletelist/*/
		#

		/**
		 * Complete dump of all presentations in the table.
		 * @return array
		 */
		public function getAllPresentationRecordsAdmin() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name");

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

	}
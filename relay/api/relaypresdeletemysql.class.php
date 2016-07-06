<?php

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
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
		protected $table_name;
		private $relayMySQLConnection, $dataporten, $config, $feideUserName;

		function __construct(Dataporten $fc) {
			//
			$this->relayMySQLConnection = new RelayMySQLConnection();
			$this->dataporten           = $fc;
			$this->config               = $this->relayMySQLConnection->getConfig();
			$this->table_name           = $this->config['db_table_name'];
			$this->feideUserName        = $this->dataporten->userName();
		}

		#
		# ADMIN ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /admin/presentations/deletelist/*/
		#
		public function getAllPresentationRecordsAdmin() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name");
		}

		public function getMovedPresentationsAdmin() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name WHERE moved = 1 AND deleted <> 1");
		}

		public function getDeletedPresentationsAdmin() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name WHERE deleted = 1");
		}


		#
		# USER (ME) ENDPOINTS (requires user-scope)
		#

		# GET /me/presentations/deletelist/*/

		// Presentations recently added to deletelist that have not yet been moved (may be cancelled)
		public function getNotMovedPresentationsMe() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name WHERE username = $this->feideUserName AND moved = 0");
		}

		// Presentations in the deletelist that have been moved, but not yet deleted (may be restored)
		public function getMovedPresentationsMe() {

		}

		// Presentations in the deletelist that have been deleted (thus cannot be restored)
		public function getDeletedPresentationsMe() {

		}

		# POST /me/presentation/deletelist/*/

		// Add a single presentation to the deletelist
		public function deletePresentationMe() {
		}

		// Remove a single presentation from the deletelist (prior to it being moved)
		public function restorePresentationMe() {
		}

		// Request a moved presentation to be moved back
		public function undeletePresentationMe() {

		}


	}
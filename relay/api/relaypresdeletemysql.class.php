<?php

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Utils\Response;
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
		private $relayMySQLConnection, $dataporten, $config;
		protected $table_name;

		function __construct(Dataporten $fc) {
			//
			$this->relayMySQLConnection = new RelayMySQLConnection();
			$this->dataporten           = $fc;
			$this->config               = $this->relayMySQLConnection->getConfig();
			$this->table_name           = $this->config['db_table_name'];
		}

		#
		# GLOBAL ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /global/presentations/deletelist/*/
		#
		public function getAllPresentationRecords() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name");
		}

		public function getMovedPresentations() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name WHERE moved = 1 AND deleted <> 1");
		}

		public function getDeletedPresentations() {
			return $this->relayMySQLConnection->query("SELECT * FROM $this->table_name WHERE deleted = 1");
		}


		#
		# ORG ENDPOINTS (requires minimum org-scope)
		#
		# /org/{org.no}/presentations/deletelist/*/
		#

		public function getOrgUserCount($org) {
			$this->verifyOrgAccess($org);

			return $this->relaySQLConnection->query("SELECT COUNT(*) FROM tblUser WHERE userName LIKE '%$org%'")[0]['computed'];
		}

		/**
		 * Prevent orgAdmin to request data for other orgs than what he belongs to.
		 *
		 * @param $orgName
		 */
		function verifyOrgAccess($orgName) {
			// If NOT superadmin AND requested org data is not for home org
			if(!$this->dataporten->isSuperAdmin() && strcasecmp($orgName, $this->dataporten->userOrg()) !== 0) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (request mismatch org/user). ');
			}
		}



		/**
		 * /me/presentations/deletelist/
		 * /user/[*:userName]/deletelist/
		 *
		 *
		 * @param $feideUserName
		 *
		 * @return array
		 */
		public function getUserPresentations($feideUserName) {
			// NOTE: This query returns ALL presentations; also those deleted.
			// TODO: Need to find a quick way to check which presentations are deleted
			return $this->relaySQLConnection->query("
						SELECT 	presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, presProfile_profId, tblPresentation.createdOn, tblPresentation.createdByUser,
								userEmail, userName
						FROM 	tblPresentation,
								tblUser
						WHERE 	tblUser.userName = '$feideUserName'
						AND 	tblPresentation.presPresenterEmail = tblUser.userEmail");
		}

		
	}
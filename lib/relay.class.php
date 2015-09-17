<?php

	/**
	 * Class to extract information pertaining to eCampus services from Kind.
	 *
	 * @author Simon Skrodal
	 * @since  August 2015
	 */
	class Relay {
		private $relayDB, $feideConnect;

		function __construct(RelayDB $DB, FeideConnect $connect) {
			$this->relayDB = $DB;
			$this->feideConnect = $connect;
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/*/
		#
		// /service/ endpoint - not sure if needed...
		public function getService() { return array('message' => 'TODO'); }
		public function getServiceVersion() { return $this->relayDB->query("SELECT * FROM tblVersion"); }
		public function getServiceWorkers() { return $this->relayDB->query("SELECT edptId, edptUrl, edptStatus, edptLastChecked, edptServicePid, edptNumEncodings, edptActivationStatus, edptVersion, edptLicensedNumEncodings, createdOn, edptWindowsName, edptRemainingMediaDiskSpaceInMB FROM tblEndpoint"); }
		public function getServiceQueue() { return $this->relayDB->query("SELECT jobId, jobPresentation_PresId, jobQueuedTime  FROM tblJob WHERE jobStartProcessingTime IS NULL AND jobType = 0 AND jobState = 0"); }

		#
		# USER ENDPOINTS
		#
		# /me/*/
		# /user/*/
		#

		/**
		 * /me/
		 * /user/[*:userName]/
		 * @param $feideUserName
		 * @return array
		 */
		public function getUser($feideUserName) {
			$query = $this->relayDB->query("SELECT userId, userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
			return empty($query) ? [] : $query[0];
		}

		/**
		 * /me/presentations/
		 * /user/[*:userName]/presentations/
		 *
		 * TODO: Could possibly be achieved using a single DB query
		 * @param $feideUserName
		 * @return array
		 */
		public function getUserPresentations($feideUserName) {
			Utils::log($feideUserName, __LINE__, __FUNCTION__);
			// Get this user's userId first
			$userId = $this->relayDB->query("SELECT userId FROM tblUser WHERE userName = '$feideUserName'");
			$userId = $userId[0]['userId'] ? $userId[0]['userId'] : null;
			Utils::log($userId, __LINE__, __FUNCTION__);

			// Then presentations
			return $userId !== null ?
				$this->relayDB->query("
					SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser
					FROM tblPresentation
					WHERE presUser_userId = $userId ")
				: array();
		}

		/**
		 * /me/presentations/count/
		 * /user/[*:userName]/presentations/count/
		 *
		 * @param $feideUserName
		 * @return int
		 */
		public function getUserPresentationCount($feideUserName) {
			return sizeof( $this->getUserPresentations($feideUserName) );
		}

		#
		# USER ENDPOINTS
		#
		# /me/*/
		# /user/*/
		#


		/**
		 * For dev purposes only. Requires Admin scope and superadmin role (i.e. uninett employee).
		 *
		 * @param $table_name
		 * @return array
		 */
		public function getTableSchema($table_name){
			if($this->feideConnect->isSuperAdmin() && $this->feideConnect->hasOauthScopeAdmin()) {
				return $this->relayDB->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table_name' ");
			}
			// Else
			Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' Unauthorized!');
		}








		// ---------------------------- UTILS ----------------------------




		// ---------------------------- ./UTILS ----------------------------

	}
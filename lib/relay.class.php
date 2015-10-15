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
		//public function getService() { return array('message' => 'TODO'); }
		public function getServiceVersion() { return $this->relayDB->query("SELECT * FROM tblVersion"); }
		public function getServiceWorkers() { return $this->relayDB->query("SELECT edptId, edptUrl, edptStatus, edptLastChecked, edptServicePid, edptNumEncodings, edptActivationStatus, edptVersion, edptLicensedNumEncodings, createdOn, edptWindowsName, edptRemainingMediaDiskSpaceInMB FROM tblEndpoint"); }
		public function getServiceQueue() { return $this->relayDB->query("SELECT jobId, jobPresentation_PresId, jobQueuedTime  FROM tblJob WHERE jobStartProcessingTime IS NULL AND jobType = 0 AND jobState = 0"); }

		#
		# GLOBAL USERS ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /global/users/*/
		#
		public function getGlobalUsers() {
			return $this->relayDB->query("SELECT userId, userName, userDisplayName, userEmail FROM tblUser");
		}

		public function getGlobalUserCount() {
			return $this->relayDB->query("SELECT COUNT(*) FROM tblUser")[0]['computed'];
		}

		#
		# GLOBAL PRESENTATIONS ENDPOINTS (requires admin-scope)
		#
		# /global/presentations/*/
		#

		// NOTE: presUser_userId is sometimes NULL - not ideal to try to match userId with presentations...
		public function getGlobalPresentations() {
			return $this->relayDB->query("SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser FROM tblPresentation");
		}
		public function getGlobalPresentationCount() {
			return $this->relayDB->query("SELECT COUNT(*) FROM tblPresentation")[0]['computed'];
		}

		#
		# ORG USERS ENDPOINTS (requires minimum org-scope)
		#
		# /org/{org.no}/users/*/
		#
		public function getOrgUsers($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("SELECT userId, userName, userDisplayName, userEmail FROM tblUser WHERE userName LIKE '%$org%' ");
		}

		public function getOrgUserCount($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("SELECT COUNT(*) FROM tblUser WHERE userName LIKE '%$org%'")[0]['computed'];
		}

		// TODO: Work in progress....
		/*
		    // SAMPLE $tblProfiles
            {
                "usprUser_userId": 96,
                "usprProfile_profId": 3,
            },
			// SAMPLE tblOrgUsers
			{
				"userId": 584
            },
		 */
		public function getOrgEmployeeCount($org) {
			$this->verifyOrgAccess($org);
			// 1. Get entire set of user profile table
			$tblProfiles = $this->relayDB->query("SELECT usprUser_userId, usprProfile_profId FROM tblUserProfile");
			// 2. Get all users from this org (get values only as indexed array, we don't need the key "userId")
			$tblOrgUsersRaw = $this->relayDB->query("SELECT userId FROM tblUser WHERE userName LIKE '%$org%'");
			$tblOrgUserIds = array();
			//
			foreach($tblOrgUsersRaw as $index => $value) {
				$tblOrgUserIds[] = $value['userId'];
			}
			error_log(print_r($tblOrgUserIds,1));

			// Count array
			$affiliationCount = array('employees' => 0, 'students' => 0, 'unknown' => 0);
			// Loop entire set of user profiles list and match with users at this org
			foreach($tblProfiles as $userObj => $userInfo){
				// If current userId exist in orgs list of user IDs, we have a match
				if(in_array($userInfo['usprUser_userId'], $tblOrgUserIds)){
					// Get the profile ID for current user and see if we're dealing with a student or employee
					switch($userInfo['usprProfile_profId']) {
						case $this->relayDB->employeeProfileId():
							$affiliationCount['employees']++;
							break;
						case $this->relayDB->studentProfileId():
							$affiliationCount['students']++;
							break;
						default:
							$affiliationCount['unknown']++;
							break;
					}
				}
			}

			return $affiliationCount;
		}

		#
		# ORG PRESENTATIONS ENDPOINTS (requires minimum org-scope)
		#
		# /org/{org.no}/presentations/*/
		#
		public function getOrgPresentations($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("
						SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser
						FROM tblPresentation
						WHERE presPresenterEmail LIKE '%$org%' ");
		}

		public function getOrgPresentationCount($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("SELECT COUNT(*) FROM tblPresentation WHERE presPresenterEmail LIKE '%$org%'")[0]['computed'];
		}

		#
		# USER ENDPOINTS  (requires minimum user-scope)
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
			return !empty($query) ? $query[0] : [];
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
			// Get this user's userId first
			// $userId = $this->relayDB->query("SELECT userId FROM tblUser WHERE userName = '$feideUserName'");
			// Use user's email for now - userId is often missing in presentation records.
			$userEmail = $this->relayDB->query("SELECT userEmail FROM tblUser WHERE userName = '$feideUserName'");
			if(empty($userEmail)) return [];
			$userEmail = $userEmail[0]['userEmail'];
			// NOTE: presUser_userId is sometimes NULL - not ideal to try to match userId with presentations...
			return $this->relayDB->query("
						SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser
						FROM tblPresentation
						WHERE presPresenterEmail = '$userEmail' ");
		}

		/**
		 * /me/presentations/count/
		 * /user/[*:userName]/presentations/count/
		 *
		 * @param $feideUserName
		 * @return int
		 */
		public function getUserPresentationCount($feideUserName) {
			// $userId = $this->relayDB->query("SELECT userId FROM tblUser WHERE userName = '$feideUserName'");
			$userEmail = $this->relayDB->query("SELECT userEmail FROM tblUser WHERE userName = '$feideUserName'");
			if(empty($userEmail)) return [];
			$userEmail = $userEmail[0]['userEmail'];
			return $this->relayDB->query("SELECT COUNT(*) FROM tblPresentation WHERE presPresenterEmail = '$userEmail'")[0]['computed'];
		}












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


		/**
		 * Prevent orgAdmin to request data for other orgs than what he belongs to.
		 * @param $orgName
		 */
		function verifyOrgAccess($orgName){
			// If NOT superadmin AND requested org data is not for home org
			if(!$this->feideConnect->isSuperAdmin() && strcasecmp($orgName, $this->feideConnect->userOrg()) !== 0) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (request mismatch org/user). ');
			}
		}

		// ---------------------------- ./UTILS ----------------------------

	}
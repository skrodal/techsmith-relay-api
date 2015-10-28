<?php

	namespace UNINETT\RelayAPI;
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

		// GLOBALS EMPLOYEE

		public function getGlobalEmployeePresentations() {
			return $this->relayDB->query("
						SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relayDB->employeeProfileId());
		}

		public function getGlobalEmployeePresentationCount(){
			return $this->relayDB->query("
						SELECT COUNT(*)
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relayDB->employeeProfileId())[0]['computed'];
		}

		// GLOBALS STUDENT
		public function getGlobalStudentPresentations() {
			return $this->relayDB->query("
						SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relayDB->studentProfileId());
		}

		public function getGlobalStudentPresentationCount(){
			return $this->relayDB->query("
						SELECT COUNT(*)
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relayDB->studentProfileId())[0]['computed'];
		}

		#
		# ORG USERS ENDPOINTS (requires minimum org-scope)
		#
		# /org/{org.no}/users/*/
		#
		public function getOrgUsers($org) {
			$this->verifyOrgAccess($org);
			$query = $this->relayDB->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName LIKE '%$org%' ");

			// Convert affiliation code to text
			// Some test users have more than one profile, thus the SQL query may return more than one entry for a single user.
			// Since we're after a specific profile - either employeeProfileId or studentProfileId - run this check and delete any entries
			// that don't match our requested profiles.
			if(!empty($query)){
				foreach($query as $key => $info) {
					switch($query[$key]['userAffiliation']){
						case $this->relayDB->employeeProfileId():
							$query[$key]['userAffiliation'] = 'employee';
							break;
						case $this->relayDB->studentProfileId():
							$query[$key]['userAffiliation'] = 'student';
							break;
						default:
							unset($query[$key]);
					}
				}
			} else {
				return [];
			}
			// Re-index array
			return array_values($query);
		}

		public function getOrgUserCount($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("SELECT COUNT(*) FROM tblUser WHERE userName LIKE '%$org%'")[0]['computed'];
		}

		/**
		 * Retrieves all employees at given org that exist in DB.
		 * Note that both users with and without content will be fetched.
		 *
		 * @param $org
		 * @return array
		 */
		public function getOrgEmployees($org){
			$this->verifyOrgAccess($org);
			// Join user/profiles table and get those users from $org with employeeProfileId only
			$query = $this->relayDB->query("
							SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relayDB->employeeProfileId());
			// Note: this replacement could be done in the query itself, if one could be bothered working it out...
			foreach($query as $key => $info){
				$query[$key]['userAffiliation'] = 'employee';
			}
			return $query;
		}

		public function getOrgEmployeeCount($org){
			$this->verifyOrgAccess($org);
			$employeeCount = $this->relayDB->query("
							SELECT COUNT(*)
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relayDB->employeeProfileId())[0]['computed'];
			return $employeeCount;
		}

		public function getOrgStudents($org){
			$this->verifyOrgAccess($org);
			// Join user/profiles table and get those users from $org with employeeProfileId only
			$query = $this->relayDB->query("
							SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relayDB->studentProfileId());
			// Note: this replacement could be done in the query itself, if one could be bothered working it out...
			foreach($query as $key => $info){
				$query[$key]['userAffiliation'] = 'student';
			}
			return $query;
		}

		public function getOrgStudentCount($org){
			$this->verifyOrgAccess($org);
			$studentCount = $this->relayDB->query("
							SELECT COUNT(*)
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relayDB->studentProfileId())[0]['computed'];
			return $studentCount;
		}

		/**
		 * Gets affiliation count (students and employees) for requested org.
		 *
		 * @param $org
		 * @return array
		 */
		public function getOrgUserCountByAffiliation($org) {
			$employeeCount = $this->getOrgEmployeeCount($org);
			$studentCount = $this->getOrgStudentCount($org);
			return array('employees' => $employeeCount, 'students' => $studentCount);
		}

		#
		# ORG PRESENTATIONS ENDPOINTS (requires minimum org-scope)
		#
		# /org/{org.no}/presentations/*/
		#
		public function getOrgPresentations($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("
						SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser, presProfile_profId
						FROM tblPresentation
						WHERE presPresenterEmail LIKE '%$org%' ");
		}

		public function getOrgPresentationCount($org) {
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("SELECT COUNT(*) FROM tblPresentation WHERE presPresenterEmail LIKE '%$org%'")[0]['computed'];
		}

		public function getOrgEmployeePresentationCount($org){
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("
						SELECT COUNT(*)
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relayDB->employeeProfileId() . "
						AND presPresenterEmail LIKE '%$org%'")[0]['computed'];
		}

		public function getOrgStudentPresentationCount($org){
			$this->verifyOrgAccess($org);
			return $this->relayDB->query("
						SELECT COUNT(*)
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relayDB->studentProfileId() . "
						AND presPresenterEmail LIKE '%$org%'")[0]['computed'];
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
			$query = $this->relayDB->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName = '$feideUserName'");

			// Convert affiliation code to text
			// Some test users have more than one profile, thus the SQL query may return more than one entry for a single user.
			// Since we're after a specific profile - either employeeProfileId or studentProfileId - run this check and return entry
			// as soon as we have a match.
			if(!empty($query)){
				foreach($query as $key => $info) {
					switch($query[$key]['userAffiliation']){
						case $this->relayDB->employeeProfileId():
							$query[$key]['userAffiliation'] = 'employee';
							return $query[$key];
						case $this->relayDB->studentProfileId():
							$query[$key]['userAffiliation'] = 'student';
							return $query[$key];
					}
				}
			} else {
				return [];
			}
		}

		/**
		 * /me/presentations/
		 * /user/[*:userName]/presentations/
		 *
		 *
		 * @param $feideUserName
		 * @return array
		 */
		public function getUserPresentations($feideUserName) {
			// NOTE: This query returns ALL presentations; also those deleted.
			// TODO: Need to find a quick way to check which presentations are deleted
			return $this->relayDB->query("
						SELECT 	presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, presProfile_profId, tblPresentation.createdOn, tblPresentation.createdByUser,
								userEmail, userName
						FROM 	tblPresentation,
								tblUser
						WHERE 	tblUser.userName = '$feideUserName'
						AND 	tblPresentation.presPresenterEmail = tblUser.userEmail");
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

		public function getTableDump($table_name, $top){
			if($this->feideConnect->isSuperAdmin() && $this->feideConnect->hasOauthScopeAdmin()) {
				return $this->relayDB->query("SELECT TOP($top) * FROM $table_name");
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
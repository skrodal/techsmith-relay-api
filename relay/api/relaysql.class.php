<?php

	namespace Relay\Api;

	use Relay\Database\RelaySQLConnection;
	use Relay\Utils\Response;

	/**
	 * Serves API routes requesting data from the TechSmith RelaySQL SQL DB.
	 *
	 * @author Simon Skrodal
	 * @since  September 2015
	 */
	class RelaySQL extends Relay  {
		private $relaySQLConnection, $dataporten, $relay;

		function __construct(Relay $relay) {
			$this->relaySQLConnection = new RelaySQLConnection();
			$this->dataporten         = $relay->dataporten();
			$this->relay              = $relay;
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/*/
		#

		public function getServiceVersion() {
			return $this->relaySQLConnection->query("SELECT * FROM tblVersion")[0];
		}

		public function getServiceInfo() {
			$response                = [];
			$response['version']     = $this->relaySQLConnection->query("SELECT * FROM tblVersion")[0];
			$response['workers']     = $this->relaySQLConnection->query("SELECT edptId, edptUrl, edptStatus, edptLastChecked,  edptNumEncodings, edptVersion, edptLicensedNumEncodings, edptRemainingMediaDiskSpaceInMB FROM tblEndpoint");
			return $response;
		}

		public function getServiceQueueFailed() {
			return $this->relaySQLConnection->query("SELECT jobId, jobType, jobState, jobPresentation_PresId, jobQueuedTime, jobPercentComplete, jobFailureReason, jobNumberOfFailures, jobTitle  FROM tblJob WHERE jobState = 3");
		}

		public function getServiceQueue() {
			return $this->relaySQLConnection->query("SELECT jobId, jobPresentation_PresId, CONVERT(VARCHAR(26), jobQueuedTime, 106) AS jobQueuedDate, CONVERT(VARCHAR(26), jobQueuedTime, 108) AS jobQueuedTime, presPresenterName, presDuration FROM tblJob
													    					INNER JOIN tblPresentation
										                					ON tblJob.jobPresentation_PresId = tblPresentation.presId WHERE tblJob.jobStartProcessingTime IS NULL AND tblJob.jobType = 0 AND tblJob.jobState = 0");
		}

		/**
		 * List of distinct orgs (domain names in username) and user count at each
		 * @return array
		 */
		public function getOrgs() {
			// Best query I could come up with... returns all domain names from username.
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT SUBSTRING(userName, CHARINDEX('@', userName) + 1, LEN(userName) - CHARINDEX('@', userName) + 1) AS org
				FROM tblUser
				ORDER BY org DESC

			");
			// Now run a count for each reoccurring domain ({"uninett.no":26,"uit.no":191, ...}
			$orgCount = array_count_values(array_map(function ($foo) {
				return $foo['org'];
			}, $sqlResponse));
			// We have a few accounts (for internal use) that don't follow user@org.no format - get rid of these
			foreach($orgCount as $org => $count) {
				// We expect org.something, if no dot, remove from list
				if(strpos($org, '.') == false) {
					unset($orgCount[$org]);
				}
			}
			// We have one testaccount with this domain, which annoys me... Get rid of it!
			unset($orgCount['outlook.com']);

			return $orgCount;
		}

		//data.org, data.org.users, data.org.presentations, data.org.total_mib, data.org.storage[]
		public function getOrgsInfo() {
			$orgsObj = $this->getOrgs();
			$response = [];
			foreach($orgsObj as $org => $count){
				$response[$org] = [];
				//$response[$org]['users'] = $count;
				$response[$org]['users'] = $this->getOrgUserCount($org);
				// $response[$org]['presentations'] = $this->getOrgPresentationCount($org);
				$storage = $this->mongo()->getOrgDiskusage($org);
				$response[$org]['storage'] = $storage['storage'];
				$response[$org]['total_mib'] = $storage['total_mib'];
			}
			return $response;
		}

		#
		# GLOBAL USERS ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /global/users/*/
		#

		public function getGlobalUserCount() {
			// Employees
			$employeeCount = $this->relaySQLConnection->query("
				SELECT COUNT(*) as total
				FROM tblUserProfile
				WHERE usprProfile_profId = " . $this->relaySQLConnection->employeeProfileId()
			);
			$employeeCount = empty($employeeCount) ? 0 : (int)$employeeCount[0]['total'];

			$studentCount = $this->relaySQLConnection->query("
				SELECT COUNT(*) as total
				FROM tblUserProfile
				WHERE usprProfile_profId = " . $this->relaySQLConnection->studentProfileId()
			);
			$studentCount = empty($studentCount) ? 0 : (int)$studentCount[0]['total'];

			// Employees with content
			$employeeActiveCount = $this->relaySQLConnection->query("
				SELECT COUNT(DISTINCT presUser_userId) AS total
				FROM tblPresentation 
				WHERE presProfile_profId = " . $this->relaySQLConnection->employeeProfileId()
			);
			$employeeActiveCount = empty($employeeActiveCount) ? 0 : (int)$employeeActiveCount[0]['total'];

			// Employees with content
			$studentActiveCount = $this->relaySQLConnection->query("
				SELECT COUNT(DISTINCT presUser_userId) AS total
				FROM tblPresentation 
				WHERE presProfile_profId = " . $this->relaySQLConnection->studentProfileId()
			);
			$studentActiveCount = empty($studentActiveCount) ? 0 : (int)$studentActiveCount[0]['total'];

			return [
				'total'     => ($employeeCount + $studentCount),
				'active'    => ($employeeActiveCount + $studentActiveCount),
				'employees' => ['total' => $employeeCount, 'active' => $employeeActiveCount],
				'students'  => ['total' => $studentCount, 'active' => $studentActiveCount]
			];
		}


		#
		# GLOBAL PRESENTATIONS ENDPOINTS (requires admin-scope)
		#
		# /global/presentations/*/
		#

		public function getGlobalPresentationCount() {
			return $this->relaySQLConnection->query("SELECT COUNT(*) AS 'count' FROM tblPresentation")[0]['count'];
		}


		public function getGlobalEmployeePresentationCount() {
			return $this->relaySQLConnection->query("
						SELECT COUNT(*) AS 'count'
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relaySQLConnection->employeeProfileId())[0]['count'];
		}

		public function getGlobalStudentPresentationCount() {
			return $this->relaySQLConnection->query("
						SELECT COUNT(*) AS 'count'
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relaySQLConnection->studentProfileId())[0]['count'];
		}

		#
		# ORG USERS ENDPOINTS (requires minimum org-scope)
		#
		# /org/{org.no}/users/*/
		#
		public function getOrgUsers($org) {
			$this->verifyOrgAccess($org);
			$query = $this->relaySQLConnection->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName LIKE '%$org%' ");

			// Convert affiliation code to text
			// Some test users have more than one profile, thus the SQL query may return more than one entry for a single user.
			// Since we're after a specific profile - either employeeProfileId or studentProfileId - run this check and delete any entries
			// that don't match our requested profiles.
			if(!empty($query)) {
				foreach($query as $key => $info) {
					switch($query[$key]['userAffiliation']) {
						case $this->relaySQLConnection->employeeProfileId():
							$query[$key]['userAffiliation'] = 'employee';
							break;
						case $this->relaySQLConnection->studentProfileId():
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

		/**
		 * Retrieves all employees at given org that exist in DB.
		 * Note that both users with and without content will be fetched.
		 *
		 * @param $org
		 *
		 * @return array
		 */
		public function getOrgEmployees($org) {
			$this->verifyOrgAccess($org);
			// Join user/profiles table and get those users from $org with employeeProfileId only
			$query = $this->relaySQLConnection->query("
							SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relaySQLConnection->employeeProfileId());
			// Note: this replacement could be done in the query itself, if one could be bothered working it out...
			foreach($query as $key => $info) {
				$query[$key]['userAffiliation'] = 'employee';
			}

			return $query;
		}

		public function getOrgStudents($org) {
			$this->verifyOrgAccess($org);
			// Join user/profiles table and get those users from $org with employeeProfileId only
			$query = $this->relaySQLConnection->query("
							SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relaySQLConnection->studentProfileId());
			// Note: this replacement could be done in the query itself, if one could be bothered working it out...
			foreach($query as $key => $info) {
				$query[$key]['userAffiliation'] = 'student';
			}

			return $query;
		}

		public function getOrgUserCount($org) {
			$this->verifyOrgAccess($org);

			$employeeCount = $this->relaySQLConnection->query("
							SELECT COUNT(*) AS 'count'
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relaySQLConnection->employeeProfileId())[0]['count'];

			$studentCount = $this->relaySQLConnection->query("
							SELECT COUNT(*) AS 'count'
								FROM   	tblUser, tblUserProfile
								WHERE 	tblUser.userId = tblUserProfile.usprUser_userId
								AND 	tblUser.userName LIKE '%$org%'
								AND 	tblUserProfile.usprProfile_profId = " . $this->relaySQLConnection->studentProfileId())[0]['count'];

			return array('total' => $employeeCount + $studentCount, 'employees' => $employeeCount, 'students' => $studentCount);
		}


		public function getOrgPresentations($org) {
			$this->verifyOrgAccess($org);

			return $this->relaySQLConnection->query("
						SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser, presProfile_profId
						FROM tblPresentation
						WHERE presPresenterEmail LIKE '%$org%' ");
		}


		public function getOrgPresentationCount($org) {
			$this->verifyOrgAccess($org);
			$employeeCount = $this->relaySQLConnection->query("
						SELECT COUNT(*) as total
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relaySQLConnection->employeeProfileId() . "
						AND presPresenterEmail LIKE '%$org%'")[0]['total'];

			$studentCount = $this->relaySQLConnection->query("
						SELECT COUNT(*) AS total
						FROM tblPresentation
						WHERE presProfile_profId = " . $this->relaySQLConnection->studentProfileId() . "
						AND presPresenterEmail LIKE '%$org%'")[0]['total'];

			return array('total' => $employeeCount + $studentCount, 'employees' => $employeeCount, 'students' => $studentCount);
		}


		/**
		 * /me/
		 * /user/[*:userName]/
		 *
		 * @param $feideUserName
		 *
		 * @return array
		 */
		public function getUser($feideUserName) {
			$query = $this->relaySQLConnection->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName = '$feideUserName'");

			// Convert affiliation code to text
			// Some test users have more than one profile, thus the SQL query may return more than one entry for a single user.
			// Since we're after a specific profile - either employeeProfileId or studentProfileId - run this check and return entry
			// as soon as we have a match.
			if(!empty($query)) {
				foreach($query as $key => $info) {
					switch($query[$key]['userAffiliation']) {
						case $this->relaySQLConnection->employeeProfileId():
							$query[$key]['userAffiliation'] = 'employee';

							return $query[$key];
						case $this->relaySQLConnection->studentProfileId():
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
		 *
		 * @return array
		 */
		public function getUserPresentations($feideUserName) {
			// NOTE: This query returns ALL presentations; also those deleted.
			return $this->relaySQLConnection->query("
						SELECT 	presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presNumberOfFiles, presMaxResolution, presPlatform, presUploaded, presProfile_profId, tblPresentation.createdOn, tblPresentation.createdByUser,
								userEmail, userName
						FROM 	tblPresentation,
								tblUser
						WHERE 	tblUser.userName = '$feideUserName'
						AND 	tblPresentation.presPresenterEmail = tblUser.userEmail");
		}

		/**
		 * For dev purposes only. Requires Admin scope and superadmin role (i.e. uninett employee).
		 *
		 * @param $table_name
		 *
		 * @return array
		 */
		public function getTableSchema($table_name) {
			if($this->dataporten->isSuperAdmin() && $this->dataporten->hasOauthScopeAdmin()) {
				return $this->relaySQLConnection->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table_name' ");
			}
			// Else
			Response::error(401, 'Unauthorized!');
		}


		// ---------------------------- UTILS ----------------------------

		public function getTableDump($table_name, $top) {
			if($this->dataporten->isSuperAdmin() && $this->dataporten->hasOauthScopeAdmin()) {
				return $this->relaySQLConnection->query("SELECT TOP($top) * FROM $table_name");
			}
			// Else
			Response::error(401, 'Unauthorized!');
		}


		// ---------------------------- ./UTILS ----------------------------

	}
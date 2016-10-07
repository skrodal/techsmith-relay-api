<?php
	namespace Relay\Api;

	use MongoRegex;
	use Relay\Auth\Dataporten;
	use Relay\Database\RelayMongoConnection;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	/**
	 * Serves API routes requesting data from UNINETTs TechSmith RelaySQL Harvesting Service.
	 *
	 * The harvester stores all consolidated information in MongoDB.
	 *
	 * @author  Simon Skrødal
	 * @see     :    https://github.com/skrodal/relay-mediasite-harvest
	 * @date    29/10/2015
	 * @time    15:24
	 */
	class RelayMongo  {
		private $relayMongoConnection, $dataporten, $relay;

		function __construct(Relay $relay) {
			$this->relayMongoConnection = new RelayMongoConnection();
			$this->dataporten           = $relay->dataporten();
			$this->relay = $relay;
		}

		########################################################################
		####
		####    ORGS
		####
		########################################################################

		// Sorted list of org names
		public function getOrgs() {
			$orgs     = $this->relayMongoConnection->findAll('org');
			$response = [];

			foreach($orgs as $org) {
				$response[] = $org['org'];
			}
			// Sorted
			sort($response);

			return $response;
		}

		// Sorted list of orgs with user count, presentation count, total_mib and storage[]
		public function getOrgsInfo() {
			$orgs = $this->getOrgs();

			foreach($orgs as $org){
				$response[$org]['users'] = $this->getOrgUserCount($org);
				$response[$org]['presentations'] = $this->getOrgPresentationCount($org);
				$diskUsage = $this->getOrgDiskusage($org);
				$response[$org]['total_mib'] = $diskUsage['total_mib'];
				$response[$org]['storage'] = $diskUsage['storage'];
			}
			return $response;
		}

		// Sorted list of org names with user count
		public function getOrgsUserCount() {
			$orgs     = $this->getOrgs();
			$response = [];

			foreach($orgs as $org) {
				$response[$org] = $this->getOrgUserCount($org);
			}

			return $response;
		}

		########################################################################
		####
		####    SINGLE USER/PRESENTATIONS
		####
		########################################################################

		// Userinfo
		public function getUser($feideUserName = NULL) {
			$feideUserName = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;

			return $this->relayMongoConnection->findOne('users', array('username' => $feideUserName));
		}

		/**
		 * User presentationson disk (new Sep. 2016: also fetching hits from IIS logparser as well as deleted presentations)
		 *
		 * @param null $feideUserName
		 * @return array
		 */
		public function getUserPresentations($feideUserName = NULL) {
			$feideUserName = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;
			$criteria      = ['username' => $feideUserName];
			// All of users content from Mongo collection
			$presentations = $this->relayMongoConnection->find('presentations', $criteria);
			// All of user's presentation hits from IIS service
			$hitList = $this->relay->presHits()->getHitsMe($feideUserName);
			// All of user's deleted presentations from delete service
			$deleteList = $this->relay->presDelete()->getDeletedPresentationsUser($feideUserName);
			// Update all presentation paths
			foreach($presentations as $index => $presObj){
				// Add deleted flag
				if(isset($deleteList[$presObj['path']])){
					$presentations[$index]['is_deleted'] = 1;
				}
				// Add hits
				if(isset($hitList[$presObj['path']])){
					$presentations[$index]['hits'] = $hitList[$presObj['path']]['hits'];
					$presentations[$index]['hits_last'] = $hitList[$presObj['path']]['timestamp_latest'];
				}
				// Remove hits attribute per file in files[] (we don't have hits per file anymore)
				foreach($presObj['files'] as $i => $fileObj){
					unset($presentations[$index]['files'][$i]['hits']);
				}
			}
			return $presentations;
		}

		// Count user presentations on disk
		public function getUserPresentationCount($feideUserName) {
			$feideUserName = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;
			$criteria      = ["username" => $feideUserName];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

		########################################################################
		####
		####    GLOBAL USERS/PRESENTATIONS
		####
		########################################################################

		// All users
		public function getGlobalUsers() {
			return $this->relayMongoConnection->findAll('users');
		}

		// Same as $this->relaySQL->getGlobalUserCount()...
		public function getGlobalUserCount() {
			return $this->relayMongoConnection->countAll('users');
		}

		// Only users with content
		public function getGlobalUserCountActive() {
			return $this->getGlobalEmployeeCount() + $this->getGlobalStudentCount();
		}

		// Same as getGlobalUserCountActive, but separated into affiliation
		public function getGlobalUserCountByAffiliation() {
			$employeeCount = $this->getGlobalEmployeeCount();
			$studentCount  = $this->getGlobalStudentCount();

			return array('total' => $employeeCount + $studentCount, 'employees' => $employeeCount, 'students' => $studentCount);
		}

		###
		# USERS BY AFFILIATION
		###

		// Userinfo, only users with content
		public function getGlobalEmployees() {
			$criteria = ['affiliation' => 'ansatt'];

			return $this->relayMongoConnection->find('users', $criteria);
		}

		// Only with content
		public function getGlobalEmployeeCount() {
			$criteria = ['affiliation' => 'ansatt'];

			return $this->relayMongoConnection->count('users', $criteria);
		}

		// Userinfo, only users with content
		public function getGlobalStudents() {
			$criteria = ['affiliation' => 'student'];

			return $this->relayMongoConnection->find('users', $criteria);
		}

		// Only with content on disk
		public function getGlobalStudentCount() {
			$criteria = ['affiliation' => 'student'];

			return $this->relayMongoConnection->count('users', $criteria);
		}

		###
		# PRESENTATIONS (use Mongo to get only content on disk since Relay MSSQL provides a view of all, inc. deleted content)
		###


		/** Simon@28.09.2016 - note to self:
		 *
		 * Works, but route commented out because a) it is not used, b) hits and deleted are not included.
		 * If route is ever needed, function getOrgPresentations shows how to incorporate b)
		 *
		 * Chews up a lot of memory, consider rewrite -> pagination or split query to find e.g. 5000 documents at a time
		 * @return array
		 */
		public function getGlobalPresentations() {
			return $this->relayMongoConnection->findAll('presentations');
		}

		public function getGlobalPresentationStats() {
			$stats = ['yesterday', 'lastweek', 'lastmonth', 'thisyear', 'prevyear', 'total'];

			$stats['yesterday'] = $this->relayMongoConnection->count('presentations', ['created' => ['$gte' => date("Y-m-d", strtotime("-1 day", time()))]]);
			$stats['lastweek'] = $this->relayMongoConnection->count('presentations', ['created' => ['$gte' => date("Y-m-d", strtotime("-7 days", time()))]]);
			$stats['lastmonth'] = $this->relayMongoConnection->count('presentations', ['created' => ['$gte' => date("Y-m-d", strtotime("-30 days", time()))]]);
			$stats['thisyear'] = $this->relayMongoConnection->count('presentations', ['created' => ['$gte' => date("Y-01-01")]]);
			$stats['prevyear'] = $this->relayMongoConnection->count('presentations', ['created' => ['$gte' => date("Y-01-01", strtotime("-1 year", time())), '$lt' =>  date("Y-01-01")]]);
			$stats['total'] = $this->getGlobalPresentationCount();
			return $stats;
		}

		public function getGlobalPresentationCount() {
			return $this->relayMongoConnection->countAll('presentations');
		}

		public function getGlobalEmployeePresentationCount() {
			$find     = 'ansatt';
			$criteria = ['path' =>
				             ['$regex' => new MongoRegex("/^$find/i")]
			];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

		public function getGlobalStudentPresentationCount() {
			$find     = 'student';
			$criteria = ['path' =>
				             ['$regex' => new MongoRegex("/^$find/i")]
			];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

		########################################################################
		####
		####    ORG USERS/PRESENTATIONS
		####
		########################################################################

		public function getOrgUsers($org) {
			$criteria = ['org' => $org];

			return $this->relayMongoConnection->find('users', $criteria);
		}

		public function getOrgUserCount($org) {
			$criteria = ['org' => $org];

			return $this->relayMongoConnection->count('users', $criteria);
		}

		public function getOrgUserCountByAffiliation($org) {
			$employeeCount = $this->getOrgEmployeeCount($org);
			$studentCount  = $this->getOrgStudentCount($org);

			return array('total' => $employeeCount + $studentCount, 'employees' => $employeeCount, 'students' => $studentCount);
		}

		public function getOrgEmployees($org) {
			$criteriaEmployee = ['org' => $org, 'affiliation' => 'ansatt'];

			return $this->relayMongoConnection->find('users', $criteriaEmployee);
		}

		public function getOrgEmployeeCount($org) {
			$criteriaEmployee = ['org' => $org, 'affiliation' => 'ansatt'];

			return $this->relayMongoConnection->count('users', $criteriaEmployee);
		}

		public function getOrgStudents($org) {
			$criteriaStudent = ['org' => $org, 'affiliation' => 'student'];

			return $this->relayMongoConnection->find('users', $criteriaStudent);
		}

		public function getOrgStudentCount($org) {
			$criteriaStudent = ['org' => $org, 'affiliation' => 'student'];

			return $this->relayMongoConnection->count('users', $criteriaStudent);
		}

		###
		# ORG PRESENTATIONS
		###

		public function getOrgPresentationsOriginal($org) {
			$criteria = ['org' => $org];
			return $this->relayMongoConnection->find('presentations', $criteria);
		}

		/**
		 * Simon@28.09.2016: Just updated this and commented out student/employee routes (not used)
		 *
		 * Get all presentations produced by $org from MongoDB. Supplement presentation info
		 * with hits (from IIS service) and deleted (from delete service) before returning.
		 *
		 * TODO: Could really use pagination - slow for orgs with 1000s of presentations
		 *
		 * @param $org
		 * @return array
		 */
		public function getOrgPresentations($org) {
			$criteria = ['org' => $org];
			// All presentations
			$presentations = $this->relayMongoConnection->find('presentations', $criteria);
			// Array with usernames -> total_hits
			$hitList = $this->relay->presHits()->getOrgPresentationsHits($org);
			// Array with path -> 'deleted'
			$deletelist = $this->relay->presDelete()->getDeletedPresentationsOrg($org);

			foreach($presentations as $index => $presObj){
				// Set deleted flag to presentations in deletelist (from delete service)
				if(isset($deletelist[$presObj['path']])){
					$presentations[$index]['is_deleted'] = 1;
				}
				// Add hits (from IIS service) to the presentations
				if(isset($hitList[$presObj['path']])){
					$presentations[$index]['hits'] = $hitList[$presObj['path']];
					//$presentations[$index]['hits_last'] = $hitList[$presObj['path']]['timestamp_latest'];
				}
				// Remove hits attribute per file in files[] (we don't have hits per file anymore)
				foreach($presObj['files'] as $i => $fileObj){
					unset($presentations[$index]['files'][$i]['hits']);
				}
			}
			return $presentations;
		}

		public function getOrgPresentationCount($org) {
			$criteria = ['org' => $org];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

		/**
		 * Simon@28.09.2016 - note to self:
		 * Works, but route commented out because a) it is not used, b) hits and deleted are not included.
		 *
		 * If route is ever needed, function getOrgPresentations shows how to incorporate b)
		 *
		 * @param $org
		 * @return array
		 */
		public function getOrgEmployeePresentations($org) {
			$find     = 'ansatt';
			$criteria = ['org'  => $org,
			             'path' =>
				             ['$regex' => new MongoRegex("/^$find/i")]
			];

			return $this->relayMongoConnection->find('presentations', $criteria);
		}

		public function getOrgEmployeePresentationCount($org) {
			$find     = 'ansatt';
			$criteria = ['org'  => $org,
			             'path' =>
				             ['$regex' => new MongoRegex("/^$find/i")]
			];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}


		/**
		 * Simon@28.09.2016 - note to self:
		 * Works, but route commented out because a) it is not used, b) hits and deleted are not included.
		 *
		 * If route is ever needed, function getOrgPresentations shows how to incorporate b)
		 *
		 * @param $org
		 * @return array
		 */
		public function getOrgStudentPresentations($org) {
			$find     = 'student';
			$criteria = ['org'  => $org,
			             'path' =>
				             ['$regex' => new MongoRegex("/^$find/i")]
			];

			return $this->relayMongoConnection->find('presentations', $criteria);
		}

		public function getOrgStudentPresentationCount($org) {
			$find     = 'student';
			$criteria = ['org'  => $org,
			             'path' =>
				             ['$regex' => new MongoRegex("/^$find/i")]
			];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

		########################################################################
		####
		####    DISKUSAGE GLOBAL/ORG/USER
		####
		########################################################################

		// Total only
		public function getServiceDiskusage() {
			$orgs = $this->relayMongoConnection->findAll('org');
			//
			$total_mib = 0;
			foreach($orgs as $org) {
				if(!empty($org['storage'])) {
					// Latest entry is most current
					$length = sizeof($org['storage']) - 1;
					$total_mib += (float)$org['storage'][$length]['size_mib'];
				}
			}

			return $total_mib;
		}

		public function getOrgsDiskusage() {
			$orgs = $this->relayMongoConnection->findAll('org');
			//
			$response['total_mib'] = 0;
			$response['orgs']      = [];

			foreach($orgs as $org) {
				if(!empty($org['storage'])) {
					// Latest entry is most current
					$length = sizeof($org['storage']) - 1;
					$latest_mib = (float)$org['storage'][$length]['size_mib'];
					$response['total_mib'] += $latest_mib;
					$response['orgs'][$org['org']] = $latest_mib;
				}
			}
			ksort($response['orgs']);

			return $response;
		}

		public function getOrgDiskusage($org) {
			$criteria              = ['org' => $org];
			$response['total_mib'] = 0;
			$response['storage']   = $this->relayMongoConnection->findOne('org', $criteria)['storage'];

			if(!empty($response['storage'])) {
				// Latest entry is most current
				$length                = sizeof($response['storage']) - 1;
				$response['total_mib'] = (float)$response['storage'][$length]['size_mib'];
			}

			return $response;
		}

		// User presentations on disk
		public function getUserDiskusage($feideUserName = NULL) {
			$feideUserName         = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;
			$criteria              = ['username' => $feideUserName];
			$response['total_mib'] = 0;
			$response['storage']   = $this->relayMongoConnection->findOne('userDiskUsage', $criteria)['storage'];

			if(!empty($response['storage'])) {
				// Latest entry is most current
				$length                = sizeof($response['storage']) - 1;
				$response['total_mib'] = (float)$response['storage'][$length]['size_mib'];
			}

			return $response;
		}

	}
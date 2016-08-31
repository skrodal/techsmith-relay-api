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
	class RelayMongo {
		private $relayMongoConnection, $relaySQL, $dataporten;

		function __construct(RelaySQL $rs, Dataporten $dataporten) {
			//
			$this->relayMongoConnection = new RelayMongoConnection();
			$this->relaySQL             = $rs;
			$this->dataporten           = $dataporten;
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

		// User presentations on disk
		public function getUserPresentations($feideUserName = NULL) {
			$feideUserName = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;
			$criteria      = ['username' => $feideUserName];

			return $this->relayMongoConnection->find('presentations', $criteria);
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
		# PRESENTATIONS (only content on disk - SQL provides a view of all, inc. deleted content)
		###

		// ALL presentations on disk
		// NOTE: Chews up a lot of memory, consider rewrite -> pagination or split query to find e.g. 5000 documents at a time
		public function getGlobalPresentations() {
			return $this->relayMongoConnection->findAll('presentations');
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

		public function getOrgPresentations($org) {
			$criteria = ['org' => $org];

			return $this->relayMongoConnection->find('presentations', $criteria);
		}

		public function getOrgPresentationCount($org) {
			$criteria = ['org' => $org];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

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
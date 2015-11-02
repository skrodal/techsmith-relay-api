<?php
	namespace Relay\Api;

	use MongoRegex;
	use Relay\Auth\FeideConnect;
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
		private $relayMongoConnection, $relaySQL, $feideConnect;

		function __construct(RelaySQL $rs, FeideConnect $fc) {
			//
			$this->relayMongoConnection = new RelayMongoConnection();
			$this->relaySQL             = $rs;
			$this->feideConnect         = $fc;
		}


		########################################################################
		####
		####    SINGLE USER/PRESENTATIONS
		####
		########################################################################

		// Userinfo
		public function getUser($feideUserName = NULL) {
			$feideUserName = is_null($feideUserName) ? $this->feideConnect->userName() : $feideUserName;

			return $this->relayMongoConnection->findOne('users', array('username' => $feideUserName));
		}

		// User presentations on disk
		public function getUserPresentations($feideUserName = NULL) {
			$feideUserName = is_null($feideUserName) ? $this->feideConnect->userName() : $feideUserName;
			$criteria      = ['username' => $feideUserName];

			return $this->relayMongoConnection->find('presentations', $criteria);
		}

		// Count user presentations on disk
		public function getUserPresentationCount($feideUserName) {
			$feideUserName = is_null($feideUserName) ? $this->feideConnect->userName() : $feideUserName;
			$criteria      = ["username" => $feideUserName];

			return $this->relayMongoConnection->count('presentations', $criteria);
		}

		########################################################################
		####
		####    GLOBAL USERS/PRESENTATIONS
		####
		########################################################################

		public function getGlobalUsers() {
			return $this->relayMongoConnection->findAll('users');
		}

		// Same as $this->relaySQL->getGlobalUserCount()...
		public function getGlobalUserCount() {
			return $this->relayMongoConnection->countAll('users');
		}

		public function getGlobalUserCountActive() {
			return $this->getGlobalEmployeeCount() + $this->getGlobalStudentCount();
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
			return $this->relayMongoConnection->count('users', ['org' => $org]);
		}

		public function getOrgUserCountByAffiliation($org) {
			$response         = [];
			$criteriaEmployee = ['org' => $org, 'affiliation' => 'ansatt'];
			$criteriaStudent  = ['org' => $org, 'affiliation' => 'student'];

			$response['employees'] = $this->relayMongoConnection->count('users', $criteriaEmployee);
			$response['students']  = $this->relayMongoConnection->count('users', $criteriaStudent);

			return $response;

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

		public function getGlobalDiskusage() {
			$response['total_mib'] = 0;
			$response['orgs']     = $this->relayMongoConnection->findAll('org');

			foreach($response['orgs'] as $org) {
				if(!empty($org['storage'])) {
					// Latest entry is most current
					$length = sizeof($org['storage']) - 1;
					$response['total_mib'] += (float)$org['storage'][$length]['size_mib'];
				}
			}

			return (float)$response;
		}

		public function getOrgDiskusage($org) {
			$criteria = ['org' => $org];
			$usageArr = $this->relayMongoConnection->find('org', $criteria);
			if(empty($usageArr)) {
				return [];
			}

			// Latest entry is most current
			return array_slice($usageArr, -1)[0];
		}

	}
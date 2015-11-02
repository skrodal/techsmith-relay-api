<?php
	namespace Relay\Api;

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
	 * @see:    https://github.com/skrodal/relay-mediasite-harvest
	 * @date    29/10/2015
	 * @time    15:24
	 */

	class RelayMongo {
		private $relayMongoConnection, $relaySQL, $feideConnect;

		function __construct(RelaySQL $rs, FeideConnect $fc) {
			//
			$this->relayMongoConnection = new RelayMongoConnection();
			$this->relaySQL = $rs;
			$this->feideConnect = $fc;
		}


		###
		# SINGLE USER
		###

		// Userinfo
		public function getUser($feideUserName){
			return $this->relayMongoConnection->findOne("users", array("username" => $feideUserName));
		}

		// User presentations on disk
		public function getUserPresentations($feideUserName){
			// Simple test to get all presentations *on disk* for a specific user.
			$response = [];
			$criteria = ['username' => $feideUserName];
			$presentations = $this->relayMongoConnection->find("presentations", $criteria);
			// Iterate the cursor
			foreach($presentations as $presentation){
				// Push document (array) into response array
				array_push($response, $presentation);
			}
			// Close the cursor (apparently recommended)
			$presentations->reset();
			return $response;
		}

		// Count user presentations on disk
		public function getUserPresentationCount($feideUserName){
			$criteria = ["username" => $feideUserName];
			return $this->relayMongoConnection->count("presentations", $criteria);
		}

		###
		# ALL USERS
		###
		public function getGlobalUsers(){
			$response = [];
			$users = $this->relayMongoConnection->findAll("users");
			// Iterate the cursor
			foreach($users as $user){
				// Push document (array) into response array
				array_push($response, $user);
			}
			// Close the cursor (apparently recommended)
			$users->reset();
			return $response;
		}

		// Same as $this->relaySQL->getGlobalUserCount()... wonder which is faster... -> TODO
		public function getGlobalUserCount() {
			return $this->relayMongoConnection->countAll('users');
		}

		###
		# USERS BY AFFILIATION
		###
		// Userinfo, only users with content
		public function getGlobalEmployees(){
			// Simple test to get all presentations *on disk* for a specific user.
			$response = [];
			$criteria = ['affiliation' => 'ansatt'];
			$employees = $this->relayMongoConnection->find("users", $criteria);
			// Iterate the cursor
			foreach($employees as $employee){
				// Push document (array) into response array
				array_push($response, $employee);
			}
			// Close the cursor (apparently recommended)
			$employees->reset();
			return $response;
		}

		// Only with content
		public function getGlobalEmployeeCount(){
			$criteria = ['affiliation' => 'ansatt'];
			return $this->relayMongoConnection->count("users", $criteria);

		}

		// Userinfo, only users with content
		public function getGlobalStudents(){
			// Simple test to get all presentations *on disk* for a specific user.
			$response = [];
			$criteria = ['affiliation' => 'student'];
			$students = $this->relayMongoConnection->find("users", $criteria);
			// Iterate the cursor
			foreach($students as $student){
				// Push document (array) into response array
				array_push($response, $student);
			}
			// Close the cursor (apparently recommended)
			$students->reset();
			return $response;
		}

		// Only with content on disk
		public function getGlobalStudentCount(){
			$criteria = ['affiliation' => 'student'];
			return $this->relayMongoConnection->count("users", $criteria);

		}

		###
		# PRESENTATIONS (only content on disk - SQL provides a view of all, inc. deleted content)
		###

		// ALL presentations on disk
		public function getGlobalPresentations() {
			// Simple test to get all presentations *on disk* for a specific user.
			$response = [];
			$presentations = $this->relayMongoConnection->findAll("presentations");
			// Iterate the cursor
			foreach($presentations as $presentation){
				// Push document (array) into response array
				array_push($response, $presentation);
			}
			// Close the cursor (apparently recommended)
			$presentations->reset();
			return $response;
		}

		public function getGlobalPresentationCount(){
			return $this->relayMongoConnection->countAll('presentations');
		}



		public function test(){
			// Simple test to get all presentations *on disk* for a specific user.
			$response = [];
			$criteria = ['username' => 'simon@uninett.no'];
			$test = $this->relayMongoConnection->find("presentations", $criteria);
			// Iterate the cursor
			foreach($test as $document){
				// Push document (array) into response array
				array_push($response, $document);
			}
			// Close the cursor (apparently recommended)
			$test->reset();
			// Response
			// Response::result(array('status' => true, 'data' => $response ));
			return $response;
		}
	}
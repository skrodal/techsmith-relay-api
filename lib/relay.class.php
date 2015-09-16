<?php

	/**
	 * Class to extract information pertaining to eCampus services from Kind.
	 *
	 * @author Simon Skrodal
	 * @since  August 2015
	 */
	class Relay {
		private $DEBUG = true;
		private $relayDB;

		function __construct($DB) {
			$this->relayDB = $DB;
		}

		// /me/ and /user/[*:userName]/
		public function getUser($feideUserName) {
			$query   = $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
			$userOjb = array();
			if(!empty($query)) {
				error_log($query[0]['userDisplayName']);
				$userObj['name']     = $query[0]['userDisplayName'];
				$userObj['email']    = $query[0]['userEmail'];
				$userObj['username'] = $query[0]['userName'];
			}

			return $userOjb;
		}

		public function getUserPresentations($feideUserName) {
			return $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
		}

		public function getUserPresentationCount($feideUserName) {
			return $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
		}


		// ---------------------------- UTILS ----------------------------


		private function _logger($text, $line, $function) {
			if($this->DEBUG) {
				error_log($function . '(' . $line . '): ' . $text);
			}
		}

		// ---------------------------- ./UTILS ----------------------------

	}
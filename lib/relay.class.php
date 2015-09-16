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
			$query = $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
			return empty($query) ? array() : $query[0];
		}

		public function getUserPresentations($feideUserName) {
			return $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
		}

		public function getUserPresentationCount($feideUserName) {
			return $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
		}








		// ---------------------------- UTILS ----------------------------




		// ---------------------------- ./UTILS ----------------------------

	}
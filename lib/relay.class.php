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

		// /me/ and /user/[*:userName]/
		public function getUser($feideUserName) {
			$query = $this->relayDB->query("SELECT userName, userDisplayName, userEmail, userTechSmithId FROM tblUser WHERE userName = '$feideUserName'");
			return empty($query) ? array() : $query[0];
		}

		/**
		 * TODO: Could possibly be achieved using a single DB query
		 * @param $feideUserName
		 *
		 * @return array
		 */
		public function getUserPresentations($feideUserName) {
			/*
			presUser_userId
			presPresenterName
			presPresenterEmail
			presTitle
			presDescription
			presDuration
			presMaxResolution
			presPlatform
			presUploaded
			createdOn
			createdByUser
			*/

			// Get this user's userId first
			$userId = $this->relayDB->query("SELECT userId FROM tblUser WHERE userName = '$feideUserName'");
			$userId = $userId[0]['userId'] ? $userId[0]['userId'] : null;
			// Then presentations
			return $userId !== null ?
				$this->relayDB->query("
					SELECT presUser_userId, presPresenterName, presPresenterEmail, presTitle, presDescription, presDuration, presMaxResolution, presPlatform, presUploaded, createdOn, createdByUser
					FROM tblPresentation
					WHERE presUser_userId = $userId ")
				: array();
		}

		public function getUserPresentationCount($feideUserName) {
			return sizeof( $this->getUserPresentations($feideUserName) );
		}



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
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
			$query = $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
			return empty($query) ? array() : $query[0];
		}

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

			SELECT

			FROM
                users u
            INNER JOIN properties p
    	        ON u.id = p.userID
			WHERE
                p.property = <some value>
			*/

			return $this->relayDB->query("SELECT presTitle, presDescription, presDuration FROM tblPresentation WHERE createdByUser = '$feideUserName'");
		}

		public function getUserPresentationCount($feideUserName) {
			return $this->relayDB->query("SELECT userName, userDisplayName, userEmail FROM tblUser WHERE userName = '$feideUserName'");
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
<?php

	namespace Relay\Auth;

	use Relay\Conf\Config;
	use Relay\Utils\Response;

	/**
	 *
	 * @author Simon Skrødal
	 * @since  August 2015
	 */
	class Dataporten {

		protected $config;
		private $isOrgAdmin = false;

		function __construct() {
			// Exits on OPTION call
			$this->_checkCORS();
			$this->config = file_get_contents(Config::get('auth')['dataporten']);
			// Sanity
			if($this->config === false) {
				Response::error(404, 'Not Found: Dataporten config.');
			}
			// Dataporten username and pass
			$this->config = json_decode($this->config, true);
			// Exits on incorrect credentials
			$this->_checkGateKeeperCredentials();

			// Make sure we have a scope
			// (NOTE: 'basic' scope is implicit and not listed in HTTP_X_DATAPORTEN_SCOPES. This means that client MUST have access
			// to at least ONE extra custom scope).
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_SCOPES"])) {
				Response::error(401, 'Unauthorized (missing scope)');
			}
			// Check that we got a username
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"])) {
				Response::error(401, 'Unauthorized (user not found)');
			}
			// Check if user is member of MediasiteAdmin group
			$this->isOrgAdmin = $this->_getOrgAdminStatus();

			// Check that username exists and is a Feide one... Function will exit if not.
			//$this->_getFeideUsername();

		}


		/**
		 * Approve initial CORS request.
		 */
		private function _checkCORS() {
			// Access-Control headers are received during OPTIONS requests
			if(strcasecmp($_SERVER['REQUEST_METHOD'], "OPTIONS") === 0) {
				Response::result('CORS OK :-)');
			}
		}

		/**
		 * Check that client credentials sent from Dataporten's GK match this APIs creds.
		 *
		 * Exits if there are any issues.
		 */
		private function _checkGateKeeperCredentials() {
			if(empty($_SERVER["PHP_AUTH_USER"]) || empty($_SERVER["PHP_AUTH_PW"])) {
				Response::error(401, 'Unauthorized (Missing API Gatekeeper Credentials)');
			}
			// Gatekeeper. user/pwd is passed along by the Dataporten Gatekeeper and must matched that of the registered API:
			if((strcmp($_SERVER["PHP_AUTH_USER"], $this->config['user']) !== 0) ||
				(strcmp($_SERVER["PHP_AUTH_PW"], $this->config['passwd']) !== 0)
			) {
				// The status code will be set in the header
				Response::error(401, 'Unauthorized (Incorrect API Gatekeeper Credentials)');
			}
		}

		/**
		 * Query the Dataporten Groups API for logged on user's MediasiteAdmin group membership status
		 * Called once by constructor.
		 * @return bool
		 */
		private function _getOrgAdminStatus() {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://groups-api.dataporten.no/groups/me/groups/' . $this->config['group_id']);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			// Set headers
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
					"Authorization: Bearer " . $_SERVER["HTTP_X_DATAPORTEN_TOKEN"],
					"Content-Type: application/json",
				]
			);
			// Send the request, don't really care about the response for now, only HTTP code
			$response = curl_exec($ch);
			//
			if(curl_errno($ch)) {
				return false;
			}
			//
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			//
			curl_close($ch);
			// code will be 200 if member, 404 otherwise
			return $httpcode == 200;
		}

		/**
		 * Checks if calling client has access to the admin scope
		 * @return bool
		 */
		public function hasOauthScopeAdmin() {
			return $this->_hasDataportenScope("admin");
		}


		/**
		 * Loop and match requested scope with what Dataporten sent over in the headers.
		 * @param $scope
		 * @return bool
		 */
		private function _hasDataportenScope($scope) {
			// Get the scope(s)
			$scopes = $_SERVER["HTTP_X_DATAPORTEN_SCOPES"];
			// Make array
			$scopes = explode(',', $scopes);

			// True/false
			return in_array($scope, $scopes);
		}

		/**
		 * Checks if calling client has access to the org scope
		 * @return bool
		 */
		public function hasOauthScopeOrg() {
			return $this->_hasDataportenScope("org");
		}

		/**
		 * A super/org admin may access the invitation link to the MediasiteAdmin group.
		 * The link may be sent to users they think should be granted orgAdmin privilege
		 * for their org.
		 *
		 * @return bool
		 */
		public function adminGroupInviteLink() {
			if($this->isOrgAdmin() || $this->isSuperAdmin()) {
				return $this->config['group_invite'];
			}
			//
			return false;
		}

		/**
		 * Send just a string back that the client can use to display the role or whatever.
		 * @return string
		 */
		public function userRole(){
			if($this->isSuperAdmin()) return "SuperAdmin";
			else if($this->isOrgAdmin()) return "OrgAdmin";
			else return "Basic";
		}

		/**
		 * Check MediasiteAdmin group membership.
		 * @return bool
		 */
		public function isOrgAdmin() {
			return $this->isOrgAdmin;
		}

		/**
		 * Any UNINETT user === SuperAdmin.
		 * @return bool
		 */
		public function isSuperAdmin() {
			return strcasecmp($this->userOrg(), "uninett.no") === 0;
		}

		public function hasOauthScopeUser() {
			return $this->_hasDataportenScope("user");
		}

		/**
		 * {orgname}.no
		 * @return mixed
		 */
		public function userOrg() {
			$userOrg = explode('@', $this->userName());

			return $userOrg[1];
		}

		/**
		 * Feide username (userid_sec from Dataporten).
		 * @return null
		 */
		public function userName() {
			return $this->_getFeideUsername();
		}

		/**
		 * Gets the feide username (if present) from the Gatekeeper via HTTP_X_DATAPORTEN_USERID_SEC.
		 *
		 * It should only return a single string, 'feide:user@org.no', but future development might introduce
		 * a comma-separated or array representation of more than one username
		 * (e.g. "openid:user@org.no, feide:user@org.no")
		 *
		 * This function takes care of all of these cases.
		 */
		private function _getFeideUsername() {
			$userIdSec = NULL;
			// Get the username(s)
			$userid = $_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"];
			// Future proofing...
			if(!is_array($userid)) {
				// If not already an array, make it so. If it is not a comma separated list, we'll get a single array item.
				$userid = explode(',', $userid);
			}
			// Fish for a Feide username
			foreach($userid as $key => $value) {
				if(strpos($value, 'feide:') !== false) {
					$value     = explode(':', $value);
					$userIdSec = $value[1];
				}
			}
			// No Feide...
			if(!isset($userIdSec)) {
				Response::error(401, 'Unauthorized (user not found)');
			}

			// 'username@org.no'
			return $userIdSec;
		}

	}
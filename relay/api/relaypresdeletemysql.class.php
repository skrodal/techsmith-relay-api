<?php

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Database\RelayMySQLConnection;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	/**
	 * Serves API `user-scope` routes requesting a user presentation to be deleted/undeleted.
	 *
	 * Talks with a MySQL DB table built for this purpose.
	 *
	 * @author Simon Skrodal
	 * @since  July 2016
	 */
	class RelayPresDeleteMySQL extends Relay {
		private $relayMySQLConnection = false;
		private $sql, $table_name, $dataporten, $feideUserName;
		private $configKey = 'relay_mysql_presdelete';

		function __construct($dataporten) {
			$this->dataporten     = $dataporten;
			$this->feideUserName  = $this->dataporten->userName();
		}

		private function init(){
			if (!$this->relayMySQLConnection){
				$this->relayMySQLConnection = new RelayMySQLConnection($this->configKey);
				$this->table_name           = $this->relayMySQLConnection->getConfig('db_table_name');
				$this->sql                  = $this->relayMySQLConnection->db_connect();
			}
		}

		#
		# ADMIN ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /admin/presentations/deletelist/*/
		#
		public function getAllPresentationRecordsAdmin() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name");
			return $this->_sqlResultToArray($result);
		}

		// Presentations recently added to deletelist that have not yet been moved (may be cancelled)
		public function getDeletedPresentationsAdmin() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE deleted = 1");
			return $this->_sqlResultToArray($result);
		}

		public function getMovedPresentationsAdmin() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE moved = 1 AND deleted <> 1");
			return $this->_sqlResultToArray($result);
		}


		#
		# USER (ME) ENDPOINTS (requires user-scope)
		#

		# GET /me/presentations/deletelist/*/


		// All presentations in the deletelist (client can choose to determine which is which).
		public function getAllDeletelistPresentationsMe() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE username = '$this->feideUserName'");
			return $this->_sqlResultToArray($result);
		}

		// Presentations in the deletelist that have been moved, but not yet deleted (may be restored)
		// Note! Will also return presentations where a restore has been requested already!
		public function getNotMovedPresentationsMe() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE username = '$this->feideUserName' AND moved = 0");
			return $this->_sqlResultToArray($result);
		}

		// Presentations in the deletelist that have been deleted (thus cannot be restored)
		public function getMovedPresentationsMe() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE username = '$this->feideUserName' AND moved = 1 AND deleted <> 1");
			return $this->_sqlResultToArray($result);
		}

		// 
		public function getDeletedPresentationsMe() {
			$this->init();
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE username = '$this->feideUserName' AND deleted = 1");
			return $this->_sqlResultToArray($result);
		}
		
		//
		private function _sqlResultToArray($result) {
			$response = array();
			// Loop returned rows and create a response
			while($row = $result->fetch_assoc()) {
				array_push($response, $row);
			}

			return $response;
		}
		
		
		# POST /me/presentation/deletelist/*/

		// Add a single presentation to the deletelist

		public function deletePresentationMe() {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			$presPath    = isset($requestBody['presentation']['path']) ? $this->sql->real_escape_string($requestBody['presentation']['path']) : Response::error(400, 'Bad request: Missing required data in request body.');
			// Double check that the username in request equals Dataporten user
			if(strcasecmp($requestBody['presentation']['username'], $this->feideUserName) !== 0){
				Response::error(400, 'Bad request: Client/API user mismatch.');
			}
			$this->init();
			// If the presentation is already in the table, exit
			if($result = $this->sql->query("SELECT * FROM $this->table_name WHERE path='$presPath'")->fetch_assoc()) {
				Response::result(array('info' => 'Presentation is already in the deletelist'));
			} else {
				// Do the insert
				$query = "INSERT INTO $this->table_name (path, username) VALUES ('$presPath', '$this->feideUserName')";
				// Exit on error
				if(!$result = $this->sql->query($query)) {
					Response::error(500, "500 Internal Server Error (DB INSERT failed): " . $this->sql->error);
				}
				return 'Request to delete presentation OK.';
			}
		}

		// Remove a single presentation from the deletelist (prior to it being moved)
		public function restorePresentationMe() {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			$presentationPath = isset($requestBody['presentation']['path']) ? $this->sql->real_escape_string($requestBody['presentation']['path']) : Response::error(400, 'Bad request: Missing required data in request body.');
			$this->init();
			// See if entry is in table and that it is not already moved/deleted
			if($presToDelete = $this->sql->query("SELECT * FROM $this->table_name WHERE path='$presentationPath' AND moved <> 1 AND deleted <> 1")->fetch_assoc()){
				$query = "DELETE FROM $this->table_name WHERE path='$presentationPath'";
				// Exit on error
				if(!$result = $this->sql->query($query)) {
					Response::error(500, "500 Internal Server Error (DB DELETE FROM table failed): ". $this->sql->error);//. $mysqli->error
				}
				return 'Request to cancel presentation delete OK.';
			} else {
				// The requested presentation record does not exist in the table
				Response::error(400, 'Bad request: The requested presentation does not exist in the table, or it is already deleted/moved.');
			}
		}

		// Request a moved presentation to be moved back
		public function undeletePresentationMe() {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			$presentationPath    = isset($requestBody['presentation']['path']) ? $this->sql->real_escape_string($requestBody['presentation']['path']) : Response::error(400, 'Bad request: Missing required data in request body.');
			$this->init();
			// See if entry is in table and that it is already marked as moved (and not deleted)
			if($presToUnDelete = $this->sql->query("SELECT * FROM $this->table_name WHERE path='$presentationPath' AND moved = 1 AND deleted <> 1")->fetch_assoc()){
				$query = "UPDATE $this->table_name SET undelete=1 WHERE path='$presentationPath'";
				// Exit on error
				if(!$result = $this->sql->query($query)) {
					Response::error(500, "500 Internal Server Error (DB UPDATE table failed): ". $this->sql->error);//. $mysqli->error
				}
				return 'Request to undelete presentation OK.';
			} else {
				// The requested presentation record does not exist in the table
				Response::error(400, 'Bad request: The requested presentation does not exist in the table, or it is already deleted.');
			}
		}

	}
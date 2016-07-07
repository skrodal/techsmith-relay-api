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
	class RelayPresDeleteMySQL {
		private $sql, $table_name, $dataporten, $feideUserName;

		function __construct(Dataporten $fc) {
			//
			$relayMySQLConnection = new RelayMySQLConnection();
			$this->sql            = $relayMySQLConnection->db_connect();
			$this->table_name     = $relayMySQLConnection->getTableName();
			$this->dataporten     = $fc;
			$this->feideUserName  = $this->dataporten->userName();
		}

		#
		# ADMIN ENDPOINTS (requires admin-scope) AND Role of Superadmin
		#
		# /admin/presentations/deletelist/*/
		#
		public function getAllPresentationRecordsAdmin() {
			$result = $this->sql->query("SELECT * FROM $this->table_name");

			return $this->_sqlResultToArray($result);
		}

		// Presentations recently added to deletelist that have not yet been moved (may be cancelled)
		public function getDeletedPresentationsAdmin() {
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE deleted = 1");

			return $this->_sqlResultToArray($result);
		}

		public function getMovedPresentationsAdmin() {
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE moved = 1 AND deleted <> 1");

			return $this->_sqlResultToArray($result);
		}


		#
		# USER (ME) ENDPOINTS (requires user-scope)
		#

		# GET /me/presentations/deletelist/*/



		// Presentations in the deletelist that have been moved, but not yet deleted (may be restored)
		// Note! Will also return presentations where a restore has been requested already!
		public function getNotMovedPresentationsMe() {
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE username = '$this->feideUserName' AND moved = 0");
			return $this->_sqlResultToArray($result);
		}

		// Presentations in the deletelist that have been deleted (thus cannot be restored)
		public function getMovedPresentationsMe() {
			$result = $this->sql->query("SELECT * FROM $this->table_name WHERE username = '$this->feideUserName' AND moved = 1 AND deleted <> 1");
			return $this->_sqlResultToArray($result);
		}

		// 
		public function getDeletedPresentationsMe() {
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

			// If the presentation is already in the table, exit
			if($result = $this->sql->query("SELECT * FROM $this->tableName WHERE path='$presPath'")->fetch_assoc()) {
				Response::result(array('info' => 'Presentation was already in the deletelist'));
			} else {
				// Do the insert
				$query = "INSERT INTO $this->tableName (path, username) VALUES ('$presPath', '$this->feideUserName')";
				// Exit on error
				if(!$result = $this->sql->query($query)) {
					Response::error(500, "500 Internal Server Error (DB INSERT failed): " . $this->sql()->error);
				}
				Response::result($result->fetch_assoc());
			}
		}

		// Remove a single presentation from the deletelist (prior to it being moved)
		public function restorePresentationMe() {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			$presID    = isset($requestBody['presentation']['id']) ? $this->sql->real_escape_string($requestBody['presentation']['id']) : Response::error(400, 'Bad request: Missing required data in request body.');
			// See if entry is in table and that it is not already moved/deleted
			if($presToDelete = $this->sql->query("SELECT * FROM $this->tableName WHERE id='$presID' AND moved <> 1 AND deleted <> 1")->fetch_assoc()){
				$sql = "DELETE FROM $this->tableName WHERE id='$presID'";
				// Exit on error
				if(!$result = $this->sql->query($sql)) {
					Response::error(500, "500 Internal Server Error (DB DELETE FROM table failed): ". $this->sql()->error);//. $mysqli->error
				}
				Response::result($result->fetch_assoc());
			} else {
				// The requested presentation record does not exist in the table
				Response::error(400, 'Bad request: The requested presentation does not exist in the table');
			}
		}

		// Request a moved presentation to be moved back
		public function undeletePresentationMe() {
			// Will exit on errors
			$requestBody = Utils::getPresentationRequestBody();
			$presID    = isset($requestBody['presentation']['id']) ? $this->sql->real_escape_string($requestBody['presentation']['id']) : Response::error(400, 'Bad request: Missing required data in request body.');
			// See if entry is in table and that it is already marked as moved (and not deleted)
			if($presToUnDelete = $this->sql->query("SELECT * FROM $this->tableName WHERE id='$presID' AND moved = 1 AND deleted <> 1")->fetch_assoc()){
				$query = "UPDATE $this->tableName SET undelete=1 WHERE id=$presID";
				// Exit on error
				if(!$result = $this->sql->query($sql)) {
					Response::error(500, "500 Internal Server Error (DB UPDATE table failed): ". $this->sql()->error);//. $mysqli->error
				}
				Response::result($result->fetch_assoc());
			} else {
				// The requested presentation record does not exist in the table
				Response::error(400, 'Bad request: The requested presentation does not exist in the table');
			}
		}


		/**
		 *
		 */
		public function movePresentations() {

			$response = array();
			$issues   = false;
			// Will exit on error
			$requestBody = Utils::getRequestBody();

			// Loop each org and save storage in db
			foreach($requestBody['presentations'] as $presentation) {
				$presentationID = $this->sql()->real_escape_string($presentation['id']);
				// Note: will not complain about presentations with the `deleted` flag already set
				$query = "UPDATE $this->tableName SET moved=1 WHERE id=$presentationID";
				// Exit on error
				if(!$result = $this->sql()->query($query)) {
					Response::error(500, "500 Internal Server Error (DB INSERT failed): " . $this->sql()->error);
				}

				// See if presentation is in table before we continue
				if($presToBeMoved = $this->sql()->query("SELECT * FROM $this->tableName WHERE id=$presentationID")->fetch_assoc()) {
					array_push($response, $presToBeMoved);
				} else {
					$issues = true;
					array_push($response, array('id' => $presentationID, 'error' => 'Not found in the table. Skipped.'));
				}
			}

			if(!$issues) {
				Response::result($response, "Presentations were successfully marked as moved.");
			} else {
				Response::result($response, "One or more presentation IDs were not found in the table. See 'response' object for more info.");
			}

		}


	}
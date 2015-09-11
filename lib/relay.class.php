<?php
/**
 * Class to extract information pertaining to eCampus services from Kind. 
 *
 * @author Simon Skrodal
 * @since  August 2015
 */


class Relay {
	private $DEBUG = false;
	private $relayDB;


	function __construct($DB) {
		$this->relayDB = $DB;
		$this->relayDB->connect();
		error_log("Connected");

	}

	// /me/ and /user/[*:userName]/
	public function getUser($feideUserName){
		$response = $this->relayDB->query("SELECT * FROM tblUser WHERE userName LIKE '$feideUserName'");
		$this->relayDB->close();
		return $response;
	}










	// ---------------------------- UTILS ----------------------------


	private function _logger($text, $line, $function) {
		if($this->DEBUG) {
			error_log($function . '(' . $line . '): ' . $text);
		}
	}

	// ---------------------------- ./UTILS ----------------------------
	
}
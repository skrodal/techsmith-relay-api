<?php
/**
 * Class to extract information pertaining to eCampus services from Kind. 
 *
 * @author Simon Skrodal
 * @since  August 2015
 */


class Relay {
	private $DEBUG = false;

	function __construct() {
		$relayDB->connect();
		error_log("Connected");

	}

	// /me/ and /user/[*:userName]/
	public function getUser($feideUserName){
		$response = $relayDB->query("SELECT * FROM tblUser WHERE userName LIKE '$feideUserName'");
		$relayDB->close();
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
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
	public function getUser($feideUserName){
		$this->_logger("Feide Username: " . $feideUserName, __LINE__, __FUNCTION__);
		$feideUserName = 'simon1@uninett.no';
		return $this->relayDB->query("SELECT * FROM INFORMATION_SCHEMA.TABLES");
	}



	// ---------------------------- UTILS ----------------------------


	private function _logger($text, $line, $function) {
		if($this->DEBUG) {
			error_log($function . '(' . $line . '): ' . $text);
		}
	}

	// ---------------------------- ./UTILS ----------------------------
	
}
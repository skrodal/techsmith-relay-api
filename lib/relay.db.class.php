<?php
/**
 * Handles DB Connection and queries
 *
 * @author Simon Skrodal
 * @since  August 2015
 */


class RelayDB {
	private $conn, $config; 
	private $DEBUG = true;

	function __construct($config) {
		$this->config = $config;
	}


	/**
	 * 
	 */
	public function query($sql){
		// 
		$this->connect();
		// Run query
		$query = mssql_query($sql, $this->conn);
		// On error
		if($query === FALSE){ Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB query failed.'); }
		// Response
		$response = array();

		$this->_logger("Rows returned: " . mssql_num_rows($query), __LINE__, __FUNCTION__);

		// Loop rows and add to response array
		if (mssql_num_rows($query) > 0) {
		    while ($row = mssql_fetch_assoc($query)) {
		        $response[] = $row;
		        error_log(print_r($response, true));
		    }
		}
		// Free the query result
		mssql_free_result($query);
		// Close link
		$this->close();
		//
		return $response;
	}


	/**
	 *	Open MSSQL connection
	 */
	private function connect(){
		// 
		$this->conn = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
		// 
		if(!$this->conn){ Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed.'); }
		//
		if(!mssql_select_db($this->config['db'])) { Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB table connection failed.'); }

		$this->_logger("DB CONNECTED", __LINE__, __FUNCTION__);
	}

	/**
	 *	Close MSSQL connection
	 */
	private function close(){
		if($this->conn !== FALSE) {
			mssql_close($this->conn);	
		}
		$this->_logger("DB CLOSED", __LINE__, __FUNCTION__);
	}

	/**
	 * DEV
	 */
	private function _logger($text, $line, $function) {
		if($this->DEBUG) {
			error_log($function . '(' . $line . '): ' . $text);
		}
	}
}
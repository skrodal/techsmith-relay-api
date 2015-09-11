<?php
/**
 * Handles DB Connection and queries
 *
 * @author Simon Skrodal
 * @since  August 2015
 */


class RelayDB {
	private $DEBUG = false;
	private $conn; private $config;

	function __construct($config) {
		$this->config = $config;
	}


	public function connect(){
		// 
		$this->conn = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
		// 
		if(!$this->conn){ Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB connection failed.'); }
		//
		if(!mssql_select_db($this->config['db'])) { Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB table connection failed.'); }
	}

	public function query($query){
			// Run query
			$response = mssql_query($query);
			// On error
			if($response === FALSE){ Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' DB query failed.'); }
			// 
			$result = mssql_result($response, 0, 'userName');
			// Clean up
			mssql_free_result($response);
			// 
			$this->close();
			//
			return $result;
	}

	/**
	 *	Close the connection
	 */
	public function close(){
		if($this->conn !== FALSE) {
			mssql_close($this->conn);	
		}
	}
}
<?php

   /**
	* Accepts following scopes:
	*	- admin
	*	- org
	*	- user
	* @author Simon Skrødal
	* @since  September 2015
	*/
	
	###			CONFIGS			###	
	
	$FEIDE_CONNECT_CONFIG_PATH 	= '/var/www/etc/techsmith-relay/feideconnect_config.js';
	$RELAY_CONFIG_PATH			= '/var/www/etc/techsmith-relay/relay_config.js';
	$API_BASE_PATH 				= '/api/techsmith-relay';								// Remember to update .htacces as well!
	
	###			SETUP			###
	
	$BASE          				= dirname(__FILE__);		// API Root
	require_once($BASE . '/lib/response.class.php');		// Result or error responses
	require_once($BASE . '/lib/feideconnect.class.php');	// Checks CORS and pulls FeideConnect info from headers
	
	### 	FEIDE CONNECT		###

	$feide_connect_config 	= file_get_contents($FEIDE_CONNECT_CONFIG_PATH);
	if($feide_connect_config === FALSE) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Connect config.'); }
	$FeideConnect 			= new FeideConnect(json_decode($feide_connect_config, true));
	
	###			RELAY			###
	
	require_once($BASE . '/lib/relay.class.php');
	$relay_config 	= file_get_contents($RELAY_CONFIG_PATH);
	if($relay_config === FALSE) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Relay config.'); }
	$TechSmithRelay	= new Relay(json_decode($relay_config, true));
	
	### 	  ALTO ROUTER		###

	require_once($BASE . '/lib/router.class.php');			// http://altorouter.com
	$Router = new Router();
	$Router->setBasePath($API_BASE_PATH);


// ---------------------- DEFINE ROUTES ----------------------


	/**
	 * GET all REST routes
	 */
	$Router->map('GET', '/', function () {
		Response::result(array('status' => true, 'data' => $GLOBALS['Router']->getRoutes()));
	}, 'All available routes.');


	// SERVICE ROUTES
	$Router->addRoutes(array(
		array('GET','/service/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getServiceDetails())); }, 	'Workers, queue and version.'),
		array('GET','/service/workers/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getWorkers())); }, 			'Service workers.'),
		array('GET','/service/queue/', 		function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getQueue())); }, 			'Service queue.'),
		array('GET','/service/version/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getVersion())); }, 			'Service version.')
	));
	
	
	// ADMIN ROUTES if scope allows
	if($FeideConnect->hasOauthScopeAdmin()) {
		// Add all routes
		$Router->addRoutes(array(
			// STORAGE
				// (todo)
			// USER
			array('GET','/user/[*:userName]/', 						function($userName){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getUser($userName))); }, 					'User account details (Scope: admin).'),
			array('GET','/user/[*:userName]/presentations/', 		function($userName){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getUserPresentations($userName))); }, 		'User presentations (Scope: admin).'),
			array('GET','/user/[*:userName]/presentations/count/', 	function($userName){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getUserPresentationCount($userName))); }, 	'User presentation count (Scope: admin).'),
			// USERS
			array('GET','/global/users/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalUsers())); }, 			'All users (Scope: admin).'),
			array('GET','/global/users/count/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalUserCount())); }, 		'Total user count (Scope: admin).'),
			array('GET','/global/users/employees/', 		function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalEmployees())); }, 		'All employees (Scope: admin).'),
			array('GET','/global/users/employees/count/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalEmployeeCount())); }, 	'Total employee count (Scope: admin).'),
			array('GET','/global/users/students/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalStudents())); }, 		'All students (Scope: admin).'),
			array('GET','/global/users/students/count/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalStudentCount())); }, 	'Total student count (Scope: admin).'),
			// PRESENTATIONS
			array('GET','/global/presentations/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalPresentations())); }, 				'All presentations (Scope: admin).'),
			array('GET','/global/presentations/count/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalPresentationCount())); }, 			'Total presentation count (Scope: admin).'),
			array('GET','/global/presentations/employees/', 		function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalEmployeePresentations())); }, 		'All employee presentations (Scope: admin).'),
			array('GET','/global/presentations/employees/count/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalEmployeePresentationCount())); }, 	'Total employee presentation count (Scope: admin).'),
			array('GET','/global/presentations/students/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalStudentPresentations())); }, 		'All student presentations (Scope: admin).'),
			array('GET','/global/presentations/students/count/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getGlobalStudentPresentationCount())); }, 	'Total student presentation count (Scope: admin).')
		));
	}
	
	// ORG ROUTES if scope allows
	if($FeideConnect->hasOauthScopeAdmin() || $FeideConnect->hasOauthScopeOrg()) {
		// Add all routes
		$Router->addRoutes(array(
			// STORAGE
				// (todo)
			// USERS
			array('GET','/org/[*:orgId]/users/', 					function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgUsers($org))); }, 			'All users at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/users/count/', 				function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgUserCount($org))); }, 		'Total user count at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/users/employees/', 			function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgEmployees($org))); }, 		'All employees at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/users/employees/count/', 	function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgEmployeeCount($org))); }, 	'Total employees count at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/users/students/', 			function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgStudents($org))); }, 		'All students at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/users/students/count/', 	function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgStudentCount($org))); }, 	'Total students count at org (Scope: admin/org).'),
			// PRESENTATIONS
			array('GET','/org/[*:orgId]/presentations/', 					function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgPresentations($org))); }, 				'All presentations at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/presentations/count/', 				function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgPresentationCount($org))); }, 			'Total presentations at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/presentations/employees/', 			function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgEmployeePresentations($org))); }, 		'All employee presentations at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/presentations/employees/count/', 	function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgEmployeePresentationCount($org))); }, 	'Total employee presentations at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/presentations/students/', 			function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgStudentPresentations($org))); }, 		'All student presentations at org (Scope: admin/org).'),
			array('GET','/org/[*:orgId]/presentations/students/count/', 	function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getOrgStudentPresentationCount($org))); }, 	'Total student presentations at org (Scope: admin/org).')
		));
	}
	
	// USER ROUTES (/me/) if scope allows
	if($FeideConnect->hasOauthScopeUser()) {
		// Add all routes
		$Router->addRoutes(array(
			// STORAGE
				// (todo)
			// USERS
			array('GET','/me/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getUser($GLOBALS['FeideConnect']->userName()))); }, 					'User account details (Scope: user).'),
			array('GET','/me/presentations/', 		function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getUserPresentations($GLOBALS['FeideConnect']->userName()))); }, 	'User presentations (Scope: user).'),
			array('GET','/me/presentations/count/', function(){ Response::result(array('status' => true, 'data' => $GLOBALS['TechSmithRelay']->getUserPresentationCount($GLOBALS['FeideConnect']->userName()))); }, 'User presentation count (Scope: user).')
		));	
	}





	/**
	 * GET complete dump of subscriber-info for service [i:id]
	 */
	$Router->map('GET', '/service/[i:serviceId]/subscribers/', function ($serviceId) {
		Response::result($GLOBALS['kind']->getServiceSubscribers($serviceId));
	}, 'Get subscription data for all subscribers.');


	/**
	 * GET subscriber-info for org [a:org] for service [i:id]
	 */
	$Router->map('GET', '/service/[i:serviceId]/org/[*:orgId]/', function ($serviceId, $orgId) {
		Response::result($GLOBALS['kind']->getServiceOrgSubscriber($serviceId, $orgId));
	}, 'Get subscription data for selected org subscribers.');


	// -------------------- UTILS -------------------- //

	// Make sure requested org name is the same as logged in user's org
	function verifyOrgAccess($orgName){
		if(strcasecmp($orgName, $GLOBALS['feide']->getUserOrg()) !== 0) {
			Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (request mismatch org/user). ');
		}
	}

	/**
	 *
	 *
	 * http://stackoverflow.com/questions/4861053/php-sanitize-values-of-a-array/4861211#4861211
	 */
	function sanitizeInput(){
		$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
	}

	// -------------------- ./UTILS -------------------- //



// ---------------------- MATCH AND EXECUTE REQUESTED ROUTE ----------------------

	
	$match = $Router->match();

	if($match && is_callable($match['target'])) {
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource route could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------



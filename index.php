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

	$CONFIG_ROOT = '/var/www/etc/techsmith-relay/';


	$API_BASE_PATH 				= '/api/techsmith-relay';								// Remember to update .htacces as well!
	$FEIDE_CONNECT_CONFIG_PATH 	= $CONFIG_ROOT . 'feideconnect_config.js';
	$RELAY_CONFIG_PATH			= $CONFIG_ROOT . 'relay_config.js';

	###			SETUP			###

	$BASE          				= dirname(__FILE__);		// API Root
	require_once($BASE . '/lib/response.class.php');		// Result or error responses
	require_once($BASE . '/lib/feideconnect.class.php');	// Checks CORS and pulls FeideConnect info from headers

	require_once($BASE . '/lib/utils.class.php');

	### 	FEIDE CONNECT		###

	$feide_connect_config 	= file_get_contents($FEIDE_CONNECT_CONFIG_PATH);
	if($feide_connect_config === FALSE) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Connect config.'); }
	$FeideConnect 			= new FeideConnect(json_decode($feide_connect_config, true));

	###			RELAY DB		###

	require_once($BASE . '/lib/relay.db.class.php');
	$relay_config = file_get_contents($RELAY_CONFIG_PATH);
	if($relay_config === FALSE) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Relay config.'); }

	###			RELAY			###

	require_once($BASE . '/lib/relay.class.php');
	$relay	= new Relay(new RelayDB(json_decode($relay_config, true)));

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
		array('GET','/service/', 											function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getServiceDetails())); }, 							'Workers, queue and version.'),
		array('GET','/service/workers/', 									function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getWorkers())); }, 									'Service workers.'),
		array('GET','/service/queue/', 										function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getQueue())); }, 										'Service queue.'),
		array('GET','/service/version/', 									function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getVersion())); }, 									'Service version.')
		));


	// ADMIN ROUTES if scope allows
	if($FeideConnect->hasOauthScopeAdmin()) {
		// Add all routes
		$Router->addRoutes(array(
			// STORAGE
				// (todo)
			// USER
			array('GET','/user/[*:userName]/', 						function($userName){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getUser($userName))); }, 					'User account details (Scope: admin).'),
			array('GET','/user/[*:userName]/presentations/', 		function($userName){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getUserPresentations($userName))); }, 		'User presentations (Scope: admin).'),
			array('GET','/user/[*:userName]/presentations/count/', 	function($userName){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getUserPresentationCount($userName))); }, 	'User presentation count (Scope: admin).'),
			// USERS
			array('GET','/global/users/', 							function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			array('GET','/global/users/count/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalUserCount())); }, 							'Total user count (Scope: admin).'),
			array('GET','/global/users/employees/', 				function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalEmployees())); }, 							'All employees (Scope: admin).'),
			array('GET','/global/users/employees/count/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalEmployeeCount())); }, 						'Total employee count (Scope: admin).'),
			array('GET','/global/users/students/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalStudents())); }, 							'All students (Scope: admin).'),
			array('GET','/global/users/students/count/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalStudentCount())); }, 						'Total student count (Scope: admin).'),
			// PRESENTATIONS
			array('GET','/global/presentations/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalPresentations())); }, 						'All presentations (Scope: admin).'),
			array('GET','/global/presentations/count/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalPresentationCount())); }, 					'Total presentation count (Scope: admin).'),
			array('GET','/global/presentations/employees/', 		function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalEmployeePresentations())); }, 				'All employee presentations (Scope: admin).'),
			array('GET','/global/presentations/employees/count/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (Scope: admin).'),
			array('GET','/global/presentations/students/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalStudentPresentations())); }, 				'All student presentations (Scope: admin).'),
			array('GET','/global/presentations/students/count/', 	function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (Scope: admin).')
		));
}

	// ORG ROUTES if scope allows
if($FeideConnect->hasOauthScopeAdmin() || $FeideConnect->hasOauthScopeOrg()) {
		// Add all routes
	$Router->addRoutes(array(
			// STORAGE
				// (todo)
			// USERS
		array('GET','/org/[*:orgId]/users/', 						 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgUsers($org))); }, 							'All users at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/users/count/', 					 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgUserCount($org))); }, 						'Total user count at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/users/employees/', 				 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgEmployees($org))); }, 						'All employees at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/users/employees/count/', 		 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgEmployeeCount($org))); }, 					'Total employees count at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/users/students/', 				 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgStudents($org))); }, 						'All students at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/users/students/count/', 		 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgStudentCount($org))); }, 					'Total students count at org (Scope: admin/org).'),
			// PRESENTATIONS
		array('GET','/org/[*:orgId]/presentations/', 				 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgPresentations($org))); }, 					'All presentations at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/presentations/count/', 			 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgPresentationCount($org))); }, 				'Total presentations at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/presentations/employees/', 		 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgEmployeePresentations($org))); }, 			'All employee presentations at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/presentations/employees/count/', function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgEmployeePresentationCount($org))); }, 		'Total employee presentations at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/presentations/students/', 		 function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgStudentPresentations($org))); }, 			'All student presentations at org (Scope: admin/org).'),
		array('GET','/org/[*:orgId]/presentations/students/count/',  function($org){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getOrgStudentPresentationCount($org))); }, 		'Total student presentations at org (Scope: admin/org).')
	));
}

	// USER ROUTES (/me/) if scope allows
if($FeideConnect->hasOauthScopeUser()) {
		// Add all routes
	$Router->addRoutes(array(
		// STORAGE
			// (todo)
		// USERS
		array('GET','/me/', 					function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getUser($GLOBALS['FeideConnect']->userName()))); }, 		            'User account details (Scope: user).'),
		array('GET','/me/presentations/', 		function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getUserPresentations($GLOBALS['FeideConnect']->userName()))); }, 		'User presentations (Scope: user).'),
		array('GET','/me/presentations/count/', function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getUserPresentationCount($GLOBALS['FeideConnect']->userName()))); },  'User presentation count (Scope: user).')
	));
}




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



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
	$feideConnect = new FeideConnect(json_decode($feide_connect_config, true));

	###			RELAY DB		###

	require_once($BASE . '/lib/relay.db.class.php');
	$relay_config = file_get_contents($RELAY_CONFIG_PATH);
	if($relay_config === FALSE) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Relay config.'); }

	###			RELAY			###

	require_once($BASE . '/lib/relay.class.php');
	$relay	= new Relay(new RelayDB(json_decode($relay_config, true)), $feideConnect);

	### 	  ALTO ROUTER		###

	require_once($BASE . '/lib/router.class.php');			// http://altorouter.com
	$router = new Router();
	$router->addMatchTypes(array('user' => '[0-9A-Za-z.@]++', 'org' => '[0-9A-Za-z.]++', 'presentation' => '[0-9A-Za-z.]++' )); // Todo: 'presentation' regex when known how to implement delete function (presId format)
	$router->setBasePath($API_BASE_PATH);


// ---------------------- DEFINE ROUTES ----------------------


	/**
	 * GET all REST routes
	 */
	$router->map('GET', '/', function () {
		global $router;
		Response::result(array('status' => true, 'data' => $router->getRoutes()));
	}, 'All available routes.');


	// SERVICE ROUTES
	$router->addRoutes([
		//array('GET','/service/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getService())); }, 	    'Workers, queue and version.'),
		/* DONE */ array('GET','/service/workers/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getServiceWorkers())); }, 'Service workers.'),
		/* DONE */ array('GET','/service/queue/', 		function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getServiceQueue())); }, 	'Service queue.'),
		/* DONE */ array('GET','/service/version/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getServiceVersion())); }, 'Service version.')
	]);


	// ADMIN ROUTES if scope allows
	// isSuperAdmin added 15.10.2015 - need to be tested and considered carefully. Should we leave the clients to decide who is SuperAdmin, or
	// hardcode in API, judging by 'uninett.no' in username (I prefer the latter). The client can actually call this API to find out if user has role(s)
	// super or org or user. simon@uninett.no should get:
	// { roles : [super, org, user] }
	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		// Add all routes
		$router->addRoutes([
			// STORAGE
				// (todo)
			// USER
			/* DONE */ array('GET','/user/[user:userName]/', 					    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->getUser($userName))); }, 					'User account details (Scope: admin).'),
			/* DONE */ array('GET','/user/[user:userName]/presentations/', 		    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->getUserPresentations($userName))); }, 		'User presentations (Scope: admin).'),
			/* DONE */ array('GET','/user/[user:userName]/presentations/count/',    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->getUserPresentationCount($userName))); }, 	'User presentation count (Scope: admin).'),
			// USERS
			/* DONE */ array('GET','/global/users/', 							    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			/* DONE */ array('GET','/global/users/count/', 					        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalUserCount())); }, 							'Total user count (Scope: admin).'),
			// Use DB call and match with profile ID==ansatt (relay.db.class has employeeProfileID!!!)
			array('GET','/global/users/employees/', 				                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalEmployees())); }, 							'All employees (Scope: admin).'),
			array('GET','/global/users/employees/count/', 			                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalEmployeeCount())); }, 						'Total employee count (Scope: admin).'),
			// Use DB call and match with profile ID==student
			array('GET','/global/users/students/', 					                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalStudents())); }, 							'All students (Scope: admin).'),
			array('GET','/global/users/students/count/', 			                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalStudentCount())); }, 						'Total student count (Scope: admin).'),
			// PRESENTATIONS
			/* DONE */ array('GET','/global/presentations/', 				        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalPresentations())); }, 						'All presentations (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalPresentationCount())); }, 					'Total presentation count (Scope: admin).'),
			array('GET','/global/presentations/employees/', 		                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalEmployeePresentations())); }, 				'All employee presentations (Scope: admin).'),
			array('GET','/global/presentations/employees/count/', 	                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (Scope: admin).'),
			array('GET','/global/presentations/students/', 			                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalStudentPresentations())); }, 				'All student presentations (Scope: admin).'),
			array('GET','/global/presentations/students/count/', 	                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (Scope: admin).')
		]);
	}

	// ORG ROUTES if scope allows
	// TODO: At present, the client talks to Kind to check if logged on user is OrgAdmin. This is not ideal, the check should happen in this API, which can call Kind and verify!
	if( $feideConnect->hasOauthScopeAdmin() || $feideConnect->hasOauthScopeOrg() ) { // TODO: Implement isOrgAdmin :: && ($feideConnect->isOrgAdmin() || $feideConnect->isSuperAdmin())) {
		// Add all routes
		$router->addRoutes([
			// STORAGE
				// (todo)
			// USERS
 /* DONE */ array('GET','/org/[org:orgId]/users/', 			                 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgUsers($orgId))); }, 							'All users at org (Scope: admin/org).'),
 /* DONE */ array('GET','/org/[org:orgId]/users/count/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgUserCount($orgId))); }, 						'Total user count at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/users/employees/', 				 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgEmployees($orgId))); }, 						'All employees at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/employees/count/', 		     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgEmployeeCount($orgId))); }, 					'Total employees count at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/users/students/', 				 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgStudents($orgId))); }, 						'All students at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/users/students/count/', 		     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgStudentCount($orgId))); }, 					'Total students count at org (Scope: admin/org).'),
			// PRESENTATIONS
 /* DONE */ array('GET','/org/[org:orgId]/presentations/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgPresentations($orgId))); }, 					'All presentations at org (Scope: admin/org).'),
 /* DONE */ array('GET','/org/[org:orgId]/presentations/count/',             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgPresentationCount($orgId))); }, 				'Total presentations at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/presentations/employees/', 		 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgEmployeePresentations($orgId))); }, 			'All employee presentations at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/presentations/employees/count/',   function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgEmployeePresentationCount($orgId))); }, 		'Total employee presentations at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/presentations/students/', 		 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgStudentPresentations($orgId))); }, 			'All student presentations at org (Scope: admin/org).'),
			//array('GET','/org/[org:orgId]/presentations/students/count/',    function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->getOrgStudentPresentationCount($orgId))); }, 		'Total student presentations at org (Scope: admin/org).')
		]);
	}

	// USER ROUTES (/me/) if scope allows
	if($feideConnect->hasOauthScopeUser()) {
			// Add all routes
		$router->addRoutes([
			// STORAGE
				// (todo)
			// USERS
 /* DONE */ array('GET','/me/', 					                            function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->getUser($feideConnect->userName()))); }, 		            'User account details (Scope: user).'),
 /* DONE */ array('GET','/me/presentations/', 		                            function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->getUserPresentations($feideConnect->userName()))); }, 	'User presentations (Scope: user).'),
 /* DONE */ array('GET','/me/presentations/count/',                             function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->getUserPresentationCount($feideConnect->userName()))); }, 'User presentation count (Scope: user).'),
		   // TODO:  array('DELETE', '/me/presentation/[presentation:presId]/delete/',   function($presId){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->deleteUserPresentation($presId, $feideConnect->userName()))); }, 'Delete user presentation (Scope: user).')
		]);
	}


	// DEV ROUTES FOR TESTING
	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		$router->addRoutes([
 /* DONE */ array('GET','/dev/table/[a:tableName]/schema/',	            function($table_name){ global $relay; Response::result(array('status' => true, 'data' => $relay->getTableSchema($table_name))); }, 'Table schema.')
		]);
	}




	// -------------------- UTILS -------------------- //

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


	$match = $router->match();

	if($match && is_callable($match['target'])) {
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource route could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------



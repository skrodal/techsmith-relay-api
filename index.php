<?php
	/**
	 * Accepts following scopes:
	 *    - admin
	 *    - org
	 *    - user
	 * @author Simon Skrødal
	 * @since  September 2015
	 */

	namespace UNINETT\RelayAPI;
	###			CONFIGS			###

	$CONFIG_ROOT = '/var/www/etc/techsmith-relay/';
	// Remember to update .htacces as well:
	$API_BASE_PATH             = '/api/techsmith-relay';
	$FEIDE_CONNECT_CONFIG_PATH = $CONFIG_ROOT . 'feideconnect_config.js';
	$RELAY_CONFIG_PATH         = $CONFIG_ROOT . 'relay_config.js';

	###		  DEPENDENCIES	    ###

	// This APIs root
	$BASE = dirname(__FILE__);
	// Result or error responses
	require_once($BASE . '/lib/response.class.php');
	// Checks CORS and pulls FeideConnect info from headers
	require_once($BASE . '/lib/feideconnect.class.php');
	// Implements all routes
	require_once($BASE . '/lib/relay.class.php');
	// Helper for filesystem reads
	require_once($BASE . '/lib/relay.fs.class.php');
	// Helper for Relay Server MMSQL reads
	require_once($BASE . '/lib/relay.db.class.php');
	// Helper for MongoDB interaction
	require_once($BASE . '/lib/mongo.class.php');
	// Logging, etc.
	require_once($BASE . '/lib/utils.class.php');
	// AltoRouter - see http://altorouter.com
	require_once($BASE . '/lib/router.class.php');

	### 	FEIDE CONNECT		###

	// Load config
	$feide_connect_config = file_get_contents($FEIDE_CONNECT_CONFIG_PATH);
	// Sanity
	if($feide_connect_config === false) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Connect config.'); }
	// Create
	$feideConnect = new FeideConnect(json_decode($feide_connect_config, true));


	###			RELAY DB		###

	// Load config
	$relay_config = file_get_contents($RELAY_CONFIG_PATH);
	// Sanity
	if($relay_config === false) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Relay config.'); };
	// Create
	$RelayDB = new RelayDB(json_decode($relay_config, true));

	###			RELAY			###

	$Relay = new Relay($RelayDB, $feideConnect);

	###			RELAY FS		###

	$RelayFS = new RelayFS($Relay, $feideConnect);

	### 	  ALTO ROUTER 		###
	$router = new Router();
	// TODO: 'presentation' regex when known how to implement delete function (presId format)
	$router->addMatchTypes(array('user' => '[0-9A-Za-z.@]++', 'org' => '[0-9A-Za-z.]++', 'presentation' => '[0-9A-Za-z.]++'));
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
		/* DONE */ array('GET','/service/workers/', 	function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getServiceWorkers())); }, 'Service workers.'),
		/* DONE */ array('GET','/service/queue/', 		function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getServiceQueue())); }, 	'Service queue.'),
		/* DONE */ array('GET','/service/version/', 	function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getServiceVersion())); }, 'Service version.')
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
			/* DONE */ array('GET','/user/[user:userName]/', 					    function($userName){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getUser($userName))); }, 					'User account details (Scope: admin).'),
			/* DONE */ // array('GET','/user/[user:userName]/presentations/', 		    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->getUserPresentations($userName))); }, 		'User presentations (Scope: admin).'),
			/* TEST WITH FS */ array('GET','/user/[user:userName]/presentations/', 					                function($userName){ global $RelayFS, $feideConnect; Response::result(array('status' => true, 'data' => $RelayFS->getRelayUserMedia($userName))); },     'User presentations, deleted ones excluded (Scope: admin).'),
			/* DONE */ array('GET','/user/[user:userName]/presentations/count/',    function($userName){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getUserPresentationCount($userName))); }, 	'User presentation count (Scope: admin).'),
			// USERS
			/* DONE */ array('GET','/global/users/', 							    function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			/* DONE */ array('GET','/global/users/count/', 					        function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalUserCount())); }, 							'Total user count (Scope: admin).'),
			// Use DB call and match with profile ID==ansatt (relay.db.class has employeeProfileID!!!)
			array('GET','/global/users/employees/', 				                function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalEmployees())); }, 							'All employees (Scope: admin).'),
			array('GET','/global/users/employees/count/', 			                function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalEmployeeCount())); }, 						'Total employee count (Scope: admin).'),
			// Use DB call and match with profile ID==student
			array('GET','/global/users/students/', 					                function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalStudents())); }, 							'All students (Scope: admin).'),
			array('GET','/global/users/students/count/', 			                function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalStudentCount())); }, 						'Total student count (Scope: admin).'),
			// PRESENTATIONS
			/* DONE */ array('GET','/global/presentations/', 				        function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalPresentations())); }, 						'All presentations (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/count/', 			        function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalPresentationCount())); }, 					'Total presentation count (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/employees/', 		        function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalEmployeePresentations())); }, 				'All employee presentations (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/employees/count/', 	    function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/students/', 			    function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalStudentPresentations())); }, 				'All student presentations (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/students/count/', 	    function(){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (Scope: admin).'),
			// CLIENTS
			/* Tested, but no useful info to be grabbed from tblClient. */
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
			/* DONE */ array('GET','/org/[org:orgId]/users/', 			                 function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgUsers($orgId))); }, 							'All users at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/', 		             function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgUserCount($orgId))); }, 						'Total user count at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/affiliation/',         function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgUserCountByAffiliation($orgId))); }, 		'Total user count at org by affiliation (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/employees/', 				 function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgEmployees($orgId))); }, 						'All employees at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/employees/', 		     function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgEmployeeCount($orgId))); }, 					'Total employees count at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/students/', 				     function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgStudents($orgId))); }, 						    'All students at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/students/', 		     function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgStudentCount($orgId))); }, 					'Total students count at org (Scope: admin/org).'),
			// PRESENTATIONS
			/* DONE */ array('GET','/org/[org:orgId]/presentations/', 		             function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgPresentations($orgId))); }, 					'All presentations at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/presentations/count/',             function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgPresentationCount($orgId))); }, 				'Total presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/employees/', 		 function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgEmployeePresentations($orgId))); }, 			'All employee presentations at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/presentations/employees/count/',   function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgEmployeePresentationCount($orgId))); }, 		'Total employee presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/students/', 		 function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgStudentPresentations($orgId))); }, 			'All student presentations at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/presentations/students/count/',    function($orgId){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getOrgStudentPresentationCount($orgId))); }, 		'Total student presentations at org (Scope: admin/org).')
		]);
	}

	// USER ROUTES (/me/) if scope allows
	if($feideConnect->hasOauthScopeUser()) {
		// Add all routes
		$router->addRoutes([
			// STORAGE
			// (todo)
			// USERS
			/* DONE */ array('GET','/me/', 					                            function(){ global $Relay, $feideConnect; Response::result(array('status' => true, 'data' => $Relay->getUser($feideConnect->userName()))); }, 		            'User account details (Scope: user).'),
			/* TEST WITH FS */ array('GET','/me/presentations/', 					                function(){ global $RelayFS, $feideConnect; Response::result(array('status' => true, 'data' => $RelayFS->getRelayUserMedia($feideConnect->userName()))); },     'User presentations, deleted ones excluded (Scope: user).'),
			/* DONE */ //array('GET','/me/presentations/', 		                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->getUserPresentations($feideConnect->userName()))); }, 	'User presentations (Scope: user).'),
			/* TEST WITH FS */ array('GET','/me/presentations/count/', 					            function(){ global $RelayFS, $feideConnect; Response::result(array('status' => true, 'data' => $RelayFS->getRelayUserMediaCount($feideConnect->userName()))); },     'User presentation count, deleted ones excluded (Scope: user).'),
			/* DONE */ //array('GET','/me/presentations/count/',                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->getUserPresentationCount($feideConnect->userName()))); }, 'User presentation count (Scope: user).'),
			// TODO:  array('DELETE', '/me/presentation/[presentation:presId]/delete/',   function($presId){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->deleteUserPresentation($presId, $feideConnect->userName()))); }, 'Delete user presentation (Scope: user).')
		]);
	}


	// DEV ROUTES FOR TESTING
	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		$router->addRoutes([
			/* DONE */ array('GET','/dev/table/[a:tableName]/schema/',	            function($table_name){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getTableSchema($table_name))); }, 'Table schema.'),
			/* DONE */ array('GET','/dev/table/[a:tableName]/dump/',	            function($table_name){ global $Relay; Response::result(array('status' => true, 'data' => $Relay->getTableDump($table_name))); }, 'Table dump - top 50.')
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

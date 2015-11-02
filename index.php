<?php
	/**
	 * Accepts following scopes:
	 *    - admin
	 *    - org
	 *    - user
	 * @author Simon Skrødal
	 * @since  September 2015
	 */

	namespace Relay;

	###	   LOAD DEPENDENCIES	###
	require_once('relay/autoload.php');

	use
		Relay\Auth\FeideConnect, Relay\Api\Relay, Relay\Conf\Config,
		Relay\Utils\Response, Relay\Vendor\Router;

	// Gatekeeper and provider of useful info
	$feideConnect = new FeideConnect();
	// Provides an interface to SQL, Mongo, FS classes
	$relay = new Relay($feideConnect);

	### 	  ALTO ROUTER 		###
	$router = new Router();
	// TODO: 'presentation' regex when known how to implement delete function (presId format)
	$router->addMatchTypes(array('user' => '[0-9A-Za-z.@]++', 'org' => '[0-9A-Za-z.]++', 'presentation' => '[0-9A-Za-z.]++'));
	$router->setBasePath(Config::get('router')['api_base_path']);

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
		/* DONE */ array('GET','/service/workers/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getServiceWorkers() )); }, 'Service workers.'),
		/* DONE */ array('GET','/service/queue/', 		function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getServiceQueue())); }, 	'Service queue.'),
		/* DONE */ array('GET','/service/version/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getServiceVersion())); }, 'Service version.'),
		/* TEST */ array('GET','/service/test/', 	    function(){ /** DO NOTHING **/ }, 'TEST ROUTE.')
	]);


	// ADMIN ROUTES - if scope allows
	// isSuperAdmin added 15.10.2015 - need to be tested and considered carefully. Should we leave the clients to decide who is SuperAdmin, or
	// hardcode in API, judging by 'uninett.no' in username (I prefer the latter)? The client can actually call this API to find out if user has role(s)
	// super or org or user. simon@uninett.no should get:
	// { roles : [super, org, user] }

	// TODO: Some global routes, e.g. user counts, may as well be public.
	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		// Add all routes
		$router->addRoutes([
			### STORAGE
			// (todo)

			### USER (prefer userinfo from Mongo over SQL)
			/* DONE (SQL) */ // array('GET','/user/[user:userName]/', 			            function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getUser($userName))); }, 			'User account details (Scope: admin).'),
			/* DONE (mongo) */ array('GET','/user/[user:userName]/', 					    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUser($userName))); }, 		'User account details (Scope: admin).'),

			### USER PRESENTATIONS
			/* DONE (SQL) */ // array('GET','/user/[user:userName]/presentations/', 		function($userName){ global $RelaySQL; Response::result(array('status' => true, 'data' => $RelaySQL->getUserPresentations($userName))); }, 		'User presentations (Scope: admin).'),
			/* DONE (SQL) */ //array('GET','/user/[user:userName]/presentations/count/',    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentationCount($userName))); }, 	'User presentation count (Scope: admin).'),
			/* DONE (FS)  */ //array('GET','/user/[user:userName]/presentations/', 		    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->fs()->getRelayUserMedia($userName))); },     'User presentations, deleted ones excluded (Scope: admin).'),
			/* DONE (mongo) */ array('GET','/user/[user:userName]/presentations/', 		    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentations($userName))); },       'User presentations, excluding deleted (Scope: admin).'),
			/* DONE (mongo) */ array('GET','/user/[user:userName]/presentations/count/', 	function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentationCount($userName))); },   'User presentation count, excluding deleted (Scope: admin).'),


			### USERS
			// mongo
			/* DONE */ array('GET','/global/users/', 							    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			/* DONE */ array('GET','/global/users/count/', 			                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUserCount())); }, 							'Total user count (Scope: admin).'),
			// sql
			/* DONE  */ // array('GET','/global/users/', 					    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			/* DONE  */ //array('GET','/global/users/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalUserCount())); }, 							'Total user count (Scope: admin).'),

			### USERS BY AFFILIATION (match with profile ID==ansatt|student (relay.db.class has employeeProfileID)
		    // employees (sql)
			/* DONE */ array('GET','/global/users/employees/',   			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployees())); }, 							'All employees (Scope: admin).'),
			/* DONE */ array('GET','/global/users/employees/count/', 			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeeCount())); }, 						'Total employee count (Scope: admin).'),
			// employees (mongo)
			/* DONE */ array('GET','/global/users/employees/active/',		        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalEmployees())); }, 							'All employees with content (Scope: admin).'),
			/* DONE */ array('GET','/global/users/employees/count/active/',		    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalEmployeeCount())); }, 						'Count employees with content (Scope: admin).'),

			// students (sql)
			/* DONE */ array('GET','/global/users/students/',    				    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudents())); }, 							    'All students (Scope: admin).'),
			/* DONE */ array('GET','/global/users/students/count/', 			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentCount())); }, 						    'Total student count (Scope: admin).'),
		    // students (mongo)
			/* DONE */ array('GET','/global/users/students/active/',			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalStudents())); }, 						    'Students with content (Scope: admin).'),
			/* DONE */ array('GET','/global/users/students/count/active/',		    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalStudentCount())); }, 					    'Count students with content (Scope: admin).'),


			// PRESENTATIONS
		    // mongo
			/* DONE */ // array('GET','/global/presentations/', 				            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalPresentations())); }, 				'All presentations on disk (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/count/', 			            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalPresentationCount())); }, 				'Total presentation count (on disk) (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/employees/', 		        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentations())); }, 			'All employee presentations (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/employees/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (on disk) (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/students/', 			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentations())); }, 				'All student presentations (Scope: admin).'),
			/* DONE */ array('GET','/global/presentations/students/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (on disk) (Scope: admin).'),

		    // sql
			/* DONE */ // array('GET','/global/presentations/', 				        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalPresentations())); }, 						'All presentations (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalPresentationCount())); }, 					'Total presentation count (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/employees/', 		        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentations())); }, 				'All employee presentations (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/employees/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/students/', 			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentations())); }, 				'All student presentations (Scope: admin).'),
			/* DONE */ // array('GET','/global/presentations/students/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (Scope: admin).'),



			##### Mongo ####
			/* DONE */ array('GET','/mongo/users/count/', 					        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUserCount())); }, 							'Total user count from MongoDB (Scope: admin).'),
			################




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
			/* DONE */ array('GET','/org/[org:orgId]/users/', 			                 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUsers($orgId))); }, 							'All users at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUserCount($orgId))); }, 						'Total user count at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/affiliation/',          function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUserCountByAffiliation($orgId))); }, 		'Total user count at org by affiliation (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/employees/', 				 function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployees($orgId))); }, 						'All employees at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/employees/', 		     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployeeCount($orgId))); }, 					'Total employees count at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/students/', 				     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudents($orgId))); }, 						    'All students at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/users/count/students/', 		     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudentCount($orgId))); }, 					'Total students count at org (Scope: admin/org).'),
			// PRESENTATIONS
			/* DONE */ array('GET','/org/[org:orgId]/presentations/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgPresentations($orgId))); }, 					'All presentations at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/presentations/count/',              function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgPresentationCount($orgId))); }, 				'Total presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/employees/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployeePresentations($orgId))); }, 			'All employee presentations at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/presentations/employees/count/',    function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployeePresentationCount($orgId))); }, 		'Total employee presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/students/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudentPresentations($orgId))); }, 			'All student presentations at org (Scope: admin/org).'),
			/* DONE */ array('GET','/org/[org:orgId]/presentations/students/count/',     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudentPresentationCount($orgId))); }, 		'Total student presentations at org (Scope: admin/org).')
		]);
	}

	// USER ROUTES (/me/) if scope allows
	if($feideConnect->hasOauthScopeUser()) {
		// Add all routes
		$router->addRoutes([
			// STORAGE
			// (todo)
			// USERS
			/* DONE */ array('GET','/me/', 					                            function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->sql()->getUser($feideConnect->userName()))); }, 		            'User account details (Scope: user).'),
			/* TEST WITH FS */ array('GET','/me/presentations/',                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->fs()->getRelayUserMedia($feideConnect->userName()))); },     'User presentations, deleted ones excluded (Scope: user).'),
			/* DONE */ //array('GET','/me/presentations/', 		                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentations($feideConnect->userName()))); }, 	'User presentations (Scope: user).'),
			/* TEST WITH FS */ array('GET','/me/presentations/count/', 					function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->fs()->getRelayUserMediaCount($feideConnect->userName()))); },     'User presentation count, deleted ones excluded (Scope: user).'),
			/* DONE */ //array('GET','/me/presentations/count/',                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentationCount($feideConnect->userName()))); }, 'User presentation count (Scope: user).'),
			// TODO:  array('DELETE', '/me/presentation/[presentation:presId]/delete/',   function($presId){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->deleteUserPresentation($presId, $feideConnect->userName()))); }, 'Delete user presentation (Scope: user).')
		]);
	}


	// DEV ROUTES FOR TESTING
	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		$router->addRoutes([
			/* DONE */ array('GET','/dev/table/[a:tableName]/schema/',	            function($table_name){ global $relay; Response::result(array('status' => true, 'data' => $relay->getTableSchema($table_name))); }, 'Table schema.'),
			/* DONE */ array('GET','/dev/table/[a:tableName]/dump/',	            function($table_name){ global $relay; Response::result(array('status' => true, 'data' => $relay->getTableDump($table_name, 50))); }, 'Table dump. Top 50.'),
		    /* DONE */ array('GET','/dev/table/[a:tableName]/dump/top/[i:top]',	    function($table_name, $top){ global $relay; Response::result(array('status' => true, 'data' => $relay->getTableDump($table_name, $top))); }, 'Table dump. Top $top.')
		]);
	}


	// ---------------------- MATCH AND EXECUTE REQUESTED ROUTE ----------------------


	$match = $router->match();

	if($match && is_callable($match['target'])) {
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource route could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------


	// -------------------- UTILS -------------------- //

	/**
	 * http://stackoverflow.com/questions/4861053/php-sanitize-values-of-a-array/4861211#4861211
	 */
	function sanitizeInput(){
		$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
	}

	// -------------------- ./UTILS -------------------- //
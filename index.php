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
	use Relay\Tests\MongoTest;

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
		// TODO: Show only routes available according to scope
		Response::result(array('status' => true, 'data' => $router->getRoutes()));
	}, 'All available routes.');


	// SERVICE ROUTES (scope basic)
	// (Update: NOT true! Basic Scope is not transferred in HTTP_X_FEIDECONNECT_SCOPES, hence client needs at least one custom scope.)
	// See GK in feideconnect.class...
	$router->addRoutes([
		//array('GET','/service/', 			function(){ Response::result(array('status' => true, 'data' => $GLOBALS['relay']->getService())); }, 	    'Workers, queue and version.'),
		array('GET','/service/workers/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getServiceWorkers() )); },     'Service workers.'),
		array('GET','/service/queue/', 		function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getServiceQueue())); }, 	    'Service queue.'),
		array('GET','/service/version/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getServiceVersion())); },      'Service version.'),

		// storage (mongo)
		array('GET','/service/diskusage/',                              function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getServiceDiskusage())); },                          'Total service diskusage (in MiB) (Scope: basic).'),
		// users (mongo)
		array('GET','/service/users/count/', 			                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUserCount())); }, 							'Total user count (Scope: basic).'),
		array('GET','/service/users/active/count/', 	                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUserCountActive())); }, 					'Count *active* users (Scope: basic).'),
		array('GET','/service/users/affiliation/active/count/',         function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUserCountByAffiliation())); }, 	            'Count *active* users by affiliation (Scope: basic).'),
		array('GET','/service/users/employees/active/count/',		    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalEmployeeCount())); }, 						'Total *active* employee count (Scope: basic).'),
		array('GET','/service/users/students/active/count/',		    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalStudentCount())); }, 					    'Count *active* student count (Scope: basic).'),
		// users (sql)
		array('GET','/service/users/affiliation/count/', 		        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalUserCountByAffiliation())); },			    'Total user count by affiliation (Scope: basic).'),
		array('GET','/service/users/employees/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeeCount())); }, 						'Total employee count (Scope: basic).'),
		array('GET','/service/users/students/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentCount())); }, 						    'Total student count (Scope: basic).'),
		// presentations (mongo)
		array('GET','/service/presentations/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalPresentationCount())); }, 				    'Total presentation count (on disk) (Scope: basic).'),
		array('GET','/service/presentations/employees/count/', 	        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (on disk) (Scope: basic).'),
		array('GET','/service/presentations/students/count/', 	        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (on disk) (Scope: basic).'),

	]);


	// ADMIN ROUTES - if scope allows
	// isSuperAdmin added 15.10.2015 - need to be tested and considered carefully. Should we leave the clients to decide who is SuperAdmin, or
	// hardcode in API, judging by 'uninett.no' in username (I prefer the latter)? The client can actually call this API to find out if user has role(s)
	// super or org or user. simon@uninett.no should get:
	// { roles : [super, org, user] }

	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		// Add all routes
		$router->addRoutes([

			### SINGLE USER (prefer userinfo from Mongo over SQL)
			array('GET','/admin/user/[user:userName]/', 					                    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUser($userName))); }, 		            'User account details (Scope: admin).'),
			array('GET','/admin/user/[user:userName]/diskusage/', 			                    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserDiskusage($userName))); }, 		    'User diskusage history (in MiB) and total (Scope: admin).'),

			### SINGLE USER PRESENTATIONS
			array('GET','/admin/user/[user:userName]/presentations/', 		                    function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentations($userName))); },       'User presentations, excluding deleted (Scope: admin).'),
			array('GET','/admin/user/[user:userName]/presentations/count/', 	                function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentationCount($userName))); },   'User presentation count, excluding deleted (Scope: admin).'),

			// Old, deprecated, functions using sql/fs
			/* DONE (SQL) */ // array('GET','/admin/user/[user:userName]/', 			        function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getUser($userName))); }, 			'User account details (Scope: admin).'),
			/* DONE (SQL) */ // array('GET','/admin/user/[user:userName]/presentations/', 		function($userName){ global $RelaySQL; Response::result(array('status' => true, 'data' => $RelaySQL->getUserPresentations($userName))); }, 		'User presentations (Scope: admin).'),
			/* DONE (SQL) */ //array('GET','/admin/user/[user:userName]/presentations/count/',  function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentationCount($userName))); }, 	'User presentation count (Scope: admin).'),
			/* DONE (FS)  */ //array('GET','/admin/user/[user:userName]/presentations/', 		function($userName){ global $relay; Response::result(array('status' => true, 'data' => $relay->fs()->getRelayUserMedia($userName))); },     'User presentations, deleted ones excluded (Scope: admin).'),

			### USERS
			// sql (all)
		    // note: we do not need these sql routes since mongo also provides all users
			/* DONE  */ // array('GET','/admin/users/', 					                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			/* DONE  */ // array('GET','/admin/users/count/', 			                    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalUserCount())); }, 							'Total user count (Scope: admin).'),

			// mongo (active == user has produced content)
			array('GET','/admin/users/', 							            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalUsers())); }, 								'All users (Scope: admin).'),
			// mongo (active == have content on disk)
			array('GET','/admin/users/employees/active/',		                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalEmployees())); }, 							'All *active* employees (Scope: admin).'),
			array('GET','/admin/users/students/active/',			            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalStudents())); }, 						    'All *active* students (Scope: admin).'),
			// sql
			array('GET','/admin/users/employees/',   			                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployees())); }, 							'All employees (Scope: admin).'),
			array('GET','/admin/users/students/',    				            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudents())); }, 							    'All students (Scope: admin).'),


			### PRESENTATIONS
		    // mongo (exclude presentation listing as it is a) unneeded and b) memory exhaustive)
		    // todo: consider writing some sort of pagination (or split mongo query to pieces) since this is memory hungry and returns a huge result that the browser can't handle!
			// array('GET','/admin/presentations/', 				                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getGlobalPresentations())); }, 				        'All presentations on disk (Scope: admin).'),
			// array('GET','/admin/presentations/employees/', 		            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentations())); }, 		        'All employee presentations (Scope: admin).'),
			// array('GET','/admin/presentations/students/', 			            function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentations())); }, 			        'All student presentations (Scope: admin).'),

		    // sql (deprecated; retrieves all presentations, also deleted ones)
			// array('GET','/admin/presentations/', 				        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalPresentations())); }, 						'All presentations (Scope: admin).'),// array('GET','/global/presentations/count/', 			        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalPresentationCount())); }, 					'Total presentation count (Scope: admin).'),
			// array('GET','/admin/presentations/employees/', 		        function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentations())); }, 				'All employee presentations (Scope: admin).'),
			// array('GET','/admin/presentations/employees/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentationCount())); }, 			'Total employee presentation count (Scope: admin).'),
			// array('GET','/admin/presentations/students/', 			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentations())); }, 				'All student presentations (Scope: admin).'),
			// array('GET','/admin/presentations/students/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentationCount())); }, 			'Total student presentation count (Scope: admin).'),

		    ### ORGS

			array('GET','/admin/orgs/', 	                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgs())); }, 			    'List all orgs (Scope: admin).'),
			array('GET','/admin/orgs/info/',                function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgsInfo())); }, 		    'List all orgs with user/presentation/diskusage info (Scope: admin).'),
			array('GET','/admin/orgs/users/count/', 	    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgsUserCount())); }, 	'List all orgs with total users (Scope: admin).'),
			array('GET','/admin/orgs/diskusage/', 		    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgsDiskusage())); },     'Total service diskusage (in MiB) plus per org (Scope: admin).'),

			// CLIENTS
			/* Tested, but no useful info to be grabbed from tblClient. */
		]);
	}

	// ORG ROUTES if scope allows
	// TODO:
	//  At present, the client talks to Kind to check if logged on user is OrgAdmin.
	//  This is not ideal, the check should happen in this API, which can call Kind and verify!
	//  FC team says there is no easy way at present for one API GK to speak to another one... (OCT 2015)
	if( $feideConnect->hasOauthScopeAdmin() || $feideConnect->hasOauthScopeOrg() ) { // TODO: Implement isOrgAdmin :: && ($feideConnect->isOrgAdmin() || $feideConnect->isSuperAdmin())) {
		// Add all routes
		$router->addRoutes([
			### DISKUSAGE
			array('GET','/org/[org:orgId]/diskusage/', 			                        function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgDiskusage($orgId))); }, 				 'Org diskusage history (in MiB) and total (Scope: admin/org).'),

		    ### SINGLE USER
		    // mongo
			array('GET','/org/[org:orgId]/user/[user:userName]/', 	                    function($orgId, $userName){ global $relay; verifyOrgAccess($orgId, $userName); Response::result(array('status' => true, 'data' => $relay->mongo()->getUser($userName))); },                        'Specific user at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/user/[user:userName]/presentations/',         function($orgId, $userName){ global $relay; verifyOrgAccess($orgId, $userName); Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentations($userName))); },           'Presentations for specific user at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/user/[user:userName]/presentations/count/', 	function($orgId, $userName){ global $relay; verifyOrgAccess($orgId, $userName); Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentationCount($userName))); },       'Presentation count for specific user at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/user/[user:userName]/diskusage/',             function($orgId, $userName){ global $relay; verifyOrgAccess($orgId, $userName); Response::result(array('status' => true, 'data' => $relay->mongo()->getUserDiskusage($userName))); },               'Diskusage for specific user at org (Scope: admin/org).'),

			### USERS

			// mongo (active == user has produced content)
			array('GET','/org/[org:orgId]/users/', 	                            function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgUsers($orgId))); }, 					'All users at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/count/', 		                function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgUserCount($orgId))); }, 				'Count users at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/affiliation/active/count/',     function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgUserCountByAffiliation($orgId))); }, 	'Count *active* users at org by affiliation (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/employees/active/', 			function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgEmployees($orgId))); }, 				'*Active* employees at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/employees/active/count/',       function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgEmployeeCount($orgId))); }, 			'Count *active* employees at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/students/active/', 		        function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgStudents($orgId))); }, 					'*Active* students at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/students/active/count/',        function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgStudentCount($orgId))); }, 				'Count *active* students at org (Scope: admin/org).'),

		    // sql (all users in DB, active *and* inactive)
			/* DONE */ // array('GET','/org/[org:orgId]/users/', 			    function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUsers($orgId))); }, 							                    'All users at org (Scope: admin/org).'),
			/* DONE */ // array('GET','/org/[org:orgId]/users/count/', 		    function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUserCount($orgId))); }, 						                    'Total user count at org (Scope: admin/org).'),array('GET','/org/[org:orgId]/users/affiliation/count/',          function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUserCountByAffiliation($orgId))); }, 		    'Total user count at org by affiliation (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/affiliation/count/', 		    function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUserCountByAffiliation($orgId))); },			                    'Count users at org by affiliation (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/employees/', 				    function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployees($orgId))); }, 						                    'Employees at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/employees/count/', 		        function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployeeCount($orgId))); }, 					                    'Count employees at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/students/', 				    function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudents($orgId))); }, 						                    'Students at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/users/students/count/', 		        function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudentCount($orgId))); }, 					                    'Count students org (Scope: admin/org).'),

			### PRESENTATIONS

		    // mongo
			array('GET','/org/[org:orgId]/presentations/', 		                function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgPresentations($orgId))); }, 					                    'All presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/count/',                function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgPresentationCount($orgId))); }, 				                    'Total presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/employees/', 		    function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgEmployeePresentations($orgId))); }, 			                    'All employee presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/employees/count/',      function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgEmployeePresentationCount($orgId))); }, 		                    'Total employee presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/students/', 		    function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgStudentPresentations($orgId))); }, 			                    'All student presentations at org (Scope: admin/org).'),
			array('GET','/org/[org:orgId]/presentations/students/count/',       function($orgId){ global $relay; verifyOrgAccess($orgId); Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgStudentPresentationCount($orgId))); }, 		                    'Total student presentations at org (Scope: admin/org).'),

		    // sql - deprecated
			/* DONE */ //array('GET','/org/[org:orgId]/presentations/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgPresentations($orgId))); }, 					            'All presentations at org (Scope: admin/org).'),
			/* DONE */ //array('GET','/org/[org:orgId]/presentations/count/',              function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgPresentationCount($orgId))); }, 				            'Total presentations at org (Scope: admin/org).'),
			/* DONE */ //array('GET','/org/[org:orgId]/presentations/employees/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployeePresentations($orgId))); }, 			'All employee presentations at org (Scope: admin/org).'),
			/* DONE */ //array('GET','/org/[org:orgId]/presentations/employees/count/',    function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgEmployeePresentationCount($orgId))); }, 		            'Total employee presentations at org (Scope: admin/org).'),
			/* DONE */ //array('GET','/org/[org:orgId]/presentations/students/', 		             function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudentPresentations($orgId))); }, 			    'All student presentations at org (Scope: admin/org).'),
			/* DONE */ //array('GET','/org/[org:orgId]/presentations/students/count/',     function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getOrgStudentPresentationCount($orgId))); }, 		            'Total student presentations at org (Scope: admin/org).')
		]);
	}

	// USER ROUTES (/me/) if scope allows
	if($feideConnect->hasOauthScopeUser()) {
		// Add all routes
		$router->addRoutes([
			// STORAGE
			array('GET','/me/diskusage/', 			    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserDiskusage())); }, 	    'User diskusage history (in MiB) and total (Scope: user).'),

			// mongo
			array('GET','/me/', 					    function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUser())); }, 		            'User account details (Scope: user).'),
			array('GET','/me/presentations/', 			function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentations())); }, 	'User presentations (Scope: user).'),
			array('GET','/me/presentations/count/', 	function(){ global $relay; Response::result(array('status' => true, 'data' => $relay->mongo()->getUserPresentationCount())); }, 'User presentation count (Scope: user).'),

			// sql deprecated
			/* DONE */ // array('GET','/me/', 					                            function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->sql()->getUser($feideConnect->userName()))); }, 		            'User account details (Scope: user).'),
			/* DONE */ //array('GET','/me/presentations/', 		                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentations($feideConnect->userName()))); }, 	'User presentations (Scope: user).'),
			/* DONE */ //array('GET','/me/presentations/count/',                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentationCount($feideConnect->userName()))); }, 'User presentation count (Scope: user).'),
			// fs deprecated
			/* TEST WITH FS */ //array('GET','/me/presentations/',                        function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->fs()->getRelayUserMedia($feideConnect->userName()))); },     'User presentations, deleted ones excluded (Scope: user).'),
			/* TEST WITH FS */ //array('GET','/me/presentations/count/', 					function(){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->fs()->getRelayUserMediaCount($feideConnect->userName()))); },     'User presentation count, deleted ones excluded (Scope: user).'),

			// TODO:  array('DELETE', '/me/presentation/[presentation:presId]/delete/',   function($presId){ global $relay, $feideConnect; Response::result(array('status' => true, 'data' => $relay->deleteUserPresentation($presId, $feideConnect->userName()))); }, 'Delete user presentation (Scope: user).')
		]);
	}


	// DEV ROUTES FOR TESTING (Superadmin access only)
	if($feideConnect->hasOauthScopeAdmin() && $feideConnect->isSuperAdmin()) {
		$router->addRoutes([
			array('GET','/dev/table/[a:tableName]/schema/',	            function($table_name){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getTableSchema($table_name))); }, 'Table schema.'),
			array('GET','/dev/table/[a:tableName]/dump/',	            function($table_name){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getTableDump($table_name, 50))); }, 'Table dump. Top 50.'),
		    array('GET','/dev/table/[a:tableName]/dump/top/[i:top]',	function($table_name, $top){ global $relay; Response::result(array('status' => true, 'data' => $relay->sql()->getTableDump($table_name, $top))); }, 'Table dump. Top $top.'),
			array('GET','/dev/memorytest/', 	                        function(){ $test = new MongoTest(); Response::result(array('status' => true, 'data' => $test->memoryTest())); }, 'Test mongodb/php memory consumption.')
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

	/**
	 * Prevent orgAdmin to request data for other orgs than what he belongs to.
	 *
	 * @param      $orgName
	 * @param null $userName
	 */
	function verifyOrgAccess($orgName, $userName = null){
		global $feideConnect;

		// Restrictions apply, unless you're superadmin...
		if(!$feideConnect->isSuperAdmin()){
			// If requested org data is not for home org
			if(strcasecmp($orgName, $feideConnect->userOrg()) !== 0) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (request mismatch org/user). ');
			}
			// If request involves a user account
			if(isset($userName)){
				// Must be user from home org
				if(!strstr($userName, $orgName)) {
					Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (request mismatch org/user). ');	                                      				}
			}
		}
	}

	// -------------------- ./UTILS -------------------- //

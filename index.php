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

	date_default_timezone_set('Europe/Oslo');
	###	   LOAD DEPENDENCIES	###
	require_once('relay/autoload.php');

	use Relay\Api\Relay;
	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Tests\MongoTest;
	use Relay\Utils\Response;
	use Relay\Vendor\Router;

	// Gatekeeper and provider of useful info
	$dataporten = new Dataporten();
	// Provides an interface to SQL, Mongo, FS classes
	$relay = new Relay($dataporten);

	### 	  ALTO ROUTER 		###
	$router = new Router();
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
		// SERVICE
		// Legacy - used by some other APIs (use service/info for richer response)
		array('GET', '/service/version/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getServiceVersion()));
		}, 'Service version'),
		array('GET', '/service/info/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getServiceInfo()));
		}, 'Version and workers'),
		array('GET', '/service/queue/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getServiceQueue()));
		}, 'Queue on server.'),
		array('GET', '/service/queue/failed/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getServiceQueueFailed()));
		}, 'Failed jobs in queue on server.'),

		// ORGS
		array('GET', '/service/subscribers/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->subscribers()->getSubscribers()));
		}, 'List all orgs : usercount'),
		// STORAGE (mongo)
		array('GET', '/service/diskusage/', function () {
			global $relay;
			// simon@14DES2016 - TODO: DENNE FUNKSJONEN HENTER INFO FRA Mongo - BURDE ERSTATTES SLIK AT VI KAN BLI KVITT AVHENGIGHET TIL https://github.com/skrodal/relay-mediasite-harvest
			Response::result(array('status' => true, 'data' => $relay->mongo()->getServiceDiskusage()));
		}, 'Total service diskusage (in MiB)'),
		// USERS (mssql)
		array('GET', '/service/users/count/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalUserCount()));
		}, 'Total user count, plus count by affiliation/active'),
		// PRESENTATIONS (mssql)
		array('GET', '/service/presentations/count/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalPresentationCount()));
		}, 'Total presentation count (inc. deleted)'),
		array('GET', '/service/presentations/employees/count/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalEmployeePresentationCount()));
		}, 'Total employee presentation count (inc. deleted)'),
		array('GET', '/service/presentations/students/count/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->sql()->getGlobalStudentPresentationCount()));
		}, 'Total student presentation count (inc. deleted)'),
		// DELETED PRESENTATIONS (mysql)
		array('GET', '/service/presentations/deleted/count/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => count($relay->presDelete()->getAllPresentationRecordsAdmin())));
		}, 'Total deleted presentation count'),
		// HITS (mysql)
		array('GET', '/service/presentations/hits/daily/all/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->presHits()->getDailyHitsAll()));
		}, 'Complete history of daily hits'),
		array('GET', '/service/presentations/hits/daily/year/[i:year]/', function ($year) {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->presHits()->getDailyHitsByYear($year)));
		}, 'History of daily hits for a given year'),
		array('GET', '/service/presentations/hits/daily/days/[i:days]/', function ($days) {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->presHits()->getDailyHitsByDays($days)));
		}, 'History of daily hits for the last given number of days'),
		array('GET', '/service/presentations/hits/orgs/total/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->presHits()->getOrgsTotalHitsAnonymised()));
		}, 'Hits distributed by orgs, but anonymised'),
		array('GET', '/service/presentations/hits/total/', function () {
			global $relay;
			Response::result(array('status' => true, 'data' => $relay->presHits()->getTotalHits()));
		}, 'Total number of hits logged'),

	]);


	// ADMIN ROUTES - if scope allows
	//
	// isSuperAdmin added 15.10.2015 - the client can call this API to find out if user has role(s)
	// super or org or user. simon@uninett.no should get: { roles : [super, org, user] }
	if($dataporten->hasOauthScopeAdmin() && $dataporten->isSuperAdmin()) {
		// Add all routes
		$router->addRoutes([
			### HITS (mysql)
			array('GET', '/admin/presentations/hits/orgs/total/', function () {
				global $relay;
				Response::result(array('status' => true, 'data' => $relay->presHits()->getOrgsTotalHits()));
			}, 'Hits distributed by orgs (Scope: admin).'),

			// CLIENTS
			/* Tested, but no useful info to be grabbed from tblClient. */
		]);
	}

	// Client must have admin scope. Does not restrict on superadmin (i.e. uninett-employees only) since these routes provides higher level data
	// per org. Suffices that client prevents access to certain groups if necessary.
	// Since Jan. 2016.
	if($dataporten->hasOauthScopeAdmin()) {
		// Add all routes
		$router->addRoutes([
			### ORGS
			array('GET', '/admin/orgs/info/', function () {
				global $relay;
				// simon@14DES2016 - TODO: DENNE FUNKSJONEN HENTER INFO FRA Mongo - BURDE ERSTATTES SLIK AT VI KAN BLI KVITT AVHENGIGHET TIL https://github.com/skrodal/relay-mediasite-harvest
				Response::result(array('status' => true, 'data' => $relay->sql()->getOrgsInfo()));
			}, 'List all orgs with user/presentation/diskusage info (Scope: admin).'),

			/*
			array('GET', '/admin/orgs/diskusage/', function () {
				global $relay;
				Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgsDiskusage()));
			}, 'Total service diskusage (in MiB) plus per org (Scope: admin).'),
			*/
		]);
	}

	/**
	 * Every org route will, via verifyOrgAndUserAccess($org, $user), check that the user
	 *
	 * 1. is orgAdmin (member of MediasiteAdmin Dataporten group) and
	 * 2. is affiliated with the $org/$user requested.
	 *
	 * The client also needs access to either the admin or org API scope.
	 */
	if($dataporten->hasOauthScopeAdmin() || $dataporten->hasOauthScopeOrg()) {
		// Add all routes
		$router->addRoutes([

			## RelayAdmin group invitation link (to make others OrgAdmins)
			array('GET', '/org/[org:orgId]/orgadmin/invitationurl/', function ($orgId) {
				global $dataporten;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $dataporten->adminGroupInviteLink()));
			}, 'Get invitation link to MediasiteAdmin group (Scope: admin/org).'),
			### DISKUSAGE
			/*
			array('GET', '/org/[org:orgId]/diskusage/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->mongo()->getOrgDiskusage($orgId)));
			}, 'Org diskusage history (in MiB) and total (Scope: admin/org).'),
			*/
			### SINGLE USER
			array('GET', '/org/[org:orgId]/user/[user:userName]/', function ($orgId, $userName) {
				global $relay;
				verifyOrgAndUserAccess($orgId, $userName);
				Response::result(array('status' => true, 'data' => $relay->sql()->getUser($userName)));
			}, 'Specific user at org (Scope: admin/org).'),
			array('GET', '/org/[org:orgId]/user/[user:userName]/presentations/', function ($orgId, $userName) {
				global $relay;
				verifyOrgAndUserAccess($orgId, $userName);
				Response::result(array('status' => true, 'data' => $relay->sql()->getUserPresentations($userName)));
			}, 'Presentations for specific user at org (Scope: admin/org).'),

			/*
			array('GET', '/org/[org:orgId]/user/[user:userName]/diskusage/', function ($orgId, $userName) {
				global $relay;
				verifyOrgAndUserAccess($orgId, $userName);
				Response::result(array('status' => true, 'data' => $relay->mongo()->getUserDiskusage($userName)));
			}, 'Diskusage for specific user at org (Scope: admin/org).'),
			*/

			### USERS

			array('GET', '/org/[org:orgId]/users/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUsers($orgId)));
			}, 'All users at org (Scope: admin/org).'),
			array('GET', '/org/[org:orgId]/users/count/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->sql()->getOrgUserCount($orgId)));
			}, 'Count users at org (Scope: admin/org).'),

			// sqlConn (all users in DB, active *and* inactive)
			/* DONE */ // array('GET','/org/[org:orgId]/users/', 			    function($orgId){ global $relay; Response::result(array('status' => true, 'data' => $relay->sqlConn()->getOrgUsers($orgId))); }, 							                    'All users at org (Scope: admin/org).'),

			### PRESENTATIONS
			array('GET', '/org/[org:orgId]/presentations/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->sql()->getOrgPresentations($orgId)));
			}, 'All presentations at org (Scope: admin/org).'),
			array('GET', '/org/[org:orgId]/presentations/count/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->sql()->getOrgPresentationCount($orgId)));
			}, 'Total presentations at org (Scope: admin/org).'),

			// mysql
			array('GET', '/org/[org:orgId]/presentations/hits/total/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->presHits()->getOrgTotalHits($orgId)));
			}, 'Total number of hits on this orgs content (Scope: admin/org).'),

			array('GET', '/org/[org:orgId]/presentations/hits/users/', function ($orgId) {
				global $relay;
				verifyOrgAndUserAccess($orgId);
				Response::result(array('status' => true, 'data' => $relay->presHits()->getOrgTotalHitsByUser($orgId)));
			}, 'Total number of hits on this orgs content, for each user (Scope: admin/org).'),
		]);
	}

	// USER ROUTES (/me/) if scope allows
	if($dataporten->hasOauthScopeUser()) {
		// Add all routes
		$router->addRoutes([
			// USER ROLE
			array('GET', '/me/role/', function () {
				global $dataporten;
				Response::result(array('status' => true, 'data' => $dataporten->userRole()));
			}, 'Logged on users role: Basic, OrgAdmin (if member of Dataporten group RelayAdmin) or SuperAdmin (Scope: user).'),
		]);
	}

	// DEV ROUTES FOR TESTING (Superadmin access only)
	if($dataporten->hasOauthScopeAdmin() && $dataporten->isSuperAdmin()) {
		$router->addRoutes([
			array('GET', '/dev/table/[a:tableName]/schema/', function ($table_name) {
				global $relay;
				Response::result(array('status' => true, 'data' => $relay->sql()->getTableSchema($table_name)));
			}, 'Table schema.'),
			array('GET', '/dev/table/[a:tableName]/dump/', function ($table_name) {
				global $relay;
				Response::result(array('status' => true, 'data' => $relay->sql()->getTableDump($table_name, 50)));
			}, 'Table dump. Top 50.'),
			array('GET', '/dev/table/[a:tableName]/dump/top/[i:top]', function ($table_name, $top) {
				global $relay;
				Response::result(array('status' => true, 'data' => $relay->sql()->getTableDump($table_name, $top)));
			}, 'Table dump. Top $top.'),
			array('GET', '/dev/memorytest/', function () {
				$test = new MongoTest();
				Response::result(array('status' => true, 'data' => $test->memoryTest()));
			}, 'Test mongodb/php memory consumption.')
		]);
	}


	// ---------------------- MATCH AND EXECUTE REQUESTED ROUTE ----------------------


	$match = $router->match();

	if($match && is_callable($match['target'])) {
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, "The requested resource route could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------


	// -------------------- UTILS -------------------- //

	/**
	 * http://stackoverflow.com/questions/4861053/php-sanitize-values-of-a-array/4861211#4861211
	 */
	function sanitizeInput() {
		$_GET  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
	}

	/**
	 * Prevent orgAdmin to request data for other orgs than what s/he belongs to.
	 *
	 * Also check that the user is member of the MediasiteAdmin group.
	 *
	 * @param      $orgName
	 * @param null $userName
	 */
	function verifyOrgAndUserAccess($orgName, $userName = NULL) {
		global $dataporten;
		// Restrictions apply, unless you're superadmin...
		if(!$dataporten->isSuperAdmin()) {
			// If requested org data is not for home org
			if(strcasecmp($orgName, $dataporten->userOrg()) !== 0) {
				Response::error(401, '401 Unauthorized (request mismatch org/user). ');
			}
			// If request involves a user account
			if(isset($userName)) {
				// Must be user from home org
				if(!strstr($userName, $orgName)) {
					Response::error(401, '401 Unauthorized (request mismatch org/user). ');
				}
			}

			if(!$dataporten->isOrgAdmin()) {
				Response::error(401, '401 Unauthorized (user is not member of the RelayAdmin group). ');
			}
		}
	}

	// -------------------- ./UTILS -------------------- //

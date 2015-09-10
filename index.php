<?php

   /**
	*
	* @author Simon Skrødal
	* @since  August 2015
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
	$feide        		= new FeideConnect(json_decode($feide_connect_config, true));
	
	###			RELAY			###
	
	require_once($BASE . '/lib/relay.class.php');
	$relay_config 		= file_get_contents($RELAY_CONFIG_PATH);
	if($relay_config === FALSE) { Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' Not Found: Relay config.'); }
	$relay      		= new Relay(json_decode($relay_config, true));
	
	Response::result(array('status' => true, 'data' => $_SERVER));
	
	### 	  ALTO ROUTER		###

	require_once($BASE . '/lib/router.class.php');			// http://altorouter.com
	$router = new Router();
	$router->setBasePath($API_BASE_PATH);


// ---------------------- DEFINE ROUTES ----------------------


	/**
	 * GET all REST routes
	 */
	$router->map('GET', '/', function () {
		Response::result(array('status' => true, 'data' => $GLOBALS['router']->getRoutes()));
	}, 'List all available routes.');


	/**
	 * GET all REST routes
	 */
	$router->map('GET', '/subscription/codes/', function () {
		Response::result($GLOBALS['kind']->getSubscriptionStatusCodeMap());
	}, 'Subscription codes mapped to textual representation.');


	/**
	 * GET complete dump of subscriber-info for service [i:id]
	 */
	$router->map('GET', '/service/[i:serviceId]/subscribers/', function ($serviceId) {
		Response::result($GLOBALS['kind']->getServiceSubscribers($serviceId));
	}, 'Get subscription data for all subscribers.');


	/**
	 * GET subscriber-info for org [a:org] for service [i:id]
	 */
	$router->map('GET', '/service/[i:serviceId]/org/[*:orgId]/', function ($serviceId, $orgId) {
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

	
	$match = $router->match();

	if($match && is_callable($match['target'])) {
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource route could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------



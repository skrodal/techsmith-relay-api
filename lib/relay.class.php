<?php
	/**
	 * Class to extract information pertaining to eCampus services from Kind. 
	 *
	 * @author Simon Skrodal
	 * @since  August 2015
	 */


	// Some calls take a long while so increase timeout limit from def. 30
	// set_time_limit(300);
	// Have experienced fatal error - allowed memory size of 128M exhausted - thus increase
	// ini_set('memory_limit', '350M');

	class Relay {
		private $DEBUG = false;

		protected $config, $apiurl;

		function __construct($config) {
			$this->config        = $config;
			$this->apiurl        = "Forget it";
		}

		/** PUBLIC SCOPE **/


		/**
		 *	Translate subscription status codes to something meaningful.
		 *	
		 *  Kind's equivalent name for each code in comments.
		 */
		public function getSubscriptionStatusCodeMap() {
			return array(	'status'	=> 	true,  	
							'data'		=> 
								array (
									'10'	=> 'Bestilt',		// Bestilt
									'15'	=> 'Utprøving',		// Demo
									'20'	=> 'Abonnent', 		// Installert
									'30'	=> 'Avbestilt',		// Avbestilt
									'40'	=> 'Stengt',		// Nedkoblet
									'50'	=> 'Utfasing' 		// Fjernes
								)
			);
		}


		/**
		 * Dump of all subscribers for the requested service. The function rearranges the data from the 
		 * rather cumbersome array of arrays of array structure to something a bit easer to work with.
		 *
		 * Response is sorted by org_id (e.g. aho.no)
		 */
		
		public function getServiceSubscribers($serviceId) {
			// From Kind
			$serviceSubscribers = $this->callKindAPI($serviceId);
			// New representation of the response
			$serviceSubscribersObj = array();
			//
			$subscriptionStatusCodeMap = $this->getSubscriptionStatusCodeMap();
			// Restructure response
			foreach($serviceSubscribers as $index => $subscriber) {
				$serviceSubscribersObj[ $subscriber[0]['org'] ] = array();
				// 'org.no'
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['org_id'] 						= strtolower( $subscriber[0]['org'] );
				// 
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['subscription_code'] 			= $subscriber[1]['abbstatus'];
				// Textual
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['subscription_description'] 	= $subscriptionStatusCodeMap['data'][$subscriber[1]['abbstatus']];
				// E.g. member/employee/student
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['affiliation_access'] 			= strtolower ($subscriber[3]['tilgang']);
				// Object {}
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['contact_person'] 				= $subscriber[4]['teknisk_ansvarlig'];
				// Object {}
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['contact_support'] 			= $subscriber[2]['support'];
				// 
				$serviceSubscribersObj[ $subscriber[0]['org'] ] ['service_uri'] 				= strtolower ($subscriber[5]['tjeneste_uri']);
			}
			// Sort by key (org_id)
			ksort($serviceSubscribersObj);
			// Return k(ey)sorted array
			return array('status' => true, 'data' => $serviceSubscribersObj);
		}
		
		
		/**
		 *
		 *
		 */
		public function getServiceOrgSubscriber($serviceId, $orgId){
			//
			$serviceSubscribers = $this->getServiceSubscribers($serviceId);
			//
			return isset($serviceSubscribers['data'][$orgId]) ? array('status' => true, 'data' => $serviceSubscribers['data'][$orgId]) : Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found: No subscription entry found for ' . $orgId . '.'); 
		}

		// ---------------------------- KIND API  ----------------------------

		private function callKindAPI($serviceId){
			$response = file_get_contents($this->apiurl . $serviceId);
			if($response === FALSE) { 
				Response::error(404, $_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found: KIND Lookup Failed.');
			}
			return json_decode($response, true);
		}


		// ---------------------------- ./KIND API ----------------------------



		// ---------------------------- UTILS ----------------------------


		private function _logger($text, $line, $function) {
			if($this->DEBUG) {
				error_log($function . '(' . $line . '): ' . $text);
			}
		}

		// ---------------------------- ./UTILS ----------------------------
		
		


	}




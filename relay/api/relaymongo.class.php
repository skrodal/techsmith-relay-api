<?php
	namespace Relay\Api;

	use Relay\Database\RelayMongoConnection;

	#############################################
	# IMPORTANT!!!!!!
	# SIMON@17.NOV.2016:
	#  - DELETED MOST QUERIES FROM MONGO. HARVESTER WAS FULL OF BUGS, PARTICULARLY IN REGARD TO PRESENTATION
	#    DETAILS. USING SQL AS SOURCE INSTEAD.
	#############################################

	/**
	 * Serves API routes requesting data from UNINETTs TechSmith RelaySQL Harvesting Service.
	 *
	 * The harvester stores all consolidated information in MongoDB.
	 *
	 *
	 * @author  Simon Skrødal
	 * @see     :    https://github.com/skrodal/relay-mediasite-harvest
	 * @date    29/10/2015
	 * @time    15:24
	 */
	class RelayMongo {
		private $relayMongoConnection, $dataporten, $relay;

		function __construct(Relay $relay) {
			$this->relayMongoConnection = new RelayMongoConnection();
			$this->dataporten           = $relay->dataporten();
			$this->relay                = $relay;
		}

		########################################################################
		####
		####    DISKUSAGE GLOBAL/ORG/USER
		####
		########################################################################


		public function getServiceDiskusage() {
			$orgs = $this->relayMongoConnection->findAll('org');
			//
			$total_mib = 0;
			foreach($orgs as $org) {
				if(!empty($org['storage'])) {
					// Latest entry is most current
					$length = sizeof($org['storage']) - 1;
					$total_mib += (float)$org['storage'][$length]['size_mib'];
				}
			}

			return $total_mib;
		}

		public function getOrgsDiskusage() {
			$orgs = $this->relayMongoConnection->findAll('org');
			//
			$response['total_mib'] = 0;
			$response['orgs']      = [];

			foreach($orgs as $org) {
				if(!empty($org['storage'])) {
					// Latest entry is most current
					$length     = sizeof($org['storage']) - 1;
					$latest_mib = (float)$org['storage'][$length]['size_mib'];
					$response['total_mib'] += $latest_mib;
					$response['orgs'][$org['org']] = $latest_mib;
				}
			}
			ksort($response['orgs']);

			return $response;
		}

		public function getOrgDiskusage($org) {
			$criteria              = ['org' => $org];
			$response['total_mib'] = 0;
			$response['storage']   = $this->relayMongoConnection->findOne('org', $criteria)['storage'];

			if(!empty($response['storage'])) {
				// Latest entry is most current
				$length                = sizeof($response['storage']) - 1;
				$response['total_mib'] = (float)$response['storage'][$length]['size_mib'];
			}

			return $response;
		}

		public function getUserDiskusage($feideUserName = NULL) {
			$feideUserName         = is_null($feideUserName) ? $this->dataporten->userName() : $feideUserName;
			$criteria              = ['username' => $feideUserName];
			$response['total_mib'] = 0;
			$response['storage']   = $this->relayMongoConnection->findOne('userDiskUsage', $criteria)['storage'];

			if(!empty($response['storage'])) {
				// Latest entry is most current
				$length                = sizeof($response['storage']) - 1;
				$response['total_mib'] = (float)$response['storage'][$length]['size_mib'];
			}

			return $response;
		}

	}
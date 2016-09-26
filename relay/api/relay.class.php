<?php
	/**
	 * Utilising RelayFS, RelaySQL and RelayMongo, this class provides responses for all routes.
	 *
	 * @author Simon Skrødal
	 * @date   29/10/2015
	 * @time   19:38
	 */

	namespace Relay\Api;

	use Relay\Auth\Dataporten;



	class Relay {

		private $dataporten;
		private $relaySQL;
		private $relayFS;
		private $relayMongo;
		private $relayPresDeleteMySQL;
		private $relayPresHitsMySQL;

		function __construct(Dataporten $dataporten) {
			#
			$this->dataporten = $dataporten;

			# SQL Class
			$this->relaySQL = new RelaySQL($this->dataporten);
			# Mongo Class
			$this->relayMongo = new RelayMongo($this->relaySQL, $this->dataporten);
			# FS Class
			$this->relayFS = new RelayFS($this->relaySQL, $this->dataporten);
			# Presentation delete
			$this->relayPresDeleteMySQL = new RelayPresDeleteMySQL($dataporten);
			# Presentation hits
			$this->relayPresHitsMySQL = new RelayPresHitsMySQL($dataporten);
		}


		public function sql(){
			return $this->relaySQL;
		}

		public function mongo(){
			return $this->relayMongo;
		}

		public function fs(){
			return $this->relayFS;
		}

		public function presDelete(){
			return $this->relayPresDeleteMySQL;
		}

		public function presHits(){
			return $this->relayPresHitsMySQL;
		}

		public function dataporten(){
			return $this->dataporten;
		}
	}
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
			$this->dataporten = $dataporten;
		}


		public function sql(){
			# SQL Class
			$this->relaySQL = new RelaySQL($this->dataporten);
			return $this->relaySQL;
		}

		public function mongo(){
			# Mongo Class
			$this->relayMongo = new RelayMongo($this->sql(), $this->dataporten);
			return $this->relayMongo;
		}

		public function fs(){
			# FS Class
			$this->relayFS = new RelayFS($this->sql(), $this->dataporten);
			return $this->relayFS;
		}

		public function presDelete(){
			# Presentation delete
			$this->relayPresDeleteMySQL = new RelayPresDeleteMySQL($this->dataporten);
			return $this->relayPresDeleteMySQL;
		}

		public function presHits(){
			# Presentation hits
			$this->relayPresHitsMySQL = new RelayPresHitsMySQL($this->mongo(), $this->dataporten);
			return $this->relayPresHitsMySQL;
		}
	}
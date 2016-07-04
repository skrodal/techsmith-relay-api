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

		function __construct(Dataporten $fc) {
			#
			$this->dataporten = $fc;

			# SQL Class
			$this->relaySQL = new RelaySQL($this->dataporten);
			# Mongo Class
			$this->relayMongo = new RelayMongo($this->relaySQL, $this->dataporten);
			# FS Class
			$this->relayFS = new RelayFS($this->relaySQL, $this->dataporten);
			# Presentation delete
			$this->relayPresDeleteMySQL = new RelayPresDeleteMySQL($fc);
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

		public function fc(){
			return $this->dataporten;
		}
	}
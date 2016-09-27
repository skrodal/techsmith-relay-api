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
		private $mongo, $sql, $fs, $presDelete, $presHits;

		function __construct(Dataporten $dataporten) {
			$this->dataporten = $dataporten;
		}

		public function mongo() {
			if(!isset($this->mongo)) $this->mongo = new RelayMongo($this->dataporten);
			return $this->mongo;
		}

		public function sql() {
			if(!isset($this->sql)) $this->sql = new RelaySQL($this->dataporten);
			return $this->sql;
		}

		public function fs() {
			if(!isset($this->fs)) $this->fs = new RelayFS($this->dataporten);
			return $this->fs;
		}

		public function presDelete() {
			if(!isset($this->presDelete)) $this->presDelete = new RelayPresDeleteMySQL($this->dataporten);
			return $this->presDelete;
		}

		public function presHits() {
			if(!isset($this->presHits)) $this->presHits = new RelayPresHitsMySQL($this->dataporten);
			return $this->presHits;
		}

		public function dataporten() {
			return $this->dataporten;
		}
	}
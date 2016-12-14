<?php
	/**
	 * Utilising RelaySQL/MySQL and RelayMongo, this class provides responses for all routes.
	 *
	 * @author Simon Skrødal
	 * @date   29/10/2015
	 * @time   19:38
	 */

	namespace Relay\Api;
	use Relay\Auth\Dataporten;


	class Relay {

		private $dataporten;
		private $mongo, $sql, $presDelete, $presHits, $subscribers;

		function __construct(Dataporten $dataporten) {
			$this->dataporten = $dataporten;
		}

		public function mongo() {
			if(!isset($this->mongo)) $this->mongo = new RelayMongo($this);
			return $this->mongo;
		}

		public function sql() {
			if(!isset($this->sql)) $this->sql = new RelaySQL($this);
			return $this->sql;
		}

		public function presDelete() {
			if(!isset($this->presDelete)) $this->presDelete = new RelayPresDeleteMySQL($this);
			return $this->presDelete;
		}

		public function presHits() {
			if(!isset($this->presHits)) $this->presHits = new RelayPresHitsMySQL($this);
			return $this->presHits;
		}

		public function subscribers() {
			if(!isset($this->subscribers)) $this->subscribers = new RelaySubscribersMySQL($this);
			return $this->subscribers;
		}

		public function dataporten() {
			return $this->dataporten;
		}
	}
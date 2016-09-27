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

		function __construct(Dataporten $dataporten) {
			$this->dataporten = $dataporten;
		}

		public function mongo() {
			return new RelayMongo();
		}

		public function sql() {
			return new RelaySQL();
		}

		public function fs() {
			return new RelayFS();
		}

		public function presDelete() {
			return new RelayPresDeleteMySQL();
		}

		public function presHits() {
			return new RelayPresHitsMySQL();
		}

		public function dataporten() {
			return $this->dataporten;
		}
	}
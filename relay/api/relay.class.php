<?php
	/**
	 * Utilising RelayFS, RelaySQL and RelayMongo, this class provides responses for all routes.
	 *
	 * @author Simon Skrødal
	 * @date   29/10/2015
	 * @time   19:38
	 */

	namespace Relay\Api;

	use Relay\Auth\FeideConnect;



	class Relay {

		private $feideConnect;
		private $relaySQL;
		private $relayFS;
		private $relayMongo;

		function __construct(FeideConnect $fc) {
			#
			$this->feideConnect = $fc;

			# SQL Class
			$this->relaySQL = new RelaySQL($this->feideConnect);
			# Mongo Class
			$this->relayMongo = new RelayMongo($this->relaySQL, $this->feideConnect);
			# FS Class
			$this->relayFS = new RelayFS($this->relaySQL, $this->feideConnect);

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

		public function fc(){
			return $this->feideConnect;
		}
	}
<?php

	Class YOURLS {

		private static $api = 'yourls-api.php';

		private $domain = null;

		private $signature = null;

		public function __construct($domain, $signature) {
			$this->domain = $domain;
			$this->signature = $signature;
		}

		public function shorten($url, $custom_slug = null) {
			$params = array(
				'signature' => $this->signature,
				'action' => 'shorturl',
				'format' => 'json',
				'url' => $url
			);

			if(!is_null($custom_slug)) {
				$params['keyword'] = $custom_slug;
			}

			$data = $this->request($params);

			if($format === 'json') {
				return json_decode($data);
			}
			else {
				return $data;
			}
		}

		public function stats($url, $format = 'json') {
			$params = array(
				'signature' => $this->signature,
				'action' => 'url-stats',
				'format' => 'json',
				'shorturl' => $url
			);

			if(!is_null($custom_slug)) {
				$params['keyword'] = $custom_slug;
			}

			$data = $this->request($params);

			if($format === 'json') {
				return json_decode($data);
			}
			else {
				return $data;
			}
		}

		private function request($params) {
			// create the Gateway object
			$gateway = new Gateway();

			// set some options
			$gateway->init($this->domain . '/' . self::$api);
			$gateway->setopt(CURLOPT_HEADER, false);
			$gateway->setopt('POST', 1);
			$gateway->setopt('POSTFIELDS', $params);

			$data = $gateway->exec();

			// clean up
			$gateway->flush();

			return $data;
		}

	}
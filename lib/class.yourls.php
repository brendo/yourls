<?php

	require_once TOOLKIT . '/class.gateway.php';

	Class YOURLS {

		/**
		 * Name of API file for YOURLS
		 * @var string
		 */
		private static $api = 'yourls-api.php';

		/**
		 * How long, in minutes, will any read requests
		 * to the API be cached for
		 * @var integer
		 */
		private $cache_time = 5;

		/**
		 * The domain where the YOURLS instance is available at
		 * @var string
		 */
		private $domain = null;

		/**
		 * The secret signature to authorise API requests to YOURLS
		 * @var string
		 */
		private $signature = null;

		/**
		 * The constructor takes the `$domain` and `$signature`
		 * parameters and sets the local variables
		 *
		 * @param string $domain
		 * @param string $signature
		 */
		public function __construct($domain, $signature) {
			$this->domain = $domain;
			$this->signature = $signature;
		}

		/**
		 * Sets the cache time in minutes
		 *
		 * @param integer $cache_time
		 */
		public function setCacheTime($cache_time) {
			$this->cache_time = $cache_time;
		}

		/**
		 * Given a `$url` and potentially a `$custom_slug`, this
		 * function will call the YOURLS service to create a
		 * short URL.
		 *
		 * @param string $url
		 *  The original URL
		 * @param string $custom_slug
		 *  If provided, YOURLS will attempt to create a short URL
		 *  using this slug as the reference. eg. domain/{custom_slug}
		 * @return string
		 */
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

			return json_decode($data);
		}

		/**
		 * Given a URL, return information about it from YOURLS.
		 * This includes clicks, the original link and original
		 * title. All requests are cached as defined by `$cache_time`
		 *
		 * @param string $url
		 * @param string $format
		 *  By default, 'json', also accepts 'xml'
		 */
		public function stats($url, $format = 'json') {
			// Get URL Stats
			$cache_id = md5($url . $format);
			$cache = new Cacheable(Symphony::Database());
			$cachedData = $cache->check($cache_id);

			// Execute if the cache doesn't exist, or if it is old.
			if(
				(!is_array($cachedData) || empty($cachedData)) // There's no cache.
				|| (time() - $cachedData['creation']) > ($this->cache_time * 60) // The cache is old.
			) {
				$params = array(
					'signature' => $this->signature,
					'action' => 'url-stats',
					'format' => $format,
					'shorturl' => $url
				);

				if(!is_null($custom_slug)) {
					$params['keyword'] = $custom_slug;
				}

				$data = $this->request($params);

				$cache->write($cache_id, $data, $this->cache_time);
			}
			// Used cached stats
			else {
				$data = $cachedData['data'];
			}

			if($format === 'json') {
				return json_decode($data);
			}
			else {
				return $data;
			}
		}

		/**
		 * Internal method that handles the actual request startup/teardown.
		 *
		 * @param array $params
		 *  Array of parameters that will be POSTed to the API
		 * @return string
		 */
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
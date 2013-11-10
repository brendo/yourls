<?php

	require_once EXTENSIONS . '/yourls/lib/class.yourls.php';

	Class fieldYOURLS extends Field {

		/**
		 * Cache time, in minutes
		 * @var integer
		 */
		private static $cache_time = 5;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
			$this->_name = __('YOURLS');
		}

		public function mustBeUnique() {
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public static function createSettingsTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_yourls` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `custom_slug` ENUM('yes','no') NOT NULL DEFAULT 'no',
				  `url_structure` VARCHAR(255) NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function yourls() {
			$instance = Symphony::Configuration()->get('instance', 'yourls');
			$signature = Symphony::Configuration()->get('signature', 'yourls');

			$yourls = new YOURLS($instance, $signature);

			return $yourls;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			// Add Permalink
			$label = Widget::Label(__('Permalink URL Structure'));
			$label->appendChild(
				new XMLElement('i', __('Permalink for an entry in this section'))
			);
			$label->appendChild(Widget::Input(
				"fields[{$this->get('sortorder')}][url_structure]", $this->get('url_structure'), 'text', array(
					'placeholder' => 'eg. $root/article/$entry_id'
				)
			));
			$label->appendChild(
				new XMLElement('p', __('%s will be prepended, %s will be appended', array(
					'<code>$root</code>',
					'<code>$entry_id</code>'
				)), array(
					'class' => 'help'
				))
			);
			$wrapper->appendChild($label);

			// Allow custom slugs?
			$label = Widget::Label();
			$label->setAttribute('class', 'column');
			$input = Widget::Input('fields['.$this->get('sortorder').'][custom_slug]', 'yes', 'checkbox');
			if($this->get('custom_slug') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Allow authors to add custom slugs', array($input->generate())));

			$wrapper->appendChild($label);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldYOURLS::createSettingsTable();

			$fields = array(
				'field_id'		=> $id,
				'custom_slug'	=> $this->get('custom_slug') ? $this->get('custom_slug') : 'no',
				'url_structure'	=> $this->get('url_structure')
			);

			return FieldManager::saveSettings($id, $fields);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){
			$element = Widget::Label($this->get('label'));

			// Do we have a short URL?
			if(isset($data['value'])) {
				// Get URL Stats
				$cache_id = md5($data['value']);
				$cache = new Cacheable(Symphony::Database());
				$cachedData = $cache->check($cache_id);

				// Execute if the cache doesn't exist, or if it is old.
				if(
					(!is_array($cachedData) || empty($cachedData)) // There's no cache.
					|| (time() - $cachedData['creation']) > (self::$cache_time * 60) // The cache is old.
				) {
					$yourls = $this->yourls();
					$response = $yourls->stats($data['value']);
					$cache->write($cache_id, json_encode($response), self::$cache_time);
				}
				// Used cached stats
				else {
					$response = json_decode($cachedData['data']);
				}

				// Display stats
				$element = new XMLElement('span');
				$element->setAttribute('class', 'frame');
				$element->appendChild(
					new XMLElement('p', Widget::Anchor($data['value'], $data['value']))
				);

				$element->appendChild(
					new XMlElement('span', __('Created: %s, %d clicks.', array(
						DateTimeObj::format($response->link->timestamp, __SYM_DATETIME_FORMAT__),
						$response->link->clicks
					)))
				);
			}
			// Can the Author suggest a custom slug?
			else if($this->get('custom_slug') == 'yes') {
				$element->appendChild(new XMLElement('i', __('Optional')));
				$element->appendChild(
					Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix)
				);
				$element->appendChild(
					new XMLElement('p', __('This value will be used as the short URL slug.'), array(
						'class' => 'help'
					))
				);
			}
			else {
				$element = new XMLElement('p', __('A short URL will be generated when this entry is created'));
			}

			if($flagWithError != NULL) {
				$wrapper->appendChild(Widget::Error($element, $flagWithError));
			}
			else {
				$wrapper->appendChild($element);
			}
		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			// Do we have a short URL?
			if(isset($data['value'])) {
				return $data;
			}
			// No, okay generate one
			else {
				// What is the permalink for this entry?
				$structure = $this->get('url_structure');
				$structure = rtrim(trim($structure, '/'), '/');
				$local_url = URL . '/' . $structure . '/' . $entry_id . '/';

				// Can the Author suggest a custom slug?
				$custom_slug = ($this->get('custom_slug') == 'yes') ? Lang::createHandle($data) : null;

				$yourls = $this->yourls();
				$response = $yourls->shorten($local_url, $custom_slug);

				return array(
					'value' => $response->shorturl
				);
			}
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			// @TODO
		}

	}
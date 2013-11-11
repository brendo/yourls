<?php

	require_once EXTENSIONS . '/yourls/lib/class.yourls.php';

	Class fieldYOURLS extends Field {

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
				  `slug` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `slug` (`slug`)
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

		/**
		 * Function is called from the context of the delegate
		 * `EntryPostCreate`. It generates a representation of
		 * XML for the entry so that data can be extracted from it
		 * using XPath.
		 *
		 * @param Entry $entry
		 */
		public function compile($entry) {
			// Do we already have a shorturl?
			$shorturl = $entry->getData($this->get('id'), true)->value;

			if(isset($shorturl)) return;

			$xpath = Extension_yourls::getXPath($entry);

			$expression = $this->get('url_structure');
			$replacements = array();

			// Find queries:
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);

			// Find replacements:
			foreach ($matches[0] as $match) {
				$results = $this->getExpressionValue(trim($match, '{}'), $xpath);

				$replacements[$match] = (is_array($results)) ? current($results) : '';
			}

			// Apply replacements:
			$value = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);

			// Get optional slug
			$slug = $entry->getData($this->get('id'), true)->slug;

			$this->shortenEntry($value, $slug, $entry->get('id'));
		}

		/**
		 * @param string $expression
		 * @param DOMXPath $xpath
		 * @return array|null
		 */
		protected function getExpressionValue($expression, DOMXPath $xpath) {
			$matches = $xpath->evaluate($expression);

			if ($matches instanceof DOMNodeList) {
				$values = array();

				foreach ($matches as $match) {
					if ($match instanceof DOMAttr or $match instanceof DOMText) {
						$values[] = $match->nodeValue;
					}
				}

				return $values;
			}

			else if (!is_null($matches)) {
				return array(strval($matches));
			}

			return null;
		}

		/**
		 * Given a local path and an optional slug, generate
		 * and save the short URL for this entry in the database.
		 * This will prepend the current URL to the local path
		 *
		 * @param string $local_path
		 * @param string $slug
		 * @param integer $entry_id
		 * @return boolean
		 */
		public function shortenEntry($local_path, $slug = null, $entry_id = null) {
			// Can the Author suggest a custom slug?
			$custom_slug = ($this->get('custom_slug') == 'yes') ? Lang::createHandle($slug) : null;

			$yourls = $this->yourls();
			$response = $yourls->shorten(URL . $local_path, $custom_slug);

			return Symphony::Database()->update(array(
					'value' => $response->shorturl,
					'slug' => $slug
				),
				'tbl_entries_data_' . $this->get('id'),
				sprintf('`entry_id` = %d', $entry_id)
			);
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			// Add Permalink
			$label = Widget::Label(__('Permalink URL Structure'));
			$label->appendChild(
				new XMLElement('i', __('XPath expression'))
			);
			$label->appendChild(Widget::Input(
				"fields[{$this->get('sortorder')}][url_structure]", $this->get('url_structure'), 'text', array(
					'placeholder' => 'eg. /article/{entry/@id}'
				)
			));
			$label->appendChild(
				new XMLElement('p', __('%s will be prepended', array(
					'<code>' . URL . '</code>',
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
				$yourls = $this->yourls();
				$response = $yourls->stats($data['value']);

				// Display stats
				$element = new XMLElement('span');
				$element->setAttribute('class', 'frame');
				$element->appendChild(
					new XMLElement('p', Widget::Anchor($response->link->shorturl, $response->link->shorturl))
				);

				$element->appendChild(
					new XMlElement('span', __('Created: %s, %d clicks.', array(
						DateTimeObj::format($response->link->timestamp, __SYM_DATETIME_FORMAT__),
						$response->link->clicks
					)))
				);

				$element->appendChild(
					Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][value]'.$fieldnamePostfix, $data['value'], 'hidden')
				);
				$element->appendChild(
					Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][slug]'.$fieldnamePostfix, $data['slug'], 'hidden')
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

		public function checkPostFieldData($data, &$message, $entry_id = null) {
			Extension_yourls::registerField($this);

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			// Do we have a short URL?
			if(isset($data['value'])) {
				return $data;
			}
			// No, okay, save custom slug (if it exists)
			// The url will be generated by the delegate callback
			else if(isset($data)) {
				$custom_slug = ($this->get('custom_slug') == 'yes') ? Lang::createHandle($data) : null;

				return array(
					'slug' => $custom_slug
				);
			}
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false) {
			if(!isset($data['value'])) {
				return;
			}

			// Get the stats as XML
			$yourls = $this->yourls();
			$response = $yourls->stats($data['value']);

			$item = new XMLElement($this->get('element_name'));
			$item->setAttribute('clicks', $response->link->clicks);

			$link = new XMLElement('shorturl', $response->link->shorturl);
			$item->appendChild($link);

			$original_link = new XMLElement('url', $response->link->url);
			$item->appendChild($original_link);

			$title = new XMLElement('title', $response->link->title);
			$title->setAttribute('handle', Lang::createHandle($response->link->title));
			$item->appendChild($title);

			$item->appendChild(General::createXMLDateObject($response->link->timestamp, 'created'));

			$wrapper->appendChild($item);
		}

	}
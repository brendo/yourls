<?php

	/**
	 * @package YOURLS
	 */

	class Extension_yourls extends Extension {

		public function install() {
			return true;
		}

		public function uninstall() {
			Symphony::Configuration()->remove('yourls');
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				)
			);
		}
		
		/** 
		 * Display settings on the Preferences page
		 */
		public function appendPreferences($context) {
			$instance = Symphony::Configuration()->get('instance', 'yourls');
			$signature = Symphony::Configuration()->get('signature', 'yourls');

			// Create preference group
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('YOURLS')));

			$line = new XMLElement('div');
			$line->setAttribute('class', 'two columns');

			// Append domain
			$label = Widget::Label('Instance URL');
			$label->setAttribute('class', 'column');
			$input = Widget::Input('settings[yourls][instance]', $instance, 'text', array(
				'placeholder' => 'http://example.com'
			));

			$label->appendChild($input);
			$line->appendChild($label);

			// Append signature
			$label = Widget::Label('API Signature');
			$label->setAttribute('class', 'column');
			$input = Widget::Input('settings[yourls][signature]', $signature, 'text');
			$label->appendChild($input);
			$line->appendChild($label);

			$group->appendChild($line);

			// Append help
			$group->appendChild(new XMLElement('p', __('API requests will automatically add %s to your instance URL.', array('<code>yourls-api.php</code>')), array('class' => 'help')));

			// Append new preference group
			$context['wrapper']->appendChild($group);
		}

	}
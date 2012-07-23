<?php
/**
 * =====================================================
 * Unrelated: Escape the Related Tags
 * -----------------------------------------------------
 * Copyright 2012 EpicVoyage. Free for distribution
 * and use. Visit http://www.epicvoyage.org/ee/unrelated
 * -----------------------------------------------------
 * v0.1: Initial release
 * =====================================================
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Unrelated_ext {
	# Basic information about this extension
	var $name = 'Unrelated: Escape the Related Tags';
	var $version = '0.3';
	var $description = 'Access channel entry tags while inside {related_entries} tag pairs.';
	var $settings_exist = 'n';
	var $docs_url = 'http://www.epicvoyage.org/ee/unrelated';

	# Our script's settings
	var $settings = array();

	function __construct($settings = '') {
		$this->EE =& get_instance();

		return;
	}

	# Our main callback function.
	function more_tags($tagdata, $row, $chan) {
		$has_related = !empty($this->EE->TMPL->related_data);
		$has_rev_related = !empty($this->EE->TMPL->reverse_related_data);

		if (strpos($tagdata, '{unrelated:debug_instance}') !== false) {
			# This will output to STDOUT (which will be printed before the template).
			$this->debug_instance($has_related, $has_rev_related, $tagdata);
		}

		# Only do anything if there are parsed Related Fields.
		if (($has_related || $has_rev_related) && ($channel = $this->_get_channel_instance())) {
			# Load the replacement tags.
			$tags = $this->_load($row);

			$has_related = $has_related && isset($channel->related_entries[$row['entry_id']]);
			$has_rev_related = $has_rev_related && isset($channel->reverse_related_entries[$row['entry_id']]);

			# Process Related fields.
			if ($has_related) {
				foreach ($this->EE->TMPL->related_data as $k => $v) {
					$this->EE->TMPL->related_data[$k.'-'.$row['entry_id']] = $v;
					$this->EE->TMPL->related_data[$k.'-'.$row['entry_id']]['tagdata'] = $this->EE->functions->var_swap($v['tagdata'], $tags);
				}

				foreach ($channel->related_entries[$row['entry_id']] as &$v) {
					$v .= '-'.$row['entry_id'];
				}
			}

			# Process Reverse Related fields.
			if ($has_rev_related) {
				foreach ($this->EE->TMPL->reverse_related_data as $k => $v) {
					$this->EE->TMPL->reverse_related_data[$k.'-'.$row['entry_id']] = $v;
					$this->EE->TMPL->reverse_related_data[$k.'-'.$row['entry_id']]['tagdata'] = $this->EE->functions->var_swap($v['tagdata'], $tags);
				}

				foreach ($channel->reverse_related_entries[$row['entry_id']] as &$v) {
					$v .= '-'.$row['entry_id'];
				}
			}
		}

		if (strpos($tagdata, '{unrelated:debug}') !== false) {
			$tagdata = str_replace('{unrelated:debug}', $this->debug($tagdata), $tagdata);
		}

		return $tagdata;
	}

	/**
	 * Called at the beginning of the processing.
	 */
	private function debug_instance($related, $reverse, $tagdata) {
		echo 'Related: '.($related ? 'y' : 'n').', Reverse: '.($related ? 'y' : 'n').'<br />';
		echo '<div id="get_channel_instance">';
		var_dump($this->_get_channel_instance());
		echo '</div>';

		echo $this->debug($tagdata);
		return;
	}

	/**
	 * Called at the end of the processing.
	 */
	private function debug($tagdata) {
		$ret  = '<div id="get_channel_related">'.htmlentities(print_r($this->EE->TMPL->related_data, true)).'</div>';
		$ret .= '<div id="get_channel_reverse">'.htmlentities(print_r($this->EE->TMPL->reverse_related_data, true)).'</div>';
		$ret .= '<div id="get_channel_tagdata">'.htmlentities($this->EE->TMPL->tagdata).'</div>';

		return $ret;
	}

	/**
	 * Yes, we are abusing PHP to accomplish this...
	 */
	private function _get_channel_instance() {
		$trace = debug_backtrace(defined('DEBUG_BACKTRACE_PROVIDE_OBJECT') ? DEBUG_BACKTRACE_PROVIDE_OBJECT : true);
		$ret = null;

		foreach ($trace as $index => &$t) {
			if (isset($t['class']) && ($t['class'] == 'Channel')) {
				$ret = &$t['object'];
				break;
			}
		}

		return $ret;
	}

	# Load the array with custom tag names...
	function _load($tags) {
		$ret = array();

		$fields = $this->_custom_fields();

		foreach ($tags as $k => $v) {
			if (preg_match('/^field_id_(\d+)$/', $k, $matches) && isset($fields[$matches[1]])) {
				$ret['unrelated_'.$matches[1]] = $v;
			} else {
				$ret['unrelated_'.$k] = $v;
			}
		}

		return $ret;
	}

	# Pull the custom fields list, either from cache or database.
	function _custom_fields() {
		$ret = array();
		if (isset($this->EE->session->cache['channel']['custom_channel_fields'])) {
			$ret = $this->EE->session->cache['channel']['custom_channel_fields'];
		} else {
			$this->EE->load->library('api');
			$this->EE->api->instantiate('channel_fields');

			$fields = $this->EE->api_channel_fields->fetch_custom_channel_fields();
			$ret = $channel_fields['custom_channel_fields'];
		}

		return $ret;
	}

	function settings() {
		return array();
	}
	
	# Install ourselves into the database.
	function activate_extension() {
		$hooks = array(
			'channel_entries_tagdata_end' => 'more_tags'
		);
		$ext_template = array(
			'class'	=> __CLASS__,
			'settings' => serialize($this->settings),
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		);

		$this->disable_extension();
		foreach ($hooks as $hook => $method) {
			$ext_template['hook'] = $hook;
			$ext_template['method'] = $method;
			$this->EE->db->insert('extensions', $ext_template);
		}

		return;
	}


	# No updates yet, but the manual says this function is required.
	function update_extension($current = '') {
		$this->activate_extension();
		return;
	}

	# Uninstalls extension
	function disable_extension() {
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');

		return;
	}
}

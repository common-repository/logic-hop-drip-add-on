<?php

if (!defined('ABSPATH')) die;

/**
 * Drip functionality.
 *
 * Provides Drip functionality.
 *
 * @since      1.1.0
 * @package    LogicHop
 * @subpackage LogicHop/includes/services
 */

class LogicHop_Drip {

	/**
	 * Core functionality & logic class
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      LogicHop_Core    $logic    Core functionality & logic.
	 */
	private $logic;

	/**
	 * Plugin version
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      integer    $version    Core functionality & logic.
	 */
	public $version;

	/**
	 * Drip API URL
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $drip_url    Drip API URL
	 */
	private $drip_url;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    	1.1.0
	 * @param       object    $logic	LogicHop_Core functionality & logic.
	 */
	public function __construct( $logic ) {
		$this->logic		= $logic;
		$this->version 		= '2.0.0';
		$this->drip_url		= 'https://api.getdrip.com/v2/';
	}

	/**
	 * Check if Drip has been set
	 *
	 * @since    	1.1.0
	 * @return      boolean     If Drip variables have been set
	 */
	public function active () {
		if ($this->logic->get_option('drip_account_id') !='' && $this->logic->get_option('drip_api_token') !='') return true;
		return false;
	}

	/**
	 * If Drip enabled and __s query string or stored ID are present, than retrieve data
	 *
	 * @since    	1.1.0
	 * @return      boolean     If Drip variables have been set
	 */
	public function data_check () {
		if ( ! $this->active() ) return false;
		if ( $this->data_loaded() ) return false;

		$drip_id = $this->logic->data_factory->get_value( 'DripID' );

		if ( ! is_null( $drip_id ) && $drip_id != '' ) {
			return $this->data_retrieve();
		}
		if ( isset( $_REQUEST['__s'] ) ) return $this->data_retrieve( $_REQUEST['__s'] );
		return false;
	}

	/**
	 * Check if Drip data has already been loaded
	 * Load new data on false
	 * Bypass data load on true
	 *
	 * @since    	1.2.0
	 * @return      boolean     If Drip data has already been loaded
	 */
	public function data_loaded () {
		if (isset($_REQUEST['__s'])) return false; // FORCE DATA REFRESH

		$drip = $this->logic->data_factory->get_value( 'Drip' );

		if ( isset( $drip->email ) && $drip->email != '' ) {
			return true;
		}
		return false;
	}

	/**
	 * Retrieve Drip Data
	 *
	 * @since    	1.1.0
	 * @param      	string     	$drip_id       Optional Drip user ID
	 * @return      boolean     If Drip variables have been set
	 */
	public function data_retrieve ($drip_id = false) {
		$args = array(
					'headers' => array(
    					'Authorization' => 'Basic ' . base64_encode($this->logic->get_option('drip_api_token') . ':')
  						)
					);
		$url = sprintf('%s%s/subscribers/%s',
							$this->drip_url,
							$this->logic->get_option('drip_account_id'),
							($drip_id) ? urlencode($drip_id) : $this->logic->data_factory->get_value( 'DripID' )
						);

		$response = wp_remote_get($url, $args);

		if (!is_wp_error($response)) {
			if (isset($response['body'])) $data = json_decode($response['body'], false);
		} else {
			return $response->get_error_message();
		}

		$drip_data = isset( $data->subscribers[0]) ? $data->subscribers[0] : false;

		if ( $drip_data ) {

			if ( is_null( $this->logic->data_factory->get_value( 'Drip' ) ) ) {
				logichop_object_create_drip();
			}

			$tags = array();
			if ( isset( $drip_data->tags )) {
				foreach ( $drip_data->tags as $tag ) {
					$tags[sanitize_key($tag)] = $tag;
				}
			}
			$drip_data->tags = $tags;

			if ( $drip_id && isset( $drip_data->id ) ) { // STORE Drip ID
				$this->logic->data_remote_put( 'drip', $drip_data->id );
				$drip_id = $this->logic->data_factory->set_value( 'DripID', $drip_data->id, false );
				$uid = ( isset( $_COOKIE['logichop'] ) ) ? $_COOKIE['logichop'] : $this->logic->hash;
				$this->update_field( 'logichop', $uid );
				$drip_data->custom_fields->logichop = $uid;
			}

			$this->logic->data_factory->set_value( 'Drip', $drip_data, false );
			$this->logic->data_factory->gravatar_object( 'Drip', $drip_data->email );
			$this->logic->data_factory->transient_save();
			return true;
		}
		return false;
	}

	/**
	 * Drip Update Field
	 * Updated Drip custom field value
	 *
	 * @since    	1.1.0
	 * @param      	string     $field      Field Name
	 * @param      	string     $value      Field Value
	 */
	public function update_field ($field, $value) {
		$drip_id = $this->logic->data_factory->get_value( 'DripID' );
		if ( $drip_id == '' ) return false;
		$data = array (
						'subscribers'	=> array (
							0 => array (
								'id' => $drip_id,
								'custom_fields' => array (
									$field => $value
								)
							)
						)
					);
		$args = array(
						'headers' => array(
							'method' => 'POST',
    						'Authorization' => 'Basic ' . base64_encode($this->logic->get_option('drip_api_token') . ':'),
    						'Content-Type' => 'application/json'
  							),
						'body' => json_encode($data)
					);
		$url = sprintf('%s%s/subscribers',
							$this->drip_url,
							$this->logic->get_option('drip_account_id')
						);
		$response = wp_remote_post($url, $args);

		if (!is_wp_error($response)) {
			return true;
		}
		return false;
	}

	/**
	 * Drip Track Event
	 * Checks for tracking actions
	 *
	 * @since    	1.1.0
	 * @param      	$id			integer     Post ID
	 * @param      	$values		array     	WordPress get_post_custom()
	 */
	public function track_event ($id, $values) {

		$drip = $this->logic->data_factory->get_value( 'Drip' );
		$drip_id = $this->logic->data_factory->get_value( 'DripID' );

		if ($this->active() && isset($drip->email)) {

			if (isset($values['logichop_goal_drip_tag'][0])) {
				$tag = $values['logichop_goal_drip_tag'][0];
				if ($tag && $drip_id) {
					if ($values['logichop_goal_drip_tag_action'][0] == 'add') {
						$this->add_tag($tag, $drip->email);
					} else {
						$this->remove_tag($tag, $drip->email);
					}
				}
			}

			if (isset($values['logichop_goal_drip_event'][0])) {
				$event = $values['logichop_goal_drip_event'][0];
				$event = $this->logic->get_liquid_value($event);
				if ($event && $drip_id) {
					if ($values['logichop_goal_drip_add_event'][0] == 'add') {
						$this->add_event($event, $drip->email);
					}
				}
			}

			if (isset($values['logichop_goal_drip_custom_field'][0])) {
				$field 	= $values['logichop_goal_drip_custom_field'][0];
				$value 	= $values['logichop_goal_drip_custom_value'][0];
				$type 	= $values['logichop_goal_drip_custom_type'][0];

				$value 	= $this->logic->get_liquid_value($value);

				if ($field && $value && $drip_id) {
					if ($type == 'increment' || $type == 'decrement' ) {
						$amount = (float) $value;
						if ($amount < 0) $amount = 0;
						$stored_value = 0;
						if (isset($drip) && isset($drip->custom_fields->{$field})) {
							$stored_value = (float) $drip->custom_fields->{$field};
							if ($stored_value < 0) $stored_value = 0;
						}
						if ($type == 'increment') {
							$value = $stored_value + $amount;
						} else {
							$value = $stored_value - $amount;
							if ($value < 0) $value = 0;
						}
					}

					if ($this->update_field($field, $value)) {
						$this->data_retrieve();
					}
				}
			}
		}
	}

	/**
	 * Send Add Event request to Drip
	 *
	 * @since    	1.1.0
	 * @param      	string     $event     	Event
	 * @param      	string     $email 		Email
	 * @return     	boolean     			Success state
	 */
	public function add_event ($event, $email) {
		$data = array (
						'events'	=> array (
							0 => array (
								'email' => $email,
								'action' => $event
							)
						)
					);
		$url = sprintf('%s%s/events',
								$this->drip_url,
								$this->logic->get_option('drip_account_id')
							);
		$args = array (
						'headers' => array(
							'Authorization' => 'Basic ' . base64_encode($this->logic->get_option('drip_api_token') . ':'),
							'Content-Type' => 'application/json'
  							),
						'body' => json_encode($data)
					);
		$response = wp_remote_post($url, $args);

		if (!is_wp_error($response)) return true;
		return false;
	}

	/**
	 * Send Add Tag request to Drip
	 *
	 * @since    	1.1.0
	 * @param      	string     $tag     	Tag
	 * @param      	string     $email 		Email
	 * @return     	boolean     			Success state
	 */
	public function add_tag ($tag, $email) {
		$data = array (
						'tags'	=> array (
							0 => array (
								'email' => $email,
								'tag' => $tag
							)
						)
					);
		$url = sprintf('%s%s/tags',
								$this->drip_url,
								$this->logic->get_option('drip_account_id')
							);
		$args = array (
						'headers' => array(
							'Authorization' => 'Basic ' . base64_encode($this->logic->get_option('drip_api_token') . ':'),
							'Content-Type' => 'application/json'
  							),
						'body' => json_encode($data)
					);
		$response = wp_remote_post($url, $args);

		if (!is_wp_error($response)) {
			$this->data_retrieve();
			return true;
		}
		return false;
	}

	/**
	 * Send Remove Tag request to Drip
	 *
	 * @since    	1.1.0
	 * @param      	string     $tag     	Tag
	 * @param      	string     $email 		Email
	 * @return     	boolean     			Success state
	 */
	public function remove_tag ($tag, $email) {
		$url = sprintf('%s%s/subscribers/%s/tags/%s',
								$this->drip_url,
								$this->logic->get_option('drip_account_id'),
								$email,
								urlencode($tag)
							);
		$args = array (
						'method' => 'DELETE',
						'headers' => array(
							'method' => 'DELETE',
							'Authorization' => 'Basic ' . base64_encode($this->logic->get_option('drip_api_token') . ':')
  						)
					);
		$response = wp_remote_request($url, $args);

		if (!is_wp_error($response)) {
			$this->data_retrieve();
			return true;
		}
		return false;
	}

	/**
	 * Get Drip Tags
	 *
	 * @since    	1.1.0
	 * @param      	string     $api_token      	Optional API Token
	 * @param      	string     $account_id      Optional Account ID
	 * @return      object    					Drip Tags
	 */
	public function tags_get ($api_token = false, $account_id = false) {
		if ($this->active() || $api_token && $account_id) {
			$api_token = ($api_token) ? $api_token : $this->logic->get_option('drip_api_token');
			$account_id = ($account_id) ? $account_id : $this->logic->get_option('drip_account_id');

			if (!preg_match('/\d{1,10}$/i', $account_id)) return false;

			$args = array(
					'headers' => array(
    					'Authorization' => 'Basic ' . base64_encode($api_token . ':')
  						)
					);
			$url = sprintf('%s%s/tags',
							$this->drip_url,
							$account_id
						);
			$response = wp_remote_get($url, $args);

			if (!is_wp_error($response)) {
				if (isset($response['body'])) $data = json_decode($response['body'], false);
				if (isset($data->tags)) return $data->tags;
			}
		}
		return false;
	}

	/**
	 * Get Drip Tags as JSON object
	 *
	 * @since    	1.1.0
	 * @return      json object    JSON encoded tags
	 */
	public function tags_get_json () {
		$tags = array();

		if ($data = $this->tags_get() ) {
			foreach ($data as $tag) {
				$tags[sanitize_key($tag)] = $tag;
			}
		}
		return json_encode($tags);
	}

	/**
	 * Get Drip Tags as options for select input
	 *
	 * @since    	1.1.0
	 * @param		string		$id		Selected option value
	 * @return      string		Goal options
	 */
	public function tags_get_options ($id = false) {
		$options = '';
		if ($data = $this->tags_get() ) {
			foreach ($data as $tag) {
				$options .= sprintf('<option value="%s" %s>%s</option>',
								$tag,
								($tag == $id) ? 'selected' : '',
								$tag
							);
			}
		}
		return $options;
	}

	/**
	 * Get Drip Custom Fields
	 *
	 * @since    	1.1.0
	 * @param      	string     $api_token      	Optional API Token
	 * @param      	string     $account_id      Optional Account ID
	 * @return      object    					Custom Fields
	 */
	public function fields_get ($api_token = false, $account_id = false) {
		if ($this->active() || $api_token && $account_id) {
			$api_token = ($api_token) ? $api_token : $this->logic->get_option('drip_api_token');
			$account_id = ($account_id) ? $account_id : $this->logic->get_option('drip_account_id');

			if (!preg_match('/\d{1,10}$/i', $account_id)) return false;

			$args = array(
					'headers' => array(
    					'Authorization' => 'Basic ' . base64_encode($api_token . ':')
  						)
					);
			$url = sprintf('%s%s/custom_field_identifiers',
							$this->drip_url,
							$account_id
						);
			$response = wp_remote_get($url, $args);

			if (!is_wp_error($response)) {
				if (isset($response['body'])) $data = json_decode($response['body'], false);
				if (isset($data->custom_field_identifiers)) return $data->custom_field_identifiers;
			}
		}
		return false;
	}

	/**
	 * Get Drip Fields as JSON object
	 *
	 * @since    	1.1.0
	 * @return      json object    JSON encoded fields
	 */
	public function fields_get_json () {
		$fields = array();

		if ($data = $this->fields_get() ) {
			foreach ($data as $field) {
				$fields[$field] = $field;
			}
		}
		return json_encode($fields);
	}

	/**
	 * Get Drip Fields as options for select input
	 *
	 * @since    	1.1.0
	 * @param		string		$id		Selected option value
	 * @return      string		Goal options
	 */
	public function fields_get_options ($id = false) {
		$options = '';
		if ($data = $this->fields_get() ) {
			foreach ($data as $field) {
				$options .= sprintf('<option value="%s" %s>%s</option>',
								$field,
								($field == $id) ? 'selected' : '',
								$field
							);
			}
		}
		return $options;
	}

	/**
	 * Get Drip variables as array of options for shortcodes
	 *
	 * @since    	1.1.0
	 * @return      array		Drip custom fields
	 */
	public function shortcode_variables_data ($invert = false) {
		$vars = array (
			'Drip.email' => 'Email Address',
			'Drip.gravatar.img.fullsize' => 'Gravatar Full Size (2048px)',
			'Drip.gravatar.img.large' => 'Gravatar Large (1024px)',
			'Drip.gravatar.img.medium' => 'Gravatar Medium (512px)',
			'Drip.gravatar.img.small' => 'Gravatar Small (256px)',
			'Drip.gravatar.img.thumb' => 'Gravatar Thumbnail (100px)',
			'Drip.landing_url' => 'Landing URL',
			'Drip.original_referrer' => 'Original Referrer',
			'Drip.created_at' => 'Created At',
			'Drip.prospect' => 'Prospect',
			'Drip.lifetime_value' => 'Lifetime Value',
			'Drip.lead_score' => 'Lead Score',
			'Drip.base_lead_score' => 'Base Lead Score',
			'Drip.time_zone' => 'Time Zone',
			'Drip.utc_offset' => 'UTC Offset'
		);

		if ($data = $this->fields_get()) {
			foreach ($data as $f) {
				$key = sprintf('Drip.custom_fields.%s', $f);
				$vars[$key] = sprintf('Custom Field: %s', $f);
			}
		}

		if ($invert) {
			$inverted = array();
			foreach ($vars as $k => $v) $inverted[$v] = $k;
			return $inverted;
		}

		return $vars;
	}

	/**
	 * Get Drip variables as options for shortcodes
	 *
	 * @since    	1.1.0
	 * @return      string		Drip options
	 */
	public function shortcode_variables () {
		$options = '';
		if ($data = $this->shortcode_variables_data()) {
			foreach ($data as $k => $v) {
				$options .= sprintf('<option value="%s">%s</option>', $k, $v);
			}
		}
		return $options;
	}

	/**
	 * Displays Drip Tag metabox on Goal editor
	 *
	 * @since    	1.1.0
	 * @param		object		$post		Wordpress Post object
	 * @return		string					Echos metabox form
	 */
	public function goal_tag_display ($post) {

		$values	= get_post_custom($post->ID);
		$drip_tag_action = isset($values['logichop_goal_drip_tag_action']) ? esc_attr($values['logichop_goal_drip_tag_action'][0]) : '';
		$drip_tag = isset($values['logichop_goal_drip_tag']) ? esc_attr($values['logichop_goal_drip_tag'][0]) : '';

		$drip_add_event = isset($values['logichop_goal_drip_add_event']) ? esc_attr($values['logichop_goal_drip_add_event'][0]) : '';
		$drip_event = isset($values['logichop_goal_drip_event']) ? esc_attr($values['logichop_goal_drip_event'][0]) : '';

		$drip_custom_field 	= isset($values['logichop_goal_drip_custom_field']) ? esc_attr($values['logichop_goal_drip_custom_field'][0]) : '';
		$drip_custom_value 	= isset($values['logichop_goal_drip_custom_value']) ? esc_attr($values['logichop_goal_drip_custom_value'][0]) : '';
		$drip_custom_type 	= isset($values['logichop_goal_drip_custom_type']) ? esc_attr($values['logichop_goal_drip_custom_type'][0]) : '';

		$tag_options	= $this->tags_get_options($drip_tag);
		$field_options 	= $this->fields_get_options($drip_custom_field);

		if ($this->active()) {
			printf('<div>
						<p>
							<label for="logichop_goal_drip_tag" class="">%s</label><br>
							<select id="logichop_goal_drip_tag_action" name="logichop_goal_drip_tag_action">
								<option value=""></option>
								<option value="add" %s>Add Tag</option>
								<option value="remove" %s>Remove Tag</option>
							</select>
							<select id="logichop_goal_drip_tag" name="logichop_goal_drip_tag">
								<option value=""></option>
								%s
							</select>
							<a href="#" class="logichop_drip_clear">Clear</a>
						</p>
					</div>',
					__('Drip Tag Action', 'logichop'),
					($drip_tag_action == 'add') ? 'selected' : '',
					($drip_tag_action == 'remove') ? 'selected' : '',
					$tag_options
				);
			printf('<div>
						<label for="logichop_goal_drip_event" class="">%s</label><br>
						<select id="logichop_goal_drip_add_event" name="logichop_goal_drip_add_event">
							<option value=""></option>
							<option value="add" %s>Add Event</option>
						</select>
						<input type="text" id="logichop_goal_drip_event" name="logichop_goal_drip_event" value="%s" placeholder="%s">
						<a href="#" class="logichop_drip_clear">Clear</a>
					</div>',
					__('Drip Add Event Action', 'logichop'),
					($drip_add_event == 'add') ? 'selected' : '',
					$drip_event,
					__('Event Action', 'logichop')
				);
			printf('<div>
						<p>
							<label for="logichop_goal_drip_custom_field" class="">%s</label><br>
							<select id="logichop_goal_drip_custom_field" name="logichop_goal_drip_custom_field">
								<option value=""></option>
								%s
							</select>
							<select id="logichop_goal_drip_custom_type" name="logichop_goal_drip_custom_type">
								<option value=""></option>
								<option value="set" %s>set value to</option>
								<option value="increment" %s>increment value by</option>
								<option value="decrement" %s>decrement value by</option>
							</select>
							<input type="text" id="logichop_goal_drip_custom_value" name="logichop_goal_drip_custom_value" value="%s" placeholder="%s">
							<a href="#" class="logichop_drip_clear">Clear</a>
						</p>
					</div>',
					__('Drip Add/Update Custom Field', 'logichop'),
					$field_options,
					($drip_custom_type == 'set') ? 'selected' : '',
					($drip_custom_type == 'increment') ? 'selected' : '',
					($drip_custom_type == 'decrement') ? 'selected' : '',
					($drip_custom_value) ? $drip_custom_value : '',
					'Custom Field Value'
				);
		} else {
			printf('<div>
						<h4>%s</h4>
						<p>
							%s
						</p>
					</div>',
					__('Drip is currently disabled.', 'logichop'),
					sprintf(__('To enable, add a valid Drip API Key & Secret on the <a href="%s">Settings page</a>.', 'logichop'),
							admin_url( 'admin.php?page=logichop-settings' ) )
				);
		}
	}
}

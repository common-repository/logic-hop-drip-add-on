<?php

	/*
		Plugin Name: Logic Hop Drip Add-on
		Plugin URI:	https://logichop.com/docs/drip
		Description: Enables Drip integration for Logic Hop
		Author: Logic Hop
		Version: 3.0.3
		Author URI: https://logichop.com
	*/

	if (!defined('ABSPATH')) die;

	if ( is_admin() ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( ! is_plugin_active( 'logichop/logichop.php' ) && ! is_plugin_active( 'logic-hop/logichop.php' ) ) {
			add_action( 'admin_notices', 'logichop_drip_plugin_notice' );
		}
	}

	function logichop_drip_plugin_notice () {
		$message = sprintf(__('Drip for Logic Hop requires the Logic Hop plugin. Please download and activate the <a href="%s" target="_blank">Logic Hop plugin</a>.', 'logichop'),
							'http://wordpress.org/plugins/logic-hop/'
						);

		printf('<div class="notice notice-warning is-dismissible">
						<p>
							%s
						</p>
					</div>',
					$message
				);
	}

	require_once 'includes/drip.php';

	/**
	 * Plugin activation/deactviation routine to clear Logic Hop transients
	 *
	 * @since    2.0.1
	 */
	function logichop_drip_activation () {
		delete_transient( 'logichop' );
  }
	register_activation_hook( __FILE__, 'logichop_drip_activation' );
	register_deactivation_hook( __FILE__, 'logichop_drip_activation' );

	/**
	 * Register admin notices
	 *
	 * @since    2.0.1
	 */
	function logichop_drip_admin_notice () {
		global $logichop;

		$message = '';

		if ( ! $logichop->logic->addon_active('drip') ) {
			$message = sprintf(__('The Logic Hop Drip Add-on requires a <a href="%s" target="_blank">Logic Hop License Key or Data Plan</a>.', 'logichop'),
							'https://logichop.com/get-started/?ref=addon-drip'
						);
		}

		if ( $message ) {
			printf('<div class="notice notice-warning is-dismissible">
						<p>
							%s
						</p>
					</div>',
					$message
				);
		}
	}
	add_action( 'logichop_admin_notice', 'logichop_drip_admin_notice' );

	/**
	 * Plugin page links
	 *
	 * @since    1.0.0
	 * @param    array		$links			Plugin links
	 * @return   array  	$new_links 		Plugin links
	 */
	function logichop_plugin_action_links_drip ($links) {
		$new_links = array();
        $new_links['settings'] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://logichop.com/docs/drip', 'Instructions' );
 		$new_links['deactivate'] = $links['deactivate'];
 		return $new_links;
	}
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'logichop_plugin_action_links_drip');

	/**
	 * Initialize functionality
	 *
	 * @since    1.0.0
	 */
	function logichop_integration_init_drip () {
		global $logichop;

		if ( isset( $logichop->logic ) ) {
			$logichop->logic->drip = new LogicHop_Drip($logichop->logic);
		}
	}
	add_action('logichop_integration_init', 'logichop_integration_init_drip');

	/**
	 * Check for user data
	 *
	 * @since    1.0.0
	 */
	function logichop_drip_user_check () {
		global $logichop;

		$bypass = false;

		if ( isset( $logichop ) && $logichop->logic->drip->active() ) {

			$drip_cookie = sprintf( '_drip_client_%s', $logichop->logic->get_option('drip_account_id') );

			if ( isset( $_COOKIE[$drip_cookie] ) ) {
				preg_match('/vid=(.*?)&/', urldecode( $_COOKIE[$drip_cookie] ), $cookie_vid);

				if ( isset( $cookie_vid[1] ) ) {
					$logichop->logic->hash = md5( $cookie_vid[1] ); // LOGIC HOP HASH FROM DRIP visitor_uuid
					$logichop->logic->session_create(); // CREATE THE SESSION :: NEW USER
					$logichop->logic->cookie_create(); // CREATE THE COOKIE :: STORE HASH
					$data = $logichop->logic->data_retrieve(); // LOAD USER DATA
					$bypass = true;
				}
			}
		}

		return $bypass;
	}
	add_filter('logichop_initialize_core', 'logichop_drip_user_check');

	/**
	 * Check for Drip data
	 *
	 * @since    1.0.0
	 */
	function logichop_data_check_drip () {
		global $logichop;
		if ( ! isset( $logichop ) ) return;
		$logichop->logic->drip->data_check();
	}
	add_action('logichop_initialize_core_data_check', 'logichop_data_check_drip');

	/**
	 * Parse data returned from SPF lookup
	 *
	 * @since    1.0.0
	 * @param    array		$data	Store data
	 * @return   boolean   	Data retrieved
	 */
	function logichop_data_retrieve_drip ( $data ) {
		global $logichop;

		$data = array_change_key_case( $data, CASE_LOWER );

		if ( isset( $data['drip'] ) ) {

			if ( is_string( $data['drip'] ) && $data['drip'] != '' ) {
				$logichop->logic->data_factory->set_value( 'DripID', $data['drip'] );
				return true;
			}

			if ( is_array( $data['drip'] ) ) {
				foreach ($data['drip'] as $key => $value) {
					$logichop->logic->data_factory->set_value( 'DripID', $key );
					return true;
				}
			}
		}

		return false;
	}
	add_action('logichop_data_retrieve', 'logichop_data_retrieve_drip', 10, 1);

	/**
	 * Handle event tracking
	 *
	 * @since    1.0.0
	 * @param    integer	$id			Goal ID
	 * @param    array     	$values		WordPress get_post_custom()
	 * @return   boolean   	Event tracked
	 */
	function logichop_track_event_drip ($id, $values) {
		global $logichop;

		return $logichop->logic->drip->track_event($id, $values);
	}
	add_filter('logichop_check_track_event', 'logichop_track_event_drip', 10, 2);

	/**
	 * Create default data object
	 *
	 * @since    1.0.0
	 */
	function logichop_object_create_drip ( $data = null ) {
		if ( is_null( $data ) ) {
			$data = new stdclass;
		}
		$data->DripID = '';
		$data->Drip = new stdclass();
		$data->Drip->tags	= array ();
		return $data;
	}
	add_filter( 'logichop_data_object_create', 'logichop_object_create_drip' );

	/**
	 * Generate default conditions
	 *
	 * @since    1.0.0
	 * @param    array		$conditions		Array of default conditions
	 * @return   array    	$conditions		Array of default conditions
	 */
	function logichop_condition_default_drip ($conditions) {
		global $logichop;

		if ( ! isset( $logichop->logic->convertkit ) ) {
			return array();
		}

		if ($logichop->logic->drip->active()) {
			$conditions['drip'] = array (
					'title' => "Drip Data Is Available for User",
					'rule'	=> '{"==": [ {"var": "Drip.email" }, true ] }',
					'info'	=> "Is Drip data available for the current user."
				);
		}
		return $conditions;
	}
	add_filter('logichop_condition_default_get', 'logichop_condition_default_drip');

	/**
	 * Generate client meta data
	 *
	 * @since    1.0.0
	 * @param    array		$integrations	Integration names
	 * @return   array    	$integrations	Integration names
	 */
	function logichop_client_meta_drip ($integrations) {
		$integrations[] = 'drip';
		return $integrations;
	}
	add_filter('logichop_client_meta_integrations', 'logichop_client_meta_drip');

	/**
	 * Add settings
	 *
	 * @since    1.0.0
	 * @param    array		$settings	Settings parameters
	 * @return   array    	$settings	Settings parameters
	 */
	function logichop_settings_register_drip ($settings) {

		$settings['drip_account_id'] = array (
								'name' 	=> __('Drip Account ID', 'logichop'),
								'meta' 	=> __('Enables Drip integration. <a href="https://logichop.com/docs/using-logic-hop-with-drip/" target="_blank">Learn More</a>.', 'logichop'),
								'type' 	=> 'text',
								'label' => '',
								'opts'  => null
							);
		$settings['drip_api_token'] = array (
								'name' 	=> __('Drip API Token', 'logichop'),
								'meta' 	=> __('Enables Drip integration. <a href="https://logichop.com/docs/using-logic-hop-with-drip/" target="_blank">Learn More</a>.', 'logichop'),
								'type' 	=> 'text',
								'label' => '',
								'opts'  => null
							);

		return $settings;
	}
	add_filter('logichop_settings_register', 'logichop_settings_register_drip');

	/**
	 * Validate settings
	 *
	 * @since    1.0.0
	 * @param    string		$key		Settings key
	 * @return   string    	$result		Error object
	 */
	function logichop_settings_validate_drip ($validation, $key, $input) {
		global $logichop;

		if ( $key == 'drip_api_token' || $key == 'drip_account_id' )	{

			$drip_api_token = get_transient( 'logichop_drip_api_token' );

			if ( $key == 'drip_api_token' ) {
				if ( ! $drip_api_token ) {
					$drip_api_token = $input[$key];
					set_transient( 'logichop_drip_api_token', $drip_api_token, HOUR_IN_SECONDS );
				}
			}

			$drip_account_id = get_transient( 'logichop_drip_account_id' );

			if ( $key == 'drip_account_id' ) {
				if ( ! $drip_account_id ) {
					$drip_account_id = $input[$key];
					set_transient( 'logichop_drip_account_id', $drip_account_id, HOUR_IN_SECONDS );
				}
			}

			if ($drip_api_token != '' && $drip_account_id != '') {
				if ($logichop->logic->drip->fields_get($drip_api_token, $drip_account_id) === false) {
					$validation->error = true;
					$validation->error_msg = ($key == 'drip_api_token') ? '<li>Invalid Drip API Token</li>' : '<li>Invalid Drip Account ID</li>';
				}
			}
		}
		return $validation;
	}
	add_filter('logichop_settings_validate', 'logichop_settings_validate_drip', 10, 3);

	/**
	 * Generate editor modal nav
	 *
	 * @since    1.0.0
	 * @param    string		$tab_navigation	Navigation tabs
	 * @return   string    	Navigation tab
	 *
	 * DEPRECATED 3.0.0
	 *
	 */
	function logichop_editor_nav_drip ($tab_navigation) {
		return $tab_navigation . '<a href="#" class="nav-tab" data-tab="logichop-modal-drip">Drip</a>';
	}
	//add_filter('logichop_editor_modal_nav', 'logichop_editor_nav_drip');

	/**
	 * Generate editor modal panel
	 *
	 * @since    1.0.0
	 * @param    string		$tab_panel	Modal panel
	 * @return   string    	Modal panel
	 *
	 * DEPRECATED 3.0.0
	 *
	 */
	function logichop_editor_panel_drip ($tab_panel) {
		global $logichop;

		$panel = '';
		if ($logichop->logic->drip->active()) {
			$drip_vars = $logichop->logic->drip->shortcode_variables();
			$panel = sprintf('<div class="nav-tab-display logichop-modal-drip">
									<h4>%s</h4>
									<select id="logichop_drip_var">
										<option value="">%s</option>
										%s
									</select>

									<p>
										<button class="button button-primary logichop_insert_data_shortcode" data-input="#logichop_drip_var">%s</button>
									</p>
									<hr>

									<h4>%s</h4>
									<select id="logichop_drip_js">
										<option value="">%s</option>
										%s
									</select>

									<h4>%s</h4>
									<select id="logichop_drip_js_event">
										<option value="show">Show</option>
										<option value="fadeIn">Fade In</option>
										<option value="slideDown">Slide Down</option>
									</select>

									<p>
										<button class="button button-primary logichop_insert_data_javascript" data-input="#logichop_drip_js">%s</button>
									</p>
								</div>',
					__('Drip Variable Display Shortcode', 'logichop'),
					__('Select a variable', 'logichop'),
					$drip_vars,
					__('Insert Shortcode', 'logichop'),

					__('Drip Variable Display Javascript', 'logichop'),
					__('Select a variable', 'logichop'),
					$drip_vars,
					__('Event', 'logichop'),
					__('Insert Variable Javascript ', 'logichop')
				);
		}

		return $tab_panel . $panel;
	}
	//add_filter('logichop_editor_modal_panel', 'logichop_editor_panel_drip');

	/**
	 * Add variables to editor
	 *
	 * @since    2.0.0
	 * @return   string    	Variables as datalist options
	 */
	function logichop_editor_drip_variables ( $datalist ) {
		global $logichop;

		return $datalist . $logichop->logic->drip->shortcode_variables();
	}
	add_filter('logichop_editor_shortcode_variables', 'logichop_editor_drip_variables');

	/**
	 * Add goal metabox
	 *
	 * @since    1.0.0
	 */
	function logichop_configure_metabox_drip () {
		global $logichop;

		add_meta_box(
				'logichop_goal_drip_tag',
				__('Drip', 'logichop'),
				array($logichop->logic->drip, 'goal_tag_display'),
				array('logichop-goals'),
				'normal',
				'low'
			);
	}
	add_action('logichop_configure_metaboxes', 'logichop_configure_metabox_drip');

	/**
	 * Save event data
	 *
	 * @since    1.0.0
	 * @param    integer	$post_id	WP post ID
	 */
	function logichop_event_save_drip ($post_id) {
		if (isset($_POST['logichop_goal_drip_tag'])) 		update_post_meta($post_id, 'logichop_goal_drip_tag', wp_kses($_POST['logichop_goal_drip_tag'],''));
		if (isset($_POST['logichop_goal_drip_tag_action'])) update_post_meta($post_id, 'logichop_goal_drip_tag_action', wp_kses($_POST['logichop_goal_drip_tag_action'],''));
		if (isset($_POST['logichop_goal_drip_add_event'])) 	update_post_meta($post_id, 'logichop_goal_drip_add_event', wp_kses($_POST['logichop_goal_drip_add_event'],''));
		if (isset($_POST['logichop_goal_drip_event'])) 		update_post_meta($post_id, 'logichop_goal_drip_event', wp_kses($_POST['logichop_goal_drip_event'],''));

		if (isset($_POST['logichop_goal_drip_custom_field'])) 	update_post_meta($post_id, 'logichop_goal_drip_custom_field', wp_kses($_POST['logichop_goal_drip_custom_field'],''));
		if (isset($_POST['logichop_goal_drip_custom_value'])) 	update_post_meta($post_id, 'logichop_goal_drip_custom_value', wp_kses($_POST['logichop_goal_drip_custom_value'],''));
		if (isset($_POST['logichop_goal_drip_custom_type'])) 	update_post_meta($post_id, 'logichop_goal_drip_custom_type', wp_kses($_POST['logichop_goal_drip_custom_type'],''));
	}
	add_action('logichop_event_save', 'logichop_event_save_drip');

	/**
	 * Output Javscript variables
	 *
	 * @since    1.0.0
	 * @return   string    Javscript variables
	 */
	function logichop_condition_builder_vars_drip ($condition_vars) {
		global $logichop;

		$drip_tags		= $logichop->logic->drip->tags_get_json();
		$drip_fields	= $logichop->logic->drip->fields_get_json();

		return sprintf('%s var logichop_drip_tags = %s; var logichop_drip_fields = %s;', $condition_vars, $drip_tags, $drip_fields);
	}
	add_filter('logichop_condition_builder_vars', 'logichop_condition_builder_vars_drip');

	/**
	 * Enqueue styles
	 *
	 * @since    1.0.0
	 */
	function logichop_admin_enqueue_styles_drip ($hook) {	// ADD CLEAR BUTTON
		global $logichop;

		if (in_array($hook, array('post.php', 'post-new.php'))) {
			$css_path = sprintf('%sadmin/logichop_drip.css', plugin_dir_url( __FILE__ ));
			wp_enqueue_style( 'logichop_drip', $css_path, array(), $logichop->logic->drip->version, 'all' );
		}
	}
	add_action('logichop_admin_enqueue_styles', 'logichop_admin_enqueue_styles_drip');

	/**
	 * Enqueue scripts
	 *
	 * @since    1.0.0
	 */
	function logichop_admin_enqueue_scripts_drip ($hook, $post_type) {
		global $logichop;

		if ($post_type == 'logichop-conditions') {
			$js_path = sprintf('%sadmin/logichop_drip.js', plugin_dir_url( __FILE__ ));

			$js_params = array(
						'tags' 		=> json_decode($logichop->logic->drip->tags_get_json()),
						'fields'	=> json_decode($logichop->logic->drip->fields_get_json())
					);

 			wp_enqueue_script('logichop_drip', $js_path, array( 'jquery' ), $logichop->logic->drip->version, false);
 			wp_localize_script('logichop_drip', 'logichop_drip', $js_params);
		}

		if ($post_type == 'logichop-goals') {
			$js_path = sprintf('%sadmin/logichop_drip_goals.js', plugin_dir_url( __FILE__ ));
			wp_enqueue_script( 'logichop_drip', $js_path, array( 'jquery' ), $logichop->logic->drip->version, false );
		}
	}
	add_action('logichop_admin_enqueue_scripts', 'logichop_admin_enqueue_scripts_drip', 10, 2);

	/**
	 * Add admin menu
	 *
	 * @since    1.0.0
	 */
	function logichop_admin_menu_page_drip () {
		add_submenu_page(
			'logichop-menu',
			'Drip',
			'Drip',
			'manage_options',
			get_admin_url( null, 'admin.php?page=logichop-settings&tab=drip' ),
			''
		);
	}
	add_action('logichop_admin_menu_pages', 'logichop_admin_menu_page_drip');

	/**
	 * Add tab navigation to settings page
	 *
	 * @param    string		$tabs	Tab HTML
	 * @param    string		$active	Active tab
	 * @return   string    	$tabs	Tab HTML
	 * @since    1.0.0
	 */
	function logichop_admin_settings_tab_drip ($tabs, $active) {
		return sprintf('%s <a href="%s" class="nav-tab %s">Drip</a>',
							$tabs,
							get_admin_url( null, '?admin.phppage=logichop-settings' ),
							($active == 'drip') ? 'nav-tab-active' : ''
						);
	}
	add_action('logichop_admin_settings_tabs', 'logichop_admin_settings_tab_drip', 10, 2);

	/**
	 * Include settings page when tab is active
	 *
	 * @param    string		$active	Active tab
	 * @since    1.0.0
	 */
	function logichop_admin_settings_page_drip ($active) {
		if ($active == 'drip') include_once('admin/settings.php');
	}
	add_action('logichop_admin_settings_page', 'logichop_admin_settings_page_drip');

	/**
	 * Enqueue public scripts
	 *
	 * @since    2.0.0
	 */
	function logichop_enqueue_scripts_drip ($hook, $post_type) {
		global $logichop;

		$js_path = sprintf('%spublic/logichop_drip.js', plugin_dir_url( __FILE__ ));
		wp_enqueue_script( 'logichop_drip', $js_path, array( 'jquery' ), $logichop->logic->drip->version, false );
	}
	add_action('logichop_public_enqueue_scripts', 'logichop_enqueue_scripts_drip', 10, 2);

	/**
	 * Register shortcodes
	 *
	 * @param    object		$public		Public class
	 * @since    1.0.0
	 */
	function logichop_register_shortcodes_drip ($public) {
		add_shortcode( 'logichop_data_drip', array($public, 'shortcode_logichop_data_display') );
	}
	add_action('logichop_register_shortcodes', 'logichop_register_shortcodes_drip', 10, 1);

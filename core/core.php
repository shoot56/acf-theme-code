<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class ACFTC_Core {

	public static $plugin_path = '';
	public static $plugin_url = '';
	public static $plugin_basename = '';
	public static $plugin_version = '';
	public static $db_table = '';
	public static $indent_repeater = 2;
	public static $indent_flexible_content = 3;
	public static $basic_types = array(
		'text',
		'textarea',
		'number',
		'email',
		'url',
		'color_picker',
		'wysiwyg',
		'oembed',
		'radio'
	);

	// Field types supported by TC Pro
	public static $tc_pro_field_types = array(
		'flexible_content',
		'repeater',
		'gallery',
		'clone',
		'font-awesome',
		'google_font_selector',
		'rgba_color',
		'image_crop',
		'markdown',
		'nav_menu',
		'smart_button',
		'sidebar_selector',
		'tablepress_field',
		'table'
	);

	/**
	 * ACFTC_Core constructor
	 */
	public function __construct( $plugin_path, $plugin_url, $plugin_basename, $plugin_version ) {

		// Paths and URLs
		self::$plugin_path = $plugin_path;
		self::$plugin_url = $plugin_url;
		self::$plugin_basename = $plugin_basename;
		self::$plugin_version = $plugin_version;

		// Hooks
		add_action( 'admin_init', array($this, 'acf_theme_code_pro_check') );
		add_action( 'admin_init', array($this, 'set_db_table') );
		add_action( 'add_meta_boxes', array($this, 'register_meta_boxes') );
		add_action( 'admin_enqueue_scripts', array($this, 'enqueue') );

	}

	/**
	 * Check if ACF Theme Code Pro is activated
	 */
	public function acf_theme_code_pro_check() {

		// If Theme Code Pro is activated, disable Theme Code (free) and display notice
		if ( is_plugin_active( 'acf-theme-code-pro/acf_theme_code_pro.php' ) ) {
			deactivate_plugins( self::$plugin_basename );
			add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}

	}

	/**
	 * ACF Theme Code (free) disabled notice
	 */
	public function disabled_notice() {
		echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Plugin <strong>Advanced Custom Fields: Theme Code Pro</strong> is activated so plugin <strong>Advanced Custom Fields: Theme Code</strong> has been disabled.</p>';
		echo '</div>';
	}


	/**
	 * Check if ACF plugin is free or pro version
	 */
	public function set_db_table() {

		/**
		 * If fields are created with ACF and then ACF PRO is installed and
		 * activated, the ACF fields are moved to the ACF PRO database structure.
		 * If ACF PRO is then deactivated and ACF is reactivated the fields
		 * won't appear in the admin.
		 *
		 * If fields are created with ACF PRO and then ACF is activated
		 * as well, only the fields created in ACF PRO will be visible
		 * in the the admin.
		 *
		 * If fields are created with ACF after activating and
		 * deactivating ACF PRO, they will only appear while ACF is
		 * activated and ACF PRO is deactivated. ACF PRO doesn't appear to
		 * convert fields to the new database structure more than once.
		 *
		 * Hence the following logic order is used:
		 */

		if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) || // ACF Pro
			 is_plugin_active( 'advanced-custom-fields-pro-beta/acf.php') || // ACF Pro Beta
			 is_plugin_active( 'acf-pro-master/acf.php' ) ) { // ACF Pro Beta alt
			 self::$db_table = 'posts';
		} elseif  ( is_plugin_active('advanced-custom-fields/acf.php' ) ) {
			self::$db_table = 'postmeta';
		}

	}


	/**
	 * Register meta box
	 */
	public function register_meta_boxes() {

		add_meta_box(
			'acftc-meta-box',
			__( 'Theme Code', 'textdomain' ),
			array( $this, 'display_callback'),
			array( 'acf', 'acf-field-group' ) // same meta box used for ACF and ACF PRO
		);

	}


	/**
	 * Meta box display callback
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function display_callback( $post ) {

		if ( self::$db_table == 'postmeta') {

			// Quick check for locations

			// setup the location rules
			$location_rules = [];

			global $wpdb;

			// Prepend table prefix
			$table = $wpdb->prefix . 'postmeta';

			// Query postmeta table for location rules associated with this field group
			$query_results = $wpdb->get_results( "SELECT * FROM " . $table . " WHERE post_id = " . $post->ID . " AND meta_key LIKE 'rule'" );

			foreach ( $query_results as $query_result ) {

				// Unserialize location rule data
				$location_rule = unserialize( $query_result->meta_value );

				// add the location to the array
				$location_rules[] = $location_rule;

			}

			// count the location rules
			$location_rules_count = count( $location_rules );

			// if we have more than 1 location show the notice
			if( $location_rules_count >= 2 ) {
				echo '<div class="acftc-pro-notice acftc-pro-notice--top"><a class="acftc-pro-notice__link" href="https://hookturn.io/downloads/acf-theme-code-pro/?utm_source=acftclocation" target="_blank">Upgrade to <strong>ACF Theme Code Pro</strong> to generate code for each of your location rules.</a></div>';
			}

			// render the field group
			$parent_field_group = new ACFTC_Group( $post->ID );


		} elseif ( self::$db_table == 'posts') {

			// get an array of the locations
			$field_group_location_array = $this->get_field_group_locations( $post );

			// count them
			$field_group_location_array_count = count( $field_group_location_array );

			// if we have more than 1 location show the notice
			if( $field_group_location_array_count >= 2 ) {
				echo '<div class="acftc-pro-notice acftc-pro-notice--top"><a class="acftc-pro-notice__link" href="https://hookturn.io/downloads/acf-theme-code-pro/?utm_source=acftclocation" target="_blank">Upgrade to <strong>ACF Theme Code Pro</strong> to generate code for each of your location rules.</a></div>';
			}

			// render the field group
			$parent_field_group = new ACFTC_Group( $post->ID, 0 , 0 , '');

		}

		if ( !empty( $parent_field_group->fields ) ) {

			$parent_field_group->render_field_group();

			// Upgrade to TC Pro notice
			echo '<div class="acftc-pro-notice"><a class="acftc-pro-notice__link" href="https://hookturn.io/downloads/acf-theme-code-pro/?utm_source=acftcfree" target="_blank">Upgrade to <strong>ACF Theme Code Pro</strong>.</a></div>';

		} else {

			echo '<div class="acftc-intro-notice"><p>Create some fields and publish the field group to generate theme code.</p></div>';

		}

	}


	/**
	 * Get field group locations
	 *
	 * @param WP_Post $post Current post object.
	 */

	private function get_field_group_locations( $post ) {

		// define a locations array
		$location_array = [];

		// get field group locations from field group post content
		if ( $post->post_content ) {

			$field_group_location_content = unserialize( $post->post_content );

			foreach ( $field_group_location_content['location']  as $location_condition_group ) {


				foreach ( $location_condition_group as $location_condition ) {

					$location_array[] = $location_condition['param'];

				}

			}

			return $location_array;

		}
	}


	// load scripts and styles
	public function enqueue( $hook ) {

		// grab the post type
		global $post_type;

		// if post type is an ACF field group
		if( 'acf-field-group' == $post_type || 'acf' == $post_type ) {

			// Plugin styles
			wp_enqueue_style( 'acftc_css', self::$plugin_url . 'assets/acf-theme-code.css', '' , self::$plugin_version);

			// Prism (code formatting)
			wp_enqueue_style( 'acftc_prism_css', self::$plugin_url . 'assets/prism.css', '' , self::$plugin_version);
			wp_enqueue_script( 'acftc_prism_js', self::$plugin_url . 'assets/prism.js', '' , self::$plugin_version);

			// Clipboard
			wp_enqueue_script( 'acftc_clipboard_js', self::$plugin_url . 'assets/clipboard.js', '' , self::$plugin_version);

			// Plugin js
			wp_enqueue_script( 'acftc_js', self::$plugin_url . 'assets/acf-theme-code.js', array( 'acftc_clipboard_js' ), '', self::$plugin_version );

		}

	}

}

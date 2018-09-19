<?php
/**
 * Plugin Name:       Gravity Forms Facebook Pixel Tracking
 * Plugin URI:        https://wordpress.org/plugins/gf-facebook-pixel-tracking/
 * Description:       This plugin provides an easy way to add Facebook event tracking to your Gravity Forms using Facebook’s Tracking Pixel.
 * Version:           1.0.0
 * Author:            Hiilite
 * Author URI:        https://hiilite.com
 * Text Domain:       gf-facebook-pixel-tracking
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * Developer Credit: pvigilante
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class GFFBPT {

	/**
	 * Holds the class instance.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Retrieve a class instance.
	 *
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	} //end get_instance

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		load_plugin_textdomain( 'gf-facebook-pixel-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		spl_autoload_register( array( $this, 'loader' ) );

		add_action( 'gform_loaded', array( $this, 'gforms_loaded' ) );
	}

	/**
	 * Check for the minimum supported PHP version.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if meets minimum version, false if not
	 */
	public static function check_php_version() {
		if( ! version_compare( '5.3', PHP_VERSION, '<=' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check the plugin to make sure it meets the minimum requirements.
	 *
	 * @since 1.0.0
	 */
	public static function check_plugin() {
		if( ! GFFBPT::check_php_version() ) {
			deactivate_plugins( GFFBPT::get_plugin_basename() );
			exit( sprintf( esc_html__( 'Gravity Forms Facebook Pixel Tracking requires PHP version 5.3 and up. You are currently running PHP version %s.', 'gf-facebook-pixel-tracking' ), esc_html( PHP_VERSION ) ) );
		}
	}

	/**
	 * Retrieve the plugin basename.
	 *
	 * @since 1.0.0
	 *
	 * @return string plugin basename
	 */
	public static function get_plugin_basename() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Return the absolute path to an asset.
	 *
	 * @since 1.0.0
	 *
	 * @param string @path Relative path to the asset.
	 *
	 * return string Absolute path to the relative asset.
	 */
	public static function get_plugin_dir( $path = '' ) {
		$dir = rtrim( plugin_dir_path(__FILE__), '/' );
		if ( !empty( $path ) && is_string( $path) )
			$dir .= '/' . ltrim( $path, '/' );
		return $dir;
	}

	/**
	 * Initialize Gravity Forms related add-ons.
	 *
	 * @since 1.0.0
	 */
	public function gforms_loaded() {
		if ( ! GFFBPT::check_php_version() ) return;
		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		// Initialize settings screen and feeds
		GFAddOn::register( 'GFFBPT_FBP' );
		GFAddOn::register( 'GFFBPT_Submission_Feeds' );

		// Initialize pagination
		add_action( 'gform_post_paging', array( $this, 'pagination'), 10, 3 );
	}

	/**
	 * Get the Facebook Pixel Code
	 *
	 * @since 1.0.0
	 * @return string/bool Returns string UA code, false otherwise
	 */
	 // TODO: RegEX the facebook pixel to check for just numeric
	public static function get_fbp_code() {
		$gravity_forms_add_on_settings = get_option( 'gravityformsaddon_GFFBPT_FBP_settings', array() );
		$fbp_id = isset( $gravity_forms_add_on_settings[ 'gravity_forms_pixel_tracking_fbp' ] ) ? $gravity_forms_add_on_settings[ 'gravity_forms_pixel_tracking_fbp' ] : false;

		return $fbp_id;
	
	}

	/**
	 * Autoload class files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class_name The class name
	 */
	public function loader( $class_name ) {
		if ( class_exists( $class_name, false ) || false === strpos( $class_name, 'GFFBPT' ) ) {
			return;
		}
		$file = GFFBPT::get_plugin_dir( "includes/{$class_name}.php" );
		if ( file_exists( $file ) ) {
			include_once( $file );
		}
	}


	/**
	 * Initialize the pagination events.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form                The form arguments
	 * @param int   @source_page_number  The original page number
	 * @param int   $current_page_number The new page number
	 */
	public function pagination( $form, $source_page_number, $current_page_number ) {
		$pagination = GFFBPT_Pagination::get_instance();
		$pagination->paginate( $form, $source_page_number, $current_page_number );
	}
}

register_activation_hook( __FILE__, array( 'GFFBPT', 'check_plugin' ) );
GFFBPT::get_instance();

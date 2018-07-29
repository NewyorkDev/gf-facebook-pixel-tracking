<?php
GFForms::include_addon_framework();
class GFFBPT_FBP extends GFAddOn {
	protected $_version = '2.0';
	protected $_min_gravityforms_version = '1.8.20';
	protected $_slug = 'GFFBPT_FBP';
	protected $_path = 'gravity-forms-facebook-pixel-tracking/gravity-forms-facebook-tracking.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Facebook Pixel Tracking';
	protected $_short_title = 'FB Pixel Tracking';
	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_pixel_tracking', 'gravityforms_pixel_tracking_uninstall' );
	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_pixel_tracking';
	protected $_capabilities_form_settings = 'gravityforms_pixel_tracking';
	protected $_capabilities_uninstall = 'gravityforms_pixel_tracking_uninstall';

	private static $_instance = null;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @return object $_instance An instance of this class.
	 */
	public static function get_instance() {
	    if ( self::$_instance == null ) {
	        self::$_instance = new self();
	    }

	    return self::$_instance;
	}

	public function init() {
		parent::init();

		// Migrate old GA Code over to new add-on
		$fbp_options = get_option( 'gravityformsaddon_GFFBPT_FBP_settings', false );
		if ( ! $fbp_options ) {
			$old_fpb_option = get_option( 'gravityformsaddon_gravity-forms-pixel-tracking_settings', false );
			if ( $old_fpb_option ) {
				update_option( 'gravityformsaddon_GFFBPT_FBP_settings', $old_fpb_option );
			}
		}

	}

	/**
	 * Plugin settings fields
	 *
	 * @return array Array of plugin settings
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title' => __( 'Facebook Pixel Tracking', 'gravity-forms-facebook-pixel-tracking' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'              => 'gravity_forms_pixel_tracking_fbp',
						'tooltip' 			=> __( '', 'gravity-forms-facebook-pixel-tracking' ),
						'label'             => __( 'Facebook Pixel ID', 'gravity-forms-facebook-pixel-tracking' ),
						'type'              => 'text',
						'class'             => 'small',

					),
				),
			),
		);
	}
}

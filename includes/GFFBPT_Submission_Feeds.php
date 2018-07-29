<?php
/**
 * Gravity forms facebook pixel tracking
 *
 * @package   Gravity_Forms_Facebook_Pixel_Tracking
 * @author    Peter Vigilante <peter@hiilite.com>
 * @license   GPL-2.0+
 * @link      https://hiilite.com
 * @copyright 2018 Peter Singh-Vigilante
 */

GFForms::include_feed_addon_framework();

class GFFBPT_Submission_Feeds extends GFFeedAddOn {

	protected $_version = "1.0.0";
	protected $_min_gravityforms_version = "1.8.20";
	protected $_slug = "gravity-forms-pixel-tracking";
	protected $_path = "gravity-forms-facebook-pixel-tracking/gravity-forms-pixel-tracking.php";
	protected $_full_path = __FILE__;
	protected $_title = "Gravity Forms Facebook Pixel Tracking";
	protected $_short_title = "FB Pixel Tracking";

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_pixel_tracking', 'gravityforms_pixel_tracking_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_pixel_tracking';
	protected $_capabilities_form_settings = 'gravityforms_pixel_tracking';
	protected $_capabilities_uninstall = 'gravityforms_pixel_tracking_uninstall';

	public $fbp_id = false;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Overriding init function to change the load_plugin_textdomain call.
	 * See comment above for explanation.
	 */
	public function init() {
		parent::init();
	}

	/**
	 * Public facing init
	 *
	 * @since 1.0.0
	 */
	public function init_frontend() {

		parent::init_frontend();

		// IPN hook for paypal standard!
		if ( class_exists( 'GFPayPal' ) ) {
			add_action( 'gform_paypal_post_ipn', array( $this, 'paypal_track_form_post_ipn' ), 10, 2 );
		}

	}

	/**
	 * Process the feed!
	 * @param  array $feed  feed data and settings
	 * @param  array $entry gf entry object
	 * @param  array $form  gf form data
	 */
	public function process_feed( $feed, $entry, $form ) {

		$paypal_feeds = $this->get_feeds_by_slug( 'gravityformspaypal', $form['id'] );
		$has_paypal_feed = false;

		foreach ( $paypal_feeds as $paypal_feed ){
			if ( $paypal_feed['is_active'] && $this->is_feed_condition_met( $paypal_feed, $form, $entry ) ){
				$has_paypal_feed = true;
				break;
			}
		}

		$fb_event_data = $this->get_event_data( $feed, $entry, $form );

		if ( $has_paypal_feed ) {
			gform_update_meta( $entry['id'], 'fb_event_data', maybe_serialize( $fb_event_data ) );
		}
		else {
			$this->track_form_after_submission( $entry, $form, $fb_event_data );
		}
	}

	/**
	 * Load FBP Settings
	 *
	 * @since 1.0.0
	 * @return bool Returns true if UA ID is loaded, false otherwise
	 */
	private function load_fbp_settings() {

		$this->fbp_id = $fbp_id = GFFBPT::get_fbp_code();

		if ( false !== $this->fbp_id ) {
			return true;
		}
		return false;
	}

	/**
	 * Load UA Settings
	 *
	 * @since 1.0.0
	 * @return bool Returns true if UA ID is loaded, false otherwise
	 */
	 private function get_fbp_id() {
        $this->load_fbp_settings();
        if ( $this->fbp_id == false ) {
            return '';
        } else {
            return $this->fbp_id;
        }
     }

	/**
	 * Get data required for processing
	 * @param  array $feed  feed
	 * @param  array $entry GF Entry object
	 * @param  array $form  GF Form object
	 */
	private function get_event_data( $feed, $entry, $form ) {
		global $post;

		// Paypal will need this cookie for the IPN
		$fbp_cookie = isset( $_COOKIE['_fbp'] ) ? $_COOKIE['_fbp'] : '';

		// Location
		$document_location = str_replace( home_url(), '', $entry[ 'source_url' ] );

		// Title
		$document_title = isset( $post ) && get_the_title( $post ) ? get_the_title( $post ) : 'no title';

		// Store everything we need for later
		$fb_event_data = array(
			'feed_id' => $feed['id'],
			'entry_id' => $entry['id'],
			'fbp_cookie' => $fbp_cookie,
			'document_location' => $document_location,
			'document_title' => $document_title,
			'fbEventID' => $this->get_event_var( 'fbEventID', $feed, $entry, $form ),
			'fbEventContentCategory' => $this->get_event_var( 'fbEventContentCategory', $feed, $entry, $form ),
			'fbEventContentName' => $this->get_event_var( 'fbEventContentName', $feed, $entry, $form ),
			'fbEventCurrency' => $this->get_event_var( 'fbEventCurrency', $feed, $entry, $form ),
			'fbEventValue' => $this->get_event_var( 'fbEventValue', $feed, $entry, $form ),
		);

		return $fb_event_data;
	}

	/**
	 * Get our event vars
	 */
	private function get_event_var( $var, $feed, $entry, $form ) {

		if ( isset( $feed['meta'][ $var ] ) && ! empty( $feed['meta'][ $var ] ) ) {
			return $feed['meta'][ $var ];
		}
		else {
			switch ( $var ) {
				case 'fbEventContentCategory':
					return 'Forms';

				case 'fbEventContentName':
					return 'Submission';

				case 'fbEventCurrency':
					return 'CAD';

				case 'fbEventValue':
					return false;

				default:
					return false;
			}
		}

	}

	/**
	 * Handle the form after submission before sending to the event push
	 *
	 * @since 1.4.0
	 * @param array $entry Gravity Forms entry object
	 * @param array $form Gravity Forms form object
	 */
	private function track_form_after_submission( $entry, $form, $fb_event_data ) {

		// Try to get payment amount
		// This needs to go here in case something changes with the amount
		if ( ! $fb_event_data['fbEventValue'] ) {
			$fb_event_data['fbEventValue'] = $this->get_event_value( $entry, $form );
		}

		$event_vars = array( 'fbEventID', 'fbEventContentCategory', 'fbEventContentName', 'fbEventCurrency', 'fbEventValue' );

		foreach ( $event_vars as $var ) {
			if ( $fb_event_data[ $var ] ) {
				$fb_event_data[ $var ] = GFCommon::replace_variables( $fb_event_data[ $var ], $form, $entry, false, false, true, 'text' );
			}
		}


		// Push the event to google
		$this->push_event( $entry, $form, $fb_event_data );
	}

	/**
	 * Handle the IPN response for pushing the event
	 *
	 * @since 1.4.0
	 * @param array $post_object global post array from the IPN
	 * @param array $entry Gravity Forms entry object
	 */
	public function paypal_track_form_post_ipn( $post_object, $entry ) {
		// Check if the payment was completed before continuing
		if ( strtolower( $entry['payment_status'] ) != 'paid' ) {
			return;
		}

		$form = GFFormsModel::get_form_meta( $entry['form_id'] );

		$fb_event_data = maybe_unserialize( gform_get_meta( $entry['id'], 'fb_event_data' ) );

		// Override this coming from paypal IPN
		$_COOKIE['_fbp'] = $fb_event_data['fbp_cookie'];

		// Push the event to google
		$this->push_event( $entry, $form, $fb_event_data );
	}

	/**
	 * Push the Facebook Pixel Event!
	 *
	 * @since 1.4.0
	 * @param array $event Gravity Forms event object
	 * @param array $form Gravity Forms form object
	 */
	private function push_event( $entry, $form, $fb_event_data ) {

		//Get all analytics codes to send
		$facebook_pixels = $this->get_fbp_codes( $fb_event_data[ 'fbEventID' ], $this->get_fbp_id() );

		/**
		* Filter: gform_fbp_ids
		*
		* Filter all outgoing UA IDs to send events to
		*
		* @since 1.6.5
		*
		* @param array  $facebook_pixels UA codes
		* @param object $form Gravity Form form object
		* @param object $entry Gravity Form Entry Object
		*/
		$facebook_pixels = apply_filters( 'gform_fbp_ids', $facebook_pixels, $form, $entry );

        if ( !is_array( $facebook_pixels ) || empty( $facebook_pixels ) ) return;

		$event = new GFFBPT_Measurement_Protocol();
		$event->init();

		// Set some defaults
		$event->set_document_path( str_replace( home_url(), '', $entry[ 'source_url' ] ) );
		$event_url_parsed = parse_url( home_url() );
		$event->set_document_host( $event_url_parsed[ 'host' ] );
		$event->set_document_location( str_replace( '//', '/', 'http' . ( isset( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . '/' . $_SERVER['REQUEST_URI'] ) );
		$event->set_document_title( $fb_event_data['document_title'] );

		// Set our event object variables
		/**
		 * Filter: gform_event_category
		 *
		 * Filter the event category dynamically
		 *
		 * @since 1.6.5
		 *
		 * @param string $category Event Category
		 * @param object $form     Gravity Form form object
		 * @param object $entry    Gravity Form Entry Object
		 */
		$event_category = apply_filters( 'gform_event_category', $fb_event_data['fbEventContentCategory'], $form, $entry );
		$event->set_event_category( $event_category );

		/**
		 * Filter: gform_event_name
		 *
		 * Filter the event action dynamically
		 *
		 * @since 1.6.5
		 *
		 * @param string $action Event Action
		 * @param object $form   Gravity Form form object
		 * @param object $entry  Gravity Form Entry Object
		 */
		$event_name = apply_filters( 'gform_event_name', $fb_event_data['fbEventContentName'], $form, $entry );
		$event->set_event_name( $event_name );

		/**
		 * Filter: gform_event_currency
		 *
		 * Filter the event label dynamically
		 *
		 * @since 1.6.5
		 *
		 * @param string $label Event Label
		 * @param object $form  Gravity Form form object
		 * @param object $entry Gravity Form Entry Object
		 */
		$event_currency = apply_filters( 'gform_event_currency', $fb_event_data['fbEventCurrency'], $form, $entry );
		$event->set_event_currency( $event_currency );

		/**
		 * Filter: gform_event_value
		 *
		 * Filter the event value dynamically
		 *
		 * @since 1.6.5
		 *
		 * @param object $form Gravity Form form object
		 * @param object $entry Gravity Form Entry Object
		 */
		$event_value = apply_filters( 'gform_event_value', $fb_event_data['fbEventValue'], $form, $entry );
		if ( $event_value ) {
			// Event value must be a valid float!
			$event_value = GFCommon::to_number( $event_value );
			$event->set_event_value( $event_value );
		}

		$feed_id = absint( $fb_event_data[ 'feed_id' ] );
		$entry_id = $entry['id'];

		$count = 1;
		?>
		<script>
		<?php
		foreach( $facebook_pixels as $fbp_code ) {
			?>
			!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
			n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
			n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
			t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
			document,'script','https://connect.facebook.net/en_US/fbevents.js');
			// Insert Your Facebook Pixel ID below. 
			fbq('init', '<?php echo esc_js( $fbp_code ); ?>');
			
			fbq('track', 'Lead', {
			  content_name: '<?php echo esc_js( $event_name ); ?>',
			  content_category: '<?php echo esc_js( $event_category ); ?>',
			  value: <?php echo esc_js( $event_value ); ?>,
			  currency: '<?php echo esc_js( $event_currency ); ?>'
			});

			<?php
			$count += 1;
		}
		?>
		</script>
		<?php
		return;
	

		//Push out the event to each UA code
		foreach( $facebook_pixels as $fbp_code ) {
			// Submit the event
			$event->send( $fbp_code );
		}
	}


	/**
	 * Get the event value for payment entries
	 *
	 * @since 1.4.0
	 * @param array $event Gravity Forms event object
	 * @return string/boolean Event value or false if not a payment form
	 */
	private function get_event_value( $entry ) {
		$value = rgar( $entry, 'payment_amount' );

		if ( ! empty( $value ) && intval( $value ) ) {
			return intval( $value );
		}

		return false;
	}

	//---------- Form Settings Pages --------------------------

	/**
	 * Form settings page title
	 *
	 * @since 1.5.0
	 * @return string Form Settings Title
	 */
	public function feed_settings_title() {
		return __( 'Submission Tracking Settings', 'gravity-forms-facebook-pixel-tracking' );
	}

	public function maybe_save_feed_settings( $feed_id, $form_id ) {
		if ( ! rgpost( 'gform-settings-save' ) ) {
			return $feed_id;
		}

		check_admin_referer( $this->_slug . '_save_settings', '_' . $this->_slug . '_save_settings_nonce' );

		if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			GFCommon::add_error_message( esc_html__( "You don't have sufficient permissions to update the form settings.", 'gravityforms' ) );
			return $feed_id;
		}

		// store a copy of the previous settings for cases where action would only happen if value has changed
		$feed = $this->get_feed( $feed_id );
		$this->set_previous_settings( $feed['meta'] );

		$settings = $this->get_posted_settings();
		$sections = $this->get_feed_settings_fields();
		$settings = $this->trim_conditional_logic_vales( $settings, $form_id );

		$is_valid = $this->validate_settings( $sections, $settings );
		$result   = false;

		//Check for a valid Pixel code
		$feed_ua_code = isset( $settings[ 'fbEventID' ] ) ? $settings[ 'fbEventID' ] : '';
		$fbp_codes = $this->get_fbp_codes( $feed_ua_code, $this->get_fbp_id() );

		if ( $is_valid ) {
			$settings = $this->filter_settings( $sections, $settings );
			$feed_id = $this->save_feed_settings( $feed_id, $form_id, $settings );
			if ( $feed_id ) {
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		} else {
			GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
		}

		return $feed_id;
	}

	/**
	 * Return Google Analytics GA Codes
	 *
	 * @since 1.7.0
	 * @return array Array of GA codes
	 */
	private function get_fbp_codes( $feed_ua, $settings_fb ) {
		$facebook_pixels = array();
        if ( !empty( $feed_ua ) ) {
            $fbp_id = explode( ',', $feed_ua );
            if ( is_array( $fbp_id ) ) {
                foreach( $fbp_id as &$value ) {
                    $value = trim( $value );
                }
            }
            $facebook_pixels = $fbp_id;
        }
        if( $settings_fb ) {
            $facebook_pixels[] = $settings_fb;
        }
        $facebook_pixels = array_unique( $facebook_pixels );
        return $facebook_pixels;
	}

	/**
	 * Form settings fields
	 *
	 * @since 1.5.0
	 * @return array Array of form settings
	 */
	public function feed_settings_fields() {
    	$ga_id_placeholder = $this->get_fbp_id();
		return array(
			array(
				"title"  => __( 'Feed Settings', 'gravity-forms-facebook-pixel-tracking' ),
				"fields" => array(
					array(
						'label'    => __( 'Feed Name', 'gravity-forms-facebook-pixel-tracking' ),
						'type'     => 'text',
						'name'     => 'feedName',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . __( 'Feed Name', 'gravity-forms-facebook-pixel-tracking' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravity-forms-facebook-pixel-tracking' )
					)
				),
			),
			array(
				"title"  => __( 'Pixel Tracking Settings', 'gravity-forms-facebook-pixel-tracking' ),
				"fields" => array(
					array(
						"label"   => "",
						"type"    => "instruction_field",
						"name"    => "instructions"
					),
					array(
						"label"   => __( 'Event Pixel ID', 'gravity-forms-facebook-pixel-tracking' ),
						"type"    => "text",
						"name"    => "fbEventID",
						"class"   => "medium",
						"tooltip" => sprintf( '<h6>%s</h6>%s', __( 'Google Analytics UA Code (Optional)', 'gravity-forms-facebook-pixel-tracking' ), __( 'Leave empty to use global GA Code. You can enter multiple UA codes as long as they are comma separated.', 'gravity-forms-facebook-pixel-tracking' ) ),
						"placeholder" => $ga_id_placeholder,
					),
					array(
						"label"   => __( 'Content Category', 'gravity-forms-facebook-pixel-tracking' ),
						"type"    => "text",
						"name"    => "fbEventContentCategory",
						"class"   => "medium merge-tag-support mt-position-right",
						"tooltip" => sprintf( '<h6>%s</h6>%s', __( 'Event Category', 'gravity-forms-facebook-pixel-tracking' ), __( 'Enter your Google Analytics event category', 'gravity-forms-facebook-pixel-tracking' ) ),
					),
					array(
						"label"   => __( 'Content Name', 'gravity-forms-facebook-pixel-tracking' ),
						"type"    => "text",
						"name"    => "fbEventContentName",
						"class"   => "medium merge-tag-support mt-position-right",
						"tooltip" => sprintf( '<h6>%s</h6>%s', __( 'Event Action', 'gravity-forms-facebook-pixel-tracking' ), __( 'Enter your Google Analytics event action', 'gravity-forms-facebook-pixel-tracking' ) ),
					),
					array(
						"label"   => __( 'Currency', 'gravity-forms-facebook-pixel-tracking' ),
						"type"    => "text",
						"name"    => "fbEventCurrency",
						"class"   => "medium merge-tag-support mt-position-right",
						"tooltip" => sprintf( '<h6>%s</h6>%s', __( 'Event Label', 'gravity-forms-facebook-pixel-tracking' ), __( 'Enter your Google Analytics event label', 'gravity-forms-facebook-pixel-tracking' ) ),
						"placeholder" => 'CAD',
					),
					array(
						"label"   => __( 'Value', 'gravity-forms-facebook-pixel-tracking' ),
						"type"    => "text",
						"name"    => "fbEventValue",
						"class"   => "medium merge-tag-support mt-position-right",
						"tooltip" => sprintf( '<h6>%s</h6>%s', __( 'Event Value', 'gravity-forms-facebook-pixel-tracking' ), __( 'Enter your Google Analytics event value. Leave blank to omit pushing a value to Google Analytics. Or to use the purchase value of a payment based form. <strong>Note:</strong> This must be a number (int/float).', 'gravity-forms-facebook-pixel-tracking' ) ),
					),
				)
			),
			array(
				"title"  => __( 'Other Settings', 'gravity-forms-facebook-pixel-tracking' ),
				"fields" => array(
					array(
						'name'    => 'conditionalLogic',
						'label'   => __( 'Conditional Logic', 'gravity-forms-facebook-pixel-tracking' ),
						'type'    => 'feed_condition',
						'tooltip' => '<h6>' . __( 'Conditional Logic', 'gravity-forms-facebook-pixel-tracking' ) . '</h6>' . __( 'When conditions are enabled, events will only be sent to google when the conditions are met. When disabled, all form submissions will trigger an event.', 'gravity-forms-facebook-pixel-tracking' )
					)
				)
			),
		);
	}

	/**
	 * Instruction field
	 *
	 * @since 1.5.0
	 */
	public function single_setting_row_instruction_field(){
		echo '
			<tr>
				<th colspan="2">
					<p>' . __( "If you leave these blank, the following defaults will be used when the event is tracked", 'gravity-forms-facebook-pixel-tracking' ) . ':</p>
					<p>
						<strong>' . __( "Content Category", 'gravity-forms-facebook-pixel-tracking' ) . ':</strong> Forms<br>
						<strong>' . __( "Content Name", 'gravity-forms-facebook-pixel-tracking' ) . ':</strong> Submission<br>
						<strong>' . __( "Currency", 'gravity-forms-facebook-pixel-tracking' ) . ':</strong> CAD<br>
						<strong>' . __( "Value", 'gravity-forms-facebook-pixel-tracking' ) . ':</strong> Payment Amount (on payment forms only, otherwise nothing is sent by default)
					</p>
				</td>
			</tr>';
	}

	/**
	 * Return the feed list columns
	 * @return array columns
	 */
	public function feed_list_columns() {
		return array(
			'feedName'        => __( 'Name', 'gravity-forms-facebook-pixel-tracking' ),
			'fbEventContentCategory' => __( 'Content Category', 'gravity-forms-facebook-pixel-tracking' ),
			'fbEventContentName'   => __( 'Content Name', 'gravity-forms-facebook-pixel-tracking' ),
			'fbEventCurrency'    => __( 'Currency', 'gravity-forms-facebook-pixel-tracking' ),
			'fbEventValue'    => __( 'Value', 'gravity-forms-facebook-pixel-tracking' ),
		);
	}

}

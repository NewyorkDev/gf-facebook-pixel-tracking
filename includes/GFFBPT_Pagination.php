<?php
class GFFBPT_Pagination {
	/**
	 * Holds the class instance.
	 *
	 * @since 2.0.0
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Retrieve a class instance.
	 *
	 * @since 2.0.0
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
	 * @since 2.0.0
	 */
	private function __construct() {

	}

	/**
	 * Send pagination events.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form                The form arguments
	 * @param int   @source_page_number  The original page number
	 * @param int   $current_page_number The new page number
	 */
	public function paginate( $form, $source_page_number, $current_page_number ) {

		$fbp_code = GFFBPT::get_fbp_code();
		if ( false !== $fbp_code ) {
			$event = new GFFBPT_Measurement_Protocol();
			$event->init();

			/**
			 * Filter: gform_pagination_event_category
			 *
			 * Filter the event category dynamically
			 *
			 * @since 2.0.0
			 *
			 * @param string $category              Event Category
			 * @param array  $form                  Gravity Form form array
			 * @param int    $source_page_number    Source page number
			 * @param int    $current_page_number   Current Page Number
			 */
			$event_category = apply_filters( 'gform_pagination_event_category', 'form', $form, $source_page_number, $current_page_number );

			/**
			 * Filter: gform_pagination_event_action
			 *
			 * Filter the event action dynamically
			 *
			 * @since 2.0.0
			 *
			 * @param string $action                Event Action
			 * @param array  $form                  Gravity Form form array
			 * @param int    $source_page_number    Source page number
			 * @param int    $current_page_number   Current Page Number
			 */
			$event_name = apply_filters( 'gform_pagination_event_action', 'pagination', $form, $source_page_number, $current_page_number );

			/**
			 * Filter: gform_pagination_event_label
			 *
			 * Filter the event label dynamically
			 *
			 * @since 2.0.0
			 *
			 * @param string $label                 Event Label
			 * @param array  $form                  Gravity Form form array
			 * @param int    $source_page_number    Source page number
			 * @param int    $current_page_number   Current Page Number
			 */
			$event_currency = sprintf( '%s::%d::%d', esc_html( $form['title'] ), absint( $source_page_number ), absint( $current_page_number ) );
			$event_currency = apply_filters( 'gform_pagination_event_label', $event_currency, $form, $source_page_number, $current_page_number );

			$event->set_event_category( $event_category );
			$event->set_event_name( $event_name );
			$event->set_event_currency( $event_currency );


			?>
			<script>
			if ( typeof window.parent.ga == 'undefined' ) {
				if ( typeof window.parent.__fbpTracker != 'undefined' ) {
					window.parent.ga = window.parent.__fbpTracker;
				}
			}
			if ( typeof window.parent.ga != 'undefined' ) {

				// Try to get original UA code from third-party plugins or tag manager
				var default_ua_code = null;
				window.parent.ga(function(tracker) {
					default_ua_code = tracker.get('trackingId');
				});

				// If UA code matches, use that tracker
				if ( default_ua_code == '<?php echo esc_js( $fbp_code ); ?>' ) {
					window.parent.ga( 'send', 'event', '<?php echo esc_js( $event_category ); ?>', '<?php echo esc_js( $event_name ); ?>', '<?php echo esc_js( $event_currency ); ?>' );
				} else {
					// UA code doesn't match, use another tracker
					window.parent.ga( 'create', '<?php echo esc_js( $fbp_code ); ?>', 'auto', 'GTGAET_Tracker' );
					window.parent.ga( 'GTGAET_Tracker.send', 'event', '<?php echo esc_js( $event_category );?>', '<?php echo esc_js( $event_name ); ?>', '<?php echo esc_js( $event_currency ); ?>' );
				}
			}
			</script>
			<?php
			return;
	
			// Submit the event
			$event->send( $fbp_code );
		}

	}

	
}

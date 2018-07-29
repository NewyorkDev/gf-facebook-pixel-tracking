<?php
class GFFBPT_Pagination {
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

	}

	/**
	 * Send pagination events.
	 *
	 * @since 1.0.0
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
			 * @since 1.0.0
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
			 * @since 1.0.0
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
			 * @since 1.0.0
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
			!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
			n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
			n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
			t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
			document,'script','https://connect.facebook.net/en_US/fbevents.js');
			// Insert Your Facebook Pixel ID below. 
			fbq('init', '<?php echo esc_js( $fbp_code ); ?>');
			
			fbq('track', 'ViewContent', {
			  content_name: '<?php echo esc_js( $event_name ); ?>',
			  content_category: '<?php echo esc_js( $event_category ); ?>',
			  value: <?php echo esc_js( $event_value ); ?>,
			  currency: '<?php echo esc_js( $event_currency ); ?>'
			});
			</script>
			<?php
			return;
	
			// Submit the event
			$event->send( $fbp_code );
		}

	}

	
}

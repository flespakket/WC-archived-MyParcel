<?php
class WC_Flespakket_Writepanel {

	public function __construct() {
		$this->settings = get_option( 'wcflespakket_settings' );

		// Add meta box with Flespakket links/buttons
		add_action( 'add_meta_boxes_shop_order', array( $this, 'add_box' ) );

		// Add export action to drop down menu
		add_action(	'admin_footer-edit.php', array( &$this, 'export_actions' ) ); 

		//Add buttons in order listing
		add_action( 'woocommerce_admin_order_actions_end', array( $this, 'add_listing_actions' ), 20 );
		
    	// Customer Emails
		if (isset($this->settings['email_tracktrace']))
	    	add_action( 'woocommerce_email_before_order_table', array( $this, 'track_trace_email' ), 10, 2 );
		
	}

	/**
	 * Add the meta box on the single order page
	 */
	public function add_box() {
		add_meta_box(
			'flespakket', //$id
			__( 'Flespakket', 'wcflespakket' ), //$title
			array( $this, 'create_box_content' ), //$callback
			'shop_order', //$post_type
			'side', //$context
			'default' //$priority
		);
	}

	/**
	 * Callback: Create the meta box content on the single order page
	 */
	public function create_box_content() {
		global $post_id;
		$pdf_link = wp_nonce_url( admin_url( 'edit.php?&action=wcflespakket-label&order_ids=' . $post_id ), 'wcflespakket-label' );
		$export_link = wp_nonce_url( admin_url( 'edit.php?&action=wcflespakket&order_ids=' . $post_id ), 'wcflespakket' );

		$target = ( isset($this->settings['download_display']) && $this->settings['download_display'] == 'display') ? 'target="_blank"' : '';


		if (get_post_meta($post_id,'_flespakket_consignment_id',true)) {
			$consignment_id = get_post_meta($post_id,'_flespakket_consignment_id',true);

			$tracktrace = get_post_meta($post_id,'_flespakket_tracktrace',true);
			$tracktrace_url = $this->get_tracktrace_url($post_id);


			// fetch TNT status
			$tnt_status_url = 'http://www.flespakket.nl/status/tnt/' . $consignment_id;
			$tnt_status = explode('|', @file_get_contents($tnt_status_url));
            $tnt_status = (count($tnt_status) == 3) ? $tnt_status[2] : '';
			
			?>
			<ul>
				<li>
					<a href="<?php echo $pdf_link; ?>" class="button" alt="Download label (PDF)" <?php echo $target; ?>>Download label (PDF)</a>
				</li>
				<li>Status: <?php echo $tnt_status ?></li>
				<li>Track&Trace code: <a href="<?php echo $tracktrace_url; ?>"><?php echo $tracktrace; ?></a></li>
				<li>
					<a href="<?php echo $export_link; ?>" class="button flespakket one-flespakket" alt="Exporteer naar Flespakket">Exporteer opnieuw</a>
				</li>
			</ul>
			<?php
		} else {
			?>
			<ul>
				<li>
					<a href="<?php echo $export_link; ?>" class="button flespakket one-flespakket" alt="Exporteer naar Flespakket">Exporteer naar Flespakket</a>
				</li>
			</ul>
			<?php			
		}
	}

	/**
	 * Add export option to bulk action drop down menu
	 *
	 * Using Javascript until WordPress core fixes: http://core.trac.wordpress.org/ticket/16031
	 *
	 * @access public
	 * @return void
	 */
	public function export_actions() {
		global $post_type;

		if ( 'shop_order' == $post_type ) {
			?>
			<script type="text/javascript">
			jQuery(document).ready(function() {
				jQuery('<option>').val('wcflespakket').text('<?php _e( 'Exporteer naar Flespakket', 'wcflespakket' )?>').appendTo("select[name='action']");
				jQuery('<option>').val('wcflespakket').text('<?php _e( 'Exporteer naar Flespakket', 'wcflespakket' )?>').appendTo("select[name='action2']");

				jQuery('<option>').val('wcflespakket-label').text('<?php _e( 'Print Flespakket labels', 'wcflespakket' )?>').appendTo("select[name='action']");
				jQuery('<option>').val('wcflespakket-label').text('<?php _e( 'Print Flespakket labels', 'wcflespakket' )?>').appendTo("select[name='action2']");
			});
			</script>
			<?php
		}
	}		

	/**
	 * Add print actions to the orders listing
	 */
	public function add_listing_actions( $order ) {
		$consignment_id = get_post_meta($order->id,'_flespakket_consignment_id',true);

		$pdf_link = wp_nonce_url( admin_url( 'edit.php?&action=wcflespakket-label&order_ids=' . $order->id ), 'wcflespakket-label' );
		$export_link = wp_nonce_url( admin_url( 'edit.php?&action=wcflespakket&order_ids=' . $order->id ), 'wcflespakket' );

		$target = ( isset($this->settings['download_display']) && $this->settings['download_display'] == 'display') ? 'target="_blank"' : '';
		if (!empty($consignment_id)) {
			?>
			<a href="<?php echo $pdf_link; ?>" class="button tips flespakket" alt="Print Flespakket label" data-tip="Print Flespakket label" <?php echo $target; ?>>
				<img src="<?php echo dirname(plugin_dir_url(__FILE__)) . '/img/flespakket-pdf.png'; ?>" alt="Print Flespakket label">
			</a>
			<a href="<?php echo $export_link; ?>" class="button tips flespakket one-flespakket" alt="Exporteer naar Flespakket" data-tip="Exporteer naar Flespakket">
				<img src="<?php echo dirname(plugin_dir_url(__FILE__)) . '/img/flespakket-up.png'; ?>" alt="Exporteer naar Flespakket">
			</a>
			<?php
		} else {
			?>
			<a href="<?php echo $export_link; ?>" class="button tips flespakket one-flespakket" alt="Exporteer naar Flespakket" data-tip="Exporteer naar Flespakket">
				<img src="<?php echo dirname(plugin_dir_url(__FILE__)) . '/img/flespakket-up.png'; ?>" alt="Exporteer naar Flespakket">
			</a>
			<?php
			
		}
	}

    /**
    * Add track&trace to user email
    **/
    function track_trace_email( $order, $sent_to_admin ) {

    	if ( $sent_to_admin ) return;

    	if ( $order->status != 'completed') return;

		$tracktrace = get_post_meta($order->id,'_flespakket_tracktrace',true);
		if ( !empty($tracktrace) ) {
			$tracktrace_url = $this->get_tracktrace_url($order->id);

			$tracktrace_link = '<a href="'.$tracktrace_url.'">'.$tracktrace.'</a>';
			$email_text = apply_filters( 'wcflespakket_email_text', 'U kunt uw bestelling volgen met het volgende PostNL track&trace nummer:' );
			?>
			<p><?php echo $email_text.' '.$tracktrace_link; ?></p>
	
			<?php
		}
	}

	public function get_tracktrace_url($order_id) {
		if (empty($order_id))
			return;

		$tracktrace = get_post_meta($order_id,'_flespakket_tracktrace',true);
		$postcode = preg_replace('/\s+/', '',get_post_meta($order_id,'_shipping_postcode',true));
		$tracktrace_url = sprintf('https://mijnpakket.postnl.nl/Claim?Barcode=%s&Postalcode=%s', $tracktrace, $postcode);
		
		//Check if foreign
		$country = get_post_meta($order_id,'_shipping_country',true);
		if ($country != 'NL')
			$tracktrace_url = add_query_arg( 'Foreign', 'True', $tracktrace_url );

		return $tracktrace_url;
	}
}
<?php
/**
 * Upsell Banner
 *
 * @link
 * @since 1.7.5
 *
 * @package  Custom_Related_Products
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wbte_Crp_Upsell_Banner {

	private static $instance        = null;
	protected $dismiss_option_key   = 'wt_related_products_upsell_dismiss';

	public function __construct() {

		add_action( 'wp_ajax_wt_crp_dismiss_upsell_banner', array( $this, 'ajax_dismiss_upsell_banner' ) );
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

	}
	
	/**
	 *  Get Instance
	 *
	 *  @since 1.7.5
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Wbte_Crp_Upsell_Banner();
		}
		return self::$instance;
	}

	/**
	 * Enqueue required scripts and styles.
	 */
	public function enqueue_scripts($hook) { 
		if ( $this->is_banner_dismissed() ) {
			return;
		}

		wp_enqueue_style('wt-crp-upsell-banner',plugin_dir_url(__FILE__) . 'assets/css/wt-crp-upsell-banner.css',array(),WT_RELATED_PRODUCTS_VERSION);

		wp_enqueue_script('wt-crp-upsell-banner',plugin_dir_url(__FILE__) . 'assets/js/wt-crp-upsell-banner.js',array('jquery'),WT_RELATED_PRODUCTS_VERSION,true);

		// Localize script with AJAX data
		wp_localize_script('wt-crp-upsell-banner', 'wt_crp_upsell_banner_params', array(
			'ajax_url' => esc_url( admin_url('admin-ajax.php') ),
			'nonce' => wp_create_nonce('wt_crp_dismiss_upsell_banner'),
			'action' => 'wt_crp_dismiss_upsell_banner'
		));
	}

	/**
	 * Check if the upsell banner is currently dismissed (permanent or within 7 days window)
	 *
	 * @since 1.7.5
	 * 
	 * @return bool
	 */
	public function is_banner_dismissed() {
		$option_value = get_option( $this->dismiss_option_key, false );
        if ( 'dismiss' === $option_value ) {
			return true;
		}
		$dismiss_until = (int) $option_value;
		return ( $dismiss_until && $dismiss_until > time() );
	}


	/**
	 *  Display the upsell banner
	 *
	 *  @since 1.7.5
	 */
	public function pro_banner_content() {
		if ( $this->is_banner_dismissed() ) {
			return;
		}
		?>

        <tr class="crp-tr-field wbte_crp_upsell_banner_content">
			<td colspan="2" style="padding: 0px;">
				<div class="crp-banner wbte_crp_upsell_banner">
					<div class="wbte_crp_upsell_banner_text">
						<img src="<?php echo esc_url( CRP_PLUGIN_URL . 'admin/modules/banners/assets/images/bulb.svg' ) ?>">
						<span class="wbte_crp_upsell_banner_upsell_title"><?php esc_html_e( 'Did you know?', 'wt-woocommerce-related-products' ); ?></span>
						<?php esc_html_e( 'You can create advanced product recommendation campaigns like Best Sellers, New Arrivals, Frequently Bought Together, and more using the WooCommerce Product Recommendations plugin.', 'wt-woocommerce-related-products' ); ?>
					</div>
					<div class="wbte_crp_upsell_banner_actions">
						<a href="<?php echo esc_url( 'https://www.webtoffee.com/product/woocommerce-product-recommendations/?utm_source=free_plugin_settings_page&utm_medium=related_products&utm_campaign=Product_Recommendations' ); ?>" class="btn-primary" target="_blank"><?php esc_html_e( 'Get plugin now →', 'wt-woocommerce-related-products' ); ?></a>
						<a href="<?php echo esc_url( '#' ); ?>" class="btn-secondary wbte_crp_upsell_banner_dismiss" ><?php esc_html_e( 'Dismiss', 'wt-woocommerce-related-products' ); ?></a>
						<button type="button" class="popup-close wbte_crp_upsell_banner_closed">
							<svg class="wt_pklist_banner_dismiss" width="11" height="11" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M9.5 1L1 9.5" stroke="#505050" stroke-width="1.5"></path>
								<path d="M1 1L9.5 9.5" stroke="#505050" stroke-width="1.5"></path>
							</svg>
					</button>
					</div>
				</div>
			</td>
		</tr>	

		<?php
	}

	/**
	 *  Hide the upsell metabox
	 *
	 *  @since 1.7.5
	 */
	public function maybe_hide_upsell_metabox_script() {
		if ( $this->is_banner_dismissed() ) {
			?>
			<script type="text/javascript">
				jQuery(function($){
					$('#wt-crp-upsell-banner-metabox').hide();
				});
			</script>
			<?php
		}
	}

	/**
	 * Handle AJAX request to dismiss/close the upsell banner
	 *
	 * If `dismiss` is 1, permanently hide the banner.
	 * If `dismiss` is 0, hide the banner for 7 days.
	 *
	 * @since 1.7.5
	 */
	public function ajax_dismiss_upsell_banner() {
		check_ajax_referer( 'wt_crp_dismiss_upsell_banner', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'wt-woocommerce-related-products' ) ), 403 );
		}

		$dismiss = isset( $_POST['dismiss'] ) ? absint( $_POST['dismiss'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$current_value = get_option( $this->dismiss_option_key, false );

		if ( 1 === $dismiss ) {
			update_option( $this->dismiss_option_key, 'dismiss' ); // Permanently hide the banner.
		} else { 
			if ( 'dismiss' !== $current_value ) {
				$until = time() + WEEK_IN_SECONDS;
				update_option( $this->dismiss_option_key, $until ); // Hide for 7 days, but do not override a permanent dismissal if already set.
			}
		}

		wp_send_json_success();
	}
}
Wbte_Crp_Upsell_Banner::get_instance();

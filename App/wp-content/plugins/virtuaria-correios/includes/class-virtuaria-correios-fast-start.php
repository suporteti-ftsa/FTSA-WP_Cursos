<?php
/**
 * Add feature to faster start the plugin.
 *
 * @package virtuaria/integrations/correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Virtuaria_Correios_Fast_Start
 */
class Virtuaria_Correios_Fast_Start {
	use Virtuaria_Correios_Fast_Start_Functions;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_make_fast_start', array( $this, 'make_fast_start' ) );
	}

	/**
	 * Enqueue admin scripts to the page Virtuaria Settings.
	 *
	 * @param string $hook The current hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_virtuaria-settings' === $hook ) {
			$url = VIRTUARIA_CORREIOS_URL . 'admin/';
			$dir = VIRTUARIA_CORREIOS_DIR . 'admin/';

			wp_enqueue_script(
				'virtuaria-correios-fast-start',
				$url . 'js/fast-start.min.js',
				array( 'jquery' ),
				filemtime( $dir . 'js/fast-start.min.js' ),
				true
			);

			wp_localize_script(
				'virtuaria-correios-fast-start',
				'start',
				array(
					'nonce'         => wp_create_nonce( 'do-fast-start' ),
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'shipping_url'  => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
					'cep'           => get_option( 'woocommerce_store_postcode' ),
					'exist_methods' => $this->has_virtuaria_correios_methods(),
				)
			);

			wp_enqueue_style(
				'fast-start',
				$url . 'css/fast-start.css',
				array(),
				filemtime( $dir . 'css/fast-start.css' )
			);
		}
	}

	/**
	 * Checks if there are any Virtuaria_Correios_Shipping instance.
	 *
	 * @return bool
	 */
	private function has_virtuaria_correios_methods() {
		$exist_virtuaria_methods = false;

		$zones = \WC_Shipping_Zones::get_zones();
		if ( $zones ) {
			foreach ( $zones as $zone ) {
				foreach ( $zone['shipping_methods'] as $shipping_method ) {
					if ( $shipping_method->is_enabled() !== true
						|| ! $shipping_method instanceof Virtuaria_Correios_Shipping ) {
						continue;
					}

					$exist_virtuaria_methods = true;
					break;
				}
			}
		}
		return $exist_virtuaria_methods;
	}
}

new Virtuaria_Correios_Fast_Start();

<?php
/**
 * Reused functions to support international shipping.
 *
 * @package virtuaria/correios
 */

defined( 'ABSPATH' ) || exit;

trait Virtuaria_Correios_International {
	/**
	 * Check if the shipping address is international.
	 *
	 * It checks if the "correios-int" parameter is set in the URL and if its value is not "BR".
	 * If the parameter is not set, it checks if the logged in user's country is not "BR".
	 *
	 * @return bool True if the shipping address is international, false otherwise.
	 */
	private function is_international_shipping() {
		$is_international = false;

		if ( isset( $_GET['correios-int'] ) && ! empty( $_GET['correios-int'] ) ) {
			$is_international = 'BR' !== $_GET['correios-int'];

			if ( $is_international && isset( WC()->customer ) ) {
				$country = sanitize_text_field( wp_unslash( $_GET['correios-int'] ) );
				WC()->customer->set_billing_country(
					$country
				);
				WC()->customer->set_shipping_country(
					$country
				);
			}
		} elseif ( isset( WC()->customer ) ) {
			$is_international = 'BR' !== WC()->customer->get_billing_country()
				|| 'BR' !== WC()->customer->get_shipping_country();
		}

		return $is_international;
	}
}

<?php
/**
 * Handle formatters from extra fields.
 *
 * @package Virtuaria/Integrations/Correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Correios_Extra_Fields_Formatters {
	use Virtuaria_Correios_Fields;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		if ( $this->is_checkout_block() ) {
			return;
		}
		add_filter( 'woocommerce_localisation_address_formats', array( $this, 'localisation_address_formats' ) );
		add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'formatted_address_replacements' ), 1, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'order_formatted_billing_address' ), 1, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'order_formatted_shipping_address' ), 1, 2 );
		add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'my_account_my_address_formatted_address' ), 1, 3 );

		// Orders.
		add_filter( 'woocommerce_get_order_address', array( $this, 'order_address' ), 10, 3 );
	}

	/**
	 * Custom country address formats.
	 *
	 * @param  array $formats Defaul formats.
	 *
	 * @return array          New BR format.
	 */
	public function localisation_address_formats( $formats ) {
		$formats['BR'] = "{name}\n{address_1}, {number}\n{address_2}\n{neighborhood}\n{city}\n{state}\n{postcode}\n{country}";

		return $formats;
	}

	/**
	 * Custom country address format.
	 *
	 * @param  array $replacements Default replacements.
	 * @param  array $args         Arguments to replace.
	 *
	 * @return array               New replacements.
	 */
	public function formatted_address_replacements( $replacements, $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'number'       => '',
				'neighborhood' => '',
			)
		);

		$replacements['{number}']       = $args['number'];
		$replacements['{neighborhood}'] = $args['neighborhood'];

		return $replacements;
	}

	/**
	 * Custom order formatted billing address.
	 *
	 * @param  array  $address Default address.
	 * @param  object $order   Order data.
	 *
	 * @return array           New address format.
	 */
	public function order_formatted_billing_address( $address, $order ) {
		$address['number']       = $order->get_meta( '_billing_number' );
		$address['neighborhood'] = $order->get_meta( '_billing_neighborhood' );

		return $address;
	}

	/**
	 * Custom order formatted shipping address.
	 *
	 * @param  array  $address Default address.
	 * @param  object $order   Order data.
	 *
	 * @return array           New address format.
	 */
	public function order_formatted_shipping_address( $address, $order ) {
		if ( ! is_array( $address ) ) {
			return $address;
		}

		$address['number']       = $order->get_meta( '_shipping_number' );
		$address['neighborhood'] = $order->get_meta( '_shipping_neighborhood' );

		return $address;
	}

	/**
	 * Custom my address formatted address.
	 *
	 * @param  array  $address     Default address.
	 * @param  int    $customer_id Customer ID.
	 * @param  string $name        Field name (billing or shipping).
	 *
	 * @return array               New address format.
	 */
	public function my_account_my_address_formatted_address( $address, $customer_id, $name ) {
		$address['number']       = get_user_meta( $customer_id, $name . '_number', true );
		$address['neighborhood'] = get_user_meta( $customer_id, $name . '_neighborhood', true );

		return $address;
	}

	/**
	 * Order address.
	 *
	 * @param  array    $address Address data.
	 * @param  string   $type    Address type.
	 * @param  WC_Order $order   Order object.
	 * @return array
	 */
	public function order_address( $address, $type, $order ) {
		$address['number']       = $order->get_meta( '_' . $type . '_number' );
		$address['neighborhood'] = $order->get_meta( '_' . $type . '_neighborhood' );

		return $address;
	}
}

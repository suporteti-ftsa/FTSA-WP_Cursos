<?php
/**
 * Handle Correios REST API.
 *
 * @package virtuaria/correios
 */

defined( 'ABSPATH' ) || exit;

/**
 * VIRTUARIA_Correios_REST_API class.
 */
class Virtuaria_Correios_REST_API {

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		add_filter( 'woocommerce_api_order_response', array( $this, 'legacy_orders_response' ), 100, 3 );
		add_filter( 'woocommerce_api_create_order', array( $this, 'legacy_orders_update' ), 100, 2 );
		add_filter( 'woocommerce_api_edit_order', array( $this, 'legacy_orders_update' ), 100, 2 );
		add_action( 'rest_api_init', array( $this, 'register_tracking_code_endpoint' ), 110 );
	}

	/**
	 * Add the tracking code to the legacy WooCOmmerce REST API.
	 *
	 * @param  array    $data   Endpoint response.
	 * @param  WC_Order $order  Order object.
	 * @param  array    $fields Fields filter.
	 *
	 * @return array
	 */
	public function legacy_orders_response( $data, $order, $fields ) {
		$data['correios_tracking_code'] = $order->get_meta( '_virt_correios_trakking_code' );

		if ( $fields ) {
			$data = WC()->api->WC_API_Customers->filter_response_fields( $data, $order, $fields );
		}

		return $data;
	}

	/**
	 * Update tracking code using the legacy WooCOmmerce REST API.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Posted data.
	 */
	public function legacy_orders_update( $order_id, $data ) {
		if ( isset( $data['correios_tracking_code'] ) ) {
			$this->update_tracking_code( $data['correios_tracking_code'], $order_id );
		}
	}

	/**
	 * Register tracking code field in WP REST API.
	 */
	public function register_tracking_code_endpoint() {
		if ( ! function_exists( 'register_rest_field' ) ) {
			return;
		}

		register_rest_field(
			'shop_order',
			'correios_tracking_code',
			array(
				'get_callback'    => array( $this, 'get_tracking_code' ),
				'update_callback' => array( $this, 'update_tracking_code' ),
				'schema'          => array(
					'description' => __( 'Virtuaria Correios handle tracking code.', 'virtuaria-correios' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}


	/**
	 * Retrieves the tracking code associated with the given order.
	 *
	 * @param array $data The order data.
	 * @return string The tracking code, or an empty string if none is associated with the order.
	 */
	public function get_tracking_code( $data ) {
		$order = wc_get_order( $data['id'] );
		if ( $order ) {
			return $order->get_meta( '_virt_correios_trakking_code' );
		}
		return '';
	}

	/**
	 * Update the tracking code associated with the given order.
	 *
	 * @param string $value The new tracking code.
	 * @param mixed  $data  The order data.
	 *
	 * @return bool True if the tracking code was updated successfully, false otherwise.
	 */
	public function update_tracking_code( $value, $data ) {
		$order = wc_get_order( isset( $data->ID ) ? $data->ID : $data );
		if ( ! $value || ! $order || ! is_string( $value ) ) {
			return;
		}

		$order->update_meta_data(
			'_virt_correios_trakking_code',
			$value
		);

		$order->add_order_note(
			sprintf(
				/* translators: %1$s: trakking code. */
				__( 'Virtuaria Correios: Novo código de rastreamento (%1$s) adicionado ao pedido.', 'virtuaria-correios' ),
				$value
			),
			false,
			true
		);

		$order->save();

		Virtuaria_Correios_Trakking::send_trakking_notification( $order, $value );
		do_action( 'virtuaria_correios_trakking_updated', $order->get_id(), $order, $value );

		return true;
	}
}

new Virtuaria_Correios_REST_API();

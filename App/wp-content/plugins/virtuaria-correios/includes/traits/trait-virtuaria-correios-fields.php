<?php
/**
 * Trait to handle extra fields.
 *
 * @package Virtuaria/Integrations/Correios.
 */

defined( 'ABSPATH' ) || exit;

trait Virtuaria_Correios_Fields {
	/**
	 * Add element in specific position of array.
	 *
	 * @param array  $collection collections of elements.
	 * @param array  $elem mixed.
	 * @param int    $pos position to insert.
	 * @param string $key key element to insert.
	 */
	private function add_elem_specific_position( &$collection, $elem, $pos = 0, $key = null ) {
		if ( ! $collection ) {
			return;
		}

		$first         = array_slice( $collection, 0, $pos, true );
		$first[ $key ] = $elem;
		$collection    = array_merge(
			$first,
			array_slice(
				$collection,
				$pos,
				count( $collection ) - $pos,
				true
			)
		);
	}

	/**
	 * Checks if the checkout block is active.
	 *
	 * @return boolean true if active, false otherwise.
	 */
	private function is_checkout_block() {
		return WC_Blocks_Utils::has_block_in_page(
			wc_get_page_id( 'checkout' ),
			'woocommerce/checkout'
		);
	}
}

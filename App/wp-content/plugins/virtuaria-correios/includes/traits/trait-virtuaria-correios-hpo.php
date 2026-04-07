<?php
/**
 * Handle function HPO Woocommerce.
 *
 * @package virtuaria/correios.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

trait Virtuaria_Correios_HPO {

	/**
	 * Retrieve the screen ID for meta boxes.
	 *
	 * @return string
	 */
	private function get_meta_boxes_screen() {
		return class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}
}

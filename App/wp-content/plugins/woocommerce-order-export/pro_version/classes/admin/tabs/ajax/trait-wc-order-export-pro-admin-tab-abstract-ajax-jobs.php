<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait WC_Order_Export_Pro_Admin_Tab_Abstract_Ajax_Jobs {
	public function ajax_run_one_job() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['schedule'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $id = sanitize_text_field( wp_unslash($_REQUEST['schedule']));
			$settings = WC_Order_Export_Pro_Manage::get( WC_Order_Export_Pro_Manage::EXPORT_SCHEDULE, $id );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_REQUEST['profile'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $_REQUEST['profile'] == 'now' ) {
				$settings = WC_Order_Export_Pro_Manage::get( WC_Order_Export_Pro_Manage::EXPORT_NOW );
			} else {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $id = sanitize_text_field( wp_unslash($_REQUEST['profile']));
				$settings = WC_Order_Export_Pro_Manage::get( WC_Order_Export_Pro_Manage::EXPORT_PROFILE, $id );
			}
		} else {
            esc_html_e( 'Schedule or profile required!', 'woocommerce-order-export' );
		}

		$woe_order_post_type = isset($settings['post_type']) ? $settings['post_type'] : 'shop_order';

		WC_Order_Export_Pro_Admin::set_order_post_type($woe_order_post_type);

		$filename = WC_Order_Export_Pro_Engine::build_file_full( $settings );
		WC_Order_Export_Pro_Manage::set_correct_file_ext( $settings );

		$this->send_headers( $settings['format'], WC_Order_Export_Pro_Engine::make_filename( $settings['export_filename'] ) );
		$this->send_contents_delete_file( $filename );
	}

	public function ajax_run_one_scheduled_job() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		WC_Order_Export_Cron::run_one_scheduled_job();
	}

	public function ajax_run_cron_jobs() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		WC_Order_Export_Cron::wc_export_cron_global_f();
	}

}

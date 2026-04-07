<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait WC_Order_Export_Pro_Admin_Tab_Abstract_Ajax {
	use WC_Order_Export_Pro_Admin_Tab_Abstract_Ajax_Jobs;

	public function ajax_test_destination() {

        $nonce = isset($_GET['woe_nonce']) ? sanitize_text_field(wp_unslash($_GET['woe_nonce'])) :
            (isset($_POST['woe_nonce']) ? sanitize_text_field(wp_unslash($_POST['woe_nonce'])) : '');

        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'woe_nonce') ) {
            wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
            exit;
        }

		$settings = WC_Order_Export_Pro_Manage::make_new_settings( $_POST );

		unset( $settings['destination']['type'] );

        $destination = isset( $_POST['destination'] ) ? sanitize_text_field(wp_unslash( $_POST['destination'] )) : '';
        $settings['destination']['type'][] = $destination;

        $id = isset( $_POST['id'] ) ? sanitize_text_field(wp_unslash( $_POST['id'] )) : '';;
        // use unsaved settings
		do_action( 'woe_start_test_job', $id, $settings );

		if ( isset( $settings['change_order_status_to'] ) ) {
		    unset( $settings['change_order_status_to'] );
		}
		
		$settings['mark_exported_orders'] = false; // don't mark

		$main_settings = WC_Order_Export_Main_Settings::get_settings();

		$result = WC_Order_Export_Pro_Engine::build_files_and_export( $settings, '', $main_settings['limit_button_test'] );

        //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo implode("\n\r", array_map(function ($v) { return $v['text']; }, $result));
	}

	public function ajax_reorder_jobs() {

        $nonce = isset($_GET['woe_nonce']) ? sanitize_text_field(wp_unslash($_GET['woe_nonce'])) :
            (isset($_POST['woe_nonce']) ? sanitize_text_field(wp_unslash($_POST['woe_nonce'])) : '');

        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'woe_nonce') ) {
            wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
            exit;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $order_data = $_REQUEST['new_jobs_order'] ?? null;
        if ( is_array( $order_data ) ) {
            $order_data = array_map( function( $value ) {
                return sanitize_text_field( wp_unslash( $value ) );
            }, $order_data );
        }

        if ( ! empty( $order_data ) AND ! empty( $_REQUEST['tab_name'] ) ) {

			if ( $_REQUEST['tab_name'] == 'schedule' ) {
				$mode = WC_Order_Export_Pro_Manage::EXPORT_SCHEDULE;
			} elseif ( $_REQUEST['tab_name'] == 'profile' ) {
				$mode = WC_Order_Export_Pro_Manage::EXPORT_PROFILE;
			} elseif ( $_REQUEST['tab_name'] == 'order_action' ) {
				$mode = WC_Order_Export_Pro_Manage::EXPORT_ORDER_ACTION;
			} else {
				echo json_encode( array( 'result' => false ) );
				die();
			}

			//skip zero ids
			foreach ( array_filter( $order_data ) as $index => $job_id ) {
				$job             = WC_Order_Export_Pro_Manage::get( $mode, $job_id );
				$job['priority'] = $index + 1;
				WC_Order_Export_Pro_Manage::save_export_settings( $mode, $job_id, $job );
			}
			echo json_encode( array( 'result' => true ) );
		} else {
			echo json_encode( array( 'result' => false ) );
		}
	}

}
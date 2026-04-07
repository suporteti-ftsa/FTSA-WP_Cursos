<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Order_Export_Pro_Admin_Tab_Schedule_Jobs extends WC_Order_Export_Admin_Tab_Schedule_Jobs {
	use WC_Order_Export_Pro_Admin_Tab_Abstract;

	public function __construct() {
		parent::__construct();
		$this->title = __( 'Scheduled jobs', 'woocommerce-order-export' );
	}

	public function render() {

		$wc_oe = isset( $_REQUEST['wc_oe'] ) ? sanitize_text_field( wp_unslash($_REQUEST['wc_oe'])) : '';

		if ( in_array( $wc_oe, array(
				'copy_schedule',
				'delete_schedule',
				'change_status_schedule',
				'bulk_actions_schedules',
			) ) && ! check_admin_referer( 'woe_nonce', 'woe_nonce' ) ) {
			return;
		}

		$ajaxurl  = admin_url( 'admin-ajax.php' );
		$mode     = WC_Order_Export_Pro_Manage::EXPORT_SCHEDULE;
		$all_jobs = WC_Order_Export_Pro_Manage::get_export_settings_collection( $mode );
		$show     = array(
			'date_filter'         => true,
			'export_button'       => true,
			'export_button_plain' => true,
		);

		switch ( $wc_oe ) {
			case 'add_schedule':
				end( $all_jobs );
				$next_id = key( $all_jobs ) + 1;
				add_action( 'woe_settings_form_view_top', array( $this, 'add_top_to_view' ) );
				add_action( 'woe_settings_form_view_destinations', array( $this, 'add_destinations_to_view' ), 10, 1 );
				// for refunds -- Allow to export fully refunded items by default
				$this->tweak_refund_settings();
				$this->render_template( 'settings-form', array(
					'mode'    => $mode,
					'id'      => $next_id,
					'ajaxurl' => $ajaxurl,
					'show'    => $show,
				) );

				return;
			case 'edit_schedule':
				if ( ! isset( $_REQUEST['schedule_id'] ) ) {
					break;
				}
				$schedule_id                                   = sanitize_text_field( wp_unslash($_REQUEST['schedule_id']));
				WC_Order_Export_Pro_Manage::$edit_existing_job = true;
				add_action( 'woe_settings_form_view_top', array( $this, 'add_top_to_view' ) );
				add_action( 'woe_settings_form_view_destinations', array( $this, 'add_destinations_to_view' ), 10, 1 );
				$this->render_template( 'settings-form', array(
					'mode'    => $mode,
					'id'      => $schedule_id,
					'ajaxurl' => $ajaxurl,
					'show'    => $show,
				) );

				return;
			case 'copy_schedule':
				if ( ! isset( $_REQUEST['schedule_id'] ) ) {
					break;
				}
				$schedule_id = sanitize_text_field( wp_unslash($_REQUEST['schedule_id']));
				$schedule_id = WC_Order_Export_Pro_Manage::clone_export_settings( $mode, $schedule_id );

				$url = add_query_arg( array(
					'schedule_id' => $schedule_id,
					'wc_oe'       => 'edit_schedule',
				) );

				$url = remove_query_arg( array( 'woe_nonce' ), $url );

				wp_redirect( $url );

				return;
			case 'delete_schedule':
				if ( ! isset( $_REQUEST['schedule_id'] ) ) {
					break;
				}
				$schedule_id = sanitize_text_field( wp_unslash($_REQUEST['schedule_id']));
				unset( $all_jobs[ $schedule_id ] );
				WC_Order_Export_Pro_Manage::save_export_settings_collection( $mode, $all_jobs );

				$url = remove_query_arg( array( 'wc_oe', 'schedule_id', 'woe_nonce' ) );
				wp_redirect( $url );

				break;
			case 'change_status_schedule':
				if ( ! isset( $_REQUEST['schedule_id'] ) ) {
					break;
				}
				$schedule_id                        = sanitize_text_field( wp_unslash($_REQUEST['schedule_id']));
				$all_jobs[ $schedule_id ]['active'] = isset($_REQUEST['status']) ? sanitize_text_field( wp_unslash($_REQUEST['status'])) : '';
				WC_Order_Export_Pro_Manage::save_export_settings_collection( $mode, $all_jobs );
				$url = remove_query_arg( array( 'wc_oe', 'schedule_id', 'status', 'woe_nonce' ) );
				wp_redirect( $url );
				break;
			case 'bulk_actions_schedules':
				if ( ! isset( $_REQUEST['chosen_schedules'] ) || empty( $_REQUEST['chosen_schedules'] ) ) {
					break;
				}
				if ( ! isset( $_REQUEST['chosen_schedules'] ) AND ! isset( $_REQUEST['doaction'] ) AND - 1 == $_REQUEST['doaction'] ) {
					break;
				}
				$chosen_schedules = explode( ',', sanitize_text_field( wp_unslash($_REQUEST['chosen_schedules'])) );
				$doaction         = sanitize_text_field( wp_unslash($_REQUEST['doaction']));

				if ( 'run_now' == $doaction ) {
				    $results = WC_Order_Export_Cron::run_now_jobs($chosen_schedules);
				    set_transient('woe_schedules_bulk_action_run_now_output', $results, 60);
				    $url = remove_query_arg( array( 'wc_oe', 'chosen_schedules', 'doaction', 'woe_nonce' ) );
				    wp_redirect( $url );
				    exit();
				}

				foreach ( $chosen_schedules as $schedule_id ) {
					if ( 'activate' == $doaction ) {
						$all_jobs[ $schedule_id ]['active'] = 1;
					} elseif ( 'deactivate' == $doaction ) {
						$all_jobs[ $schedule_id ]['active'] = 0;
					} elseif ( 'delete' == $doaction ) {
						unset( $all_jobs[ $schedule_id ] );
					}
				}
				WC_Order_Export_Pro_Manage::save_export_settings_collection( $mode, $all_jobs );
				$url = remove_query_arg( array( 'wc_oe', 'chosen_schedules', 'doaction', 'woe_nonce' ) );
				wp_redirect( $url );
				break;
		}

		if ( false === $this->settings['cron_tasks_active'] ) {
			foreach ( $all_jobs as $id => $job ) {
				$all_jobs[ $id ]['active'] = 0;
			}
			WC_Order_Export_Pro_Manage::save_export_settings_collection( $mode, $all_jobs );
		}

		$this->render_template( 'tab/schedules', array( 'ajaxurl' => $ajaxurl ), $this->get_template_path() );
	}

	public function add_top_to_view( $settings ) {
		$this->render_template( 'top-scheduled-jobs', array( 'settings' => $settings ), $this->get_template_path() );
	}

}
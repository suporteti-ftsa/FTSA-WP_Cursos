<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Order_Export_Pro_Admin_Tab_Profiles extends WC_Order_Export_Admin_Tab_Profiles {
	use WC_Order_Export_Pro_Admin_Tab_Abstract;

	public function __construct() {
		parent::__construct();
		$this->title = __( 'Profiles', 'woocommerce-order-export' );
	}

	public function render() {

		$wc_oe = isset( $_REQUEST['wc_oe'] ) ? sanitize_text_field(wp_unslash($_REQUEST['wc_oe'])) : '';
		$settings = $this->get_settings();

		if ( in_array( $wc_oe, array(
				'copy_profile',
				'copy_profile_to_scheduled',
				'copy_profile_to_actions',
				'delete_profile',
				'change_profile_statuses',
			) ) && ! check_admin_referer( 'woe_nonce', 'woe_nonce' ) ) {
			return;
		}

		$ajaxurl   = admin_url( 'admin-ajax.php' );
		$mode      = WC_Order_Export_Pro_Manage::EXPORT_PROFILE;
		$all_items = WC_Order_Export_Pro_Manage::get_export_settings_collection( $mode );

		$show = array(
			'date_filter'         => true,
			'export_button'       => true,
			'export_button_plain' => true,
		);

		switch ( $wc_oe ) {
			case 'add_profile':
				end( $all_items );
				$next_id = key( $all_items ) + 1;
				add_action( 'woe_settings_form_view_top', array( $this, 'add_top_to_view' ) );
				if ( $settings['display_profiles_export_date_range'] ) {
					add_action( 'woe_settings_form_view_top', array( $this, 'add_export_date_range' ) );
				}

				if ( $settings['show_destination_in_profile'] ) {
					add_action( 'woe_settings_form_view_destinations', array( $this, 'add_destinations_to_view' ), 10, 1 );
				}
				
				// for refunds -- Allow to export fully refunded items by default
				$this->tweak_refund_settings();
				
				$this->render_template( 'settings-form', array(
					'mode'    => $mode,
					'id'      => $next_id,
					'ajaxurl' => $ajaxurl,
					'show'    => $show,
				) );

				return;
			case 'edit_profile':
				if ( ! isset( $_REQUEST['profile_id'] ) ) {
					break;
				}
				$profile_id                                    = sanitize_text_field( wp_unslash($_REQUEST['profile_id']));
				WC_Order_Export_Pro_Manage::$edit_existing_job = true;
				add_action( 'woe_settings_form_view_top', array( $this, 'add_top_to_view' ) );

				if ( $settings['display_profiles_export_date_range'] ) {
					add_action( 'woe_settings_form_view_top', array( $this, 'add_export_date_range' ) );
				}

				if ( $settings['show_destination_in_profile'] ) {
					add_action( 'woe_settings_form_view_destinations', array( $this, 'add_destinations_to_view' ), 10, 1 );
				}

				$this->render_template( 'settings-form', array(
					'mode'           => $mode,
					'id'             => $profile_id,
					'ajaxurl'        => $ajaxurl,
					'show'           => $show,
				) );

				return;
			case 'copy_profile':
				if ( ! isset( $_REQUEST['profile_id'] ) ) {
					break;
				}

				$profile_id = sanitize_text_field( wp_unslash($_REQUEST['profile_id']));
				$profile_id = WC_Order_Export_Pro_Manage::clone_export_settings( $mode, $profile_id );

				$url = add_query_arg( array(
					'profile_id' => $profile_id,
					'wc_oe'      => 'edit_profile',
				) );

				$url = remove_query_arg( array( 'woe_nonce' ), $url );

				wp_redirect( $url );

				return;
			case 'copy_profile_to_scheduled':
				$profile_id  = isset( $_REQUEST['profile_id'] ) ? sanitize_text_field( wp_unslash($_REQUEST['profile_id'])) : '';
				$schedule_id = WC_Order_Export_Pro_Manage::advanced_clone_export_settings( $profile_id, $mode,
					WC_Order_Export_Pro_Manage::EXPORT_SCHEDULE );
				$url         = remove_query_arg( array( 'profile_id', 'woe_nonce' ) );
				$url         = add_query_arg( 'tab', 'schedules', $url );
				$url         = add_query_arg( 'wc_oe', 'edit_schedule', $url );
				$url         = add_query_arg( 'schedule_id', $schedule_id, $url );
				wp_redirect( $url );
				break;
			case 'copy_profile_to_actions':
				$profile_id  = isset( $_REQUEST['profile_id'] ) ? sanitize_text_field( wp_unslash($_REQUEST['profile_id'])) : '';
				$schedule_id = WC_Order_Export_Pro_Manage::advanced_clone_export_settings( $profile_id, $mode,
					WC_Order_Export_Pro_Manage::EXPORT_ORDER_ACTION );
				$url         = remove_query_arg( array( 'profile_id', 'woe_nonce' ) );
				$url         = add_query_arg( 'tab', 'order_actions', $url );
				$url         = add_query_arg( 'wc_oe', 'edit_action', $url );
				$url         = add_query_arg( 'action_id', $schedule_id, $url );
				wp_redirect( $url );
				break;
			case 'delete_profile':
				if ( ! isset( $_REQUEST['profile_id'] ) ) {
					break;
				}
				$profile_id = sanitize_text_field( wp_unslash($_REQUEST['profile_id']));
				unset( $all_items[ $profile_id ] );
				WC_Order_Export_Pro_Manage::save_export_settings_collection( $mode, $all_items );

				$url = remove_query_arg( array( 'wc_oe', 'profile_id', 'woe_nonce' ) );
				wp_redirect( $url );

				break;
			case 'change_profile_statuses':
				if ( ! isset( $_REQUEST['chosen_profiles'] ) || empty(  $_REQUEST['chosen_profiles']  ) ) {
					break;
				}
				if ( ! isset( $_REQUEST['chosen_profiles'] ) AND ! isset( $_REQUEST['doaction'] ) AND - 1 == $_REQUEST['doaction'] ) {
					break;
				}
                $chosen_profiles = isset($_REQUEST['chosen_profiles']) ? sanitize_text_field(wp_unslash($_REQUEST['chosen_profiles'])) : '';
                $doaction        = isset($_REQUEST['doaction']) ? sanitize_text_field( wp_unslash($_REQUEST['doaction']) ) : '';
				$chosen_profiles = explode( ',', sanitize_text_field($chosen_profiles));

				foreach ( $chosen_profiles as $profile_id ) {
					if ( 'activate' == $doaction ) {
						$all_items[ $profile_id ]['use_as_bulk'] = 'on';
					} elseif ( 'deactivate' == $doaction ) {
						unset( $all_items[ $profile_id ]['use_as_bulk'] );
					} elseif ( 'delete' == $doaction ) {
						unset( $all_items[ $profile_id ] );
					}
				}
				WC_Order_Export_Pro_Manage::save_export_settings_collection( $mode, $all_items );
				$url = remove_query_arg( array( 'wc_oe', 'chosen_profiles', 'doaction', 'woe_nonce' ) );
				wp_redirect( $url );
				break;
		}

		//code to copy default settings as profile
		$profiles = WC_Order_Export_Pro_Manage::get_export_settings_collection( $mode );
		$free_job = WC_Order_Export_Pro_Manage::get_export_settings_collection( WC_Order_Export_Pro_Manage::EXPORT_NOW );
		if ( empty( $profiles ) AND ! empty( $free_job ) ) {
			$free_job['title'] = __( 'Copied from "Export now"', 'woocommerce-order-export' );
			$free_job['mode']  = $mode;
			$profiles[1]       = $free_job;
			update_option( WC_Order_Export_Pro_Manage::settings_name_profiles, $profiles, false );
		}

		$this->render_template( 'tab/profiles', array( 'ajaxurl' => $ajaxurl ), $this->get_template_path() );
	}

	public function add_top_to_view( $settings ) {
		$this->render_template( 'top-profile', array( 'settings' => $settings ), $this->get_template_path() );
	}

	public function add_export_date_range( $settings ) {
		$this->render_template( 'export-date-range', array( 'settings' => $settings ), $this->get_template_path() );
	}
}
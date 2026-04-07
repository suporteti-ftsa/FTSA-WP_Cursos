<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Order_Export_Pro_Admin {

	protected $methods_allowed_for_guests;
	protected $url_plugin;
	protected $changed_order_id;


	public function __construct() {
		add_action( 'woe_order_export_admin_init', array( $this, 'init' ) );
	}

	public function init( $plugin ) {

		$this->url_plugin = dirname( plugin_dir_url( __FILE__ ) ) . '/';

		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_filter( 'cron_schedules', array( 'WC_Order_Export_Cron', 'create_custom_schedules' ), 999, 1 );
		add_action( 'wc_export_cron_global', array( 'WC_Order_Export_Cron', 'wc_export_cron_global_f' ) );

		//for direct calls
		add_action( 'wp_ajax_order_exporter_run', array( $this, 'ajax_gate_guest' ) );
		add_action( 'wp_ajax_nopriv_order_exporter_run', array( $this, 'ajax_gate_guest' ) );

		$this->methods_allowed_for_guests = array( 'run_cron_jobs', 'run_one_job', 'run_one_scheduled_job' );

		// order actions
		add_action( 'woocommerce_order_status_changed', array( $this, 'wc_order_status_changed' ), 1000, 3 );
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'wc_subscription_status_changed' ), 1000, 3 );
		add_action( 'woe_process_status_change_job_from_queue', array( $this, 'wc_order_status_changed_job_from_queue' ), 10, 3 );

		add_filter( 'woe_order_export_admin_tabs', array( $this, 'get_tabs' ), 10, 1 );

		add_filter( 'woe_get_main_settings', array( 'WC_Order_Export_Pro_Main_Settings', 'get_settings' ), 10, 1 );

		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'export_orders_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'export_orders_bulk_action_process' ), 10, 3 );

		//HPOS bulk actions
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'export_orders_bulk_action' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'export_orders_bulk_action_process' ), 10, 3 );

		add_filter( 'woe_load_custom_formatter_zapier', array( $this, 'load_custom_formatter_zapier' ), 10, 1 );

		add_action( 'woe_thematic_enqueue_scripts', array( $this, 'thematic_enqueue_scripts' ) );
		add_action( 'woe_thematic_enqueue_scripts_settings_form', array(
			$this,
			'thematic_enqueue_scripts_settings_form',
		) );

		// activate CRON hook if it was removed
		add_action( 'wp_loaded', function () {
			WC_Order_Export_Cron::try_install_job();
		} );

		$settings = WC_Order_Export_Main_Settings::get_settings();
		new WC_Order_Export_Zapier_Engine( $settings );

		add_filter( 'woe_global_ajax_handler', function ( $handler ) {
			return new WC_Order_Export_Pro_Ajax();
		}, 10, 1 );

		//extra links in >Plugins
		add_filter( 'plugin_action_links_' . WOE_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

		add_filter( 'woe_settings_page_prepare', function($settings ){
		    if( $settings['mode'] == 'now') {
				$main_settings = WC_Order_Export_Main_Settings::get_settings();
				$range	       = WOE_Helper_DateRangeExportNow::get_range_by_key($main_settings['default_date_range_for_export_now']);
				if( is_array($range) ) {
					$settings['from_date'] = $range['start'];
					$settings['to_date']   = $range['end'];
				}
		    }
		    return $settings;
		});

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $woe_order_post_type = self::get_order_post_type_by_params($_GET);

		if ( $woe_order_post_type ) {
		    self::init_order_post_type($woe_order_post_type);
		}

		add_action( 'woe_order_export_admin_ajax_gate_before', function () {

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            $params = stripslashes_deep( $_POST );

		    $json = isset($params['json']) ? json_decode( $params['json'], true ) : array();

		    $woe_order_post_type = isset($params['woe_order_post_type']) ? $params['woe_order_post_type'] : null;

		    if ( ! $woe_order_post_type ) {
			$woe_order_post_type = isset($json['settings']['post_type']) ? $json['settings']['post_type'] : null;
		    }

		    if ( ! $woe_order_post_type ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $woe_order_post_type = self::get_order_post_type_by_params($_GET);
		    }

		    if ( $woe_order_post_type ) {
			self::init_order_post_type($woe_order_post_type);
		    }
		});

		new WC_Order_Export_Subscription();
	}

	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-order-export' );
		load_textdomain( 'woocommerce-order-export',
			WP_LANG_DIR . '/woocommerce-order-export/woocommerce-order-export-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-order-export', false,
			plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/i18n/languages' );
	}

	//on status change, for Woocommerce Subscriptions
	public function wc_subscription_status_changed( $subscription, $new_status, $old_status ) {
		add_filter( 'woe_run_only_shop_subscription_export', '__return_true' );
		$this->wc_order_status_changed( $subscription->get_id(), $old_status, $new_status );
		remove_filter( 'woe_run_only_shop_subscription_export', '__return_true' );
	}


	//on status change, for Woocommerce Orders
	public function wc_order_status_changed( $order_id, $old_status, $new_status ) {
		global $wp_filter;

		$main_settings = WC_Order_Export_Main_Settings::get_settings();
		$all_items = get_option( WC_Order_Export_Pro_Manage::settings_name_actions, array() );
		if ( empty( $all_items ) ) {
			return;
		}
		$old_status = is_string( $old_status ) && strpos( $old_status, 'wc-' ) !== 0 ? "wc-{$old_status}" : $old_status;
		$new_status = is_string( $new_status ) && strpos( $new_status, 'wc-' ) !== 0 ? "wc-{$new_status}" : $new_status;

		$this->changed_order_id = $order_id;
		add_filter( 'woe_sql_get_order_ids_where', array( $this, "filter_by_changed_order" ), 10, 2 );

		$logger         = function_exists( "wc_get_logger" ) ? wc_get_logger() : false; //new logger in 3.0+
		$logger_context = array( 'source' => 'woocommerce-order-export' );

		$failed_jobs = array();

		//apply jobs in same order as they are shown at tab "Status Change"
		uasort( $all_items , function ( $a, $b ) {
			$a['priority'] = isset( $a['priority'] ) ? $a['priority'] : 0;
			$b['priority'] = isset( $b['priority'] ) ? $b['priority'] : 0;
			$compare_results = $a['priority'] - $b['priority'];
			return apply_filters( "woe_sort_displayed_jobs", $compare_results, $a, $b );
		} );

		//stop Jetpack , we need local images for previews
		add_filter("jetpack_photon_override_image_downsize","__return_true");

		$pos = 1;
		foreach ( $all_items as $key => $item ) {
			$item = WC_Order_Export_Pro_Manage::get( WC_Order_Export_Pro_Manage::EXPORT_ORDER_ACTION, $key );
			if ( isset( $item['active'] ) && ! $item['active'] ) {
				continue;
			}

			$woe_order_post_type = isset($item['post_type']) ? $item['post_type'] : 'shop_order';

			//  differentiate status change for Woocommerce Subscriptions and Orders
			if( apply_filters( 'woe_run_only_shop_subscription_export', false) AND $woe_order_post_type!='shop_subscription' )
				continue;
			if( !apply_filters( 'woe_run_only_shop_subscription_export', false) AND $woe_order_post_type=='shop_subscription' )
				continue;

			WC_Order_Export_Pro_Admin::set_order_post_type($woe_order_post_type);

			// use empty for ANY status
			if ( ( empty( $item['from_status'] ) OR in_array( $old_status, $item['from_status'] ) )
			     AND
			     ( empty( $item['to_status'] ) OR in_array( $new_status, $item['to_status'] ) )
			) {
				$filters = $wp_filter;//remember hooks/filters
				$item['destination']['changed_order_id'] = $order_id;
				do_action( 'woe_order_action_started', $order_id, $item );

				if($main_settings['status_change_use_queue']) {
					$result = [];
					$result_str = WC_Order_Export_Pro_Admin::add_job_to_queue($woe_order_post_type, $key, $order_id, $pos++);
				}
				else {
					$result = WC_Order_Export_Pro_Engine::build_files_and_export( $item );
					$result_str = implode("<br>\n\r", array_map(function ($v) { return $v['text']; }, $result));
				}

                /* translators: The result of the job when changing the order status */
                $output = sprintf( __( 'Status change job #%1$s for order #%2$s. Result: %3$s', 'woocommerce-order-export' ),
					$key, $order_id, $result_str );
				// log if required
				if ( $logger AND ! empty( $item['log_results'] ) ) {
					$logger->info( $output, $logger_context );
				}

				//TEST
				$results[] = $output;

				$failed_messages = array();

				foreach ($result as $message) {
					if (!$message['status']  && ( ! isset( $message['empty_export'] ) || true !== $message['empty_export'] ) ) {
						$failed_messages[] = $message['text'];
					}
				}

				if ( $failed_messages ) {
                    /* translators: Changing the order status */
					$start_message = sprintf( __( 'Status change for order #%s.', 'woocommerce-order-export' ),$order_id);
					array_unshift( $failed_messages, $start_message);
					$failed_jobs[] = array('title' => $item['title'], 'messages' => $failed_messages);
				}
				//TEST

				do_action( 'woe_order_action_completed', $order_id, $item, $result_str );
				$wp_filter = $filters;//reset hooks/filters
			}
		}

		if( ! empty( $failed_jobs ) ) {
			self::email_failed_jobs( $failed_jobs, $main_settings );
		}

		remove_filter( 'woe_sql_get_order_ids_where', array( $this, "filter_by_changed_order" ), 10 );
	}

	public static function add_job_to_queue($woe_order_post_type, $key, $order_id, $pos) {
		$result = wp_schedule_single_event( apply_filters('woe_status_change_job_queue_start_time', time()+1,$pos),
										   'woe_process_status_change_job_from_queue',
										   array($woe_order_post_type, $key, $order_id),
										   true);
		if(is_wp_error($result)) {
			return __( 'failed adding to queue!', 'woocommerce-order-export' ) . $result->get_error_message();
		}
		else
			return __( 'successfully added to queue', 'woocommerce-order-export' );
	}

	public function wc_order_status_changed_job_from_queue( $woe_order_post_type, $key, $order_id ) {
		global $wp_filter;
		//init common
		$logger         = function_exists( "wc_get_logger" ) ? wc_get_logger() : false; //new logger in 3.0+
		$logger_context = array( 'source' => 'woocommerce-order-export' );

		WC_Order_Export_Pro_Admin::set_order_post_type($woe_order_post_type);
		//init order
		$item = WC_Order_Export_Pro_Manage::get( WC_Order_Export_Pro_Manage::EXPORT_ORDER_ACTION, $key );
		$this->changed_order_id = $item['destination']['changed_order_id'] = $order_id;
		add_filter( 'woe_sql_get_order_ids_where', array( $this, "filter_by_changed_order" ), 10, 2 );
		$filters = $wp_filter;//remember hooks/filters
		//start
		$result = WC_Order_Export_Pro_Engine::build_files_and_export( $item );
		$result_str = implode("<br>\n\r", array_map(function ($v) { return $v['text']; }, $result));
        /* translators: The result of the job when changing the order status */
		$output = sprintf( __( '[QUEUE] Status change job #%1$s for order #%2$s. Result: %3$s', 'woocommerce-order-export' ),
			$key, $order_id, $result_str );
		// log if required
		if ( $logger AND ! empty( $item['log_results'] ) ) {
			$logger->info( $output, $logger_context );
		}
		//done
		$wp_filter = $filters;//reset hooks/filters
		remove_filter( 'woe_sql_get_order_ids_where', array( $this, "filter_by_changed_order" ), 10 );
	}


	public static function email_failed_jobs(array $failed_jobs, $main_settings) {

	    $to = $main_settings['notify_failed_jobs_email_recipients'];

	    if ( !$main_settings['notify_failed_jobs'] || !$failed_jobs || !$to ) {
			return;
	    }

	    $subject = $main_settings['notify_failed_jobs_email_subject'];

	    if ( !$subject ) {
			$subject = __( 'Status change jobs failed', 'woocommerce-order-export' );
	    }

	    $headers = array("Content-Type: text/plain");

	    $errors = array();

	    foreach ($failed_jobs as $job) {
			$errors[] = sprintf('%s: %s', $job['title'], implode(', ', $job['messages']));
	    }

	    $message = implode("\n\r", $errors);

	    wp_mail($to, $subject, $message, $headers);
	}

	public function filter_by_changed_order( $where, $settings ) {
		$where[] = "orders.ID = " . $this->changed_order_id;

		return $where;
	}

	//TODO: debug!
	public function ajax_gate_guest() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_REQUEST['method'] ) AND in_array( $_REQUEST['method'], $this->methods_allowed_for_guests ) ) {

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $met = isset($_REQUEST['method']) ? sanitize_text_field(wp_unslash( $_REQUEST['method'] )) : '';
			$method = 'ajax_' . $met;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST = array_map( 'stripslashes_deep', $_POST );

			$this->validate_url_key();

			if ( method_exists( 'WC_Order_Export_Pro_Ajax', $method ) ) {
				$ajax = new WC_Order_Export_Pro_Ajax();
				$ajax->$method();
			}
		}
		die();
	}

	public function validate_url_key() {

		$main_settings = WC_Order_Export_Main_Settings::get_settings();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_REQUEST['key'] ) OR $_REQUEST['key'] != $main_settings['cron_key'] ) {
            esc_html_e( 'Wrong key for cron url!', 'woocommerce-order-export' );
			die();
		}
	}

	public function get_tabs( $tabs ) {

		return array(
			WC_Order_Export_Pro_Admin_Tab_Export_Now::get_key()         => new WC_Order_Export_Pro_Admin_Tab_Export_Now(),
			WC_Order_Export_Pro_Admin_Tab_Profiles::get_key()           => new WC_Order_Export_Pro_Admin_Tab_Profiles(),
			WC_Order_Export_Pro_Admin_Tab_Status_Change_Jobs::get_key() => new WC_Order_Export_Pro_Admin_Tab_Status_Change_Jobs(),
			WC_Order_Export_Pro_Admin_Tab_Schedule_Jobs::get_key()      => new WC_Order_Export_Pro_Admin_Tab_Schedule_Jobs(),
			WC_Order_Export_Pro_Admin_Tab_Settings::get_key()           => new WC_Order_Export_Pro_Admin_Tab_Settings(),
			WC_Order_Export_Pro_Admin_Tab_Tools::get_key()              => new WC_Order_Export_Pro_Admin_Tab_Tools(),
			WC_Order_Export_Pro_Admin_Tab_License::get_key()            => new WC_Order_Export_Pro_Admin_Tab_License(),
			WC_Order_Export_Pro_Admin_Tab_Help::get_key()               => new WC_Order_Export_Pro_Admin_Tab_Help(),
		);
	}

	public function export_orders_bulk_action( $actions ) {
		$new_actions = array();
		$all_items = WC_Order_Export_Pro_Manage::get_export_settings_collection( WC_Order_Export_Pro_Manage::EXPORT_PROFILE );
		foreach ( $all_items as $job_id => $job ) {
			if ( apply_filters("woe_use_as_bulk" , isset( $job['use_as_bulk'] ) ,  $job) ) {
                /* translators: Exporting job to a profile */
				$new_actions[ 'woe_export_selected_orders_profile_' . $job_id ] = sprintf( __( "Export as profile '%s'",
					'woocommerce-order-export' ), $job['title'] );
			}
		}

		if( empty($actions) ) $actions = array(); //fix if another plugin damaged $actions

		return $new_actions+$actions;
	}

	public function export_orders_bulk_action_process( $redirect_to, $action, $ids ) {
		$new_redirect_to = false;
		if ( preg_match( '/woe_export_selected_orders_profile_(\d+)/', $action, $matches ) ) {
			if ( isset( $matches[1] ) ) {
				$id          = $matches[1];
				$new_redirect_to = admin_url( 'admin-ajax.php' ) . "?action=order_exporter&method=export_download_bulk_file&export_bulk_profile={$id}&ids=" . join( ',', $ids );
			}
		}

		if ( $new_redirect_to ) {
			wp_redirect( $new_redirect_to );
			exit();
		}

		return $redirect_to;
	}

	public function load_custom_formatter_zapier() {

		include_once WOE_PRO_PLUGIN_BASEPATH . '/classes/formats/class-woe-formatter-zapier.php';

		return true;
	}

	public function thematic_enqueue_scripts() {

		wp_enqueue_script( 'woe_pro_wc_tables', $this->url_plugin . 'assets/js/wc-tables.js', array( 'jquery' ),
			WOE_VERSION, true );

		wp_enqueue_style( 'woe_pro_export', $this->url_plugin . 'assets/css/export.css', array(), WOE_VERSION );
	}

	public function thematic_enqueue_scripts_settings_form() {

		wp_enqueue_script( 'woe_pro_buttons', $this->url_plugin . 'assets/js/buttons.js', array( 'jquery' ),
			WOE_VERSION, true );

		wp_enqueue_script( 'woe_pro_destinations', $this->url_plugin . 'assets/js/destinations.js', array( 'jquery' ),
			WOE_VERSION, true  );

		wp_enqueue_script( 'woe_pro_schedules', $this->url_plugin . 'assets/js/schedules.js', array( 'jquery' ),
			WOE_VERSION, true  );

		wp_enqueue_script( 'woe_date_time_picker', $this->url_plugin . 'assets/js/date-time-picker.js', array('jquery', 'settings-form'), WOE_VERSION, true  );
	}

	public function add_action_links( $links ) {
		$key    = WC_Order_Export_Pro_Admin_Tab_License::get_key();
		$url    = esc_url( add_query_arg( array( 'tab' => $key ), menu_page_url( 'wc-order-export', false ) ) );
		$status = get_option( 'edd_woe_license_status' );

		if ( empty( $status ) || $status !== 'valid' ) {
			$links[] = '<a style="color:red" href="' . $url . ' ">' . __( 'Activate license', 'woocommerce-order-export' ) . '</a>';
		}

		return $links;
	}

	public static function init_order_post_type($woe_order_post_type) {

	    self::set_order_post_type($woe_order_post_type);

	    switch ($woe_order_post_type) {
		case 'shop_subscription':
		    add_filter('woe_settings_order_statuses', function($statuses) {
			return function_exists('wcs_get_subscription_statuses') ? wcs_get_subscription_statuses() : array();
		    });
		    break;

		case 'shop_order_refund':
		    add_filter('woe_settings_order_statuses', function($statuses) {

			$_statuses = wc_get_order_statuses();

			return array_filter($_statuses, function ($status) {
			    return $status === 'wc-completed';
			}, ARRAY_FILTER_USE_KEY);
		    });
		    break;
	    }
	}

	public static function set_order_post_type($woe_order_post_type) {
	    WC_Order_Export_Data_Extractor_UI::$object_type = $woe_order_post_type;
	    WC_Order_Export_Data_Extractor::$object_type    = $woe_order_post_type;
	}

	public static function get_order_post_type_by_params(array $params) {

	    if (isset($params['woe_post_type'])) {
		return $params['woe_post_type'];
	    }

	    $id   = 0;
	    $mode = null;

	    if (isset($params['wc_oe']) && $params['wc_oe'] === 'edit_profile') {
		$mode = WC_Order_Export_Pro_Manage::EXPORT_PROFILE;
		$id   = isset($params['profile_id']) ? $params['profile_id'] : null;
	    }

	    if (isset($params['wc_oe']) && $params['wc_oe'] === 'edit_action') {
		$mode = WC_Order_Export_Pro_Manage::EXPORT_ORDER_ACTION;
		$id   = isset($params['action_id']) ? $params['action_id'] : null;
	    }

	    if (isset($params['wc_oe']) && $params['wc_oe'] === 'edit_schedule') {
		$mode = WC_Order_Export_Pro_Manage::EXPORT_SCHEDULE;
		$id   = isset($params['schedule_id']) ? $params['schedule_id'] : null;
	    }

	    if (isset($params['method']) &&  $params['method'] === 'run_one_job' && $params['tab'] === 'profiles') {
		$mode = WC_Order_Export_Pro_Manage::EXPORT_PROFILE;
		$id   = isset($params['profile']) ? $params['profile'] : null;
	    }

	    if (isset($params['method']) &&  $params['method'] === 'run_one_job' && $params['tab'] === 'schedules') {
		$mode = WC_Order_Export_Pro_Manage::EXPORT_SCHEDULE;
		$id   = isset($params['schedule']) ? $params['schedule'] : null;
	    }

	    if (!$mode || !$id) {
		return null;
	    }

	    $settings = WC_Order_Export_Pro_Manage::get($mode, $id, true); //we need only post_type!

	    return isset($settings['post_type']) ? $settings['post_type'] : 'shop_order';
	}

}

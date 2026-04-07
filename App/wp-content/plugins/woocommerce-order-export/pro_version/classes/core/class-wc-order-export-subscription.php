<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Order_Export_Subscription {
    private $sub;
    private $sub_fields;
    private $sub_fields_all;
    private $sub_fields_active;


    function __construct() {
		add_filter('init', array($this, 'set_fields'));
		add_filter('woe_get_order_segments', array($this, 'add_order_segments'));
		add_filter('woe_get_order_fields_subscription', array($this, 'add_order_fields') );
		add_action('woe_order_export_started', array($this, 'get_subscription_details') );
		add_filter('woe_fetch_order_row', array($this, 'fill_new_columns'), 10, 2);
    }

    function add_order_segments($segments) {
		if ( function_exists('wcs_get_subscription') ) {
		    $segments['subscription'] = __( 'Subscription', 'woocommerce-order-export' );
		}
		return $segments;
    }

    function set_fields() {
        $this->sub_fields['sub_number']	= array( 'number', __( 'Subscription Number', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_status']		= array( 'string', __( 'Subscription Status', 'woocommerce-order-export' ) );
        $this->sub_fields['sub_renewal_frequency'] = array( 'string', __( 'Subscription Renewal Frequency', 'woocommerce-order-export' ) );
        $this->sub_fields['sub_renewal_interval'] = array( 'string', __( 'Subscription Renewal Interval', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_start_date']	= array( 'date', __( 'Subscription Start Date', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_end_date']	= array( 'date', __( 'Subscription End Date', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_cancel_date']	= array( 'date', __( 'Subscription Cancelled Date', 'woocommerce-order-export' ) );
        $this->sub_fields['sub_first_payment'] = array( 'date', __( 'Subscription First Payment', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_next_payment']	= array( 'date', __( 'Subscription Next Payment', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_last_order_date'] = array( 'date', __( 'Subscription Last Order Date', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_num_renewals']	= array( 'number', __( 'Subscription Number of Renewals', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_total_orders']	= array( 'number', __( 'Subscription Total Orders', 'woocommerce-order-export' ) );
		$this->sub_fields['sub_total_amount_paid']	= array( 'money', __( 'Subscription Total Amount Paid', 'woocommerce-order-export' ) );
		$this->sub_fields_all = array_keys($this->sub_fields);
	}

    function add_order_fields($fields) {
		foreach($this->sub_fields as $key=>$data)  {
			list($format,$label) = $data;
			$fields[$key]	= array('segment' => 'subscription', 'format' => $format, 'label' => $label);
		}
		return $fields;
    }

    function get_subscription_details($order_id) {
		if( !isset($this->sub_fields_active) ) {
			$this->sub_fields_active = array();
			foreach(WC_Order_Export_Engine::$current_job_settings["order_fields"] as $field) {
				if( isset($field['key']) AND in_array($field['key'], $this->sub_fields_all) )
					$this->sub_fields_active[$field['key']] = 1;					
			}
		}

	    $this->sub = array();

		$sub = false;
	    if(WC_Order_Export_Data_Extractor::$object_type === 'shop_subscription' && function_exists('wcs_get_subscription')) {
			$sub = wcs_get_subscription($order_id);
		} elseif(WC_Order_Export_Data_Extractor::$object_type === 'shop_order' && function_exists('wcs_get_subscriptions_for_order')) {
			$subs = wcs_get_subscriptions_for_order($order_id, array('order_type'=>'any'));
			$subs = array_values($subs);
			if($subs)
				$sub = array_shift($subs);
		}
		if( $sub ) {
                if( isset($this->sub_fields_active['sub_number']) )
                    $this->sub['sub_number']	= $sub->get_id();

				if( isset($this->sub_fields_active['sub_status']) )
					$this->sub['sub_status']	= $sub->get_status();

                if ( isset($this->sub_fields_active['sub_renewal_frequency']) )
                    $this->sub['sub_renewal_frequency'] = $sub->is_one_payment() ? __( "One-time payment", 'woocommerce-order-export' ) : $sub->get_billing_period();
                if ( isset($this->sub_fields_active['sub_renewal_interval']) )
                    $this->sub['sub_renewal_interval'] = $sub->is_one_payment() ?  '' : $sub->get_billing_interval();

				if( isset($this->sub_fields_active['sub_start_date']) )
					$this->sub['sub_start_date']	= gmdate( "Y-m-d", $sub->get_time( 'start', 'site' ) );

				if( isset($this->sub_fields_active['sub_end_date']) )
					$this->sub['sub_end_date']	= gmdate( "Y-m-d", $sub->get_time( 'end', 'site' ) );

				if( isset($this->sub_fields_active['sub_cancel_date']) )
					$this->sub['sub_cancel_date']	= gmdate( "Y-m-d", $sub->get_time( 'cancelled', 'site' ) );

                if( isset($this->sub_fields_active['sub_first_payment']) ) {
                    $datetime = '';

                    $parent_order = $sub->get_parent();
                    if ( $parent_order ) {
                        if ( $parent_order->get_date_paid() ) {
                            $datetime = wc_format_datetime( $parent_order->get_date_paid(), get_option('time_format'));
                        }
                    }

                    $this->sub['sub_first_payment'] = $datetime;
                }

                if( isset($this->sub_fields_active['sub_next_payment']) )
					$this->sub['sub_next_payment']	= $sub->get_time( 'next_payment_date', 'site' ) ? gmdate( "Y-m-d", $sub->get_time( 'next_payment_date', 'site' ) ) : '-';

				if( isset($this->sub_fields_active['sub_last_order_date']) )
					$this->sub['sub_last_order_date'] = $sub->get_time( 'last_order_date_created', 'site' ) ? gmdate( "Y-m-d", $sub->get_time( 'last_order_date_created', 'site' ) ) : '-';

				if( isset($this->sub_fields_active['sub_num_renewals']) )
					$this->sub['sub_num_renewals'] = count( array_unique( $sub->get_related_orders( 'ids', array('renewal') ) ) );

				if( isset($this->sub_fields_active['sub_total_orders']) )
					$this->sub['sub_total_orders'] = count( array_unique( $sub->get_related_orders( 'ids', 'any' ) ) );

				if( isset($this->sub_fields_active['sub_total_amount_paid']) ) {
					$this->sub['sub_total_amount_paid'] = 0;
					foreach($sub->get_related_orders( 'all', 'any' ) as $related_order) {
						if ( null !== $related_order->get_date_paid() ) 
							$this->sub['sub_total_amount_paid'] += $related_order->get_total();
					}
				}

		}	//if order has subscription

	    return $order_id;
    }

    // add new values to row
    function fill_new_columns($row, $order_id) {
		foreach($this->sub as $k => $v) {
		    if(isset($row[$k])) {
				$row[$k] = $v;
		    }
		}
		return $row;
    }
}
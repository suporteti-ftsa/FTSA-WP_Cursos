<?php
if (!defined('ABSPATH')) exit;
class FTSA_Checkout_Fixes {
    public function __construct() {
        add_action('user_register', [$this, 'capture_user_register'], 99, 1);
        add_action('woocommerce_created_customer', [$this, 'capture_created_customer'], 99, 3);
        add_action('woocommerce_checkout_order_processed', [$this, 'retry_user_created_after_checkout'], 20, 1);
        add_action('ftsa_retry_user_created', [$this, 'retry_user_created'], 10, 2);
    }

    public function capture_user_register($user_id) {
        $phone = $this->resolve_phone($user_id, null);
        if ($phone) {
            update_user_meta($user_id, 'billing_phone', $phone);
        } else {
            $this->debug('phone_not_persisted_yet', ['user_id' => $user_id, 'source' => 'user_register']);
            if (!wp_next_scheduled('ftsa_retry_user_created', [$user_id, 'user_register'])) {
                wp_schedule_single_event(time() + 90, 'ftsa_retry_user_created', [$user_id, 'user_register']);
            }
        }
    }

    public function capture_created_customer($customer_id) {
        $phone = $this->resolve_phone($customer_id, null);
        if ($phone) {
            update_user_meta($customer_id, 'billing_phone', $phone);
        } else {
            $this->debug('phone_not_persisted_yet', ['user_id' => $customer_id, 'source' => 'woocommerce_created_customer']);
            if (!wp_next_scheduled('ftsa_retry_user_created', [$customer_id, 'woocommerce_created_customer'])) {
                wp_schedule_single_event(time() + 90, 'ftsa_retry_user_created', [$customer_id, 'woocommerce_created_customer']);
            }
        }
    }

    public function retry_user_created_after_checkout($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        if (!$order) return;
        $user_id = (int) $order->get_user_id();
        if (!$user_id) return;
        $phone = $this->resolve_phone($user_id, $order);
        if ($phone) {
            update_user_meta($user_id, 'billing_phone', $phone);
            do_action('ftsa_notify_event', 'user.created', ['user_id' => $user_id, 'order_id' => $order_id, 'phone' => $phone, 'diagnostic' => 'retry_after_checkout']);
        } else {
            $this->debug('phone_missing_real', ['user_id' => $user_id, 'order_id' => $order_id, 'source' => 'checkout_processed']);
        }
    }

    public function retry_user_created($user_id, $source = 'retry') {
        $user_id = (int) $user_id;
        if (!$user_id) return;
        $phone = $this->resolve_phone($user_id, null);
        if ($phone) {
            update_user_meta($user_id, 'billing_phone', $phone);
            do_action('ftsa_notify_event', 'user.created', ['user_id' => $user_id, 'phone' => $phone, 'diagnostic' => 'retry_scheduled', 'source' => $source]);
        } else {
            $this->debug('phone_missing_real', ['user_id' => $user_id, 'source' => $source]);
        }
    }

    private function resolve_phone($user_id, $order = null) {
        $phone = '';
        if ($user_id) $phone = get_user_meta($user_id, 'billing_phone', true);
        if (!$phone and isset($_POST['billing_phone'])) {
            $raw = is_string($_POST['billing_phone']) ? wp_unslash($_POST['billing_phone']) : '';
            $phone = sanitize_text_field($raw);
        }
        if (!$phone and $order and method_exists($order, 'get_billing_phone')) {
            $phone = $order->get_billing_phone();
        }
        return trim((string) $phone);
    }

    private function debug($event, $context = []) {
        do_action('ftsa_notify_event', 'system.debug', ['channel' => 'system', 'provider' => 'internal', 'event_name' => $event, 'debug_context' => $context]);
    }
}

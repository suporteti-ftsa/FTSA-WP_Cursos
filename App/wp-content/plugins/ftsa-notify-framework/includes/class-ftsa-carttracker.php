<?php
if (!defined('ABSPATH')) exit;
class FTSA_CartTracker {
    const META_LAST_ACTIVITY = 'ftsa_last_cart_activity';
    const META_CART = 'ftsa_cart_snapshot';
    const META_CART_HASH = 'ftsa_cart_hash';
    const META_FLAG = 'ftsa_abandoned_flag';
    const META_STEP1 = 'ftsa_abandoned_step1_sent';
    const META_STEP2 = 'ftsa_abandoned_step2_sent';
    const META_LAST_REASON = 'ftsa_cart_last_reason';

    public function __construct() {
        add_action('woocommerce_add_to_cart', [$this, 'track']);
        add_action('woocommerce_cart_updated', [$this, 'track']);
        add_action('woocommerce_before_checkout_form', [$this, 'track']);
        add_action('wp_loaded', [$this, 'track_from_session'], 20);
        add_action('woocommerce_checkout_order_processed', [$this, 'mark_recovered'], 10, 1);
        add_action('ftsa_check_abandoned_carts', [$this, 'process']);
        add_action('init', [$this, 'schedule']);
        add_filter('cron_schedules', [$this, 'cron_interval']);
    }

    public function cron_interval($schedules) {
        $schedules['every_5_minutes'] = ['interval' => 300, 'display' => 'Every 5 Minutes'];
        return $schedules;
    }

    public function schedule() {
        if (!wp_next_scheduled('ftsa_check_abandoned_carts')) {
            wp_schedule_event(time() + 60, 'every_5_minutes', 'ftsa_check_abandoned_carts');
            $this->debug('cart_cron_scheduled', ['scheduled' => true]);
        }
    }

    public function track() {
        if (!(bool) get_option('ftsa_track_cart_enabled', get_option('ftsa_track_cart', 0))) {
            $this->debug('cart_track_skipped', ['reason' => 'tracking_disabled']);
            return;
        }
        if (!function_exists('WC') || !WC()->cart) {
            $this->debug('cart_track_skipped', ['reason' => 'cart_unavailable']);
            return;
        }
        if (!is_user_logged_in()) {
            $this->debug('cart_track_skipped', ['reason' => 'user_not_logged_in']);
            return;
        }
        $user_id = get_current_user_id();
        $snapshot = $this->get_cart_snapshot();
        if (empty($snapshot['items'])) {
            $this->debug('cart_track_skipped', ['reason' => 'cart_empty', 'user_id' => $user_id]);
            return;
        }
        update_user_meta($user_id, self::META_LAST_ACTIVITY, time());
        update_user_meta($user_id, self::META_CART, wp_json_encode($snapshot));
        update_user_meta($user_id, self::META_CART_HASH, md5(wp_json_encode($snapshot['items'])));
        update_user_meta($user_id, self::META_LAST_REASON, 'tracked');
        $this->debug('cart_track_saved', ['user_id' => $user_id, 'items' => count($snapshot['items'])]);
    }

    public function track_from_session() {
        if (!is_user_logged_in()) return;
        if (!function_exists('is_cart') || !function_exists('is_checkout')) return;
        if (!(is_cart() || is_checkout())) return;
        $this->track();
    }

    public function process() {
        $this->debug('cart_cron_started', ['time' => current_time('mysql')]);
        if (!(bool) get_option('ftsa_track_cart_enabled', get_option('ftsa_track_cart', 0))) {
            $this->debug('cart_cron_skipped', ['reason' => 'tracking_disabled']);
            return;
        }
        $users = get_users(['meta_key' => self::META_LAST_ACTIVITY, 'fields' => ['ID']]);
        $t1 = max(1, (int) get_option('ftsa_abandoned_time_1', 30)) * 60;
        $t2_enabled = (bool) get_option('ftsa_abandoned_step2_enabled', 1);
        $t2 = max(1, (int) get_option('ftsa_abandoned_time_2', 960)) * 60;
        if (empty($users)) {
            $this->debug('cart_cron_skipped', ['reason' => 'no_candidates']);
            return;
        }
        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            $last = (int) get_user_meta($user_id, self::META_LAST_ACTIVITY, true);
            $snapshot = json_decode((string) get_user_meta($user_id, self::META_CART, true), true);
            if (!$last) { $this->debug('cart_cron_dispatch_skipped', ['user_id' => $user_id, 'reason' => 'no_last_activity']); continue; }
            if (empty($snapshot['items'])) { $this->debug('cart_cron_dispatch_skipped', ['user_id' => $user_id, 'reason' => 'cart_empty_snapshot']); continue; }
            $elapsed = time() - $last;
            if ($elapsed < $t1) { $this->debug('cart_abandoned_threshold_not_reached', ['user_id' => $user_id, 'elapsed' => $elapsed, 'threshold' => $t1]); continue; }
            $phone = trim((string) get_user_meta($user_id, 'billing_phone', true));
            if (!$phone) { $this->debug('cart_cron_dispatch_skipped', ['user_id' => $user_id, 'reason' => 'phone_missing_real']); continue; }

            if (!get_user_meta($user_id, self::META_STEP1, true)) {
                $this->debug('cart_cron_dispatch_attempt', ['user_id' => $user_id, 'event' => 'cart.abandoned']);
                do_action('ftsa_notify_event', 'cart.abandoned', ['user_id' => $user_id, 'phone' => $phone, 'cart' => $snapshot, 'diagnostic' => 'dispatch_ok']);
                update_user_meta($user_id, self::META_STEP1, 1);
                update_user_meta($user_id, self::META_FLAG, 1);
                update_user_meta($user_id, self::META_LAST_REASON, 'cart_abandoned_sent');
                $this->debug('cart_cron_dispatch_ok', ['user_id' => $user_id, 'event' => 'cart.abandoned']);
            }

            if ($t2_enabled && $elapsed >= $t2 && !get_user_meta($user_id, self::META_STEP2, true)) {
                $this->debug('cart_cron_dispatch_attempt', ['user_id' => $user_id, 'event' => 'payment.reminder']);
                do_action('ftsa_notify_event', 'payment.reminder', ['user_id' => $user_id, 'phone' => $phone, 'cart' => $snapshot, 'diagnostic' => 'dispatch_ok']);
                update_user_meta($user_id, self::META_STEP2, 1);
                update_user_meta($user_id, self::META_LAST_REASON, 'payment_reminder_sent');
                $this->debug('cart_cron_dispatch_ok', ['user_id' => $user_id, 'event' => 'payment.reminder']);
            }
        }
    }

    public function mark_recovered($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        if (!$order) return;
        $user_id = (int) $order->get_user_id();
        if (!$user_id) { $this->debug('cart_recovered_skipped', ['order_id' => $order_id, 'reason' => 'user_missing']); return; }
        if (!get_user_meta($user_id, self::META_FLAG, true)) { $this->debug('cart_recovered_skipped', ['order_id' => $order_id, 'user_id' => $user_id, 'reason' => 'abandoned_flag_missing']); return; }
        $phone = trim((string) $order->get_billing_phone());
        if (!$phone) $phone = trim((string) get_user_meta($user_id, 'billing_phone', true));
        $this->debug('cart_recovered_detected', ['order_id' => $order_id, 'user_id' => $user_id]);
        do_action('ftsa_notify_event', 'cart.recovered', ['user_id' => $user_id, 'order_id' => $order_id, 'phone' => $phone, 'diagnostic' => 'dispatch_ok']);
        delete_user_meta($user_id, self::META_FLAG);
        delete_user_meta($user_id, self::META_STEP1);
        delete_user_meta($user_id, self::META_STEP2);
        delete_user_meta($user_id, self::META_LAST_REASON);
        $this->debug('cart_recovered_dispatch_ok', ['order_id' => $order_id, 'user_id' => $user_id]);
    }

    private function get_cart_snapshot() {
        $items = [];
        $total = 0;
        if (!function_exists('WC') || !WC()->cart) return ['items' => [], 'total' => 0];
        foreach ((array) WC()->cart->get_cart() as $item) {
            $line_total = isset($item['line_total']) ? (float) $item['line_total'] : 0;
            $total += $line_total;
            $items[] = ['product_id' => isset($item['product_id']) ? (int)$item['product_id'] : 0, 'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 1, 'line_total' => $line_total];
        }
        return ['items' => $items, 'total' => $total];
    }

    private function debug($event, $context = []) {
        do_action('ftsa_notify_event', 'system.debug', ['channel' => 'system', 'provider' => 'internal', 'event_name' => $event, 'debug_context' => $context]);
    }
}

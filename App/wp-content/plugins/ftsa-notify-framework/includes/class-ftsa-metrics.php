<?php
if (!defined('ABSPATH')) exit;
class FTSA_Metrics {
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ftsa_notify_messages';
    }
    public static function get_stats() {
        global $wpdb;
        $table = self::table();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $success = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('sent','success')");
        $error = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('failed','error')");
        $sms_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE channel = 'sms'");
        $whatsapp_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE channel = 'whatsapp'");
        $phone_missing_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE error_code = '400' AND (error_message LIKE '%[phone_missing]%' OR error_message LIKE '%phone_missing%')");
        $phone_persist_lag_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE error_message LIKE '%phone_not_persisted_yet%'");
        $cart_abandoned_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE event_name = 'cart.abandoned'");
        $cart_recovered_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE event_name = 'cart.recovered'");
        return ['total' => $total, 'success' => $success, 'error' => $error, 'delivery_rate' => self::rate($success, $total), 'error_rate' => self::rate($error, $total), 'sms_total' => $sms_total, 'whatsapp_total' => $whatsapp_total, 'phone_missing_total' => $phone_missing_total, 'phone_persist_lag_total' => $phone_persist_lag_total, 'cart_abandoned_total' => $cart_abandoned_total, 'cart_recovered_total' => $cart_recovered_total];
    }
    public static function by_event() {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results("SELECT event_name AS event, COUNT(*) as total, SUM(CASE WHEN status IN ('sent','success') THEN 1 ELSE 0 END) as success, SUM(CASE WHEN status IN ('failed','error') THEN 1 ELSE 0 END) as error FROM $table GROUP BY event_name ORDER BY total DESC, event_name ASC");
    }
    private static function rate($p, $t) {
        if ((int)$t === 0) return 0;
        return round(($p / $t) * 100, 2);
    }
}

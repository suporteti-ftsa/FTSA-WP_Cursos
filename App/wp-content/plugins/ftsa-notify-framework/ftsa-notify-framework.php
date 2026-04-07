<?php
/*
Plugin Name: FTSA Notify Framework
Description: Plugin interno WordPress/WooCommerce para mensageria transacional via Twilio (SMS, WhatsApp e Verify).
Version: 0.8.12
Author: FTSA / OpenAI
Text Domain: ftsa-notify-framework
*/

if (!defined('ABSPATH')) { exit; }

define('FTSA_NOTIFY_VERSION', '0.8.12');
define('FTSA_NOTIFY_FILE', __FILE__);
define('FTSA_NOTIFY_DIR', plugin_dir_path(__FILE__));
define('FTSA_NOTIFY_URL', plugin_dir_url(__FILE__));

require_once FTSA_NOTIFY_DIR . 'includes/class-ftsa-notify-plugin.php';

register_activation_hook(__FILE__, ['FTSA_Notify_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FTSA_Notify_Plugin', 'deactivate']);

FTSA_Notify_Plugin::instance();


require_once plugin_dir_path(__FILE__) . 'includes/class-ftsa-carttracker.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ftsa-metrics.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-ftsa-checkout-fixes.php';

add_action('plugins_loaded', function(){
    if (class_exists('FTSA_CartTracker')) { new FTSA_CartTracker(); }
    if (class_exists('FTSA_Checkout_Fixes')) { new FTSA_Checkout_Fixes(); }
}, 20);

add_action('admin_menu', function(){
    add_submenu_page('ftsa-notify', 'Dashboard', 'Dashboard', 'manage_options', 'ftsa-notify-dashboard', 'ftsa_dashboard_page');
}, 20);

if (!function_exists('ftsa_dashboard_page')) {
    function ftsa_dashboard_page(){
        $stats = FTSA_Metrics::get_stats();
        $events = FTSA_Metrics::by_event();
        echo "<div class='wrap'><h1>FTSA Notify — Dashboard</h1>";
        echo "<div style='display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:12px;max-width:1200px;margin:16px 0;'>";
        $cards = [
            'Total de envios' => (int) $stats['total'],
            'Sucesso' => (int) $stats['success'],
            'Erro' => (int) $stats['error'],
            'Taxa de entrega' => $stats['delivery_rate'] . '%',
            'Taxa de erro' => $stats['error_rate'] . '%',
            'SMS' => (int) $stats['sms_total'],
            'WhatsApp' => (int) $stats['whatsapp_total'],
            'Falhas phone_missing' => (int) $stats['phone_missing_total'],
            'Persistência tardia do telefone' => (int) $stats['phone_persist_lag_total'],
            'Carrinho abandonado' => (int) $stats['cart_abandoned_total'],
            'Carrinho recuperado' => (int) $stats['cart_recovered_total'],
        ];
        foreach ($cards as $label => $value) {
            echo "<div style='background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.04)'>";
            echo "<div style='font-size:12px;color:#50575e;margin-bottom:6px;'>" . esc_html($label) . "</div>";
            echo "<div style='font-size:24px;font-weight:700;line-height:1.2;'>" . esc_html((string)$value) . "</div>";
            echo "</div>";
        }
        echo "</div>";
        echo "<h2>Taxa de erro por evento</h2>";
        echo "<table class='widefat striped'><thead><tr><th>Evento</th><th>Total</th><th>Sucesso</th><th>Erro</th><th>Taxa de entrega</th><th>Taxa de erro</th></tr></thead><tbody>";
        if (!empty($events)) {
            foreach($events as $e){
                $delivery_rate = (int)$e->total > 0 ? round(((int)$e->success / (int)$e->total) * 100, 2) : 0;
                $error_rate = (int)$e->total > 0 ? round(((int)$e->error / (int)$e->total) * 100, 2) : 0;
                echo "<tr><td>" . esc_html($e->event) . "</td><td>" . (int)$e->total . "</td><td>" . (int)$e->success . "</td><td>" . (int)$e->error . "</td><td>" . esc_html(number_format($delivery_rate, 2, ',', '.')) . "%</td><td>" . esc_html(number_format($error_rate, 2, ',', '.')) . "%</td></tr>";
            }
        } else {
            echo "<tr><td colspan='6'>Nenhum dado encontrado.</td></tr>";
        }
        echo "</tbody></table></div>";
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

class FTSA_Notify_Plugin {
    private static $instance = null;
    private $settings_key = 'ftsa_notify_settings';
    private $db_version_key = 'ftsa_notify_db_version';
    private $messages_table;
    private $templates_table;
    private $carts_table;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $messages = $wpdb->prefix . 'ftsa_notify_messages';
        $templates = $wpdb->prefix . 'ftsa_notify_templates';
        $carts = $wpdb->prefix . 'ftsa_notify_carts';

        $sql1 = "CREATE TABLE $messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(80) NOT NULL,
            channel VARCHAR(20) NOT NULL,
            recipient VARCHAR(120) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            provider_status VARCHAR(60) DEFAULT '',
            error_code VARCHAR(30) DEFAULT '',
            error_message TEXT,
            payload_json LONGTEXT,
            rendered_message LONGTEXT,
            meta_json LONGTEXT,
            attempt_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_name (event_name),
            KEY channel (channel),
            KEY status (status),
            KEY order_id (order_id),
            KEY user_id (user_id)
        ) $charset;";

        $sql2 = "CREATE TABLE $templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            channel VARCHAR(20) NOT NULL,
            content LONGTEXT,
            whatsapp_template_name VARCHAR(150) DEFAULT '',
            whatsapp_template_lang VARCHAR(20) DEFAULT 'pt_BR',
            is_whatsapp_template TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code_channel (code, channel)
        ) $charset;";

        $sql3 = "CREATE TABLE $carts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_key VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(150) DEFAULT '',
            email VARCHAR(150) DEFAULT '',
            phone_raw VARCHAR(80) DEFAULT '',
            phone_e164 VARCHAR(120) DEFAULT '',
            country VARCHAR(5) DEFAULT 'BR',
            cart_hash VARCHAR(80) DEFAULT '',
            cart_items_count INT NOT NULL DEFAULT 0,
            cart_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            recovery_url TEXT,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_activity_at DATETIME NOT NULL,
            abandoned_at DATETIME DEFAULT NULL,
            first_sent_at DATETIME DEFAULT NULL,
            second_sent_at DATETIME DEFAULT NULL,
            recovered_at DATETIME DEFAULT NULL,
            checkout_completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_key (session_key),
            KEY status (status),
            KEY user_id (user_id)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);

        update_option('ftsa_notify_db_version', FTSA_NOTIFY_VERSION);
        $plugin = self::instance();
        $plugin->seed_defaults();
        $plugin->seed_templates();

        wp_clear_scheduled_hook('ftsa_notify_cart_scan');
        if (!wp_next_scheduled('ftsa_notify_cart_scan')) {
            wp_schedule_event(time() + 300, 'ftsa_notify_5min', 'ftsa_notify_cart_scan');
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('ftsa_notify_cart_scan');
    }

    private function __construct() {
        global $wpdb;
        $this->messages_table = $wpdb->prefix . 'ftsa_notify_messages';
        $this->templates_table = $wpdb->prefix . 'ftsa_notify_templates';
        $this->carts_table = $wpdb->prefix . 'ftsa_notify_carts';
        add_action('plugins_loaded', [$this, 'bootstrap']);
    }

    public function bootstrap() {
        $this->maybe_upgrade();
        $this->seed_defaults();
        $this->seed_templates();

        add_filter('cron_schedules', [$this, 'cron_schedules']);
        add_filter('password_reset_expiration', [$this, 'password_reset_expiration']);

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        add_action('woocommerce_checkout_order_processed', [$this, 'on_order_created'], 20, 1);
        add_action('woocommerce_order_status_processing', [$this, 'on_order_processing'], 20, 1);
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed'], 20, 1);
        add_action('woocommerce_order_status_pending', [$this, 'on_order_pending'], 20, 1);
        add_action('woocommerce_order_status_on-hold', [$this, 'on_order_on_hold'], 20, 1);
        add_action('user_register', [$this, 'on_user_created'], 20, 1);
        add_action('retrieve_password_key', [$this, 'on_password_reset_requested'], 20, 2);

        add_action('woocommerce_cart_updated', [$this, 'track_current_cart']);
        add_action('woocommerce_add_to_cart', [$this, 'track_current_cart']);
        add_action('template_redirect', [$this, 'track_current_cart']);
        add_action('ftsa_notify_cart_scan', [$this, 'scan_abandoned_carts']);
    }

    public function cron_schedules($schedules) {
        if (!isset($schedules['ftsa_notify_5min'])) {
            $schedules['ftsa_notify_5min'] = ['interval' => 300, 'display' => 'Every 5 Minutes'];
        }
        return $schedules;
    }

    public function password_reset_expiration() {
        $hours = (int) ($this->settings()['reset_expiry_hours'] ?? 6);
        if ($hours < 1) { $hours = 6; }
        return $hours * HOUR_IN_SECONDS;
    }

    private function maybe_upgrade() {
        $ver = get_option($this->db_version_key);
        if ($ver !== FTSA_NOTIFY_VERSION) {
            self::activate();
        }
    }

    private function settings() {
        return get_option($this->settings_key, []);
    }

    private function seed_defaults() {
        $defaults = [
            'country_default' => 'BR',
            'reset_expiry_hours' => 6,
            'sms_from' => '',
            'whatsapp_from' => '',
            'account_sid' => '',
            'auth_token' => '',
            'verify_service_sid' => '',
            'callback_enabled' => 1,
            'callback_token' => function_exists('wp_hash') ? wp_hash('ftsa-notify-callback-' . time()) : md5('ftsa-notify-' . time()),
            'fallback_enabled' => 1,
            'fallback_channel' => 'sms',
            'order.created' => 'sms',
            'order.processing' => 'none',
            'order.completed' => 'sms',
            'order.pending' => 'none',
            'order.on_hold' => 'none',
            'payment.reminder' => 'none',
            'user.created' => 'sms',
            'password.reset' => 'sms',
            'cart.abandoned' => 'none',
            'cart.recovered' => 'none',
            'cart_tracking_enabled' => 0,
            'cart_first_minutes' => 60,
            'cart_second_enabled' => 1,
            'cart_second_minutes' => 1440,
            'payment_first_minutes' => 60,
            'payment_second_enabled' => 1,
            'payment_second_minutes' => 1440,
        ];
        $current = get_option($this->settings_key, []);
        update_option($this->settings_key, wp_parse_args($current, $defaults));
    }

    private function default_templates() {
        return [
            ['cart.abandoned', 'whatsapp', "Olá {customer_name}!\nEncontramos itens no seu carrinho no site {site_title}.\nPara retomar sua compra, acesse este link:\n{cart_recovery_url}\n\nSe você já concluiu o pedido, desconsidere esta mensagem.", '', 'pt_BR', 0],
            ['cart.recovered', 'sms', "Olá {customer_name}!\nIdentificamos que você retomou sua compra no site {site_title}.\nSe precisar concluir o pedido, continue aqui:\n{cart_recovery_url}", '', 'pt_BR', 0],
            ['order.created', 'sms', "Olá {customer_name}!\nSeu pedido #{order_id}, no valor de R$ {order_total}, no site {site_title} foi criado com sucesso!", '', 'pt_BR', 0],
            ['order.created', 'whatsapp', "Olá {customer_name}!\nSeu pedido #{order_id}, no valor de R$ {order_total}, no site {site_title} foi criado com sucesso!", '', 'pt_BR', 0],
            ['order.processing', 'sms', "Olá {customer_name}!\nSeu pedido número {order_id}, no valor de R$ {order_total}, no site {site_title}, está em processamento.", '', 'pt_BR', 0],
            ['order.processing', 'whatsapp', "Olá {customer_name}!\nSeu pedido número {order_id}, no valor de R$ {order_total}, no site {site_title}, está em processamento.", '', 'pt_BR', 0],
            ['order.completed', 'sms', "Olá {customer_name}!\nSeu pedido #{order_id}, no valor de R$ {order_total}, realizado no site {site_title}, foi concluído com sucesso.", '', 'pt_BR', 0],
            ['order.completed', 'whatsapp', "Olá {customer_name}!\nSeu pedido #{order_id}, no valor de R$ {order_total}, realizado no site {site_title}, foi concluído com sucesso.", '', 'pt_BR', 0],
            ['password.reset', 'sms', "Olá {customer_name}!\nRecebemos sua solicitação de redefinição de senha no site {site_title}.\nUse o link:\n{password_reset_url}\n\nEste link é válido por {password_reset_expiry_hours} horas.", '', 'pt_BR', 0],
            ['password.reset', 'whatsapp', "Olá {customer_name}!\nRecebemos sua solicitação de redefinição de senha no site {site_title}.\nUse o link:\n{password_reset_url}\n\nSe você não fez esta solicitação, desconsidere esta mensagem.", '', 'pt_BR', 0],
            ['user.created', 'sms', "Olá {customer_name}!\nSua conta foi criada com sucesso no site {site_title}.\nAcesse: {site_url} informe o e-mail {email} como login e a senha cadastrada, ou clique em Esqueci a Senha.", '', 'pt_BR', 0],
            ['user.created', 'whatsapp', "Olá {customer_name}!\nSua conta foi criada com sucesso no site {site_title}.\nAcesse: {site_url} informe o e-mail {email} como login e a senha cadastrada, ou clique em Esqueci a Senha.", '', 'pt_BR', 0],
        ];
    }

    private function seed_templates() {
        global $wpdb;
        $now = current_time('mysql');
        foreach ($this->default_templates() as $r) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$this->templates_table} (code, channel, content, whatsapp_template_name, whatsapp_template_lang, is_whatsapp_template, updated_at)
                 VALUES (%s,%s,%s,%s,%s,%d,%s)
                 ON DUPLICATE KEY UPDATE
                    content = IF(content IS NULL OR content = '', VALUES(content), content),
                    whatsapp_template_lang = IF(whatsapp_template_lang IS NULL OR whatsapp_template_lang = '', VALUES(whatsapp_template_lang), whatsapp_template_lang),
                    updated_at = VALUES(updated_at)",
                $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $now
            ));
        }
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'ftsa-notify') === false) { return; }
        wp_enqueue_script('ftsa-notify-admin', FTSA_NOTIFY_URL . 'assets/js/admin.js', [], FTSA_NOTIFY_VERSION, true);
    }

    public function register_settings() {
        register_setting('ftsa_notify_group', $this->settings_key, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $current = $this->settings();
        $input = is_array($input) ? $input : [];
        $out = wp_parse_args($input, $current);

        foreach (['callback_enabled','fallback_enabled','cart_tracking_enabled','cart_second_enabled','payment_second_enabled'] as $flag) {
            $out[$flag] = !empty($input[$flag]) ? 1 : 0;
        }
        $out['country_default'] = strtoupper(sanitize_text_field($out['country_default'] ?? 'BR'));
        $out['reset_expiry_hours'] = max(1, (int) ($out['reset_expiry_hours'] ?? 6));
        foreach (['sms_from','whatsapp_from','account_sid','auth_token','verify_service_sid','callback_token'] as $k) {
            $out[$k] = sanitize_text_field($out[$k] ?? '');
        }
        $out['fallback_channel'] = in_array($out['fallback_channel'] ?? 'sms', ['sms','whatsapp'], true) ? $out['fallback_channel'] : 'sms';
        $out['cart_first_minutes'] = max(5, (int) ($out['cart_first_minutes'] ?? 60));
        $out['cart_second_minutes'] = max($out['cart_first_minutes'] + 5, (int) ($out['cart_second_minutes'] ?? 1440));
        $out['payment_first_minutes'] = max(5, (int) ($out['payment_first_minutes'] ?? 60));
        $out['payment_second_minutes'] = max($out['payment_first_minutes'] + 5, (int) ($out['payment_second_minutes'] ?? 1440));

        foreach (['order.created','order.processing','order.completed','order.pending','order.on_hold','payment.reminder','user.created','password.reset','cart.abandoned','cart.recovered'] as $e) {
            $out[$e] = in_array($out[$e] ?? 'sms', ['sms','whatsapp','none'], true) ? $out[$e] : 'sms';
        }
        return $out;
    }

    public function admin_menu() {
        add_menu_page('FTSA Notify', 'FTSA Notify', 'manage_options', 'ftsa-notify', [$this, 'render_settings'], 'dashicons-email-alt2');
        add_submenu_page('ftsa-notify', 'Health', 'Health', 'manage_options', 'ftsa-notify-health', [$this, 'render_health']);
        add_submenu_page('ftsa-notify', 'Logs', 'Logs', 'manage_options', 'ftsa-notify-logs', [$this, 'render_logs']);
        add_submenu_page('ftsa-notify', 'Templates', 'Templates', 'manage_options', 'ftsa-notify-templates', [$this, 'render_templates']);
    }

    public function render_settings() {
        $s = $this->settings();
        ?>
        <div class="wrap"><h1>FTSA Notify — Configurações</h1>
        <form method="post" action="options.php">
            <?php settings_fields('ftsa_notify_group'); ?>
            <table class="form-table"><tbody>
            <?php $this->field('Twilio Account SID', 'account_sid', $s); ?>
            <?php $this->field('Twilio Auth Token', 'auth_token', $s, 'password', true); ?>
            <?php $this->field('SMS From', 'sms_from', $s); ?>
            <?php $this->field('WhatsApp From', 'whatsapp_from', $s); ?>
            <tr><td></td><td><small>Use formato whatsapp:+5511999999999</small></td></tr>
            <?php $this->field('Verify Service SID', 'verify_service_sid', $s); ?>
            <tr><th>Status Callback</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->settings_key); ?>[callback_enabled]" value="1" <?php checked(!empty($s['callback_enabled'])); ?>> Ativar</label><br><code><?php echo esc_html($this->callback_url()); ?></code></td></tr>
            <?php $this->field('Token callback', 'callback_token', $s, 'password', true); ?>
            <?php $this->field('País padrão (ISO2)', 'country_default', $s); ?>
            <?php $this->field('Validade do link de reset (horas)', 'reset_expiry_hours', $s, 'number'); ?>
            <?php foreach (['order.created','order.processing','order.completed','order.pending','order.on_hold','payment.reminder','user.created','password.reset'] as $e): ?>
                <?php $this->event_select($e, $s); ?>
            <?php endforeach; ?>
            <tr><th>Fallback</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->settings_key); ?>[fallback_enabled]" value="1" <?php checked(!empty($s['fallback_enabled'])); ?>> Ativar</label> <select name="<?php echo esc_attr($this->settings_key); ?>[fallback_channel]"><option value="sms" <?php selected($s['fallback_channel'] ?? 'sms', 'sms'); ?>>SMS</option><option value="whatsapp" <?php selected($s['fallback_channel'] ?? 'sms', 'whatsapp'); ?>>WhatsApp</option></select></td></tr>
            <tr><th>Rastrear carrinho</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->settings_key); ?>[cart_tracking_enabled]" value="1" <?php checked(!empty($s['cart_tracking_enabled'])); ?>> Ativar rastreamento</label></td></tr>
            <?php $this->event_select('cart.abandoned', $s, 'Canal cart.abandoned'); ?>
            <?php $this->field('1ª notificação carrinho (minutos)', 'cart_first_minutes', $s, 'number'); ?>
            <tr><th>2ª notificação carrinho</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->settings_key); ?>[cart_second_enabled]" value="1" <?php checked(!empty($s['cart_second_enabled'])); ?>> Ativar</label></td></tr>
            <?php $this->field('2ª notificação carrinho (minutos)', 'cart_second_minutes', $s, 'number'); ?>
            <?php $this->event_select('cart.recovered', $s, 'Canal cart.recovered'); ?>
            <?php $this->field('1º lembrete pagamento (minutos)', 'payment_first_minutes', $s, 'number'); ?>
            <tr><th>2º lembrete pagamento</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->settings_key); ?>[payment_second_enabled]" value="1" <?php checked(!empty($s['payment_second_enabled'])); ?>> Ativar</label></td></tr>
            <?php $this->field('2º lembrete pagamento (minutos)', 'payment_second_minutes', $s, 'number'); ?>
            </tbody></table>
            <?php submit_button('Salvar alterações'); ?>
        </form></div>
        <?php
    }

    private function event_select($e, $s, $label = '') {
        if ($label === '') { $label = $e; }
        echo '<tr><th>' . esc_html($label) . '</th><td><select name="' . esc_attr($this->settings_key . '[' . $e . ']') . '">';
        foreach (['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'none' => 'Desativado'] as $k => $t) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($s[$e] ?? '', $k, false) . '>' . esc_html($t) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private function field($label, $key, $settings, $type = 'text', $toggle = false) {
        $name = $this->settings_key . '[' . $key . ']';
        $value = $settings[$key] ?? '';
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text">';
        if ($toggle && $type === 'password') {
            echo ' <button type="button" class="button ftsa-toggle-secret">Exibir/Ocultar</button>';
        }
        echo '</td></tr>';
    }

    public function render_health() {
        global $wpdb;
        $s = $this->settings();
        $pairs = [
            'Twilio ready' => (!empty($s['account_sid']) && !empty($s['auth_token'])) ? 'Sim' : 'Não',
            'Verify ready' => !empty($s['verify_service_sid']) ? 'Sim' : 'Não',
            'Total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->messages_table}"),
            'Pending' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->messages_table} WHERE status=%s", 'pending')),
            'Failed' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->messages_table} WHERE status=%s", 'failed')),
            'Sent' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->messages_table} WHERE status=%s", 'sent')),
            'Sms' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->messages_table} WHERE channel=%s", 'sms')),
            'Whatsapp' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->messages_table} WHERE channel=%s", 'whatsapp')),
            'Fallbacks' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->messages_table} WHERE meta_json LIKE '%\"fallback\":true%'") ,
            'Canonical templates' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->templates_table}"),
            'Twilio Status Callback' => $this->callback_url(),
        ];
        echo '<div class="wrap"><h1>FTSA Notify — Health</h1>';
        foreach ($pairs as $k => $v) {
            echo '<p><strong>' . esc_html($k) . ':</strong> ' . esc_html((string) $v) . '</p>';
        }
        echo '</div>';
    }

    public function render_logs() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->messages_table} ORDER BY id DESC LIMIT 200", ARRAY_A);
        echo '<div class="wrap"><h1>FTSA Notify — Logs</h1><table class="widefat striped"><thead><tr><th>ID</th><th>Evento</th><th>Pedido</th><th>Usuário</th><th>Canal</th><th>Destinatário</th><th>Status</th><th>Provider</th><th>Erro</th><th>Atualizado</th></tr></thead><tbody>'; 
        foreach ($rows as $r) {
            echo '<tr>';
            foreach (['id', 'event_name'] as $f) {
                echo '<td>' . esc_html((string) $r[$f]) . '</td>';
            }
            echo '<td>' . esc_html(!empty($r['order_id']) ? (string) $r['order_id'] : '—') . '</td>';
            echo '<td>' . esc_html(!empty($r['user_id']) ? (string) $r['user_id'] : '—') . '</td>';
            foreach (['channel', 'recipient', 'status', 'provider_status'] as $f) {
                echo '<td>' . esc_html((string) $r[$f]) . '</td>';
            }
            echo '<td>' . esc_html(trim(($r['error_code'] ? '[' . $r['error_code'] . '] ' : '') . (string) $r['error_message'])) . '</td>';
            echo '<td>' . esc_html((string) $r['updated_at']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_templates() {
        global $wpdb;
        $this->seed_templates();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('ftsa_notify_templates')) {
            if (!empty($_POST['tpl']) && is_array($_POST['tpl'])) {
                foreach ($_POST['tpl'] as $id => $data) {
                    $wpdb->update($this->templates_table, [
                        'content' => wp_kses_post($data['content'] ?? ''),
                        'whatsapp_template_name' => sanitize_text_field($data['whatsapp_template_name'] ?? ''),
                        'whatsapp_template_lang' => sanitize_text_field($data['whatsapp_template_lang'] ?? 'pt_BR'),
                        'is_whatsapp_template' => empty($data['is_whatsapp_template']) ? 0 : 1,
                        'updated_at' => current_time('mysql'),
                    ], ['id' => (int) $id]);
                }
                echo '<div class="updated"><p>Templates salvos.</p></div>';
            }
        }
        $rows = $wpdb->get_results("SELECT * FROM {$this->templates_table} WHERE code IS NOT NULL AND TRIM(code) <> '' ORDER BY code, channel", ARRAY_A);
        echo '<div class="wrap"><h1>FTSA Notify — Templates</h1><p>Padrão canônico: códigos com ponto, por exemplo <code>order.created</code>.</p>';
        echo '<p><strong>Variáveis disponíveis:</strong> <code>{site_title}</code>, <code>{site_url}</code>, <code>{order_id}</code>, <code>{order_total}</code>, <code>{order_status}</code>, <code>{order_date}</code>, <code>{payment_method}</code>, <code>{order_currency}</code>, <code>{customer_name}</code>, <code>{first_name}</code>, <code>{last_name}</code>, <code>{email}</code>, <code>{phone}</code>, <code>{billing_country}</code>, <code>{cart_recovery_url}</code>, <code>{password_reset_url}</code>, <code>{password_reset_expiry_hours}</code>, <code>{course_name}</code>, <code>{event_name}</code>.</p>';
        echo '<form method="post">';
        wp_nonce_field('ftsa_notify_templates');
        echo '<table class="widefat striped"><thead><tr><th>Código</th><th>Canal</th><th>Conteúdo</th><th>WhatsApp Template</th><th>Preview</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $sample = $this->render_template_string($r['content'], $this->sample_context());
            echo '<tr>';
            echo '<td><code>' . esc_html($r['code']) . '</code></td>';
            echo '<td>' . esc_html($r['channel']) . '</td>';
            echo '<td><textarea name="tpl[' . (int) $r['id'] . '][content]" rows="4" cols="55">' . esc_textarea($r['content']) . '</textarea></td>';
            echo '<td>';
            if ($r['channel'] === 'whatsapp') {
                echo '<label><input type="checkbox" name="tpl[' . (int) $r['id'] . '][is_whatsapp_template]" value="1" ' . checked((int) $r['is_whatsapp_template'], 1, false) . '> Usar template aprovado</label><br>';
                echo '<input type="text" name="tpl[' . (int) $r['id'] . '][whatsapp_template_name]" placeholder="HX... ou template name" value="' . esc_attr($r['whatsapp_template_name']) . '"> ';
                echo '<input type="text" name="tpl[' . (int) $r['id'] . '][whatsapp_template_lang]" placeholder="pt_BR" value="' . esc_attr($r['whatsapp_template_lang']) . '" size="8">';
            } else {
                echo '—';
            }
            echo '</td><td><div style="white-space:pre-wrap;max-width:360px">' . esc_html($sample) . '</div></td></tr>';
        }
        echo '</tbody></table>';
        submit_button('Salvar templates');
        echo '</form></div>';
    }

    private function sample_context() {
        return [
            'site_title' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'order_id' => '13690',
            'order_total' => '149,00',
            'order_status' => 'processing',
            'order_date' => current_time('d/m/Y H:i'),
            'payment_method' => 'Cartão',
            'order_currency' => 'BRL',
            'customer_name' => 'João Silva',
            'first_name' => 'João',
            'last_name' => 'Silva',
            'email' => 'joao@example.com',
            'phone' => '+5543991919281',
            'billing_country' => 'BR',
            'cart_recovery_url' => wc_get_cart_url(),
            'password_reset_url' => home_url('/wp-login.php?action=rp&key=EXEMPLO&login=joao'),
            'password_reset_expiry_hours' => (string) ($this->settings()['reset_expiry_hours'] ?? 6),
            'course_name' => 'Curso Exemplo',
            'event_name' => 'Evento Exemplo',
        ];
    }

    private function callback_url() {
        $s = $this->settings();
        return home_url('/wp-json/ftsa-notify/v1/twilio/status?token=' . rawurlencode($s['callback_token'] ?? ''));
    }

    public function register_rest() {
        register_rest_route('ftsa-notify/v1', '/twilio/status', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_status_callback'],
        ]);
    }

    public function rest_status_callback(WP_REST_Request $req) {
        $s = $this->settings();
        if (($req->get_param('token') ?: '') !== ($s['callback_token'] ?? '')) {
            return new WP_REST_Response(['ok' => false], 403);
        }
        global $wpdb;
        $sid = sanitize_text_field($req->get_param('MessageSid'));
        $status = sanitize_text_field($req->get_param('MessageStatus'));
        $code = sanitize_text_field($req->get_param('ErrorCode'));
        $message = sanitize_text_field($req->get_param('ErrorMessage'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, payload_json FROM {$this->messages_table} WHERE payload_json LIKE %s ORDER BY id DESC LIMIT 20", '%' . $wpdb->esc_like($sid) . '%'));
        foreach ($rows as $row) {
            $payload = json_decode($row->payload_json, true);
            if (($payload['provider_sid'] ?? '') === $sid) {
                $wpdb->update($this->messages_table, [
                    'provider_status' => $status,
                    'status' => in_array($status, ['failed', 'undelivered'], true) ? 'failed' : 'sent',
                    'error_code' => $code,
                    'error_message' => $this->friendly_error($code, $message),
                    'updated_at' => current_time('mysql'),
                ], ['id' => (int) $row->id]);
                break;
            }
        }
        return ['ok' => true];
    }

    public function on_order_created($order_id) {
        $this->dispatch_order_event('order.created', $order_id);
        $this->mark_cart_checkout_completed($order_id);
    }
    public function on_order_processing($order_id) { $this->dispatch_order_event('order.processing', $order_id); }
    public function on_order_completed($order_id) {
        $this->dispatch_order_event('order.completed', $order_id);
        $this->mark_cart_checkout_completed($order_id);
    }
    public function on_order_pending($order_id) { $this->dispatch_order_event('order.pending', $order_id); }
    public function on_order_on_hold($order_id) { $this->dispatch_order_event('order.on_hold', $order_id); }

    public function on_user_created($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) { return; }
        $event = 'user.created';
        $channel = $this->settings()[$event] ?? 'none';
        if ($channel === 'none') { return; }
        $country = get_user_meta($user_id, 'billing_country', true) ?: ($this->settings()['country_default'] ?? 'BR');
        $phone_raw = (string) get_user_meta($user_id, 'billing_phone', true);
        $recipient = $this->normalize_phone($phone_raw, $country, $channel);
        if (is_wp_error($recipient)) {
            $this->log_message($event, $channel, $phone_raw, 'failed', '400', $recipient->get_error_message(), [], '', ['user_id' => (int) $user_id], $recipient->get_error_code());
            return;
        }
        $context = $this->build_user_context($user, $country, $phone_raw);
        $this->send_event($event, $channel, $recipient, $context, false);
    }

    public function on_password_reset_requested($user_login, $key) {
        $user = get_user_by('login', $user_login);
        if (!$user) { return; }
        $event = 'password.reset';
        $channel = $this->settings()[$event] ?? 'none';
        if ($channel === 'none') { return; }
        $country = get_user_meta($user->ID, 'billing_country', true) ?: ($this->settings()['country_default'] ?? 'BR');
        $phone_raw = (string) get_user_meta($user->ID, 'billing_phone', true);
        $recipient = $this->normalize_phone($phone_raw, $country, $channel);
        if (is_wp_error($recipient)) {
            $this->log_message($event, $channel, $phone_raw, 'failed', '400', $recipient->get_error_message(), [], '', ['user_id' => (int) $user_id], $recipient->get_error_code());
            return;
        }
        $context = $this->build_user_context($user, $country, $phone_raw);
        $context['password_reset_url'] = network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
        $context['password_reset_expiry_hours'] = (string) ($this->settings()['reset_expiry_hours'] ?? 6);
        $this->send_event($event, $channel, $recipient, $context, false);
    }

    private function dispatch_order_event($event, $order_id, $extra_meta = []) {
        if (!function_exists('wc_get_order')) { return; }
        $order = wc_get_order($order_id);
        if (!$order) { return; }
        $channel = $this->settings()[$event] ?? 'none';
        if ($channel === 'none') { return; }
        $country = $order->get_billing_country() ?: ($this->settings()['country_default'] ?? 'BR');
        $phone_raw = (string) $order->get_billing_phone();
        $recipient = $this->normalize_phone($phone_raw, $country, $channel);
        if (is_wp_error($recipient)) {
            $this->log_message($event, $channel, $phone_raw, 'failed', '400', $recipient->get_error_message(), [], '', ['order_id' => (int) $order_id, 'user_id' => (int) $order->get_user_id()], $recipient->get_error_code());
            return;
        }
        $ctx = $this->build_order_context($order, $country);
        if (!empty($extra_meta)) { $ctx['_extra_meta'] = $extra_meta; }
        $this->send_event($event, $channel, $recipient, $ctx, false);
    }

    public function track_current_cart() {
        if (is_admin() || !function_exists('WC') || !WC()->cart) { return; }
        $s = $this->settings();
        if (empty($s['cart_tracking_enabled'])) { return; }
        if (WC()->cart->is_empty()) { return; }

        $session_key = '';
        if (WC()->session && method_exists(WC()->session, 'get_customer_id')) {
            $session_key = (string) WC()->session->get_customer_id();
        }
        if ($session_key === '') {
            $session_key = 'guest_' . md5(wp_json_encode($_COOKIE) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        }
        $user_id = get_current_user_id();
        $customer_name = '';
        $email = '';
        $phone_raw = '';
        $country = $s['country_default'] ?? 'BR';
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $customer_name = trim((string) get_user_meta($user_id, 'billing_first_name', true) . ' ' . (string) get_user_meta($user_id, 'billing_last_name', true));
                if ($customer_name === '') { $customer_name = $user->display_name; }
                $email = (string) $user->user_email;
                $phone_raw = (string) get_user_meta($user_id, 'billing_phone', true);
                $country = (string) get_user_meta($user_id, 'billing_country', true) ?: $country;
            }
        }
        if (WC()->customer) {
            if ($customer_name === '') { $customer_name = trim(WC()->customer->get_billing_first_name() . ' ' . WC()->customer->get_billing_last_name()); }
            if ($email === '') { $email = (string) WC()->customer->get_billing_email(); }
            if ($phone_raw === '') { $phone_raw = (string) WC()->customer->get_billing_phone(); }
            $country = (string) WC()->customer->get_billing_country() ?: $country;
        }
        $normalized = $this->normalize_phone($phone_raw, $country, 'sms');
        $phone_e164 = is_wp_error($normalized) ? '' : $normalized;
        $now = current_time('mysql');
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->carts_table} WHERE session_key=%s", $session_key), ARRAY_A);
        $data = [
            'session_key' => $session_key,
            'user_id' => (int) $user_id,
            'customer_name' => $customer_name,
            'email' => $email,
            'phone_raw' => $phone_raw,
            'phone_e164' => $phone_e164,
            'country' => $country,
            'cart_hash' => md5(wp_json_encode(WC()->cart->get_cart())),
            'cart_items_count' => (int) WC()->cart->get_cart_contents_count(),
            'cart_total' => (float) WC()->cart->get_total('edit'),
            'recovery_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/carrinho'),
            'updated_at' => $now,
            'last_activity_at' => $now,
        ];
        if ($existing) {
            $new_status = 'active';
            if ($existing['status'] === 'abandoned') {
                $new_status = 'recovered';
                $data['recovered_at'] = $now;
            } elseif ($existing['status'] === 'recovered') {
                $new_status = 'recovered';
            }
            $data['status'] = $new_status;
            $wpdb->update($this->carts_table, $data, ['session_key' => $session_key]);
            if ($existing['status'] === 'abandoned') {
                $this->maybe_send_cart_recovered($session_key);
            }
        } else {
            $data['status'] = 'active';
            $data['created_at'] = $now;
            $wpdb->insert($this->carts_table, $data);
        }
    }

    public function scan_abandoned_carts() {
        $this->scan_pending_payment_reminders();
        global $wpdb;
        $s = $this->settings();
        if (empty($s['cart_tracking_enabled'])) { return; }
        $rows = $wpdb->get_results("SELECT * FROM {$this->carts_table} WHERE status IN ('active','abandoned')", ARRAY_A);
        $now_ts = current_time('timestamp');
        foreach ($rows as $row) {
            $last_ts = strtotime($row['last_activity_at']);
            if ($row['status'] === 'active') {
                if (($now_ts - $last_ts) >= ((int) $s['cart_first_minutes'] * MINUTE_IN_SECONDS)) {
                    $this->send_cart_abandoned_notice($row, false);
                    $wpdb->update($this->carts_table, [
                        'status' => 'abandoned',
                        'abandoned_at' => current_time('mysql'),
                        'first_sent_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ], ['id' => (int) $row['id']]);
                }
            } elseif ($row['status'] === 'abandoned' && !empty($s['cart_second_enabled']) && empty($row['second_sent_at'])) {
                $base_ts = !empty($row['abandoned_at']) ? strtotime($row['abandoned_at']) : $last_ts;
                if (($now_ts - $base_ts) >= ((int) $s['cart_second_minutes'] * MINUTE_IN_SECONDS)) {
                    $this->send_cart_abandoned_notice($row, true);
                    $wpdb->update($this->carts_table, [
                        'second_sent_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ], ['id' => (int) $row['id']]);
                }
            }
        }
    }


    private function scan_pending_payment_reminders() {
        if (!function_exists('wc_get_orders')) { return; }
        $s = $this->settings();
        if (($s['payment.reminder'] ?? 'none') === 'none') { return; }
        $statuses = ['pending', 'on-hold'];
        $orders = wc_get_orders([
            'limit' => 50,
            'status' => $statuses,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        $now = current_time('timestamp');
        foreach ($orders as $order) {
            if (!$order) { continue; }
            $created = $order->get_date_created();
            $created_ts = $created ? $created->getTimestamp() : $now;
            $mins = ($now - $created_ts) / 60;
            $sent_first = $this->has_message_for_event('payment.reminder', $order->get_id(), false);
            $sent_second = $this->has_message_for_event('payment.reminder', $order->get_id(), true);
            if (!$sent_first && $mins >= (int) ($s['payment_first_minutes'] ?? 60)) {
                $this->dispatch_order_event('payment.reminder', $order->get_id(), ['second_notice' => false]);
                continue;
            }
            if (!empty($s['payment_second_enabled']) && !$sent_second && $mins >= (int) ($s['payment_second_minutes'] ?? 1440)) {
                $this->dispatch_order_event('payment.reminder', $order->get_id(), ['second_notice' => true]);
            }
        }
    }

    private function has_message_for_event($event, $order_id, $second_notice = false) {
        global $wpdb;
        $like = $second_notice ? '%"second_notice":true%' : '%"second_notice":false%';
        if ($event !== 'payment.reminder') { $like = '%'; }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->messages_table} WHERE event_name=%s AND order_id=%d AND meta_json LIKE %s",
            $event, (int) $order_id, $like
        )) > 0;
    }

    private function send_cart_abandoned_notice($row, $second_notice = false) {
        $channel = $this->settings()['cart.abandoned'] ?? 'none';
        if ($channel === 'none') { return; }
        $raw_phone = (string) ($row['phone_raw'] ?: $row['phone_e164']);
        $recipient = $this->normalize_phone($raw_phone, $row['country'] ?: ($this->settings()['country_default'] ?? 'BR'), $channel);
        if (is_wp_error($recipient)) {
            $this->log_message('cart.abandoned', $channel, $raw_phone, 'failed', '400', $recipient->get_error_message(), [], '', ['second_notice' => $second_notice, 'user_id' => (int) ($row['user_id'] ?? 0)], $recipient->get_error_code());
            return;
        }
        $context = $this->build_cart_context($row, $second_notice);
        $this->send_event('cart.abandoned', $channel, $recipient, $context, false);
    }

    private function maybe_send_cart_recovered($session_key) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->carts_table} WHERE session_key=%s", $session_key), ARRAY_A);
        if (!$row) { return; }
        $channel = $this->settings()['cart.recovered'] ?? 'none';
        if ($channel === 'none') { return; }
        $raw_phone = (string) ($row['phone_raw'] ?: $row['phone_e164']);
        $recipient = $this->normalize_phone($raw_phone, $row['country'] ?: ($this->settings()['country_default'] ?? 'BR'), $channel);
        if (is_wp_error($recipient)) {
            $this->log_message('cart.recovered', $channel, $raw_phone, 'failed', '400', $recipient->get_error_message(), [], '', ['user_id' => (int) ($row['user_id'] ?? 0)], $recipient->get_error_code());
            return;
        }
        $context = $this->build_cart_context($row, false);
        $this->send_event('cart.recovered', $channel, $recipient, $context, false);
    }

    private function mark_cart_checkout_completed($order_id) {
        if (!function_exists('wc_get_order')) { return; }
        $order = wc_get_order($order_id);
        if (!$order) { return; }
        global $wpdb;
        $phone = (string) $order->get_billing_phone();
        $session_key = (WC()->session && method_exists(WC()->session, 'get_customer_id')) ? WC()->session->get_customer_id() : '';
        $where_sql = '';
        $args = [];
        if ($session_key) {
            $where_sql = 'session_key=%s';
            $args[] = $session_key;
        } elseif ($phone) {
            $where_sql = 'phone_raw=%s';
            $args[] = $phone;
        } else {
            return;
        }
        $wpdb->query($wpdb->prepare("UPDATE {$this->carts_table} SET status='completed', checkout_completed_at=%s, updated_at=%s WHERE $where_sql", current_time('mysql'), current_time('mysql'), ...$args));
    }

    private function send_event($event, $channel, $recipient, $context, $is_fallback = false) {
        $tpl = $this->db_get_row($this->db_prepare("SELECT * FROM {$this->templates_table} WHERE code=%s AND channel=%s", $event, $channel), ARRAY_A);
        if (!$tpl) {
            $tpl = ['code' => $event, 'channel' => $channel, 'content' => "Site {site_title}!", 'is_whatsapp_template' => 0, 'whatsapp_template_name' => '', 'whatsapp_template_lang' => 'pt_BR'];
        }
        $body = $this->render_template_string($tpl['content'], $context);
        $meta = [
            'event' => $event,
            'fallback' => $is_fallback,
            'order_id' => (int) ($context['order_id'] ?? 0),
            'user_id' => (int) ($context['user_id'] ?? 0),
            'template_code_used' => $tpl['code'] ?? $event,
            'template_channel_used' => $tpl['channel'] ?? $channel,
            'template_rendered_body' => $body,
        ];
        if (!empty($context['_extra_meta']) && is_array($context['_extra_meta'])) { $meta = array_merge($meta, $context['_extra_meta']); }
        $this->deliver($event, $channel, $recipient, $body, $meta, $tpl, $context);
    }

    private function deliver($event, $channel, $recipient, $body, $meta, $tpl, $context) {
        $s = $this->settings();
        if (empty($s['account_sid']) || empty($s['auth_token'])) {
            $this->log_message($event, $channel, $recipient, 'failed', 'config_error', 'Configuração Twilio incompleta.', [], $body, $meta, 'config_error');
            return;
        }
        $from = $channel === 'whatsapp' ? ($s['whatsapp_from'] ?? '') : ($s['sms_from'] ?? '');
        if (empty($from)) {
            $this->log_message($event, $channel, $recipient, 'failed', 'config_error', 'Origem do canal não configurada.', [], $body, $meta, 'config_error');
            return;
        }
        $args = ['From' => $from, 'To' => $recipient];
        if (!empty($s['callback_enabled'])) {
            $args['StatusCallback'] = $this->callback_url();
        }
        if ($channel === 'whatsapp' && !empty($tpl['is_whatsapp_template']) && !empty($tpl['whatsapp_template_name'])) {
            $template_ref = trim((string) $tpl['whatsapp_template_name']);
            $vars = $this->build_content_variables($event, $context);
            if ($this->validate_content_variables($vars)) {
                if (preg_match('/^HX[a-zA-Z0-9]+$/', $template_ref)) {
                    $args['ContentSid'] = $template_ref;
                    $args['ContentVariables'] = wp_json_encode($vars);
                } else {
                    $args['Body'] = $body;
                }
            } else {
                $this->log_message($event, $channel, $recipient, 'failed', '400', $this->friendly_error('21656', 'The Content Variables parameter is invalid.'), [], $body, $meta, '21656');
                if (!empty($s['fallback_enabled']) && ($s['fallback_channel'] ?? '') !== $channel) {
                    $fallback_channel = $s['fallback_channel'];
                    $fallback_recipient = $this->normalize_phone($context['phone_raw'] ?? ($context['phone'] ?? ''), $context['_country'] ?? ($s['country_default'] ?? 'BR'), $fallback_channel);
                    if (!is_wp_error($fallback_recipient)) {
                        $this->send_event($event, $fallback_channel, $fallback_recipient, $context, true);
                    }
                }
                return;
            }
        } else {
            $args['Body'] = $body;
        }
        $res = wp_remote_post(
            "https://api.twilio.com/2010-04-01/Accounts/{$s['account_sid']}/Messages.json",
            [
                'timeout' => 20,
                'headers' => ['Authorization' => 'Basic ' . base64_encode($s['account_sid'] . ':' . $s['auth_token'])],
                'body' => $args,
            ]
        );
        if (is_wp_error($res)) {
            $this->log_message($event, $channel, $recipient, 'failed', 'request_error', $res->get_error_message(), [], $body, $meta, 'request_error');
            return;
        }
        $http_code = wp_remote_retrieve_response_code($res);
        $data = json_decode(wp_remote_retrieve_body($res), true) ?: [];
        $provider_status = $data['status'] ?? (string) $http_code;
        $error_code = (string) ($data['code'] ?? '');
        $error_message = $this->friendly_error($error_code, (string) ($data['message'] ?? ''));
        $status = ($http_code >= 200 && $http_code < 300 && empty($error_code)) ? 'sent' : 'failed';
        $payload = ['request' => $args, 'response' => $data, 'provider_sid' => $data['sid'] ?? ''];
        $this->log_message($event, $channel, $recipient, $status, (string) $provider_status, $error_message, $payload, $body, $meta, $error_code);
        if ($status === 'failed' && !empty($s['fallback_enabled']) && ($s['fallback_channel'] ?? '') !== $channel && $this->eligible_for_fallback($error_code)) {
            $fallback_channel = $s['fallback_channel'];
            $fallback_recipient = $this->normalize_phone($context['phone_raw'] ?? ($context['phone'] ?? ''), $context['_country'] ?? ($s['country_default'] ?? 'BR'), $fallback_channel);
            if (!is_wp_error($fallback_recipient)) {
                $this->send_event($event, $fallback_channel, $fallback_recipient, $context, true);
            }
        }
    }

    private function validate_content_variables($vars) {
        if (!is_array($vars) || empty($vars)) { return false; }
        foreach ($vars as $k => $v) {
            if (!preg_match('/^\d+$/', (string) $k)) { return false; }
            if ($v === null || $v === '') { return false; }
        }
        return true;
    }

    private function build_content_variables($event, $context) {
        switch ($event) {
            case 'order.created':
            case 'order.processing':
            case 'order.completed':
            case 'order.pending':
            case 'order.on_hold':
            case 'payment.reminder':
                return [
                    '1' => (string) ($context['customer_name'] ?: $context['first_name'] ?: 'Cliente'),
                    '2' => (string) ($context['order_id'] ?? ''),
                    '3' => (string) ($context['order_total'] ?? ''),
                    '4' => (string) ($context['site_title'] ?? get_bloginfo('name')),
                ];
            case 'password.reset':
                return [
                    '1' => (string) ($context['customer_name'] ?: $context['first_name'] ?: 'Cliente'),
                    '2' => (string) ($context['site_title'] ?? get_bloginfo('name')),
                    '3' => (string) ($context['password_reset_url'] ?? ''),
                ];
            case 'cart.abandoned':
            case 'cart.recovered':
                return [
                    '1' => (string) ($context['customer_name'] ?: $context['first_name'] ?: 'Cliente'),
                    '2' => (string) ($context['site_title'] ?? get_bloginfo('name')),
                    '3' => (string) ($context['cart_recovery_url'] ?? ''),
                ];
            case 'user.created':
                return [
                    '1' => (string) ($context['customer_name'] ?: $context['first_name'] ?: 'Cliente'),
                    '2' => (string) ($context['site_title'] ?? get_bloginfo('name')),
                    '3' => (string) ($context['site_url'] ?? home_url('/')),
                    '4' => (string) ($context['email'] ?? ''),
                ];
            default:
                return ['1' => (string) ($context['site_title'] ?? get_bloginfo('name'))];
        }
    }

    private function eligible_for_fallback($code) {
        return in_array((string) $code, ['63016', '30003', '30005', '30006', '30008', '21656'], true);
    }

    private function build_order_context($order, $country) {
        $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        return [
            'site_title' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'order_id' => $order->get_id(),
            'order_total' => number_format((float) $order->get_total(), 2, ',', '.'),
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y H:i') : '',
            'payment_method' => (string) $order->get_payment_method_title(),
            'order_currency' => (string) $order->get_currency(),
            'user_id' => (int) $order->get_user_id(),
            'customer_name' => $name,
            'first_name' => (string) $order->get_billing_first_name(),
            'last_name' => (string) $order->get_billing_last_name(),
            'email' => (string) $order->get_billing_email(),
            'phone' => (string) $order->get_billing_phone(),
            'phone_raw' => (string) $order->get_billing_phone(),
            'billing_country' => $country,
            '_country' => $country,
        ];
    }

    private function build_user_context($user, $country, $phone_raw) {
        return [
            'site_title' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'user_id' => (int) $user->ID,
            'customer_name' => $user->display_name,
            'first_name' => (string) get_user_meta($user->ID, 'billing_first_name', true),
            'last_name' => (string) get_user_meta($user->ID, 'billing_last_name', true),
            'email' => (string) $user->user_email,
            'phone' => $phone_raw,
            'phone_raw' => $phone_raw,
            'billing_country' => $country,
            '_country' => $country,
            'password_reset_expiry_hours' => (string) ($this->settings()['reset_expiry_hours'] ?? 6),
        ];
    }

    private function build_cart_context($row, $second_notice = false) {
        return [
            'site_title' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'customer_name' => (string) ($row['customer_name'] ?: 'Cliente'),
            'first_name' => (string) ($row['customer_name'] ?: 'Cliente'),
            'email' => (string) ($row['email'] ?: ''),
            'phone' => (string) ($row['phone_raw'] ?: $row['phone_e164']),
            'phone_raw' => (string) ($row['phone_raw'] ?: $row['phone_e164']),
            'billing_country' => (string) $row['country'],
            '_country' => (string) $row['country'],
            'cart_recovery_url' => (string) $row['recovery_url'],
            'cart_items_count' => (string) $row['cart_items_count'],
            'order_total' => number_format((float) $row['cart_total'], 2, ',', '.'),
            'second_notice' => $second_notice ? '1' : '0',
        ];
    }

    private function ddi_map() {
        return ['BR' => '55', 'US' => '1', 'PT' => '351', 'AT' => '43', 'AR' => '54', 'PY' => '595', 'UY' => '598', 'ES' => '34', 'IT' => '39', 'FR' => '33', 'DE' => '49', 'GB' => '44'];
    }

    private function normalize_phone($raw, $country, $channel) {
        $country = strtoupper($country ?: ($this->settings()['country_default'] ?? 'BR'));
        $raw = trim((string) $raw);
        if ($raw === '') {
            return new WP_Error('phone_missing', 'Telefone não informado para envio.');
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || strlen($digits) < 10) {
            return new WP_Error('21211', 'Número de destino inválido. Use formato E.164, por exemplo +5543999999999.');
        }
        if (strpos($raw, '+') === 0) {
            $e164 = '+' . ltrim($digits, '+');
        } else {
            $ddi = $this->ddi_map()[$country] ?? '';
            if ($ddi === '') {
                return new WP_Error('phone_country_unknown', 'País do telefone não reconhecido para normalização.');
            }
            if ($country === 'BR' && (strlen($digits) < 10 || strlen($digits) > 11)) {
                return new WP_Error('phone_invalid_local', 'Número local inválido. Para Brasil, informe DDD + número.');
            }
            $e164 = '+' . $ddi . ltrim($digits, '0');
        }
        if (!preg_match('/^\+[1-9]\d{7,14}$/', $e164)) {
            return new WP_Error('21211', 'Número de destino inválido. Use formato E.164, por exemplo +5543999999999.');
        }
        return $channel === 'whatsapp' ? 'whatsapp:' . $e164 : $e164;
    }

    private function render_template_string($template, $vars) {
        $replace = [];
        foreach ($vars as $k => $v) {
            $replace['{' . $k . '}'] = (string) $v;
        }
        return strtr((string) $template, $replace);
    }

    private function friendly_error($code, $message) {
        $m = trim((string) $message);
        switch ((string) $code) {
            case 'phone_missing':
                return 'Telefone não informado para envio.';
            case 'phone_invalid_local':
                return 'Número local inválido. Para Brasil, informe DDD + número.';
            case 'phone_country_unknown':
                return 'País do telefone não reconhecido para normalização.';
            case '21211':
                return 'Número de destino inválido. Use formato E.164, por exemplo +5543999999999.' . ($m ? ' ' . $m : '');
            case '21910':
                return 'Canal inconsistente entre origem e destino. Para WhatsApp, From e To devem usar whatsapp:+...' . ($m ? ' ' . $m : '');
            case '21656':
                return 'As variáveis do template aprovado do WhatsApp estão inválidas ou incompletas.' . ($m ? ' ' . $m : '');
            case '63016':
                return 'Falha ao enviar mensagem livre no WhatsApp fora da janela permitida; use template aprovado.' . ($m ? ' ' . $m : '');
            case 'config_error':
                return $m ?: 'Configuração Twilio incompleta.';
            default:
                return $m;
        }
    }

    private function log_message($event, $channel, $recipient, $status, $provider_status, $error_message, $payload, $rendered, $meta, $error_code = '') {
        $this->db_insert($this->messages_table, [
            'event_name' => $event,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $status,
            'order_id' => (int) ($meta['order_id'] ?? 0),
            'user_id' => (int) ($meta['user_id'] ?? 0),
            'provider_status' => $provider_status,
            'error_code' => $error_code,
            'error_message' => $error_message,
            'payload_json' => wp_json_encode($payload),
            'rendered_message' => $rendered,
            'meta_json' => wp_json_encode($meta),
            'attempt_count' => 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    private function db_prepare($query, ...$args) {
        global $wpdb;
        return $wpdb->prepare($query, ...$args);
    }
    private function db_get_row($query, $output = OBJECT) { global $wpdb; return $wpdb->get_row($query, $output); }
    private function db_insert($table, $data) { global $wpdb; return $wpdb->insert($table, $data); }
}

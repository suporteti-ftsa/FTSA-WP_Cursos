<?php
/**
 * Plugin Name: FTSA Woo Suite – CIELO + Autores (Relatórios)
 * Description: Relatórios otimizados (SQL) para Conciliação CIELO e Comissões de Autores: filtros no banco, exportação CSV, paginação opcional, baixa/registro de pagamento de comissão. Inclui importação TXT/CSV da CIELO, Histórico de Importações e Gestão de Estoque (opcional).
 * Author: FTSA
 * Version: 4.0.6
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) { exit; }

class FTSA_Woo_Suite {
    const VER  = '4.0.6';
    const SLUG = 'ftsa-woo-suite';

    // Root
    const MENU_ROOT_SLUG = 'ftsa-plugins-root';

    // Slugs reais (itens do menu)
    const MENU_IMPORT_SLUG             = 'ftsa-cielo-import';              // Importar EDI Cielo (view import-cielo.php)
    const MENU_IMPORT_HISTORY_SLUG     = 'ftsa-cielo-import-history';      // Histórico de Importações (render interno)
    const MENU_REPORT_SLUG             = 'ftsa-cielo-report';              // Relatório Analítico Cielo (view report-cielo.php)

    const MENU_BAIXA_COMMISSION_SLUG   = 'ftsa-baixa-comissoes-autor';     // NOVO: Baixa de Comissões (view baixa-comissoes-autor.php)

    const MENU_PAY_SLUG                = 'ftsa-commission-payments';       // Registro/edição pagamento comissão (view report-payments.php)
    const MENU_COMM_SLUG               = 'ftsa-commission-report';         // Relatório de Comissões (itens) (view report-commission.php)
    const MENU_PAID_BY_AUTHOR_SLUG     = 'ftsa-commission-paid-by-author'; // Relatório de Comissões por Autor (view report-paid-by-author.php)

    const MENU_SALES_REPORT_SLUG       = 'ftsa-sales-report';              // Relatório Vendas (view report-sales.php)

    const MENU_STOCK_SLUG              = 'ftsa-stock';                     // Gestão Estoque (view stock.php)
    const MENU_STOCK_REPORT_SLUG       = 'ftsa-stock-report';              // Relatório de Estoque (view report-stock.php)

    const MENU_SETTINGS_SLUG           = 'ftsa-settings';                  // Configurações (view settings.php)

    // Compatibilidade (slug antigo do histórico, fica oculto no menu)
    const MENU_IMPORT_LOG_SLUG         = 'ftsa-cielo-import-log';

    // CPT/Metas
    const CPT_AUTHOR = 'ftsa_author';

    // Metas no Produto
    const PRODUCT_META_AUTHOR_ID   = '_ftsa_author_id';
    const PRODUCT_META_AUTHOR_PERC = '_ftsa_author_percent';

    // Metas no Autor
    const AUTHOR_META_DEFAULT_PERC  = '_ftsa_author_default_percent';
    const AUTHOR_META_RECEIVES_COMM = '_ftsa_author_receives_commission';

    // Metas em itens do pedido (pagto de comissão)
    const COMM_PAID_META      = '_ftsa_comm_paid';
    const COMM_PAID_DATE_META = '_ftsa_comm_paid_date';

    /** @var wpdb */
    private $db;
    /** @var FTSA_Report_Store */
    public $store;
    /** @var object|null */
    public $import = null;
    /** @var FTSA_Stock_Manager|null */
    public $stock  = null;

    /** @var bool */
    private $has_stock = false;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        // Base
        require_once plugin_dir_path(__FILE__) . 'includes/class-ftsa-report-store.php';

        // Logger de importações (histórico + ndjson)
        $logger_file = plugin_dir_path(__FILE__) . 'includes/class-ftsa-import-logger.php';
        if (file_exists($logger_file)) {
            require_once $logger_file;
            add_action('admin_init', function() use ($wpdb) {
                if (class_exists('FTSA_Import_Logger')) {
                    FTSA_Import_Logger::ensure_env($wpdb);
                }
            });
        }

        // Opcionais
        $import_file = plugin_dir_path(__FILE__) . 'includes/class-ftsa-cielo-import.php';
        if (file_exists($import_file)) require_once $import_file;

        $stock_file  = plugin_dir_path(__FILE__) . 'includes/class-ftsa-stock.php';
        if (file_exists($stock_file)) {
            require_once $stock_file;
            $this->has_stock = class_exists('FTSA_Stock_Manager');
        }

        // Services
        $this->store = new FTSA_Report_Store();

        // Importador (duas variantes)
        if (class_exists('FTSA_Cielo_Import')) {
            $this->import = new FTSA_Cielo_Import();
        } elseif (class_exists('FTSA_Cielo_Import_Service')) {
            $this->import = new FTSA_Cielo_Import_Service();
        }

        // Estoque
        if ($this->has_stock) $this->stock = new FTSA_Stock_Manager();

        // CPT Autor + campos
        add_action('init', [$this, 'register_author_cpt']);
        add_action('add_meta_boxes', [$this, 'add_author_meta_boxes']);
        add_action('save_post_' . self::CPT_AUTHOR, [$this, 'save_author_meta']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'product_author_fields']);
        add_action('woocommerce_admin_process_product_object',        [$this, 'save_product_author_fields']);

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        // Menus
        add_action('admin_menu', [$this, 'admin_menu'], 56);
        add_action('admin_menu', [$this, 'strip_root_submenu'], 999);

        // Ações
        add_action('admin_init', [$this, 'handle_save_payments']);
        if ($this->import && method_exists($this->import, 'handle_import')) {
            add_action('admin_init', [$this->import, 'handle_import']);
        }
        if ($this->has_stock) add_action('admin_init', [$this, 'handle_save_stock']);
        add_action('admin_init', [$this, 'handle_settings_actions']);

        // Download de log do histórico
        add_action('admin_init', [$this, 'handle_download_import_log']);

        // Isolar conflitos
        add_action('admin_enqueue_scripts', [$this, 'maybe_dequeue_conflicting_scripts'], 1000);
    }

    public function maybe_dequeue_conflicting_scripts($hook) {
        if (!$this->current_screen_is_plugin_page()) return;
        foreach (['edwiser-multiple-user-course-purchase-manage-group','edwiser-datatables','jquery-dataTables','datatables','dataTables'] as $h) {
            wp_dequeue_script($h); wp_deregister_script($h);
            wp_dequeue_style($h);  wp_deregister_style($h);
        }
    }

    /* ================= HELPERS ================= */
    private function pick_scalar($v) {
        if (is_array($v)) { foreach ($v as $x) { $x = $this->pick_scalar($x); if ($x !== '') return $x; } return ''; }
        if (is_object($v) || $v === null) return '';
        return is_string($v) ? $v : (string)$v;
    }

    public function normalize_date_input($v) {
        $v = $this->pick_scalar($v);
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') return '';
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) { list($d,$m,$y)=explode('/',$v); return sprintf('%04d-%02d-%02d', (int)$y,(int)$m,(int)$d); }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $ts = @strtotime($v); return $ts ? date('Y-m-d', $ts) : '';
    }

    public function get_status_array($raw) {
        $s = $this->pick_scalar($raw); $s = is_string($s) ? trim($s) : '';
        if ($s === '') return [];
        $s = strtolower($s);
        if ($s === 'concluido' || $s === 'concluído') $s = 'completed';
        if (strpos($s, 'wc-') !== 0) $s = 'wc-'.$s;
        return [$s];
    }

    private function current_screen_is_plugin_page() {
        if (empty($_GET['page'])) return false;
        $pages = [
            self::MENU_IMPORT_SLUG,
            self::MENU_IMPORT_HISTORY_SLUG,
            self::MENU_IMPORT_LOG_SLUG, // compat
            self::MENU_REPORT_SLUG,

            self::MENU_BAIXA_COMMISSION_SLUG,
            self::MENU_PAY_SLUG,
            self::MENU_COMM_SLUG,
            self::MENU_PAID_BY_AUTHOR_SLUG,

            self::MENU_SALES_REPORT_SLUG,

            self::MENU_STOCK_SLUG,
            self::MENU_STOCK_REPORT_SLUG,
            self::MENU_SETTINGS_SLUG,
        ];
        return in_array($_GET['page'], $pages, true);
    }

    /* ================= ASSETS ================= */
    public function admin_assets($hook) {
        if (!$this->current_screen_is_plugin_page()) return;
        wp_enqueue_style(self::SLUG.'-admin', plugins_url('assets/css/admin.css', __FILE__), [], self::VER);
        wp_enqueue_script(self::SLUG.'-admin', plugins_url('assets/js/admin.js', __FILE__), ['jquery'], self::VER, true);
    }

    /* ================= CPT AUTOR ================= */
    public function register_author_cpt() {
        $labels = [
            'name'               => 'Autores',
            'singular_name'      => 'Autor',
            'menu_name'          => 'Autores',
            'name_admin_bar'     => 'Autor',
            'add_new'            => 'Adicionar novo',
            'add_new_item'       => 'Adicionar Autor',
            'new_item'           => 'Novo Autor',
            'edit_item'          => 'Editar Autor',
            'view_item'          => 'Ver Autor',
            'all_items'          => 'Todos Autores',
        ];
        $args = [
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => ['title'],
        ];
        register_post_type(self::CPT_AUTHOR, $args);
    }

    public function add_author_meta_boxes() {
        add_meta_box(
            'ftsa_author_commission',
            'Comissão do Autor',
            [$this, 'render_author_meta_box'],
            self::CPT_AUTHOR,
            'normal',
            'default'
        );
    }

    public function render_author_meta_box($post) {
        $default_perc = get_post_meta($post->ID, self::AUTHOR_META_DEFAULT_PERC, true);
        $receives     = get_post_meta($post->ID, self::AUTHOR_META_RECEIVES_COMM, true);
        if ($default_perc === '') $default_perc = '';
        $receives = ($receives === '0') ? '0' : '1';

        wp_nonce_field('ftsa_author_meta_save', 'ftsa_author_meta_nonce'); ?>
        <table class="form-table">
          <tr>
            <th><label for="ftsa_author_default_percent">% Comissão padrão</label></th>
            <td>
              <input type="number" step="0.01" min="0" max="100" id="ftsa_author_default_percent" name="ftsa_author_default_percent" value="<?php echo esc_attr($default_perc); ?>" style="width:120px">
              <p class="description">Percentual padrão para este autor (ex.: 40 para 40%).</p>
            </td>
          </tr>
          <tr>
            <th><label for="ftsa_author_receives_commission">Recebe comissão?</label></th>
            <td>
              <label><input type="checkbox" id="ftsa_author_receives_commission" name="ftsa_author_receives_commission" value="1" <?php checked($receives, '1'); ?>> Sim</label>
              <p class="description">Se desmarcado, produtos vinculados ao autor não terão comissão calculada.</p>
            </td>
          </tr>
        </table>
        <?php
    }

    public function save_author_meta($post_id) {
        if (!isset($_POST['ftsa_author_meta_nonce']) || !wp_verify_nonce($_POST['ftsa_author_meta_nonce'], 'ftsa_author_meta_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $perc = isset($_POST['ftsa_author_default_percent']) ? wc_format_decimal($_POST['ftsa_author_default_percent']) : '';
        $rcv  = !empty($_POST['ftsa_author_receives_commission']) ? '1' : '0';

        if ($perc !== '') update_post_meta($post_id, self::AUTHOR_META_DEFAULT_PERC, $perc);
        else delete_post_meta($post_id, self::AUTHOR_META_DEFAULT_PERC);

        update_post_meta($post_id, self::AUTHOR_META_RECEIVES_COMM, $rcv);
    }

    public function product_author_fields() {
        echo '<div class="options_group">';
        $authors = get_posts([
            'post_type'      => self::CPT_AUTHOR,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ]);
        $options = ['' => '— Selecione —'];
        foreach ($authors as $a) $options[$a->ID] = $a->post_title;

        woocommerce_wp_select([
            'id'          => self::PRODUCT_META_AUTHOR_ID,
            'label'       => 'Autor do curso',
            'options'     => $options,
            'desc_tip'    => true,
            'description' => 'Selecione o autor deste produto/curso.',
        ]);
        woocommerce_wp_text_input([
            'id'                => self::PRODUCT_META_AUTHOR_PERC,
            'label'             => '% Comissão do Autor (produto)',
            'type'              => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0', 'max' => '100'],
            'desc_tip'          => true,
            'description'       => 'Percentual específico para este produto (deixa em branco para usar o padrão do autor).',
        ]);
        echo '</div>';
    }

    public function save_product_author_fields($product) {
        $author_id = isset($_POST[self::PRODUCT_META_AUTHOR_ID]) ? intval($_POST[self::PRODUCT_META_AUTHOR_ID]) : 0;
        $perc      = isset($_POST[self::PRODUCT_META_AUTHOR_PERC]) ? wc_format_decimal($_POST[self::PRODUCT_META_AUTHOR_PERC]) : '';
        if ($author_id > 0) $product->update_meta_data(self::PRODUCT_META_AUTHOR_ID, $author_id);
        else $product->delete_meta_data(self::PRODUCT_META_AUTHOR_ID);
        if ($perc !== '') $product->update_meta_data(self::PRODUCT_META_AUTHOR_PERC, $perc);
        else $product->delete_meta_data(self::PRODUCT_META_AUTHOR_PERC);
    }

    /* ================= MENUS ================= */
    public function admin_menu() {
        // Topo
        add_menu_page(
            'FTSA Plugins',
            'FTSA Plugins',
            'manage_woocommerce',
            self::MENU_ROOT_SLUG,
            '__return_null',
            'dashicons-admin-generic',
            56
        );

        // 1) Importador / Histórico / Relatório Cielo
        add_submenu_page(self::MENU_ROOT_SLUG, 'Importar EDI Cielo',        'Importar EDI Cielo',        'manage_woocommerce', self::MENU_IMPORT_SLUG,         [$this, 'render_import_cielo'],      1);
        add_submenu_page(self::MENU_ROOT_SLUG, 'Histórico de Importações',  'Histórico de Importações',  'manage_woocommerce', self::MENU_IMPORT_HISTORY_SLUG, [$this, 'render_import_history'],     2);
        add_submenu_page(self::MENU_ROOT_SLUG, 'Relatório Analítico Cielo', 'Relatório Analítico Cielo', 'manage_woocommerce', self::MENU_REPORT_SLUG,         [$this, 'render_report_cielo'],       3);

        // 2) Comissões (fluxo)
        add_submenu_page(self::MENU_ROOT_SLUG, 'Baixa de Comissões',        'Baixa de Comissões',        'manage_woocommerce', self::MENU_BAIXA_COMMISSION_SLUG, [$this, 'render_baixa_comissoes_autor'], 4);
        add_submenu_page(self::MENU_ROOT_SLUG, 'Pagamento Comissões',       'Pagamento Comissões',       'manage_woocommerce', self::MENU_PAY_SLUG,            [$this, 'render_report_payments'],   5);
        add_submenu_page(self::MENU_ROOT_SLUG, 'Relatório Comissões Pagas', 'Relatório Comissões Pagas', 'manage_woocommerce', self::MENU_COMM_SLUG,           [$this, 'render_report_commission'], 6);
        add_submenu_page(self::MENU_ROOT_SLUG, 'Comissões por Autor',       'Comissões por Autor',       'manage_woocommerce', self::MENU_PAID_BY_AUTHOR_SLUG, [$this, 'render_report_paid_by_author'], 7);

        // 3) Vendas
        add_submenu_page(self::MENU_ROOT_SLUG, 'Relatório Vendas',          'Relatório Vendas',          'manage_woocommerce', self::MENU_SALES_REPORT_SLUG,   [$this, 'render_report_sales'],      8);

        // 4) Autores (CPT)
        add_submenu_page(self::MENU_ROOT_SLUG, 'Cadastro de Autores',       'Cadastro de Autores',       'manage_woocommerce', 'edit.php?post_type=' . self::CPT_AUTHOR, null, 9);

        // 5) Estoque
        add_submenu_page(self::MENU_ROOT_SLUG, 'Gestão Estoque',            'Gestão Estoque',            'manage_woocommerce', self::MENU_STOCK_SLUG,          [$this, 'render_stock_page'],        20);
        add_submenu_page(self::MENU_ROOT_SLUG, 'Relatório de Estoque',      'Relatório de Estoque',      'manage_woocommerce', self::MENU_STOCK_REPORT_SLUG,   [$this, 'render_report_stock'],      21);

        // 6) Configurações
        add_submenu_page(self::MENU_ROOT_SLUG, 'Configurações',             'Configurações',             'manage_woocommerce', self::MENU_SETTINGS_SLUG,       [$this, 'render_settings_page'],     99);

        // Compat (oculto): slug antigo do histórico (para links antigos não quebrarem)
        add_submenu_page(
            self::MENU_ROOT_SLUG,
            'Histórico de Importações (compat)',
            'Histórico de Importações (compat)',
            'manage_woocommerce',
            self::MENU_IMPORT_LOG_SLUG,
            [$this, 'render_import_history'],
            999
        );
    }

    public function strip_root_submenu() {
        remove_submenu_page(self::MENU_ROOT_SLUG, self::MENU_ROOT_SLUG);
        remove_submenu_page(self::MENU_ROOT_SLUG, self::MENU_IMPORT_LOG_SLUG); // oculta o compat
    }

    /* ================= EXPORT HELPER ================= */
    public function export_csv_generic($filename, $headers, $printer_callback) {
        $filename = sanitize_file_name((string)$filename);

        // Gera CSV em arquivo temporário (evita HTML acidental no output)
        $tmp = wp_tempnam($filename);
        if (!$tmp) {
            wp_die('Falha ao criar arquivo temporário para exportação.');
        }

        $fh = fopen($tmp, 'w');
        if (!$fh) {
            @unlink($tmp);
            wp_die('Falha ao abrir arquivo temporário para exportação.');
        }

        // BOM UTF-8 (para abrir acentuados no Excel)
        fwrite($fh, "\xEF\xBB\xBF");

        // Cabeçalho
        fputcsv($fh, $headers, ';');

        // Linhas (callback imprime no $fh)
        $printer_callback($fh);

        fclose($fh);

        // Limpa TUDO que possa estar no buffer
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }

        // Desliga compressão de saída (evita tamanho inconsistente)
        if (function_exists('ini_get') && ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', 'Off');
        }

        // Headers seguros
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $size = @filesize($tmp);
        if ($size !== false) {
            header('Content-Length: '.$size);
        }

        @readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /* ================= RENDER PAGES ================= */
    public function render_import_cielo() {
        $view = plugin_dir_path(__FILE__) . 'views/import-cielo.php';
        if (file_exists($view)) require $view; else $this->missing_view('import-cielo.php');
    }

    // Compat handler (se algum código chamar o nome antigo)
    public function render_import_log() {
        $this->render_import_history();
    }

    /** Página: Histórico de Importações (lista + botão baixar log) */
    public function render_import_history() {
        echo '<div class="wrap"><h1>Histórico de Importações – Cielo</h1>';

        if (!class_exists('FTSA_Import_Logger')) {
            echo '<div class="notice notice-error"><p>Arquivo ausente: <code>includes/class-ftsa-import-logger.php</code>. Sem ele, o histórico não pode ser exibido.</p></div></div>';
            return;
        }

        global $wpdb;
        $rows = FTSA_Import_Logger::list_imports($wpdb, 200);

        if (empty($rows)) {
            echo '<p>Nenhuma importação registrada ainda.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>
            <th>ID</th><th>Início</th><th>Fim</th><th>Arquivo</th><th>Tam</th>
            <th>Status</th><th>Proc.</th><th>#Núm</th><th>#TID</th><th>#NSU</th>
            <th>Dup</th><th>NF</th><th>Erros</th><th>Log</th></tr></thead><tbody>';

        foreach ($rows as $r) {
            $dl = wp_nonce_url(
                add_query_arg(['ftsa_dl_log'=>1, 'id'=>$r['id']], admin_url('admin.php?page='.self::MENU_IMPORT_HISTORY_SLUG)),
                'ftsa_dl_log_'.$r['id']
            );
            printf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s KB</td><td>%s</td>
                 <td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td>
                 <td><a class="button" href="%s">Baixar log</a></td></tr>',
                $r['id'],
                esc_html($r['started_at']),
                esc_html($r['finished_at']),
                esc_html($r['filename']),
                number_format_i18n((int)($r['filesize']/1024)),
                esc_html($r['status']),
                (int)$r['processed'],
                (int)$r['matched_number'],
                (int)$r['matched_tid'],
                (int)$r['matched_nsu'],
                (int)$r['skipped_duplicate'],
                (int)$r['not_found'],
                (int)$r['errors'],
                esc_url($dl)
            );
        }
        echo '</tbody></table></div>';
    }

    /** Download seguro do arquivo de log (NDJSON) */
    public function handle_download_import_log() {
        if (!is_admin()) return;
        if (empty($_GET['ftsa_dl_log']) || empty($_GET['id'])) return;
        if (!current_user_can('manage_woocommerce')) wp_die('Sem permissão.');
        $id = (int) $_GET['id'];
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ftsa_dl_log_'.$id)) wp_die('Nonce inválido.');
        if (!class_exists('FTSA_Import_Logger')) wp_die('Logger indisponível.');

        global $wpdb;
        $path = FTSA_Import_Logger::get_log_path($wpdb, $id);
        if (!$path || !file_exists($path)) wp_die('Log não encontrado.');

        nocache_headers();
        header('Content-Type: application/x-ndjson; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cielo-import-'.$id.'.jsonl"');
        readfile($path);
        exit;
    }

    public function render_report_cielo() {
        $view = plugin_dir_path(__FILE__) . 'views/report-cielo.php';
        if (file_exists($view)) require $view; else $this->missing_view('report-cielo.php');
    }

    public function render_report_commission() {
        $view = plugin_dir_path(__FILE__) . 'views/report-commission.php';
        if (file_exists($view)) require $view; else $this->missing_view('report-commission.php');
    }

    public function render_report_payments() {
        $view = plugin_dir_path(__FILE__) . 'views/report-payments.php';
        if (file_exists($view)) require $view; else $this->missing_view('report-payments.php');
    }

    // Wrapper antigo (se algo ainda chamar)
    public function render_menu_paid_commissions() {
        $this->render_report_payments();
    }

    public function render_report_paid_by_author() {
        $view = plugin_dir_path(__FILE__) . 'views/report-paid-by-author.php';
        if (file_exists($view)) require $view; else $this->missing_view('report-paid-by-author.php');
    }

    public function render_baixa_comissoes_autor() {
        $view = plugin_dir_path(__FILE__) . 'views/baixa-comissoes-autor.php';
        if (file_exists($view)) require $view; else $this->missing_view('baixa-comissoes-autor.php');
    }

    public function render_report_sales() {
        $view = plugin_dir_path(__FILE__) . 'views/report-sales.php';
        if (!file_exists($view)) { $this->missing_view('report-sales.php'); return; }

        /**
         * PATCH DE COMPAT:
         * Seu report-sales.php atual ainda pode estar com "page = MENU_COMM_SLUG" (ftsa-commission-report).
         * Para não “pular” de página quando filtra/exporta, fazemos um replace no HTML gerado.
         * Depois, na sanitização, você pode corrigir o report-sales.php de forma definitiva.
         */
        ob_start();
        require $view;
        $html = ob_get_clean();

        $html = str_replace('page='.self::MENU_COMM_SLUG, 'page='.self::MENU_SALES_REPORT_SLUG, $html);
        $html = str_replace('value="'.self::MENU_COMM_SLUG.'"', 'value="'.self::MENU_SALES_REPORT_SLUG.'"', $html);

        echo $html;
    }

    public function render_report_stock() {
        $view = plugin_dir_path(__FILE__) . 'views/report-stock.php';
        if (file_exists($view)) require $view; else $this->missing_view('report-stock.php');
    }

    // Estoque (gestão)
    public function render_stock_page() {
        $view = plugin_dir_path(__FILE__) . 'views/stock.php';
        if (!file_exists($view)) {
            echo '<div class="wrap"><h1>Estoque</h1><div class="notice notice-warning"><p>Arquivo de view do estoque ausente: <code>views/stock.php</code>.</p></div></div>';
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen) {
            $screen->add_help_tab([
                'id'    => 'ftsa_stock_help',
                'title' => 'Ajuda',
                'content' =>
                    '<p><strong>Gestão de Estoque</strong></p>'.
                    '<p>Edite <em>Gerenciar estoque</em>, <em>Qtd</em>, <em>Ponto de reposição</em>, <em>Status</em>, <em>Status do Produto</em> e <em>Visibilidade</em> e salve.</p>'.
                    '<p>Exportações CSV respeitam os filtros.</p>'
            ]);
        }

        require $view;
    }

    private function missing_view($file) {
        echo '<div class="wrap"><h1>FTSA Plugins</h1><div class="notice notice-error"><p>View ausente: <code>'.esc_html($file).'</code>.</p></div></div>';
    }

    /* ================= CONFIGURAÇÕES (View) ================= */
    public function render_settings_page() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen) {
            $screen->add_help_tab([
                'id'    => 'ftsa_settings_help',
                'title' => 'Ajuda',
                'content' =>
                    '<p><strong>Configurações » Exclusões (Reprocessar)</strong></p>'.
                    '<p>Use o <em>Pré-teste</em> para ver quantos registros/fees seriam afetados antes de executar.</p>'.
                    '<p>As remoções de fees “Taxa CIELO” são feitas via WooCommerce, garantindo consistência.</p>'
            ]);
        }
        $view = plugin_dir_path(__FILE__) . 'views/settings.php';
        if (file_exists($view)) require $view; else $this->missing_view('settings.php');
    }

    /* ================= CONFIGURAÇÕES (AÇÕES) ================= */
    public function handle_settings_actions() {
        if (!is_admin()) return;
        if (empty($_POST['ftsa_settings_do'])) return;
        if (!current_user_can('manage_woocommerce')) wp_die('Sem permissão.');
        if (!isset($_POST['ftsa_settings_nonce']) || !wp_verify_nonce($_POST['ftsa_settings_nonce'], 'ftsa_settings_do')) {
            wp_die('Falha de verificação de segurança.');
        }

        @ini_set('memory_limit', '512M');
        if (function_exists('set_time_limit')) @set_time_limit(300);

        global $wpdb;
        $p   = $wpdb->posts;
        $oi  = $wpdb->prefix . 'woocommerce_order_items';
        $oim = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $rows_tbl = $wpdb->prefix . 'cielo_conciliacao_rows';

        $task   = isset($_POST['task']) ? sanitize_text_field($_POST['task']) : '';
        $do     = isset($_POST['do'])   ? sanitize_text_field($_POST['do'])   : 'preview';
        $scope  = isset($_POST['fees_scope']) ? sanitize_text_field($_POST['fees_scope']) : 'match';

        $unique_key      = isset($_POST['unique_key']) ? trim((string)$_POST['unique_key']) : '';
        $order_id        = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $source_filename = isset($_POST['source_filename']) ? sanitize_text_field($_POST['source_filename']) : '';
        $start           = $this->normalize_date_input($_POST['start'] ?? '');
        $end             = $this->normalize_date_input($_POST['end']   ?? '');

        $rows_count = 0; $fees_count = 0; $orders_touched = 0; $fees_removed = 0; $rows_deleted = 0;

        $count_rows = function($where_sql, $params) use ($wpdb, $rows_tbl) {
            $sql = "SELECT COUNT(*) FROM {$rows_tbl} {$where_sql}";
            return (int) $wpdb->get_var($params ? $wpdb->prepare($sql, $params) : $sql);
        };
        $delete_rows = function($where_sql, $params) use ($wpdb, $rows_tbl) {
            $sql = "DELETE FROM {$rows_tbl} {$where_sql}";
            return (int) $wpdb->query($params ? $wpdb->prepare($sql, $params) : $sql);
        };
        $group_by_order = function(array $pairs) {
            $out = [];
            foreach ($pairs as $r) {
                $oid = (int) $r['order_id'];
                $iid = (int) $r['order_item_id'];
                if (!isset($out[$oid])) $out[$oid] = [];
                $out[$oid][] = $iid;
            }
            return $out;
        };

        $select_fee_items = function($mode, $args) use ($wpdb, $oi, $oim, $rows_tbl, $p, $scope) {
            switch ($mode) {
                case 'by_key': {
                    $key = $args['key'];
                    $sql = "
                        SELECT oi.order_id, oi.order_item_id
                        FROM {$oi} oi
                        JOIN {$oim} mkey ON mkey.order_item_id = oi.order_item_id
                            AND mkey.meta_key = '_cielo_import_key' AND mkey.meta_value = %s
                        WHERE oi.order_item_type = 'fee' AND oi.order_item_name = 'Taxa CIELO'
                    ";
                    return $wpdb->get_results($wpdb->prepare($sql, $key), ARRAY_A);
                }
                case 'by_order': {
                    $order_id = (int) $args['order_id'];
                    if (!empty($args['key'])) {
                        $sql = "
                            SELECT oi.order_id, oi.order_item_id
                            FROM {$oi} oi
                            JOIN {$oim} mkey ON mkey.order_item_id = oi.order_item_id
                                AND mkey.meta_key = '_cielo_import_key' AND mkey.meta_value = %s
                            WHERE oi.order_item_type = 'fee' AND oi.order_item_name = 'Taxa CIELO'
                              AND oi.order_id = %d
                        ";
                        return $wpdb->get_results($wpdb->prepare($sql, $args['key'], $order_id), ARRAY_A);
                    } else {
                        $sql = "
                            SELECT oi.order_id, oi.order_item_id
                            FROM {$oi} oi
                            WHERE oi.order_item_type = 'fee' AND oi.order_item_name = 'Taxa CIELO'
                              AND oi.order_id = %d
                        ";
                        return $wpdb->get_results($wpdb->prepare($sql, $order_id), ARRAY_A);
                    }
                }
                case 'by_file': {
                    $file = $args['file'];
                    if ($scope === 'match') {
                        $sql = "
                            SELECT DISTINCT oi.order_id, oi.order_item_id
                            FROM {$rows_tbl} r
                            JOIN {$oi} oi  ON oi.order_id = r.order_id
                            JOIN {$oim} m  ON m.order_item_id = oi.order_item_id
                            WHERE r.source_filename = %s
                              AND oi.order_item_type = 'fee'
                              AND oi.order_item_name = 'Taxa CIELO'
                              AND m.meta_key = '_cielo_import_key'
                              AND m.meta_value = r.unique_key
                        ";
                        return $wpdb->get_results($wpdb->prepare($sql, $file), ARRAY_A);
                    } else {
                        $sql = "
                            SELECT DISTINCT oi.order_id, oi.order_item_id
                            FROM {$rows_tbl} r
                            JOIN {$oi} oi ON oi.order_id = r.order_id
                            WHERE r.source_filename = %s
                              AND oi.order_item_type = 'fee'
                              AND oi.order_item_name = 'Taxa CIELO'
                        ";
                        return $wpdb->get_results($wpdb->prepare($sql, $file), ARRAY_A);
                    }
                }
                case 'by_period': {
                    $start = $args['start'] . ' 00:00:00';
                    $end   = $args['end']   . ' 23:59:59';
                    if ($scope === 'match') {
                        $sql = "
                            SELECT DISTINCT oi.order_id, oi.order_item_id
                            FROM {$p} o
                            JOIN {$oi} oi  ON oi.order_id = o.ID
                            JOIN {$oim} m  ON m.order_item_id = oi.order_item_id
                            JOIN {$rows_tbl} r ON r.order_id = o.ID
                            WHERE o.post_type = 'shop_order'
                              AND o.post_date BETWEEN %s AND %s
                              AND oi.order_item_type = 'fee'
                              AND oi.order_item_name = 'Taxa CIELO'
                              AND m.meta_key = '_cielo_import_key'
                              AND m.meta_value = r.unique_key
                        ";
                        return $wpdb->get_results($wpdb->prepare($sql, $start, $end), ARRAY_A);
                    } else {
                        $sql = "
                            SELECT DISTINCT oi.order_id, oi.order_item_id
                            FROM {$p} o
                            JOIN {$oi} oi ON oi.order_id = o.ID
                            WHERE o.post_type = 'shop_order'
                              AND o.post_date BETWEEN %s AND %s
                              AND oi.order_item_type = 'fee'
                              AND oi.order_item_name = 'Taxa CIELO'
                        ";
                        return $wpdb->get_results($wpdb->prepare($sql, $start, $end), ARRAY_A);
                    }
                }
            }
            return [];
        };

        // Execução por tarefa
        if ($task === 'unique_key') {
            if ($unique_key === '') wp_die('Informe a unique_key.');
            $rows_count = $count_rows("WHERE unique_key = %s", [$unique_key]);
            $fee_items  = $select_fee_items('by_key', ['key' => $unique_key]);
            $fees_count = count($fee_items);
            if ($do === 'execute') {
                $rows_deleted = $delete_rows("WHERE unique_key = %s", [$unique_key]);
                $map = $group_by_order($fee_items);
                foreach ($map as $oid => $item_ids) {
                    $order = wc_get_order($oid); if (!$order) continue;
                    foreach ($item_ids as $iid) $order->remove_item($iid);
                    $order->save();
                    $fees_removed += count($item_ids); $orders_touched++;
                }
            }
        } elseif ($task === 'order_id') {
            if ($order_id <= 0) wp_die('Informe um Nº de pedido válido.');
            $rows_count = $count_rows("WHERE order_id = %d", [$order_id]);
            $key_opt = isset($_POST['order_import_key']) ? trim((string)$_POST['order_import_key']) : '';
            $fee_items = $key_opt !== '' ? $select_fee_items('by_order', ['order_id'=>$order_id,'key'=>$key_opt])
                                         : $select_fee_items('by_order', ['order_id'=>$order_id]);
            $fees_count = count($fee_items);
            if ($do === 'execute') {
                $rows_deleted = $delete_rows("WHERE order_id = %d", [$order_id]);
                $map = $group_by_order($fee_items);
                foreach ($map as $oid => $item_ids) {
                    $order = wc_get_order($oid); if (!$order) continue;
                    foreach ($item_ids as $iid) $order->remove_item($iid);
                    $order->save();
                    $fees_removed += count($item_ids); $orders_touched++;
                }
            }
        } elseif ($task === 'file') {
            if ($source_filename === '') wp_die('Informe o nome exato do arquivo (source_filename).');
            $rows_count = $count_rows("WHERE source_filename = %s", [$source_filename]);
            $fee_items  = $select_fee_items('by_file', ['file' => $source_filename]);
            $fees_count = count($fee_items);
            if ($do === 'execute') {
                $rows_deleted = $delete_rows("WHERE source_filename = %s", [$source_filename]);
                $map = $group_by_order($fee_items);
                foreach ($map as $oid => $item_ids) {
                    $order = wc_get_order($oid); if (!$order) continue;
                    foreach ($item_ids as $iid) $order->remove_item($iid);
                    $order->save();
                    $fees_removed += count($item_ids); $orders_touched++;
                }
            }
        } elseif ($task === 'period') {
            if ($start === '' || $end === '') wp_die('Informe Data Início e Data Término.');
            $rows_count = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$rows_tbl} r
                JOIN {$p} o ON o.ID = r.order_id
                WHERE o.post_type='shop_order'
                  AND o.post_date BETWEEN %s AND %s
            ", $start.' 00:00:00', $end.' 23:59:59'));
            $fee_items  = $select_fee_items('by_period', ['start'=>$start, 'end'=>$end]);
            $fees_count = count($fee_items);
            if ($do === 'execute') {
                $rows_deleted = (int) $wpdb->query($wpdb->prepare("
                    DELETE r FROM {$rows_tbl} r
                    JOIN {$p} o ON o.ID = r.order_id
                    WHERE o.post_type='shop_order'
                      AND o.post_date BETWEEN %s AND %s
                ", $start.' 00:00:00', $end.' 23:59:59'));
                $map = $group_by_order($fee_items);
                foreach ($map as $oid => $item_ids) {
                    $order = wc_get_order($oid); if (!$order) continue;
                    foreach ($item_ids as $iid) $order->remove_item($iid);
                    $order->save();
                    $fees_removed += count($item_ids); $orders_touched++;
                }
            }
        } else {
            wp_die('Ação inválida.');
        }

        $msg = ($do === 'preview')
            ? "PRÉ-TESTE — Rows a excluir: {$rows_count}; Fees a remover: {$fees_count}."
            : "EXECUTADO — Rows excluídos: {$rows_deleted}; Fees removidos: {$fees_removed}; Pedidos atualizados: {$orders_touched}.";

        $url = add_query_arg(['page' => self::MENU_SETTINGS_SLUG, 'smsg' => rawurlencode($msg)], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    /* ================= SAVE PAYMENTS ================= */
    public function handle_save_payments() {
        if (!is_admin()) return;
        if (empty($_POST['ftsa_save_payments'])) return;
        if (!isset($_POST['ftsa_payments_nonce']) || !wp_verify_nonce($_POST['ftsa_payments_nonce'], 'ftsa_payments_save')) {
            wp_die('Falha de verificação de segurança.');
        }
        if (!current_user_can('manage_woocommerce')) wp_die('Sem permissão.');

        $present = isset($_POST['payment_present']) && is_array($_POST['payment_present'])
            ? array_map('intval', array_keys($_POST['payment_present']))
            : [];

        $data    = isset($_POST['payment']) && is_array($_POST['payment']) ? $_POST['payment'] : [];

        $updated = 0;
        foreach ($present as $item_id) {
            $row  = isset($data[$item_id]) ? $data[$item_id] : [];
            $paid = !empty($row['paid']) ? '1' : '0';
            $date = isset($row['date']) ? sanitize_text_field($row['date']) : '';
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';

            wc_update_order_item_meta($item_id, self::COMM_PAID_META, $paid);
            if ($date !== '') wc_update_order_item_meta($item_id, self::COMM_PAID_DATE_META, $date);
            else wc_delete_order_item_meta($item_id, self::COMM_PAID_DATE_META);

            $updated++;
        }

        $msg = $updated > 0 ? "Pagamentos atualizados para {$updated} item(ns)." : "Nada a atualizar.";

        // ✅ Redirect inteligente: volta para a página que enviou o POST
        $ref = wp_get_referer();
        if ($ref) {
            $ref = remove_query_arg(['pmsg'], $ref);
            $url = add_query_arg(['pmsg' => rawurlencode($msg)], $ref);
        } else {
            $url = add_query_arg(['page' => self::MENU_PAY_SLUG, 'pmsg' => rawurlencode($msg)], admin_url('admin.php'));
        }

        wp_safe_redirect($url);
        exit;
    }

    /* ================= SAVE STOCK (inclui status/visibilidade) ================= */
    public function handle_save_stock() {
        if (!is_admin()) return;
        if (empty($_POST['ftsa_stock_save'])) return;
        if (!isset($_POST['ftsa_stock_nonce']) || !wp_verify_nonce($_POST['ftsa_stock_nonce'], 'ftsa_stock_save')) {
            wp_die('Falha de verificação de segurança.');
        }
        if (!current_user_can('manage_woocommerce')) wp_die('Sem permissão.');

        $present = isset($_POST['stock_present']) && is_array($_POST['stock_present']) ? array_map('intval', array_keys($_POST['stock_present'])) : [];
        $data    = isset($_POST['stock']) && is_array($_POST['stock']) ? $_POST['stock'] : [];

        $auto_status = !empty($_POST['auto_status_on_save']);

        $updated = 0;
        foreach ($present as $post_id) {
            $row = isset($data[$post_id]) ? $data[$post_id] : [];

            $manage = isset($row['manage']) && $row['manage'] === 'yes' ? 'yes' : 'no';

            $qty_raw = isset($row['qty']) ? trim((string)$row['qty']) : '';
            $low_raw = isset($row['low']) ? trim((string)$row['low']) : '';

            $qty = ($qty_raw === '') ? '' : wc_stock_amount($qty_raw);
            $low = ($low_raw === '') ? '' : wc_stock_amount($low_raw);

            $status = isset($row['status']) ? sanitize_text_field($row['status']) : '';

            $post_status = isset($row['post_status']) ? sanitize_key($row['post_status']) : '';
            $visibility  = isset($row['visibility'])  ? sanitize_key($row['visibility'])  : '';

            update_post_meta($post_id, '_manage_stock', $manage);

            if ($qty === '') update_post_meta($post_id, '_stock', null) || delete_post_meta($post_id, '_stock');
            else update_post_meta($post_id, '_stock', $qty);

            if ($low === '') update_post_meta($post_id, '_low_stock_amount', null) || delete_post_meta($post_id, '_low_stock_amount');
            else update_post_meta($post_id, '_low_stock_amount', $low);

            if ($auto_status && $manage === 'yes') {
                $qnum = ($qty === '' ? 0 : (float)$qty);
                $status = ($qnum <= 0) ? 'outofstock' : 'instock';
            }

            if (in_array($status, ['instock','outofstock','onbackorder',''], true)) {
                if ($status !== '') update_post_meta($post_id, '_stock_status', $status);
            }

            // Status do produto
            if (in_array($post_status, ['publish','pending','draft'], true)) {
                $current_ps = get_post_status($post_id);
                if ($current_ps && $current_ps !== $post_status) {
                    wp_update_post(['ID' => $post_id, 'post_status' => $post_status]);
                }
            }

            // Visibilidade
            if ($visibility !== '' && in_array($visibility, ['visible','catalog','search','hidden'], true)) {
                $product = wc_get_product($post_id);
                if ($product && method_exists($product, 'set_catalog_visibility')) {
                    if ($product->get_catalog_visibility() !== $visibility) {
                        $product->set_catalog_visibility($visibility);
                        $product->save();
                    }
                }
            }

            $updated++;
        }

        $msg = $updated > 0 ? "Estoque atualizado para {$updated} item(ns)." : "Nada a atualizar.";
        $url = add_query_arg(['page' => self::MENU_STOCK_SLUG, 'smsg' => rawurlencode($msg)], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}

new FTSA_Woo_Suite();

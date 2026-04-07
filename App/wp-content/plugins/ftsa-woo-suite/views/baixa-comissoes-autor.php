<?php
if (!defined('ABSPATH')) { exit; }

try {
    if (!current_user_can('manage_woocommerce')) {
        echo '<div class="wrap"><h1>Baixa de Comissões</h1><div class="notice notice-error"><p>Sem permissão.</p></div></div>';
        return;
    }

    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) { @set_time_limit(120); }

    // helpers (evitar array injection em GET)
    if (!function_exists('ftsa_pick_scalar')) {
        function ftsa_pick_scalar($v) {
            if (is_array($v)) {
                foreach ($v as $x) {
                    $s = ftsa_pick_scalar($x);
                    if ($s !== '') return $s;
                }
                return '';
            }
            if (is_object($v) || $v === null) return '';
            if (is_bool($v)) return $v ? '1' : '';
            return is_string($v) ? $v : (is_numeric($v) ? (string)$v : '');
        }
    }

    // Desconto do item (fallback por item do pedido)
    if (!function_exists('ftsa_item_discount_amount')) {
        function ftsa_item_discount_amount($order, $order_item_id) {
            if (!$order) return 0.0;
            $it = $order->get_item((int)$order_item_id);
            if (!$it || !is_a($it, 'WC_Order_Item_Product')) return 0.0;
            $sub = (float)$it->get_subtotal();
            $tot = (float)$it->get_total();
            $disc = $sub - $tot;
            return $disc > 0 ? $disc : 0.0;
        }
    }

    // ===== Listas dos filtros =====
    $authors = get_posts([
        'post_type'      => FTSA_Woo_Suite::CPT_AUTHOR,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);

    $products = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => 500,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);

    // ===== Filtros recebidos =====
    $start_in = ftsa_pick_scalar($_GET['start'] ?? '');
    $end_in   = ftsa_pick_scalar($_GET['end']   ?? '');

    $author_id = (int) ftsa_pick_scalar($_GET['author_id'] ?? 0);

    $product_ids = $_GET['product_ids'] ?? [];
    if (!is_array($product_ids)) $product_ids = [$product_ids];
    $product_ids = array_values(array_filter(array_map('intval', $product_ids)));

    // include_zero = 1 => exibir zeros; vazio => ocultar zeros (padrão)
    $include_zero = !empty($_GET['include_zero']) ? 1 : 0;

    // paid_flag: '' => todos | 0 => não | 1 => sim (padrão: NÃO)
    $paid_qs = isset($_GET['paid_flag']) ? trim((string)ftsa_pick_scalar($_GET['paid_flag'])) : '0';
    $paid_flag = ($paid_qs === '1') ? 1 : (($paid_qs === '0') ? 0 : null);

    $filters = [
        'start'             => $this->normalize_date_input($start_in),
        'end'               => $this->normalize_date_input($end_in),
        'status'            => [], // sem restrição (mantém coluna Status)
        'method_title_like' => '',
        'author_id'         => $author_id,
        'product_ids'       => $product_ids,
        'paid_flag'         => $paid_flag,
        'hide_zero_items'   => $include_zero ? 0 : 1,
    ];

    // Paginação
    $per   = max(10, (int) ftsa_pick_scalar($_GET['per_page'] ?? 50));
    $page  = max(1,  (int) ftsa_pick_scalar($_GET['paged'] ?? 1));
    $all   = !empty($_GET['all']);
    $off   = $all ? 0 : ($per * ($page - 1));
    $lim   = $all ? 200000 : $per;

    $res   = $this->store->query_line_items_with_commission($filters, $lim, $off, $all);
    $rows  = $res['rows'];
    $total = (int)($res['total'] ?? 0);

    $base_url = admin_url('admin.php?page=' . self::MENU_BAIXA_COMMISSION_SLUG);

    // cache de pedidos (evita wc_get_order repetido)
    $orders_cache = [];
    $get_order = function($oid) use (&$orders_cache) {
        $oid = (int)$oid;
        if (!isset($orders_cache[$oid])) {
            $orders_cache[$oid] = wc_get_order($oid);
        }
        return $orders_cache[$oid];
    };

    ?>
    <div class="wrap">
      <h1>FTSA - Baixa de Comissões (Autor)</h1>

      <?php if (!empty($_GET['pmsg'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><?php echo esc_html($_GET['pmsg']); ?></p>
        </div>
      <?php endif; ?>

      <form method="get" class="ftsa-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_BAIXA_COMMISSION_SLUG); ?>">
        <table class="form-table">
          <tr>
            <th>Data Início (Pedido)</th>
            <td><input type="date" name="start" value="<?php echo esc_attr($filters['start']); ?>"></td>
          </tr>
          <tr>
            <th>Data Término (Pedido)</th>
            <td><input type="date" name="end" value="<?php echo esc_attr($filters['end']); ?>"></td>
          </tr>

          <tr>
            <th>Autor</th>
            <td>
              <select name="author_id" style="min-width:320px;">
                <option value="0">— Todos —</option>
                <?php foreach ($authors as $a): ?>
                  <option value="<?php echo esc_attr($a->ID); ?>" <?php selected($author_id, $a->ID); ?>>
                    <?php echo esc_html($a->post_title) . ' (#' . $a->ID . ')'; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>

          <tr>
            <th>Item (Produto)</th>
            <td>
              <select name="product_ids[]" multiple size="8" style="min-width:320px;">
                <?php foreach ($products as $p): $sel=in_array($p->ID, $product_ids, true)?' selected':''; ?>
                  <option value="<?php echo esc_attr($p->ID); ?>"<?php echo $sel; ?>>
                    <?php echo esc_html($p->post_title).' (#'.$p->ID.')'; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">Selecione um ou mais produtos (Ctrl/Cmd para múltiplos).</p>
            </td>
          </tr>

          <tr>
            <th>Comissão paga?</th>
            <td>
              <select name="paid_flag">
                <option value=""  <?php selected($paid_flag, null); ?>>— Todos —</option>
                <option value="0" <?php selected($paid_flag, 0); ?>>Não (padrão)</option>
                <option value="1" <?php selected($paid_flag, 1); ?>>Sim</option>
              </select>
            </td>
          </tr>

          <tr>
            <th>Itens com valor zero</th>
            <td>
              <label>
                <input type="checkbox" name="include_zero" value="1" <?php checked($include_zero, 1); ?>>
                Exibir itens com valor = 0
              </label>
            </td>
          </tr>

          <tr>
            <th>Página única</th>
            <td>
              <label><input type="checkbox" name="all" value="1" <?php checked($all); ?>>
                Exibir tudo em uma página (todos)
              </label>
            </td>
          </tr>

          <tr>
            <th>Por página</th>
            <td><input type="number" name="per_page" min="10" value="<?php echo esc_attr($per); ?>"></td>
          </tr>
        </table>

        <?php submit_button('Filtrar'); ?>

        <div class="ftsa-actions">
          <span class="description">Total de itens filtrados: <?php echo esc_html(number_format_i18n($total)); ?></span>
        </div>
      </form>

      <form method="post">
        <?php wp_nonce_field('ftsa_payments_save', 'ftsa_payments_nonce'); ?>
        <input type="hidden" name="ftsa_save_payments" value="1" />

        <p>
          <button type="button" class="button" id="ftsa-fill-today-empty">Preencher <strong>HOJE</strong> nos vazios</button>
          &nbsp;
          <button type="submit" class="button button-primary">Salvar alterações</button>
        </p>

        <div class="ftsa-table-wrapper">
          <table class="widefat striped sticky-head">
            <thead>
              <tr>
                <th>Nº Pedido</th>
                <th>Status</th>
                <th>Nome do Item</th>
                <th>Data do Pedido</th>
                <th>Data do Pagamento</th>
                <th>Data Repasse (CIELO)</th>
                <th>Valor do Item</th>
                <th>Desconto (Cupom)</th>
                <th>Valor Taxa Cielo</th>
                <th>Valor Líquido</th>
                <th>Valor da Comissão</th>
                <th>Data do Pagamento da Comissão (editável)</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="12">Nenhum registro encontrado.</td></tr>
            <?php else: foreach ($rows as $r):
                $order = $get_order($r['order_id']);

                $edit_url = $order
                    ? ( method_exists($order, 'get_edit_order_url') ? $order->get_edit_order_url()
                        : admin_url('post.php?post=' . $r['order_id'] . '&action=edit') )
                    : admin_url('post.php?post=' . $r['order_id'] . '&action=edit');

                $order_date = isset($r['order_date']) ? substr((string)$r['order_date'], 0, 10) : '';

                $paid_date = '';
                if ($order && !is_a($order, 'WC_Order_Refund') && method_exists($order, 'get_date_paid')) {
                    $dt = $order->get_date_paid();
                    $paid_date = $dt ? $dt->date_i18n('Y-m-d') : '';
                }

                $settlement = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';

                $valor_bruto = (float)($r['line_subtotal'] ?? 0);

                $valor_total = array_key_exists('line_total', $r)
                    ? (float)$r['line_total']
                    : $valor_bruto;

                if (array_key_exists('discount_amount', $r)) {
                    $valor_desc = (float)$r['discount_amount'];
                } elseif (array_key_exists('line_total', $r)) {
                    $valor_desc = max(0.0, $valor_bruto - $valor_total);
                } else {
                    $valor_desc = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
                }

                $taxa_cielo = (float)($r['fee_share'] ?? 0);

                $valor_liq = array_key_exists('net_value', $r)
                    ? (float)$r['net_value']
                    : max(0.0, $valor_total - $taxa_cielo);

                $valor_comm = (float)($r['commission'] ?? 0);

                $item_id = (int)($r['order_item_id'] ?? 0);
                $comm_paid_date = (string)($r['comm_paid_date'] ?? '');
            ?>
              <tr>
                <td><a href="<?php echo esc_url($edit_url); ?>" target="_blank">#<?php echo esc_html($r['order_id']); ?></a></td>
                <td><?php echo esc_html($r['status_name'] ?? ''); ?></td>
                <td><?php echo esc_html($r['order_item_name'] ?? ''); ?></td>
                <td><?php echo esc_html($order_date); ?></td>
                <td><?php echo esc_html($paid_date); ?></td>
                <td><?php echo esc_html($settlement); ?></td>

                <td><?php echo wp_kses_post(wc_price($valor_bruto)); ?></td>
                <td><?php echo wp_kses_post(wc_price($valor_desc)); ?></td>
                <td><?php echo wp_kses_post(wc_price($taxa_cielo)); ?></td>
                <td><?php echo wp_kses_post(wc_price($valor_liq)); ?></td>
                <td><?php echo wp_kses_post(wc_price($valor_comm)); ?></td>

                <td>
                  <input type="hidden" name="payment_present[<?php echo esc_attr($item_id); ?>]" value="1" />
                  <input type="hidden" name="payment[<?php echo esc_attr($item_id); ?>][paid]" value="0" class="ftsa-paid-flag" />
                  <input type="date"
                         name="payment[<?php echo esc_attr($item_id); ?>][date]"
                         value="<?php echo esc_attr($comm_paid_date); ?>"
                         class="ftsa-paid-date"
                         style="width:160px;" />
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!$all):
          $total_pages = max(1, (int) ceil($total / $per)); ?>
          <div class="tablenav">
            <div class="tablenav-pages" style="margin-top:10px">
              <?php for ($p = 1; $p <= $total_pages; $p++):
                $url   = add_query_arg(array_merge($_GET, ['paged' => $p, 'all' => null]), $base_url);
                $class = ($p == $page) ? 'page-numbers current' : 'page-numbers'; ?>
                <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($p); ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>

        <p style="margin-top:12px;">
          <button type="button" class="button" id="ftsa-fill-today-empty-bottom">Preencher <strong>HOJE</strong> nos vazios</button>
          &nbsp;
          <button type="submit" class="button button-primary">Salvar alterações</button>
        </p>
      </form>
    </div>

    <script>
    (function($){
      function syncPaidFlag($row){
        var $date = $row.find('input.ftsa-paid-date');
        var $paid = $row.find('input.ftsa-paid-flag');
        $paid.val( $date.val() ? '1' : '0' );
      }

      function syncAll(){
        $('input.ftsa-paid-date').each(function(){
          syncPaidFlag($(this).closest('tr'));
        });
      }

      function fillTodayEmpty(){
        var today = new Date().toISOString().slice(0,10);
        $('input.ftsa-paid-date').each(function(){
          if(!this.value){
            this.value = today;
          }
        });
        syncAll();
      }

      $(document).on('change input', 'input.ftsa-paid-date', function(){
        syncPaidFlag($(this).closest('tr'));
      });

      $('#ftsa-fill-today-empty, #ftsa-fill-today-empty-bottom').on('click', function(){
        fillTodayEmpty();
      });

      // inicial
      syncAll();
    })(jQuery);
    </script>
    <?php

} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Baixa de Comissões</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao carregar a página.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}

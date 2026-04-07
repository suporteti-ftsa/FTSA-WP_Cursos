<?php
if (!defined('ABSPATH')) { exit; }

try {
    global $wpdb;

    // ------- helpers locais (garantem string e evitam strtotime com array) -------
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

    // ------- listas para os drop-downs -------
    // Status existentes no Woo
    $all_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
    // Método de pagamento (títulos distintos em pedidos)
    $pm = $wpdb->postmeta;
    $p  = $wpdb->posts;
    $all_methods = $wpdb->get_col("
        SELECT DISTINCT pm.meta_value
        FROM {$pm} pm
        INNER JOIN {$p} po ON po.ID = pm.post_id
        WHERE pm.meta_key = '_payment_method_title'
          AND po.post_type = 'shop_order'
          AND pm.meta_value IS NOT NULL
          AND pm.meta_value <> ''
        ORDER BY pm.meta_value ASC
    ");

    // ------- filtros seguros / paginação -------
    $start_in  = ftsa_pick_scalar($_GET['start']  ?? '');
    $end_in    = ftsa_pick_scalar($_GET['end']    ?? '');
    $status_in = ftsa_pick_scalar($_GET['status'] ?? 'completed');
    $method_in = ftsa_pick_scalar($_GET['method'] ?? '');

    $filters = [
        'start'             => $this->normalize_date_input($start_in),
        'end'               => $this->normalize_date_input($end_in),
        'status'            => $this->get_status_array($status_in ?: 'completed'),
        'method_title_like' => $method_in,                    // será usado em LIKE (case-insensitive)
        'author_id'         => 0,
        'product_ids'       => [],
        'paid_flag'         => null,
        'hide_zero_items'   => !empty($_GET['hide_zero_items']) ? 1 : 0,
    ];

    // novo filtro: ocultar líquido = 0
    $hide_zero_net = !empty($_GET['hide_zero_net']) ? 1 : 0;

    $per  = max(10, (int) ftsa_pick_scalar($_GET['per_page'] ?? 50));
    $page = max(1,  (int) ftsa_pick_scalar($_GET['paged']    ?? 1));
    $all  = !empty($_GET['all']);

    $off = $all ? 0 : ($per * ($page - 1));
    $lim = $all ? 200000 : $per;

    // ------- consulta (store) -------
    $res   = $this->store->query_line_items_with_commission($filters, $lim, $off, $all);
    $rows  = $res['rows'];
    $total = (int) $res['total'];
    $sums  = is_array($res['sums'] ?? null) ? $res['sums'] : [];

    // aplica filtro de líquido = 0 na view (sem mexer na store)
    if ($hide_zero_net) {
        $rows = array_values(array_filter($rows, function($r){
            return (float)($r['net_value'] ?? 0) > 0;
        }));
        // recalcula totais da página mostrada
        $total = count($rows);
    }

    // helper local para desconto do item (subtotal - total)
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

    // ------- exportação -------
    $export = isset($_GET['export']) ? sanitize_text_field(ftsa_pick_scalar($_GET['export'])) : '';

    if ($export === 'page' || $export === 'all') {
        $to_export = ($export === 'all')
            ? $this->store->query_line_items_with_commission($filters, 200000, 0, true)['rows']
            : $rows;

        if ($hide_zero_net) {
            $to_export = array_values(array_filter($to_export, function($r){
                return (float)($r['net_value'] ?? 0) > 0;
            }));
        }

        $scope = ($export === 'all') ? 'all' : 'page';
        $filename = 'ftsa_conciliacao_cielo_' . date('Ymd_His') . '_' . $scope . '.csv';


        $this->export_csv_generic($filename, [
            'Pedido','Status','Cliente','Data do Pedido','Método','TID','NSU','Nome do Item',
            'Valor Item','Desconto (Cupom)','Taxa CIELO','Valor Líquido'
        ], function($out) use ($to_export) {
            foreach ($to_export as $r) {
                $order = wc_get_order($r['order_id']);
                $customer = '';
                if ($order && !is_a($order, 'WC_Order_Refund')) {
                    $customer = $order->get_formatted_billing_full_name() ?: $order->get_billing_email();
                }
                $discount = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);

                fputcsv($out, [
                    '#'.$r['order_id'],
                    $r['status_name'],
                    $customer ?: '-',
                    substr($r['order_date'], 0, 16),
                    $r['method_title'] ?: '-',
                    $r['tid'] ?: '-',
                    $r['nsu'] ?: '-',
                    $r['order_item_name'],
                    number_format((float)$r['line_subtotal'], 2, ',', ''),
                    number_format((float)$discount,          2, ',', ''),
                    number_format((float)$r['fee_share'],    2, ',', ''),
                    number_format((float)$r['net_value'],    2, ',', ''),
                ], ';');
            }
        });
        return; // encerra a view após exportar
    }

    $base_url = admin_url('admin.php?page=' . self::MENU_REPORT_SLUG);
    ?>
    <div class="wrap">
      <h1>FTSA - Relatório Analitico de Conciliação</h1>

      <form method="get" class="ftsa-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_REPORT_SLUG); ?>">
        <table class="form-table">
          <tr>
            <th>Data Início</th>
            <td><input type="date" name="start" value="<?php echo esc_attr($filters['start']); ?>"></td>
          </tr>
          <tr>
            <th>Data Término</th>
            <td><input type="date" name="end" value="<?php echo esc_attr($filters['end']); ?>"></td>
          </tr>
          <tr>
            <th>Status</th>
            <td>
              <select name="status" style="min-width:260px;">
                <?php
                  $selected_status = $status_in ?: 'completed';
                  // normaliza chave (com prefixo wc-)
                  if (strpos($selected_status, 'wc-') !== 0 && $selected_status !== '') {
                      $selected_status = 'wc-' . strtolower($selected_status);
                  }
                  foreach ($all_statuses as $key => $label):
                    $sel = selected($selected_status, $key, false);
                ?>
                  <option value="<?php echo esc_attr(str_replace('wc-','',$key)); ?>" <?php echo $sel; ?>>
                    <?php echo esc_html($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>Método</th>
            <td>
              <select name="method" style="min-width:260px;">
                <option value="">— Todos —</option>
                <?php foreach ($all_methods as $m):
                    $sel = selected($method_in, $m, false); ?>
                    <option value="<?php echo esc_attr($m); ?>" <?php echo $sel; ?>>
                      <?php echo esc_html($m); ?>
                    </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>Itens com valor zero</th>
            <td>
              <label>
                <input type="checkbox" name="hide_zero_items" value="1" <?php checked(!empty($_GET['hide_zero_items'])); ?>>
                Ocultar itens com valor do item = 0
              </label>
            </td>
          </tr>
          <tr>
            <th>Itens com líquido = 0</th>
            <td>
              <label>
                <input type="checkbox" name="hide_zero_net" value="1" <?php checked($hide_zero_net, 1); ?>>
                Ocultar itens com valor líquido = 0
              </label>
            </td>
          </tr>
          <tr>
            <th>Página única</th>
            <td>
              <label>
                <input type="checkbox" name="all" value="1" <?php checked($all); ?>>
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
          <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export' => 'page']), $base_url)); ?>">Exportar CSV (página atual)</a>
          <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export' => 'all']),  $base_url)); ?>">Exportar CSV (tudo)</a>
          <span class="description">Total de itens filtrados: <?php echo esc_html(number_format_i18n($total)); ?></span>
        </div>
      </form>

      <div class="ftsa-table-wrapper">
        <table class="widefat striped sticky-head">
          <thead>
            <tr>
              <th>Pedido</th><th>Status</th><th>Cliente</th><th>Data do Pedido</th><th>Método</th><th>TID</th><th>NSU</th><th>Nome do Item</th><th>Valor Item</th><th>Desconto (Cupom)</th><th>Taxa CIELO</th><th>Valor Líquido</th>
            </tr>
          </thead>
          <tbody>
          <?php
            $sum_discount = 0.0;
            if (empty($rows)): ?>
            <tr><td colspan="12">Nenhum registro encontrado.</td></tr>
          <?php else: foreach ($rows as $r):
              $order = wc_get_order($r['order_id']);
              $customer = '';
              if ($order && !is_a($order, 'WC_Order_Refund')) {
                  $customer = $order->get_formatted_billing_full_name() ?: $order->get_billing_email();
              }
              $edit_url = $order ? ( method_exists($order,'get_edit_order_url') ? $order->get_edit_order_url() : admin_url('post.php?post='.$r['order_id'].'&action=edit') )
                                 : admin_url('post.php?post='.$r['order_id'].'&action=edit');
              $discount = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
              $sum_discount += (float)$discount;
          ?>
            <tr>
              <td><a href="<?php echo esc_url($edit_url); ?>" target="_blank">#<?php echo esc_html($r['order_id']); ?></a></td>
              <td><?php echo esc_html($r['status_name']); ?></td>
              <td><?php echo esc_html($customer ?: '-'); ?></td>
              <td><?php echo esc_html(substr($r['order_date'], 0, 16)); ?></td>
              <td><?php echo esc_html($r['method_title'] ?: '-'); ?></td>
              <td><?php echo esc_html($r['tid'] ?: '-'); ?></td>
              <td><?php echo esc_html($r['nsu'] ?: '-'); ?></td>
              <td><?php echo esc_html($r['order_item_name']); ?></td>
              <td><?php echo wp_kses_post( wc_price((float)$r['line_subtotal']) ); ?></td>
              <td><?php echo wp_kses_post( wc_price((float)$discount) ); ?></td>
              <td><?php echo wp_kses_post( wc_price((float)$r['fee_share']) ); ?></td>
              <td><?php echo wp_kses_post( wc_price((float)$r['net_value']) ); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="8" style="text-align:right">Totais (página):</th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'line_subtotal'))) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) $sum_discount) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'fee_share'))) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'net_value'))) ); ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php if (!$all):
        $total_pages = max(1, (int) ceil($total / $per)); ?>
        <div class="tablenav">
          <div class="tablenav-pages" style="margin-top:10px">
            <?php for ($p = 1; $p <= $total_pages; $p++):
              $url = add_query_arg(array_merge($_GET, ['paged' => $p, 'all' => null]), $base_url);
              $class = ($p == $page) ? 'page-numbers current' : 'page-numbers'; ?>
              <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($p); ?></a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php

} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Relatório de Conciliação CIELO</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao gerar o relatório.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}

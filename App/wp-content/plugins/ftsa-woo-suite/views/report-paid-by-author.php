<?php
if (!defined('ABSPATH')) { exit; }

try {

    // dá um respiro para consultas "all"
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) { @set_time_limit(120); }

    // --- listas pro filtro (autores) ---
    $authors = get_posts([
        'post_type'      => 'ftsa_author',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);

    // --- filtros recebidos ---
    $author_id = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
    $paid_raw  = isset($_GET['paid']) ? $_GET['paid'] : 1; // padrão SIM
    $paid      = (int) (in_array((string)$paid_raw, ['0','1'], true) ? $paid_raw : 1);

    // helper p/ desconto do item (fallback por item do pedido)
    if (!function_exists('ftsa_item_discount_amount')) {
        function ftsa_item_discount_amount($order, $order_item_id) {
            if (!$order || !$order_item_id) return 0.0;
            $it = $order->get_item((int)$order_item_id);
            if (!$it || !is_a($it, 'WC_Order_Item_Product')) return 0.0;
            $sub = (float)$it->get_subtotal();
            $tot = (float)$it->get_total();
            $disc = $sub - $tot;
            return $disc > 0 ? $disc : 0.0;
        }
    }

    // Filtros fixos solicitados: somente concluídos e ocultar itens com valor 0
    $filters = [
        'start'             => $this->normalize_date_input($_GET['start'] ?? ''), // (opcional; não exibido)
        'end'               => $this->normalize_date_input($_GET['end']   ?? ''), // (opcional; não exibido)
        'status'            => ['wc-completed'], // SOMENTE concluídos
        'method_title_like' => '',
        'author_id'         => $author_id,
        'product_ids'       => [],
        'paid_flag'         => $paid,            // *** chave deste relatório
        'hide_zero_items'   => 1,                // NÃO listar itens com valor = 0
    ];

    // --- paginação / página única ---
    $per   = max(10, (int) ($_GET['per_page'] ?? 50));
    $page  = max(1,  (int) ($_GET['paged']    ?? 1));
    $all   = !empty($_GET['all']);
    $off   = $all ? 0 : ($per * ($page - 1));
    $lim   = $all ? 200000 : $per; // trava de segurança p/ "tudo"

    // --- consulta otimizada via store (SQL) ---
    $res   = $this->store->query_line_items_with_commission($filters, $lim, $off, $all);
    $rows  = $res['rows'];
    $total = (int) $res['total'];
    $sums  = isset($res['sums']) ? $res['sums'] : [];

    // --- exportação CSV ---
    $export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';
    if ($export === 'page' || $export === 'all') {
        $to_export = ($export === 'all')
            ? $this->store->query_line_items_with_commission($filters, 200000, 0, true)['rows']
            : $rows;

        $scope = ($export === 'all') ? 'all' : 'page';
        $filename = 'ftsa_comissoes_por_autor_' . date('Ymd_His') . '_' . $scope . '.csv';


        $this->export_csv_generic($filename, [
            'Pedido','Status','Nome do Item','Data do Pedido','Data do Pagamento',
            'Data Repasse (CIELO)','Data do Pagamento da Comissão',
            'Valor do Item','Desconto (Cupom)','Valor da Taxa Cielo','Valor Líquido','Valor da Comissão paga'
        ], function($out) use ($to_export) {

            foreach ($to_export as $r) {
                $order = wc_get_order($r['order_id']);

                // Datas do pedido (evita chamar métodos inexistentes em refunds)
                $paid_date = '';
                if ($order && !is_a($order, 'WC_Order_Refund') && method_exists($order, 'get_date_paid')) {
                    $dt = $order->get_date_paid();
                    $paid_date = $dt ? $dt->date_i18n('Y-m-d') : '';
                }

                // Data de repasse (meta gravado na importação CSV da CIELO)
                $settlement = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';

                // Desconto (preferir discount_amount; depois line_total; depois fallback do item)
                $valor_bruto = (float)($r['line_subtotal'] ?? 0);
                $valor_total = array_key_exists('line_total', $r) ? (float)$r['line_total'] : $valor_bruto;
                if (array_key_exists('discount_amount', $r)) {
                    $valor_desc = (float)$r['discount_amount'];
                } elseif (array_key_exists('line_total', $r)) {
                    $valor_desc = max(0.0, $valor_bruto - $valor_total);
                } else {
                    $valor_desc = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
                }

                fputcsv($out, [
                    '#'.$r['order_id'],
                    $r['status_name'],
                    $r['order_item_name'],
                    substr($r['order_date'], 0, 10),
                    $paid_date,
                    $settlement,
                    ($r['comm_paid_date'] ?: ''),
                    number_format((float)$valor_bruto, 2, ',', ''),
                    number_format((float)$valor_desc,  2, ',', ''),
                    number_format((float)$r['fee_share'],     2, ',', ''),
                    number_format((float)$r['net_value'],     2, ',', ''),
                    number_format((float)$r['commission'],    2, ',', ''),
                ], ';');
            }
        });
        return; // encerra a view após exportar
    }

    $base_url = admin_url('admin.php?page=' . self::MENU_PAID_BY_AUTHOR_SLUG);
    ?>
    <div class="wrap">
      <h1>Relatório de Comissões Pagas por Autor</h1>

      <form method="get" class="ftsa-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_PAID_BY_AUTHOR_SLUG); ?>">
        <table class="form-table">
          <tr>
            <th>Autor</th>
            <td>
              <select name="author_id" style="min-width:320px;">
                <option value="0">— Selecione —</option>
                <?php foreach ($authors as $a): ?>
                  <option value="<?php echo esc_attr($a->ID); ?>" <?php selected($author_id, $a->ID); ?>>
                    <?php echo esc_html($a->post_title) . ' (#' . $a->ID . ')'; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>Comissão Paga</th>
            <td>
              <label style="margin-right:12px;">
                <input type="radio" name="paid" value="1" <?php checked($paid, 1); ?>> SIM
              </label>
              <label>
                <input type="radio" name="paid" value="0" <?php checked($paid, 0); ?>> NÃO
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
          <a class="button button-secondary"
             href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export' => 'page']), $base_url)); ?>">
            Exportar CSV (página atual)
          </a>
          <a class="button button-secondary"
             href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export' => 'all']),  $base_url)); ?>">
            Exportar CSV (tudo)
          </a>
          <span class="description">
            Total de itens filtrados: <?php echo esc_html(number_format_i18n($total)); ?>
          </span>
        </div>
      </form>

      <div class="ftsa-table-wrapper">
        <table class="widefat striped sticky-head">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Status</th>
              <th>Nome do Item</th>
              <th>Data do Pedido</th>
              <th>Data do Pagamento</th>
              <th>Data Repasse (CIELO)</th>
              <th>Data do Pagamento da Comissão</th>
              <th>Valor do Item</th>
              <th>Desconto (Cupom)</th>
              <th>Valor da Taxa Cielo</th>
              <th>Valor Líquido</th>
              <th>Valor da Comissão paga</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="12">Nenhum registro encontrado.</td></tr>
          <?php else: 
            $sum_discount = 0.0;
            foreach ($rows as $r):
                $order = wc_get_order($r['order_id']);

                // link de edição seguro
                $edit_url = $order
                    ? ( method_exists($order, 'get_edit_order_url') ? $order->get_edit_order_url()
                        : admin_url('post.php?post=' . $r['order_id'] . '&action=edit') )
                    : admin_url('post.php?post=' . $r['order_id'] . '&action=edit');

                // data de pagamento do pedido (se não for refund)
                $paid_date = '';
                if ($order && !is_a($order, 'WC_Order_Refund') && method_exists($order, 'get_date_paid')) {
                    $dt = $order->get_date_paid();
                    $paid_date = $dt ? $dt->date_i18n('Y-m-d') : '';
                }

                // Data de repasse da CIELO (meta do pedido)
                $settlement = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';

                // Desconto (preferir discount_amount; depois line_total; depois fallback do item)
                $valor_bruto = (float)($r['line_subtotal'] ?? 0);
                $valor_total = array_key_exists('line_total', $r) ? (float)$r['line_total'] : $valor_bruto;
                if (array_key_exists('discount_amount', $r)) {
                    $valor_desc = (float)$r['discount_amount'];
                } elseif (array_key_exists('line_total', $r)) {
                    $valor_desc = max(0.0, $valor_bruto - $valor_total);
                } else {
                    $valor_desc = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
                }
                $sum_discount += (float)$valor_desc;
            ?>
              <tr>
                <td><a href="<?php echo esc_url($edit_url); ?>" target="_blank">#<?php echo esc_html($r['order_id']); ?></a></td>
                <td><?php echo esc_html($r['status_name']); ?></td>
                <td><?php echo esc_html($r['order_item_name']); ?></td>
                <td><?php echo esc_html(substr($r['order_date'], 0, 10)); ?></td>
                <td><?php echo esc_html($paid_date); ?></td>
                <td><?php echo esc_html($settlement); ?></td>
                <td><?php echo esc_html($r['comm_paid_date'] ?: ''); ?></td>
                <td><?php echo wp_kses_post( wc_price((float) $valor_bruto) ); ?></td>
                <td><?php echo wp_kses_post( wc_price((float) $valor_desc) ); ?></td>
                <td><?php echo wp_kses_post( wc_price((float) $r['fee_share']) ); ?></td>
                <td><?php echo wp_kses_post( wc_price((float) $r['net_value']) ); ?></td>
                <td><?php echo wp_kses_post( wc_price((float) $r['commission']) ); ?></td>
              </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="7" style="text-align:right">Totais (página):</th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'line_subtotal'))) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) $sum_discount) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'fee_share'))) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'net_value'))) ); ?></th>
              <th class="ftsa-totals"><?php echo wp_kses_post( wc_price((float) array_sum(array_column($rows, 'commission'))) ); ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php if (!$all):
        $total_pages = max(1, (int) ceil($total / $per)); ?>
        <div class="tablenav">
          <div class="tablenav-pages" style="margin-top:10px">
            <?php for ($p = 1; $p <= $total_pages; $p++):
                $url   = add_query_arg(array_merge($_GET, ['paged' => $p, 'all' => null]), $base_url);
                $class = ($p == $page) ? 'page-numbers current' : 'page-numbers'; ?>
              <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                <?php echo esc_html($p); ?>
              </a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php

} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Comissões Pagas por Autor</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao gerar o relatório.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}

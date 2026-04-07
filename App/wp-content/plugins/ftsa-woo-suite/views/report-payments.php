<?php
if (!defined('ABSPATH')) { exit; }

try {
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) { @set_time_limit(120); }

    // --- listas para o filtro (autores) ---
    $authors = get_posts([
        'post_type'      => 'ftsa_author',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);

    // --- filtros recebidos ---
    $author_id    = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
    $start_filter = $this->normalize_date_input($_GET['start'] ?? '');
    $end_filter   = $this->normalize_date_input($_GET['end']   ?? '');
    // include_zero = 1 => exibir zeros; vazio => ocultar zeros
    $include_zero = !empty($_GET['include_zero']) ? 1 : 0;

    // --- filtros para a store ---
    $filters = [
        'start'             => $start_filter,
        'end'               => $end_filter,
        'status'            => [],        // sem restrição de status aqui
        'method_title_like' => '',
        'author_id'         => $author_id,
        'product_ids'       => [],
        'paid_flag'         => null,      // tela lista pagos e não pagos
        'hide_zero_items'   => $include_zero ? 0 : 1,
    ];

    // --- paginação / página única ---
    $per   = max(10, (int)($_GET['per_page'] ?? 50));
    $page  = max(1,  (int)($_GET['paged']    ?? 1));
    $all   = !empty($_GET['all']);
    $off   = $all ? 0 : ($per * ($page - 1));
    $lim   = $all ? 200000 : $per;

    // --- consulta ---
    $res   = $this->store->query_line_items_with_commission($filters, $lim, $off, $all);
    $rows  = $res['rows'];
    $total = (int)$res['total'];
    $sums  = isset($res['sums']) && is_array($res['sums']) ? $res['sums'] : [];

    $base_url = admin_url('admin.php?page=' . self::MENU_PAY_SLUG);

    if (!empty($_GET['pmsg'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($_GET['pmsg']) . '</p></div>';
    }
    ?>

    <div class="wrap">
      <h1>FTSA - Registro de Pagamento da Comissão por Autor</h1>

      <form method="get" class="ftsa-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_PAY_SLUG); ?>">
        <table class="form-table">
          <tr>
            <th>Data Início</th>
            <td><input type="date" name="start" value="<?php echo esc_attr($start_filter); ?>"></td>
          </tr>
          <tr>
            <th>Data Término</th>
            <td><input type="date" name="end" value="<?php echo esc_attr($end_filter); ?>"></td>
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
          <button type="button" class="button" id="ftsa-mark-all-yes">Marcar todos como <strong>SIM</strong></button>
          &nbsp;
          <button type="submit" class="button button-primary">Salvar alterações</button>
        </p>

        <div class="ftsa-table-wrapper">
          <table class="widefat striped sticky-head">
            <thead>
              <tr>
                <th>Nº Pedido</th>
                <th>Item</th>
                <th>Data do Pedido</th>
                <th>Data Repasse (CIELO)</th>
                <th>Qtd</th>
                <th>Valor Bruto</th>
                <th>Desconto (Cupom)</th>
                <th>Taxa Cielo</th>
                <th>Valor Líquido</th>
                <th>Autor</th>
                <th>% Comissão</th>
                <th>Valor Comissão</th>
                <th>Pago?</th>
                <th>Data Pagamento Comissão</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="14">Nenhum registro encontrado.</td></tr>
            <?php else: foreach ($rows as $r):
                $order = wc_get_order($r['order_id']);
                $edit_url = $order
                    ? ( method_exists($order, 'get_edit_order_url') ? $order->get_edit_order_url()
                        : admin_url('post.php?post=' . $r['order_id'] . '&action=edit') )
                    : admin_url('post.php?post=' . $r['order_id'] . '&action=edit');

                $author = get_the_title((int)$r['author_id']) ?: '-';
                $paid   = ($r['comm_paid'] === '1');

                // Datas
                $order_date = isset($r['order_date']) ? substr($r['order_date'], 0, 10) : '';
                $settlement = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';

                // Cálculos (com desconto)
                $valor_bruto   = (float)$r['line_subtotal'];
                $valor_total   = array_key_exists('line_total', $r) ? (float)$r['line_total'] : $valor_bruto; // após cupom
                $valor_desc    = array_key_exists('discount_amount', $r)
                                   ? (float)$r['discount_amount']
                                   : max(0.0, $valor_bruto - $valor_total);
                $taxa_cielo    = (float)$r['fee_share'];
                // net_value preferencialmente vem da store (line_total - fee_share). Se não, calculamos.
                $valor_liquido = array_key_exists('net_value', $r)
                                   ? (float)$r['net_value']
                                   : max(0.0, $valor_total - $taxa_cielo);
                $valor_comm    = (float)$r['commission'];
                $percent       = (float)$r['author_percent'];
            ?>
              <tr>
                <td><a href="<?php echo esc_url($edit_url); ?>" target="_blank">#<?php echo esc_html($r['order_id']); ?></a></td>
                <td><?php echo esc_html($r['order_item_name']); ?></td>
                <td><?php echo esc_html($order_date); ?></td>
                <td><?php echo esc_html($settlement); ?></td>
                <td><?php echo esc_html((int)$r['qty']); ?></td>
                <td><?php echo wp_kses_post( wc_price($valor_bruto) ); ?></td>
                <td><?php echo wp_kses_post( wc_price($valor_desc) ); ?></td>
                <td><?php echo wp_kses_post( wc_price($taxa_cielo) ); ?></td>
                <td><?php echo wp_kses_post( wc_price($valor_liquido) ); ?></td>
                <td><?php echo esc_html($author); ?></td>
                <td><?php echo esc_html(number_format($percent,2,',','')); ?>%</td>
                <td><?php echo wp_kses_post( wc_price($valor_comm) ); ?></td>

                <td>
                  <input type="hidden" name="payment_present[<?php echo esc_attr($r['order_item_id']); ?>]" value="1" />
                  <select name="payment[<?php echo esc_attr($r['order_item_id']); ?>][paid]" class="ftsa-paid-select">
                    <option value="0" <?php selected(!$paid); ?>>NÃO</option>
                    <option value="1" <?php selected($paid); ?>>SIM</option>
                  </select>
                </td>
                <td>
                  <input type="date" name="payment[<?php echo esc_attr($r['order_item_id']); ?>][date]"
                         value="<?php echo esc_attr($r['comm_paid_date'] ?: ''); ?>" class="ftsa-paid-date" />
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php
              // Totais (página)
              $sum_bruto = (float) array_sum(array_column($rows, 'line_subtotal'));
              $sum_desc  = (float) array_sum(array_map(function($rr){
                  $sb = (float)$rr['line_subtotal'];
                  $tl = array_key_exists('line_total', $rr) ? (float)$rr['line_total'] : $sb;
                  $d  = array_key_exists('discount_amount', $rr) ? (float)$rr['discount_amount'] : max(0.0, $sb - $tl);
                  return $d;
              }, $rows));
              $sum_taxa  = (float) array_sum(array_column($rows, 'fee_share'));
              $sum_liq   = (float) array_sum(array_map(function($rr){
                  $sb = (float)$rr['line_subtotal'];
                  $tl = array_key_exists('line_total', $rr) ? (float)$rr['line_total'] : $sb;
                  $fs = (float)$rr['fee_share'];
                  $nv = array_key_exists('net_value', $rr) ? (float)$rr['net_value'] : max(0.0, $tl - $fs);
                  return $nv;
              }, $rows));
              $sum_comm  = (float) array_sum(array_column($rows, 'commission'));
            ?>
            <tfoot>
              <tr>
                <th colspan="5" style="text-align:right">Totais (página):</th>
                <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_bruto) ); ?></th>
                <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_desc) ); ?></th>
                <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_taxa) ); ?></th>
                <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_liq) ); ?></th>
                <th></th><th></th>
                <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_comm) ); ?></th>
                <th></th><th></th>
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

        <p style="margin-top:12px;">
          <button type="button" class="button" id="ftsa-mark-all-yes-bottom">Marcar todos como <strong>SIM</strong></button>
          &nbsp;
          <button type="submit" class="button button-primary">Salvar alterações</button>
        </p>
      </form>
    </div>

    <script>
    (function($){
      function markAllYes(scope){
        $(scope).find('select.ftsa-paid-select').val('1').trigger('change');
        var today = new Date().toISOString().slice(0,10);
        $(scope).find('input.ftsa-paid-date').each(function(){
          if(!this.value) this.value = today;
        });
      }
      $('#ftsa-mark-all-yes, #ftsa-mark-all-yes-bottom').on('click', function(){
        var form = $(this).closest('form');
        markAllYes(form);
      });
      $(document).on('change', 'select.ftsa-paid-select', function(){
        var $row = $(this).closest('tr');
        if ($(this).val() === '1') {
          var $date = $row.find('input.ftsa-paid-date');
          if(!$date.val()){
            $date.val(new Date().toISOString().slice(0,10));
          }
        }
      });
    })(jQuery);
    </script>

    <?php
} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Pagamento Comissão</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao gerar a página.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}
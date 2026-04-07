<?php
if (!defined('ABSPATH')) { exit; }

/**
 * FTSA - Relatório de Vendas
 * - Valor do Item (line_subtotal)
 * - Taxa de Entrega (rateio proporcional por quantidade)
 * - Valor Líquido = Valor do Item + Taxa de Entrega - Desconto (Cupom) - Taxa Cielo
 * - Valor Comissão calculado sobre o Valor Líquido
 * - Lucro Líquido = Valor Líquido - Valor Comissão
 */

$products = get_posts([
  'post_type'      => 'product',
  'posts_per_page' => 500,
  'orderby'        => 'title',
  'order'          => 'ASC',
  'post_status'    => 'publish'
]);

// product_ids pode vir como array ou string
$product_ids = $_GET['product_ids'] ?? [];
if (!is_array($product_ids)) $product_ids = [$product_ids];

$filters = [
    'start' => $this->normalize_date_input($_GET['start'] ?? ''),
    'end'   => $this->normalize_date_input($_GET['end']   ?? ''),
    'status'=> [],
    'method_title_like' => '',
    'author_id' => 0,
    'product_ids'=> array_map('intval', $product_ids),
    'paid_flag'=> null,
    'hide_zero_items' => empty($_GET['include_zero']) ? 1 : 0,
];

// paginação
$per   = max(10, (int)($_GET['per_page'] ?? 50));
$page  = max(1,  (int)($_GET['paged'] ?? 1));
$all   = !empty($_GET['all']);
$off   = $all ? 0 : ($per * ($page-1));
$lim   = $all ? 200000 : $per;

$res   = $this->store->query_line_items_with_commission($filters, $lim, $off, $all);
$rows  = $res['rows'];
$total = (int)$res['total'];

/** ===== Helpers ===== */

/** Desconto do item (fallback por item do pedido) */
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

/**
 * Rateia a taxa de entrega (shipping_total) por quantidade total do pedido.
 * Retorna a parte proporcional referente à $line_qty.
 */
if (!function_exists('ftsa_shipping_share_by_qty')) {
    function ftsa_shipping_share_by_qty($order, $line_qty) {
        static $cache = []; // [order_id => ['qty_total'=>n, 'ship_total'=>v]]
        if (!$order) return 0.0;
        $oid = (int)$order->get_id();
        if (!isset($cache[$oid])) {
            $qty_total = 0.0;
            foreach ($order->get_items('line_item') as $item) {
                if ($item instanceof WC_Order_Item_Product) {
                    $qty_total += (float)$item->get_quantity();
                }
            }
            $ship_total = (float)$order->get_shipping_total(); // sem impostos
            $cache[$oid] = ['qty_total'=>$qty_total, 'ship_total'=>$ship_total];
        }
        $qty_total = (float)$cache[$oid]['qty_total'];
        $ship_total = (float)$cache[$oid]['ship_total'];
        if ($qty_total <= 0 || $ship_total == 0.0) return 0.0;
        return ((float)$line_qty) * ($ship_total / $qty_total);
    }
}

/* ===================== EXPORT CSV ===================== */
$export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';
if ($export === 'page' || $export === 'all') {
    $to_export = ($export === 'all')
        ? $this->store->query_line_items_with_commission($filters, 200000, 0, true)['rows']
        : $rows;

    $scope = ($export === 'all') ? 'all' : 'page';
    $filename = 'ftsa_relatorio_vendas_' . date('Ymd_His') . '_' . $scope . '.csv';

    $this->export_csv_generic($filename, [
        'Nº Pedido',
        'Nome do Item',
        'Qtd',
        'Valor do Item',
        'Taxa de Entrega',
        'Desconto (Cupom)',
        'Taxa Cielo',
        'Valor Líquido',
        'Autor',
        '% Comissão',
        'Valor Comissão',
        'Data Compra',
        'Data Pagamento',
        'Data Repasse (CIELO)',
        'Lucro Líquido'
    ], function($out) use ($to_export){
        foreach ($to_export as $r) {
            $order = wc_get_order($r['order_id']);
            $paid_date  = ($order && method_exists($order,'get_date_paid') && $order->get_date_paid())
                          ? $order->get_date_paid()->date_i18n('Y-m-d') : '';
            $settlement = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';

            $qty         = (float)($r['qty'] ?? 0);
            $valor_item  = (float)($r['line_subtotal'] ?? 0);
            $taxa_ent    = ftsa_shipping_share_by_qty($order, $qty);
            $desconto    = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
            $taxa_cielo  = (float)($r['fee_share'] ?? 0);

            $valor_liq   = $valor_item + $taxa_ent - $desconto - $taxa_cielo;

            $author_name = get_the_title((int)($r['author_id'] ?? 0)) ?: '-';
            $perc        = (float)($r['author_percent'] ?? 0);
            $valor_comm  = $valor_liq * ($perc/100.0);
            $lucro_liq   = $valor_liq - $valor_comm;

            fputcsv($out, [
                '#'.$r['order_id'],
                $r['order_item_name'],
                (int)$qty,
                number_format($valor_item,  2, ',', ''),
                number_format($taxa_ent,    2, ',', ''),
                number_format($desconto,    2, ',', ''),
                number_format($taxa_cielo,  2, ',', ''),
                number_format($valor_liq,   2, ',', ''),
                $author_name,
                number_format($perc,        2, ',', ''),
                number_format($valor_comm,  2, ',', ''),
                substr((string)$r['order_date'],0,10),
                $paid_date,
                $settlement,
                number_format($lucro_liq,   2, ',', ''),
            ], ';');
        }
    });
    return;
}

// ✅ SLUG certo
$base_url = admin_url('admin.php?page='.self::MENU_SALES_REPORT_SLUG);
?>
<div class="wrap">
  <h1>FTSA - Relatório de Vendas</h1>

  <form method="get" class="ftsa-filters">
    <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SALES_REPORT_SLUG); ?>">
    <table class="form-table">
      <tr><th>Data Início</th><td><input type="date" name="start" value="<?php echo esc_attr($filters['start']); ?>"></td></tr>
      <tr><th>Data Término</th><td><input type="date" name="end" value="<?php echo esc_attr($filters['end']); ?>"></td></tr>

      <tr><th>Produto(s)</th><td>
        <select name="product_ids[]" multiple size="8" style="min-width:320px;">
          <?php foreach ($products as $p): $sel=in_array($p->ID, $filters['product_ids'], true) ? ' selected':''; ?>
          <option value="<?php echo esc_attr($p->ID); ?>"<?php echo $sel; ?>>
            <?php echo esc_html($p->post_title).' (#'.$p->ID.')'; ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="description">Selecione um ou mais produtos (Ctrl/Cmd para múltiplos).</p>
      </td></tr>

      <tr><th>Itens com valor zero</th>
        <td><label><input type="checkbox" name="include_zero" value="1" <?php checked(!empty($_GET['include_zero'])); ?>>
          Incluir itens com valor de item = 0</label></td></tr>

      <tr><th>Página única</th>
        <td><label><input type="checkbox" name="all" value="1" <?php checked($all); ?>>
          Exibir tudo em uma página (todos)</label></td></tr>

      <tr><th>Por página</th><td><input type="number" name="per_page" min="10" value="<?php echo esc_attr($per); ?>"></td></tr>
    </table>

    <?php submit_button('Filtrar'); ?>

    <div class="ftsa-actions">
      <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET,['export'=>'page']), $base_url)); ?>">Exportar CSV (página atual)</a>
      <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET,['export'=>'all']),  $base_url)); ?>">Exportar CSV (tudo)</a>
      <span class="description">Total de itens filtrados: <?php echo esc_html(number_format_i18n($total)); ?></span>
    </div>
  </form>

  <div class="ftsa-table-wrapper">
  <table class="widefat striped sticky-head">
    <thead><tr>
      <th>Nº Pedido</th>
      <th>Item</th>
      <th>Qtd</th>
      <th>Valor do Item</th>
      <th>Taxa de Entrega</th>
      <th>Desconto (Cupom)</th>
      <th>Taxa Cielo</th>
      <th>Valor Líquido</th>
      <th>Autor</th>
      <th>% Comissão</th>
      <th>Valor Comissão</th>
      <th>Data Compra</th>
      <th>Data Pagamento</th>
      <th>Data Repasse (CIELO)</th>
      <th>Lucro Líquido</th>
    </tr></thead>
    <tbody>
    <?php
      $sum_valor_item  = 0.0;
      $sum_taxa_ent    = 0.0;
      $sum_desconto    = 0.0;
      $sum_taxa_cielo  = 0.0;
      $sum_valor_liq   = 0.0;
      $sum_comm        = 0.0;
      $sum_lucro_liq   = 0.0;

      if (empty($rows)): ?>
      <tr><td colspan="15">Nenhum registro encontrado.</td></tr>
    <?php else: foreach ($rows as $r):
        $order = wc_get_order($r['order_id']);
        $edit_url = $order
          ? ( method_exists($order,'get_edit_order_url') ? $order->get_edit_order_url() : admin_url('post.php?post='.$r['order_id'].'&action=edit') )
          : admin_url('post.php?post='.$r['order_id'].'&action=edit');

        $paid_date = ($order && method_exists($order,'get_date_paid') && $order->get_date_paid())
                    ? $order->get_date_paid()->date_i18n('Y-m-d') : '';

        $author = get_the_title((int)($r['author_id'] ?? 0)) ?: '-';

        $qty         = (float)($r['qty'] ?? 0);
        $valor_item  = (float)($r['line_subtotal'] ?? 0);
        $taxa_ent    = ftsa_shipping_share_by_qty($order, $qty);
        $desconto    = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
        $taxa_cielo  = (float)($r['fee_share'] ?? 0);

        $valor_liq   = $valor_item + $taxa_ent - $desconto - $taxa_cielo;

        $perc        = (float)($r['author_percent'] ?? 0);
        $valor_comm  = $valor_liq * ($perc/100.0);

        $settlement  = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';
        $lucro_liq   = $valor_liq - $valor_comm;

        $sum_valor_item += $valor_item;
        $sum_taxa_ent   += $taxa_ent;
        $sum_desconto   += $desconto;
        $sum_taxa_cielo += $taxa_cielo;
        $sum_valor_liq  += $valor_liq;
        $sum_comm       += $valor_comm;
        $sum_lucro_liq  += $lucro_liq;
    ?>
      <tr>
        <td><a href="<?php echo esc_url($edit_url); ?>" target="_blank">#<?php echo esc_html($r['order_id']); ?></a></td>
        <td><?php echo esc_html($r['order_item_name']); ?></td>
        <td><?php echo esc_html((int)$qty); ?></td>
        <td><?php echo wp_kses_post( wc_price($valor_item) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($taxa_ent) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($desconto) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($taxa_cielo) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($valor_liq) ); ?></td>
        <td><?php echo esc_html($author); ?></td>
        <td><?php echo esc_html(number_format($perc,2,',','')); ?>%</td>
        <td><?php echo wp_kses_post( wc_price($valor_comm) ); ?></td>
        <td><?php echo esc_html(substr((string)$r['order_date'],0,10)); ?></td>
        <td><?php echo esc_html($paid_date); ?></td>
        <td><?php echo esc_html($settlement); ?></td>
        <td><?php echo wp_kses_post( wc_price($lucro_liq) ); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <tfoot><tr>
      <th colspan="3" style="text-align:right">Totais (página):</th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_valor_item) ); ?></th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_taxa_ent) ); ?></th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_desconto) ); ?></th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_taxa_cielo) ); ?></th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_valor_liq) ); ?></th>
      <th></th>
      <th></th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_comm) ); ?></th>
      <th></th>
      <th></th>
      <th></th>
      <th class="ftsa-totals"><?php echo wp_kses_post( wc_price($sum_lucro_liq) ); ?></th>
    </tr></tfoot>
  </table>
  </div>

  <?php if (!$all):
    $total_pages = max(1, (int)ceil($total / $per)); ?>
    <div class="tablenav">
      <div class="tablenav-pages" style="margin-top:10px">
        <?php for ($p=1; $p<=$total_pages; $p++):
          $url = add_query_arg(array_merge($_GET, ['paged'=>$p, 'all'=>null]), $base_url);
          $class = ($p==$page) ? 'page-numbers current' : 'page-numbers'; ?>
          <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($p); ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
    
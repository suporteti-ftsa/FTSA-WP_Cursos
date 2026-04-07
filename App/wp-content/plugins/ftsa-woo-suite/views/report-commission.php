<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Relatório Comissão
 * - Valor do Item (ex-Valor Bruto)
 * - Taxa de Entrega (rateio proporcional por quantidade)
 * - Valor Líquido = Valor do Item + Taxa de Entrega - Desconto (Cupom) - Taxa Cielo
 * - Valor Comissão calculado sobre o Valor Líquido
 * - Lucro Líquido = Valor Líquido - Valor Comissão
 */

$authors = get_posts([
  'post_type'=>'ftsa_author','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>'publish'
]);
$products = get_posts([
  'post_type'=>'product','posts_per_page'=>500,'orderby'=>'title','order'=>'ASC','post_status'=>'publish'
]);

// lê "paid_flag" da query (?paid_flag=1|0|''), default = todos
$paid_qs = isset($_GET['paid_flag']) ? trim((string)$_GET['paid_flag']) : '';
$paid_flag = ($paid_qs === '1') ? 1 : (($paid_qs === '0') ? 0 : null);

$filters = [
    'start' => $this->normalize_date_input($_GET['start'] ?? ''),
    'end'   => $this->normalize_date_input($_GET['end']   ?? ''),
    'status'=> [],
    'method_title_like' => '',
    'author_id' => 0,
    'product_ids'=> array_map('intval', $_GET['product_ids'] ?? []),
    'paid_flag'=> $paid_flag, // << novo filtro ativo
    'hide_zero_items' => empty($_GET['include_zero']) ? 1 : 0,
];

// paginação
$per   = max(10, (int)($_GET['per_page'] ?? 50));
$page  = max(1, (int)($_GET['paged'] ?? 1));
$all   = !empty($_GET['all']);
$off   = $all ? 0 : ($per * ($page-1));
$lim   = $all ? 200000 : $per;

// ordenação (client-side nesta view)
$orderby = sanitize_key($_GET['orderby'] ?? 'order_id'); // chave
$order   = strtolower((string)($_GET['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc'; // direção

$res   = $this->store->query_line_items_with_commission($filters, $lim, $off, $all);
$rows  = $res['rows'];
$total = (int)$res['total'];
$sums  = $res['sums'] ?? [];

// base url p/ links
$base_url = admin_url('admin.php?page='.self::MENU_COMM_SLUG);

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

/** Nome do Cliente (comprador) */
if (!function_exists('ftsa_customer_name')) {
    function ftsa_customer_name($order) {
        if (!$order) return '-';
        if (method_exists($order, 'get_formatted_billing_full_name')) {
            $name = (string)$order->get_formatted_billing_full_name();
            if ($name !== '') return $name;
        }
        if (method_exists($order, 'get_billing_first_name')) {
            $fn = (string)$order->get_billing_first_name();
            $ln = (string)$order->get_billing_last_name();
            $name = trim($fn.' '.$ln);
            if ($name !== '') return $name;
        }
        $uid = method_exists($order,'get_user_id') ? (int)$order->get_user_id() : 0;
        if ($uid) {
            $u = get_user_by('id',$uid);
            if ($u && !empty($u->display_name)) return (string)$u->display_name;
        }
        return '-';
    }
}

/** Data de Pagamento da Comissão (meta do item) */
if (!function_exists('ftsa_commission_paid_date')) {
    function ftsa_commission_paid_date($order_item_id) {
        $d = (string) wc_get_order_item_meta((int)$order_item_id, FTSA_Woo_Suite::COMM_PAID_DATE_META, true);
        return $d ?: '';
    }
}

/** Cabeçalho com link de ordenação */
if (!function_exists('ftsa_sort_link')) {
    function ftsa_sort_link($base_url, $label, $key, $current_key, $current_dir) {
        $next_dir = ($current_key === $key && $current_dir === 'asc') ? 'desc' : 'asc';
        $icon     = ($current_key === $key) ? ($current_dir === 'asc' ? ' ↑' : ' ↓') : '';
        $url = add_query_arg(array_merge($_GET, ['orderby'=>$key, 'order'=>$next_dir]), $base_url);
        return '<a href="'.esc_url($url).'">'.esc_html($label).$icon.'</a>';
    }
}

/* ===================== EXPORT CSV ===================== */
$export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';
if ($export === 'page' || $export === 'all') {
    // pega os registros a exportar
    $to_export = ($export === 'all')
        ? $this->store->query_line_items_with_commission($filters, 200000, 0, true)['rows']
        : $rows;

    $scope = ($export === 'all') ? 'all' : 'page';
    $filename = 'ftsa_relatorio_comissoes_' . date('Ymd_His') . '_' . $scope . '.csv';

    $this->export_csv_generic($filename, [
        'Nº Pedido',
        'Cliente',
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
        'Comissão Paga?',
        'Data Pagto Comissão',
        'Lucro Líquido'
    ], function($out) use ($to_export){
        foreach ($to_export as $r) {
            $order = wc_get_order($r['order_id']);

            // Datas
            $paid_date  = ($order && method_exists($order,'get_date_paid') && $order->get_date_paid())
                          ? $order->get_date_paid()->date_i18n('Y-m-d') : '';
            $settlement = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';

            // Cliente (comprador)
            $customer = '';
            if ($order) {
                if (method_exists($order, 'get_formatted_billing_full_name')) {
                    $customer = trim((string) $order->get_formatted_billing_full_name());
                }
                if ($customer === '') {
                    $fn = method_exists($order,'get_billing_first_name') ? (string)$order->get_billing_first_name() : '';
                    $ln = method_exists($order,'get_billing_last_name')  ? (string)$order->get_billing_last_name()  : '';
                    $customer = trim(trim($fn).' '.trim($ln));
                }
            }

            // Comissão paga?
            $item_id = (int)($r['order_item_id'] ?? 0);
            $comm_paid = $item_id ? wc_get_order_item_meta($item_id, FTSA_Woo_Suite::COMM_PAID_META, true) : '';
            $comm_paid_date = $item_id ? wc_get_order_item_meta($item_id, FTSA_Woo_Suite::COMM_PAID_DATE_META, true) : '';
            $comm_paid_txt = ($comm_paid === '1') ? 'Sim' : 'Não';

            // Cálculos financeiros
            $qty         = (float)($r['qty'] ?? 0);
            $valor_item  = (float)($r['line_subtotal'] ?? 0);
            // rateio da entrega por quantidade
            if (!function_exists('ftsa_shipping_share_by_qty')) { function ftsa_shipping_share_by_qty() { return 0.0; } }
            $taxa_ent    = ftsa_shipping_share_by_qty($order, $qty);

            // desconto pelo item (fallback)
            if (!function_exists('ftsa_item_discount_amount')) { function ftsa_item_discount_amount() { return 0.0; } }
            $desconto    = ftsa_item_discount_amount($order, $item_id);

            $taxa_cielo  = (float)($r['fee_share'] ?? 0);
            $valor_liq   = $valor_item + $taxa_ent - $desconto - $taxa_cielo;

            $author_name = get_the_title((int)($r['author_id'] ?? 0)) ?: '-';
            $perc        = (float)($r['author_percent'] ?? 0);
            $valor_comm  = $valor_liq * ($perc/100.0);
            $lucro_liq   = $valor_liq - $valor_comm;

            // Formatação 'excel-ptBR' (sem símbolo de moeda; vírgula decimal; sem milhar)
            $nf = function($v) { return number_format((float)$v, 2, ',', ''); };

            fputcsv($out, [
                $r['order_id'],                     // Nº Pedido (sem # para facilitar planilha)
                $customer,                          // Cliente
                $r['order_item_name'],              // Nome do Item
                (int)$qty,                          // Qtd
                $nf($valor_item),                   // Valor do Item
                $nf($taxa_ent),                     // Taxa de Entrega
                $nf($desconto),                     // Desconto (Cupom)
                $nf($taxa_cielo),                   // Taxa CIELO
                $nf($valor_liq),                    // Valor Líquido
                $author_name,                       // Autor
                $nf($perc),                         // % Comissão
                $nf($valor_comm),                   // Valor Comissão
                substr((string)$r['order_date'],0,10), // Data Compra
                $paid_date,                         // Data Pagamento
                $settlement,                        // Data Repasse (CIELO)
                $comm_paid_txt,                     // Comissão Paga?
                $comm_paid_date,                    // Data Pagto Comissão
                $nf($lucro_liq),                    // Lucro Líquido
            ], ';');
        }
    });
    return;
}


/* ===================== PRÉ-CÁLCULO PARA ORDENAR/EXIBIR ===================== */
$computed = [];
foreach ($rows as $r) {
    $order     = wc_get_order($r['order_id']);
    $edit_url  = $order
        ? ( method_exists($order,'get_edit_order_url') ? $order->get_edit_order_url() : admin_url('post.php?post='.$r['order_id'].'&action=edit') )
        : admin_url('post.php?post='.$r['order_id'].'&action=edit');

    $paid_date = ($order && method_exists($order,'get_date_paid') && $order->get_date_paid())
                ? $order->get_date_paid()->date_i18n('Y-m-d') : '';

    $author_name = get_the_title((int)($r['author_id'] ?? 0)) ?: '-';
    $customer    = ftsa_customer_name($order);
    $qty         = (float)($r['qty'] ?? 0);
    $valor_item  = (float)($r['line_subtotal'] ?? 0);
    $taxa_ent    = ftsa_shipping_share_by_qty($order, $qty);
    $desconto    = ftsa_item_discount_amount($order, $r['order_item_id'] ?? 0);
    $taxa_cielo  = (float)($r['fee_share'] ?? 0);
    $valor_liq   = $valor_item + $taxa_ent - $desconto - $taxa_cielo;
    $perc        = (float)($r['author_percent'] ?? 0);
    $valor_comm  = $valor_liq * ($perc/100.0);
    $settlement  = $order ? (string) $order->get_meta('_cielo_settlement_date') : '';
    $comm_paid   = ftsa_commission_paid_date($r['order_item_id'] ?? 0);
    $lucro_liq   = $valor_liq - $valor_comm;

    $computed[] = [
        'order_id'     => (int)$r['order_id'],
        'order_date'   => substr((string)$r['order_date'],0,10),
        'edit_url'     => $edit_url,
        'order_item_name' => (string)$r['order_item_name'],
        'customer'     => $customer,
        'qty'          => $qty,
        'valor_item'   => $valor_item,
        'taxa_ent'     => $taxa_ent,
        'desconto'     => $desconto,
        'taxa_cielo'   => $taxa_cielo,
        'valor_liq'    => $valor_liq,
        'author'       => $author_name,
        'percent'      => $perc,
        'valor_comm'   => $valor_comm,
        'paid_date'    => $paid_date,
        'settlement'   => $settlement,
        'comm_paid'    => $comm_paid,
        'lucro_liq'    => $lucro_liq,
        'order_item_id'=> (int)($r['order_item_id'] ?? 0),
    ];
}

// aplica ordenação (na página atual)
$sortable_keys = ['order_id','customer','order_item_name','qty','valor_item','taxa_ent','desconto','taxa_cielo','valor_liq','author','percent','valor_comm','comm_paid','order_date','paid_date','settlement','lucro_liq'];
if (!in_array($orderby, $sortable_keys, true)) { $orderby = 'order_id'; }
usort($computed, function($a,$b) use ($orderby,$order) {
    $va = $a[$orderby] ?? null; $vb = $b[$orderby] ?? null;
    if (is_numeric($va) && is_numeric($vb)) {
        $cmp = $va <=> $vb;
    } else {
        $cmp = strcasecmp((string)$va, (string)$vb);
    }
    return ($order === 'desc') ? -$cmp : $cmp;
});

?>
<div class="wrap">
  <h1>FTSA - Relatório de Comissões Pagas aos Autores</h1>

  <form method="get" class="ftsa-filters">
    <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_COMM_SLUG); ?>">
    <table class="form-table">
      <tr><th>Data Início</th><td><input type="date" name="start" value="<?php echo esc_attr($filters['start']); ?>"></td></tr>
      <tr><th>Data Término</th><td><input type="date" name="end" value="<?php echo esc_attr($filters['end']); ?>"></td></tr>
      <tr><th>Produto(s)</th><td>
        <select name="product_ids[]" multiple size="8" style="min-width:320px;">
          <?php foreach ($products as $p): $sel=in_array($p->ID, $filters['product_ids'])?' selected':''; ?>
          <option value="<?php echo esc_attr($p->ID); ?>"<?php echo $sel; ?>><?php echo esc_html($p->post_title).' (#'.$p->ID.')'; ?></option>
          <?php endforeach; ?>
        </select>
        <p class="description">Selecione um ou mais produtos (Ctrl/Cmd para múltiplos).</p>
      </td></tr>
      <tr><th>Itens com valor zero</th><td><label><input type="checkbox" name="include_zero" value="1" <?php checked(!empty($_GET['include_zero'])); ?>> Incluir itens com valor de item = 0</label></td></tr>

      <!-- NOVO: filtro Comissão Paga -->
      <tr>
        <th>Comissão paga?</th>
        <td>
          <select name="paid_flag">
            <option value=""  <?php selected($filters['paid_flag'], null); ?>>— Todos —</option>
            <option value="1" <?php selected($filters['paid_flag'], 1); ?>>Sim</option>
            <option value="0" <?php selected($filters['paid_flag'], 0); ?>>Não</option>
          </select>
        </td>
      </tr>

      <tr><th>Página única</th><td><label><input type="checkbox" name="all" value="1" <?php checked($all); ?>> Exibir tudo em uma página (todos)</label></td></tr>
      <tr><th>Por página</th><td><input type="number" name="per_page" min="10" value="<?php echo esc_attr($per); ?>"></td></tr>
      <tr><th>Ordenação</th><td>
        <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
        <input type="hidden" name="order"   value="<?php echo esc_attr($order); ?>">
        <span class="description">Você também pode ordenar clicando nos títulos das colunas.</span>
      </td></tr>
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
      <th><?php echo ftsa_sort_link($base_url, 'Nº Pedido', 'order_id', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Cliente',   'customer', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Item',      'order_item_name', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Qtd',       'qty', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Valor do Item', 'valor_item', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Taxa de Entrega', 'taxa_ent', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Desconto (Cupom)', 'desconto', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Taxa Cielo', 'taxa_cielo', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Valor Líquido', 'valor_liq', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Autor', 'author', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, '% Comissão', 'percent', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Valor Comissão', 'valor_comm', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Data Pagto Comissão', 'comm_paid', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Data Compra', 'order_date', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Data Pagamento', 'paid_date', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Data Repasse (CIELO)', 'settlement', $orderby, $order); ?></th>
      <th><?php echo ftsa_sort_link($base_url, 'Lucro Líquido', 'lucro_liq', $orderby, $order); ?></th>
    </tr></thead>
    <tbody>
    <?php
      // Acumuladores de totais da página
      $sum_valor_item  = 0.0;
      $sum_taxa_ent    = 0.0;
      $sum_desconto    = 0.0;
      $sum_taxa_cielo  = 0.0;
      $sum_valor_liq   = 0.0;
      $sum_comm        = 0.0;
      $sum_lucro_liq   = 0.0;

      if (empty($computed)): ?>
      <tr><td colspan="17">Nenhum registro encontrado.</td></tr>
    <?php else: foreach ($computed as $c): ?>
      <tr>
        <td><a href="<?php echo esc_url($c['edit_url']); ?>" target="_blank">#<?php echo esc_html($c['order_id']); ?></a></td>
        <td><?php echo esc_html($c['customer']); ?></td>
        <td><?php echo esc_html($c['order_item_name']); ?></td>
        <td><?php echo esc_html((int)$c['qty']); ?></td>
        <td><?php echo wp_kses_post( wc_price($c['valor_item']) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($c['taxa_ent']) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($c['desconto']) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($c['taxa_cielo']) ); ?></td>
        <td><?php echo wp_kses_post( wc_price($c['valor_liq']) ); ?></td>
        <td><?php echo esc_html($c['author']); ?></td>
        <td><?php echo esc_html(number_format($c['percent'],2,',','')); ?>%</td>
        <td><?php echo wp_kses_post( wc_price($c['valor_comm']) ); ?></td>
        <td><?php echo esc_html($c['comm_paid']); ?></td>
        <td><?php echo esc_html($c['order_date']); ?></td>
        <td><?php echo esc_html($c['paid_date']); ?></td>
        <td><?php echo esc_html($c['settlement']); ?></td>
        <td><?php echo wp_kses_post( wc_price($c['lucro_liq']) ); ?></td>
      </tr>
    <?php
        // Totais da página
        $sum_valor_item += $c['valor_item'];
        $sum_taxa_ent   += $c['taxa_ent'];
        $sum_desconto   += $c['desconto'];
        $sum_taxa_cielo += $c['taxa_cielo'];
        $sum_valor_liq  += $c['valor_liq'];
        $sum_comm       += $c['valor_comm'];
        $sum_lucro_liq  += $c['lucro_liq'];
      endforeach; endif; ?>
    </tbody>
    <tfoot><tr>
      <th colspan="4" style="text-align:right">Totais (página):</th>
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

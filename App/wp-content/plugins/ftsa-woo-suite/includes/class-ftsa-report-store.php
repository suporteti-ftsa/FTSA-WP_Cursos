<?php
if (!defined('ABSPATH')) { exit; }

class FTSA_Report_Store {

    /** @var wpdb */
    private $db;
    private $is_hpos;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->is_hpos = $this->detect_hpos_enabled();
    }

    private function detect_hpos_enabled() {
        if (!class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            return false;
        }
        try {
            $c = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);
            return method_exists($c, 'custom_orders_table_usage_is_enabled') && $c->custom_orders_table_usage_is_enabled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Lista distinta de títulos de método de pagamento */
    public function list_payment_method_titles() {
        $pm = $this->db->postmeta;
        $p  = $this->db->posts;
        $sql = "
            SELECT DISTINCT pm.meta_value
            FROM {$pm} pm
            INNER JOIN {$p} p ON p.ID = pm.post_id
            WHERE p.post_type='shop_order'
              AND pm.meta_key = '_payment_method_title'
              AND pm.meta_value IS NOT NULL
              AND pm.meta_value <> ''
            ORDER BY pm.meta_value ASC
        ";
        return array_map('strval', $this->db->get_col($sql));
    }

    /** Status distintos presentes */
    public function list_order_statuses() {
        $p = $this->db->posts;
        $sql = "
            SELECT DISTINCT p.post_status
            FROM {$p} p
            WHERE p.post_type='shop_order' AND p.post_status <> ''
            ORDER BY p.post_status ASC
        ";
        $rows = $this->db->get_col($sql);
        $out = [];
        foreach ($rows as $st) {
            $slug = str_replace('wc-','',$st);
            $out[] = ['status'=>$st, 'name'=>wc_get_order_status_name($slug)];
        }
        return $out;
    }

    /**
     * Consulta itens com:
     *  - Valor do Item  = _line_subtotal
     *  - Desconto       = _line_subtotal - _line_total (>=0)
     *  - Taxa CIELO     = rateio por subtotal do pedido (mantido)
     *  - Taxa Entrega   = rateio por quantidade do pedido (novo)
     *  - Valor Líquido  = Valor do Item + Taxa Entrega - Desconto - Taxa CIELO
     *  - Comissão       = percentual efetivo * Valor Líquido
     *
     * Retorna: ['rows'=>[], 'total'=>int, 'sums'=>['bruto','taxa','liquido','comissao']]
     */
    public function query_line_items_with_commission(array $args, int $limit=50, int $offset=0, bool $all=false) {
        if ($this->is_hpos) {
            // HPOS ativo: fallback para API (mantém a mesma lógica de rateio)
            return $this->fallback_wc_query($args, $limit, $offset);
        }

        $p  = $this->db->posts;
        $pm = $this->db->postmeta;
        $oi = $this->db->prefix . 'woocommerce_order_items';
        $oim= $this->db->prefix . 'woocommerce_order_itemmeta';

        $where  = ["p.post_type = 'shop_order'"];
        $params = [];

        // Datas (post_date)
        if (!empty($args['start'])) {
            $where[] = 'p.post_date >= %s';
            $params[] = $args['start'] . ' 00:00:00';
        }
        if (!empty($args['end'])) {
            $where[] = 'p.post_date <= %s';
            $params[] = $args['end'] . ' 23:59:59';
        }

        // Status
        if (!empty($args['status']) && is_array($args['status'])) {
            $in = implode(',', array_fill(0, count($args['status']), '%s'));
            $where[] = "p.post_status IN ($in)";
            foreach ($args['status'] as $st) $params[] = $st;
        }

        // Método de pagamento (título LIKE)
        $join_method = "LEFT JOIN {$pm} pm_method ON pm_method.post_id = p.ID AND pm_method.meta_key = '_payment_method_title'";
        if (!empty($args['method_title_like'])) {
            $where[] = 'LOWER(pm_method.meta_value) LIKE %s';
            $params[] = '%' . mb_strtolower($args['method_title_like']) . '%';
        }

        // TID/NSU (para exibição)
        $join_tid_nsu = "
            LEFT JOIN {$pm} pm_tid ON pm_tid.post_id = p.ID AND pm_tid.meta_key = 'cielo_tid'
            LEFT JOIN {$pm} pm_nsu ON pm_nsu.post_id = p.ID AND pm_nsu.meta_key = 'cielo_nsu'
        ";

        // Metas do item
        $join_item_meta = "
            LEFT JOIN {$oim} oim_subtotal ON oim_subtotal.order_item_id = oi.order_item_id AND oim_subtotal.meta_key = '_line_subtotal'
            LEFT JOIN {$oim} oim_total    ON oim_total.order_item_id    = oi.order_item_id AND oim_total.meta_key    = '_line_total'
            LEFT JOIN {$oim} oim_qty      ON oim_qty.order_item_id      = oi.order_item_id AND oim_qty.meta_key      = '_qty'
            LEFT JOIN {$oim} oim_prod     ON oim_prod.order_item_id     = oi.order_item_id AND oim_prod.meta_key     = '_product_id'
            LEFT JOIN {$oim} oim_paidflag ON oim_paidflag.order_item_id = oi.order_item_id AND oim_paidflag.meta_key = '_ftsa_comm_paid'
            LEFT JOIN {$oim} oim_paiddt   ON oim_paiddt.order_item_id   = oi.order_item_id AND oim_paiddt.meta_key   = '_ftsa_comm_paid_date'
        ";

        // Produto -> Autor e metas do Autor
        $join_product_meta = "
            LEFT JOIN {$pm} pm_author  ON pm_author.post_id  = oim_prod.meta_value AND pm_author.meta_key  = '_ftsa_author_id'
            LEFT JOIN {$pm} pm_percent ON pm_percent.post_id = oim_prod.meta_value AND pm_percent.meta_key = '_ftsa_author_percent'
            LEFT JOIN {$pm} pm_author_defperc 
                   ON pm_author_defperc.post_id = CAST(pm_author.meta_value AS UNSIGNED)
                  AND pm_author_defperc.meta_key = '_ftsa_author_default_percent'
            LEFT JOIN {$pm} pm_author_enable
                   ON pm_author_enable.post_id = CAST(pm_author.meta_value AS UNSIGNED)
                  AND pm_author_enable.meta_key = '_ftsa_author_receives_commission'
        ";

        // Filtro por autor
        if (!empty($args['author_id'])) {
            $where[] = 'CAST(pm_author.meta_value AS UNSIGNED) = %d';
            $params[] = (int)$args['author_id'];
        }

        // Filtro por produto(s)
        if (!empty($args['product_ids']) && is_array($args['product_ids'])) {
            $ids = array_map('intval', $args['product_ids']);
            $in = implode(',', array_fill(0, count($ids), '%d'));
            $where[] = "CAST(oim_prod.meta_value AS UNSIGNED) IN ($in)";
            foreach ($ids as $id) $params[] = $id;
        }

        // Filtro pago/não pago (comissão)
        if (isset($args['paid_flag'])) {
            if ((int)$args['paid_flag'] === 1) {
                $where[] = "oim_paidflag.meta_value = '1'";
            } elseif ((int)$args['paid_flag'] === 0) {
                $where[] = "(oim_paidflag.meta_value IS NULL OR oim_paidflag.meta_value = '' OR oim_paidflag.meta_value = '0')";
            }
        }

        // Ocultar itens com subtotal zero
        if (!empty($args['hide_zero_items'])) {
            $where[] = "CAST(oim_subtotal.meta_value AS DECIMAL(18,6)) > 0";
        }

        // Ocultar “líquido 0” (aproximação: exige line_total > 0)
        if (!empty($args['hide_net_zero'])) {
            $where[] = "CAST(COALESCE(oim_total.meta_value, oim_subtotal.meta_value) AS DECIMAL(18,6)) > 0";
        }

        // Subquery: soma da taxa CIELO por pedido
        $fee_sum_sql = "
            SELECT
                o.ID AS order_id,
                COALESCE(SUM(
                    CASE 
                        WHEN oim_fee_val.meta_value IS NOT NULL 
                        THEN CAST(oim_fee_val.meta_value AS DECIMAL(18,6))
                        ELSE ABS(CAST(oim_fee_total.meta_value AS DECIMAL(18,6)))
                    END
                ),0) AS fee_total
            FROM {$p} o
            INNER JOIN {$oi} oi_fee 
                ON oi_fee.order_id = o.ID AND oi_fee.order_item_type = 'fee' AND oi_fee.order_item_name = %s
            LEFT JOIN {$oim} oim_fee_total 
                ON oim_fee_total.order_item_id = oi_fee.order_item_id AND oim_fee_total.meta_key = '_line_total'
            LEFT JOIN {$oim} oim_fee_val 
                ON oim_fee_val.order_item_id = oi_fee.order_item_id AND oim_fee_val.meta_key = '_cielo_fee_item_amount'
            WHERE o.post_type = 'shop_order'
            GROUP BY o.ID
        ";

        // Subquery: soma dos subtotais por pedido (rateio Taxa CIELO)
        $sum_by_order_sql = "
            SELECT 
                oi2.order_id,
                SUM(CAST(oim_subtotal2.meta_value AS DECIMAL(18,6))) AS subtotal_sum
            FROM {$oi} oi2
            INNER JOIN {$oim} oim_subtotal2 
                ON oim_subtotal2.order_item_id = oi2.order_item_id AND oim_subtotal2.meta_key = '_line_subtotal'
            WHERE oi2.order_item_type = 'line_item'
            GROUP BY oi2.order_id
        ";

        // Subquery: soma das quantidades por pedido (rateio Taxa de Entrega)
        $qty_sum_by_order_sql = "
            SELECT 
                oi2.order_id,
                SUM(CAST(oim_qty2.meta_value AS DECIMAL(18,6))) AS qty_sum
            FROM {$oi} oi2
            INNER JOIN {$oim} oim_qty2 
                ON oim_qty2.order_item_id = oi2.order_item_id AND oim_qty2.meta_key = '_qty'
            WHERE oi2.order_item_type = 'line_item'
            GROUP BY oi2.order_id
        ";

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $select_sql = "
            SELECT 
                p.ID AS order_id,
                p.post_status,
                p.post_date AS order_date,
                oi.order_item_id,
                oi.order_item_name,
                CAST(oim_qty.meta_value AS DECIMAL(18,6)) AS qty,
                CAST(oim_subtotal.meta_value AS DECIMAL(18,6)) AS line_subtotal,
                CAST(oim_total.meta_value    AS DECIMAL(18,6)) AS line_total,
                CAST(pm_author.meta_value AS UNSIGNED) AS author_id,
                pm_percent.meta_value AS author_percent,
                pm_author_defperc.meta_value AS author_default_percent,
                pm_author_enable.meta_value  AS author_enable_commission,
                oim_paidflag.meta_value AS comm_paid,
                oim_paiddt.meta_value  AS comm_paid_date,
                fee_by_order.fee_total,
                subtotal_by_order.subtotal_sum,
                qty_by_order.qty_sum,
                pm_ship.meta_value AS ship_total,
                pm_method.meta_value AS method_title,
                pm_tid.meta_value AS tid,
                pm_nsu.meta_value AS nsu
            FROM {$p} p
            INNER JOIN {$oi} oi 
                ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
            {$join_item_meta}
            {$join_product_meta}
            {$join_method}
            {$join_tid_nsu}
            LEFT JOIN ({$fee_sum_sql}) AS fee_by_order ON fee_by_order.order_id = p.ID
            LEFT JOIN ({$sum_by_order_sql}) AS subtotal_by_order ON subtotal_by_order.order_id = p.ID
            LEFT JOIN ({$qty_sum_by_order_sql}) AS qty_by_order ON qty_by_order.order_id = p.ID
            LEFT JOIN {$pm} pm_ship ON pm_ship.post_id = p.ID AND pm_ship.meta_key = '_order_shipping'
            {$where_sql}
            ORDER BY p.ID DESC, oi.order_item_id ASC
        ";

        $params_full = array_merge([ 'Taxa CIELO' ], $params);

        if (!$all) {
            $select_sql .= " LIMIT %d OFFSET %d";
            $params_full[] = $limit;
            $params_full[] = $offset;
        }

        $rows = $this->db->get_results($this->db->prepare($select_sql, $params_full), ARRAY_A);

        // Count total (sem LIMIT)
        $count_sql = "
            SELECT COUNT(*)
            FROM {$p} p
            INNER JOIN {$oi} oi 
                ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
            {$join_item_meta}
            {$join_product_meta}
            {$join_method}
            {$join_tid_nsu}
            {$where_sql}
        ";
        if (!empty($params)) {
            $total = (int) $this->db->get_var($this->db->prepare($count_sql, $params));
        } else {
            $total = (int) $this->db->get_var($count_sql);
        }

        // Pós-processamento (cálculos finais)
        $sum_bruto = $sum_taxa = $sum_liq = $sum_comm = 0.0;
        $filtered_rows = [];

        foreach ($rows as &$r) {
            $subtotal    = (float) ($r['line_subtotal'] ?? 0);         // Valor do Item
            $line_total  = ($r['line_total'] !== null && $r['line_total'] !== '') ? (float)$r['line_total'] : $subtotal;
            $discount    = max(0.0, $subtotal - $line_total);          // Desconto (cupom)

            // Rateio Taxa CIELO por subtotal
            $subtotal_s  = max(1e-9, (float)($r['subtotal_sum'] ?? 0));
            $fee_total   = max(0.0, (float)($r['fee_total'] ?? 0));
            $fee_share   = $fee_total > 0 ? ($fee_total * ($subtotal / $subtotal_s)) : 0.0;

            // Rateio Taxa de Entrega por quantidade
            $qty_sum     = max(1e-9, (float)($r['qty_sum'] ?? 0));
            $qty         = max(0.0,  (float)($r['qty'] ?? 0));
            $ship_total  = max(0.0,  (float)($r['ship_total'] ?? 0));
            $ship_share  = ($ship_total > 0 && $qty_sum > 0) ? ($ship_total * ($qty / $qty_sum)) : 0.0;

            // Percentual efetivo (produto > autor) e se autor recebe comissão
            $prod_has_value  = (isset($r['author_percent']) && $r['author_percent'] !== '' && $r['author_percent'] !== null);
            $prod_perc       = (float) str_replace(',', '.', (string)$r['author_percent']);
            $author_def_perc = (float) str_replace(',', '.', (string)($r['author_default_percent'] ?? ''));
            $author_enabled  = isset($r['author_enable_commission']) ? (int)$r['author_enable_commission'] : 1;
            $perc            = $prod_has_value ? $prod_perc : $author_def_perc;
            if ($author_enabled !== 1) { $perc = 0.0; }

            // Novo líquido
            $liq   = $subtotal + $ship_share - $discount - $fee_share;
            if ($liq < 0) $liq = 0.0; // evita comissão negativa

            $comm  = $perc > 0 ? ($liq * ($perc/100.0)) : 0.0;

            // Enriquecimento da linha
            $r['discount_amount'] = $discount;
            $r['fee_share']       = $fee_share;
            $r['shipping_share']  = $ship_share;  // NOVO
            $r['net_value']       = $liq;         // agora baseado no novo líquido
            $r['commission']      = $comm;        // comissão sobre o novo líquido
            $r['author_percent']  = $perc;        // efetivo
            $r['status_name']     = wc_get_order_status_name(str_replace('wc-','',$r['post_status']));

            // Aplicar filtro "hide_net_zero" pós-processado (consistente)
            if (!empty($args['hide_net_zero']) && $liq <= 0.0) {
                continue;
            }

            $filtered_rows[] = $r;

            $sum_bruto += $subtotal;
            $sum_taxa  += $fee_share;
            $sum_liq   += $liq;
            $sum_comm  += $comm;
        }
        unset($r);

        if (!empty($args['hide_net_zero'])) {
            $rows  = $filtered_rows;
        }

        return [
            'rows'   => $rows,
            'total'  => $total,
            'sums'   => [
                'bruto'    => $sum_bruto,
                'taxa'     => $sum_taxa,   // Taxa CIELO (rateada)
                'liquido'  => $sum_liq,    // novo líquido
                'comissao' => $sum_comm,   // sobre novo líquido
            ],
        ];
    }

    /** Fallback quando HPOS está ativo (usa objetos WC mantendo a mesma lógica). */
    private function fallback_wc_query(array $args, int $limit, int $offset) {
        $page = (int) floor($offset / max(1, $limit)) + 1;

        $wc_args = [
            'paginate' => true,
            'limit'    => $limit,
            'page'     => $page,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ];
        if (!empty($args['start']) || !empty($args['end'])) {
            $wc_args['date_created'] = [];
            if (!empty($args['start'])) $wc_args['date_created']['after']  = $args['start'] . ' 00:00:00';
            if (!empty($args['end']))   $wc_args['date_created']['before'] = $args['end']   . ' 23:59:59';
        }
        if (!empty($args['status'])) $wc_args['status'] = array_map(function($s){ return ltrim($s,'wc-'); }, $args['status']);

        $res    = wc_get_orders($wc_args);
        $orders = is_object($res) && isset($res->orders) ? $res->orders : (is_array($res) ? $res : []);
        $total  = is_object($res) && isset($res->total) ? (int)$res->total : count($orders);

        $rows = [];
        $sum_bruto = $sum_taxa = $sum_liq = $sum_comm = 0.0;

        foreach ($orders as $o) {
            if (!is_object($o)) continue;

            $order_id   = $o->get_id();
            $ship_total = (float)$o->get_shipping_total();

            // Taxa CIELO total no pedido
            $fee_total = 0.0;
            foreach ($o->get_items('fee') as $f) {
                if (mb_strtolower($f->get_name()) === mb_strtolower('Taxa CIELO')) {
                    $meta_val = $f->get_meta('_cielo_fee_item_amount', true);
                    $fee_total += ($meta_val !== '') ? (float)$meta_val : abs((float)$f->get_total());
                }
            }

            // Soma itens para rateio (subtotal e quantidade)
            $items      = $o->get_items('line_item');
            $sum_items  = 0.0;
            $sum_qty    = 0.0;
            foreach ($items as $it) {
                $sum_items += (float)$it->get_subtotal();
                $sum_qty   += (float)$it->get_quantity();
            }
            if ($sum_items <= 0) $sum_items = 1.0;
            if ($sum_qty   <= 0) $sum_qty   = 1.0;

            foreach ($items as $it) {
                $subtotal = (float)$it->get_subtotal();     // Valor do Item
                if (!empty($args['hide_zero_items']) && $subtotal <= 0) continue;

                $line_total = (float)$it->get_total();
                if (!empty($args['hide_net_zero']) && $line_total <= 0) continue;

                $qty      = (float)$it->get_quantity();
                $discount = max(0.0, $subtotal - $line_total);

                // Rateios
                $fee_share  = $fee_total > 0 ? ($fee_total * ($subtotal / $sum_items)) : 0.0; // Taxa CIELO por subtotal
                $ship_share = $ship_total > 0 ? ($ship_total * ($qty / $sum_qty)) : 0.0;      // Taxa Entrega por quantidade

                $liq = $subtotal + $ship_share - $discount - $fee_share;
                if (!empty($args['hide_net_zero']) && $liq <= 0) continue;
                if ($liq < 0) $liq = 0.0;

                $product_id = $it->get_product_id();
                $author_id  = $product_id ? (int)get_post_meta($product_id, '_ftsa_author_id', true) : 0;

                // Percentual efetivo (produto > autor) + habilitado
                $prod_meta    = $product_id ? get_post_meta($product_id, '_ftsa_author_percent', true) : '';
                $prod_has_val = ($prod_meta !== '' && $prod_meta !== null);
                $prod_perc    = $prod_has_val ? (float)$prod_meta : 0.0;

                $ena = 1;
                $def = 0.0;
                if ($author_id) {
                    $ena = (int) get_post_meta($author_id, '_ftsa_author_receives_commission', true);
                    if ($ena === 0) {
                        $perc = 0.0;
                    } else {
                        $def  = (float) get_post_meta($author_id, '_ftsa_author_default_percent', true);
                        $perc = $prod_has_val ? $prod_perc : $def;
                    }
                } else {
                    $perc = $prod_perc; // sem autor associado
                }

                $comm = $perc > 0 ? ($liq * ($perc/100.0)) : 0.0;

                $rows[] = [
                    'order_id'        => $order_id,
                    'post_status'     => 'wc-' . $o->get_status(),
                    'order_date'      => $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i:s') : '',
                    'order_item_id'   => $it->get_id(),
                    'order_item_name' => $it->get_name(),
                    'qty'             => $qty,
                    'line_subtotal'   => $subtotal,
                    'line_total'      => $line_total,
                    'discount_amount' => $discount,
                    'author_id'       => $author_id,
                    'author_percent'  => $perc, // efetivo
                    'comm_paid'       => $it->get_meta('_ftsa_comm_paid', true),
                    'comm_paid_date'  => $it->get_meta('_ftsa_comm_paid_date', true),
                    'fee_total'       => $fee_total,
                    'subtotal_sum'    => $sum_items,
                    'method_title'    => $o->get_payment_method_title(),
                    'tid'             => $o->get_meta('cielo_tid', true),
                    'nsu'             => $o->get_meta('cielo_nsu', true),
                    'status_name'     => wc_get_order_status_name($o->get_status()),
                    'fee_share'       => $fee_share,      // Taxa CIELO rateada
                    'shipping_share'  => $ship_share,     // NOVO: Taxa Entrega rateada
                    'net_value'       => $liq,            // novo líquido
                    'commission'      => $comm,           // comissão sobre novo líquido
                ];

                $sum_bruto += $subtotal;
                $sum_taxa  += $fee_share;
                $sum_liq   += $liq;
                $sum_comm  += $comm;
            }
        }

        return [
            'rows' => $rows,
            'total'=> $total,
            'sums' => [
                'bruto'    => $sum_bruto,
                'taxa'     => $sum_taxa,   // Taxa CIELO
                'liquido'  => $sum_liq,    // novo líquido
                'comissao' => $sum_comm
            ]
        ];
    }
}
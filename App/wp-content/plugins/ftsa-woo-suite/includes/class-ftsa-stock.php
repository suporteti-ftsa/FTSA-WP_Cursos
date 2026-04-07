<?php
if (!defined('ABSPATH')) { exit; }

class FTSA_Stock_Manager {
    /** @var wpdb */
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Lista produtos/variações com metas de estoque.
     * Filtros aceitos em $args:
     *  - author_id (int)        : filtra por autor (variações herdam do pai)
     *  - type ('product'|'product_variation'|'') : tipo do post
     *  - status ('instock'|'outofstock'|'onbackorder'|'') : status de estoque
     *  - q (string)             : busca por título ou SKU (LIKE)
     */
    public function query_stock(array $args, int $limit=50, int $offset=0, bool $all=false) {
        $p  = $this->db->posts;
        $pm = $this->db->postmeta;

        $where  = [
            "(p.post_type = 'product' OR p.post_type = 'product_variation')",
            "p.post_status IN ('publish')"
        ];
        $params = [];

        // Tipo (produto/variação)
        if (!empty($args['type'])) {
            $where[]  = "p.post_type = %s";
            $params[] = $args['type'];
        }

        // Joins de metas (produto/variação) + autor (próprio e do pai)
        $joins = "
            LEFT JOIN {$pm} ms  ON ms.post_id  = p.ID AND ms.meta_key  = '_manage_stock'
            LEFT JOIN {$pm} st  ON st.post_id  = p.ID AND st.meta_key  = '_stock'
            LEFT JOIN {$pm} lsa ON lsa.post_id = p.ID AND lsa.meta_key = '_low_stock_amount'
            LEFT JOIN {$pm} ss  ON ss.post_id  = p.ID AND ss.meta_key  = '_stock_status'
            LEFT JOIN {$pm} sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
            LEFT JOIN {$pm} a   ON a.post_id   = p.ID AND a.meta_key   = '_ftsa_author_id'
            LEFT JOIN {$pm} pa  ON pa.post_id  = p.post_parent AND pa.meta_key = '_ftsa_author_id'
        ";

        // Status de estoque
        if (!empty($args['status'])) {
            $where[]  = "ss.meta_value = %s";
            $params[] = $args['status'];
        }

        // Autor (variação herda do pai se não tiver no próprio)
        if (!empty($args['author_id'])) {
            $where[]  = "COALESCE(NULLIF(a.meta_value,''), NULLIF(pa.meta_value,'')) = %s";
            $params[] = (string) (int)$args['author_id'];
        }

        // Busca por nome ou SKU
        if (!empty($args['q'])) {
            $like    = '%' . $this->db->esc_like($args['q']) . '%';
            $where[] = "(p.post_title LIKE %s OR sku.meta_value LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = $where ? "WHERE " . implode(' AND ', $where) : '';

        $select_sql = "
            SELECT 
                p.ID,
                p.post_type,
                COALESCE(NULLIF(a.meta_value,''), NULLIF(pa.meta_value,'')) AS author_id,
                COALESCE(NULLIF(ms.meta_value,''),'no') AS manage_stock,
                COALESCE(st.meta_value,'')  AS stock_qty,
                COALESCE(lsa.meta_value,'') AS low_stock,
                COALESCE(ss.meta_value,'')  AS stock_status,
                COALESCE(sku.meta_value,'') AS sku
            FROM {$p} p
            {$joins}
            {$where_sql}
            ORDER BY p.ID DESC
        ";

        $params_sel = $params;
        if (!$all) {
            $select_sql .= " LIMIT %d OFFSET %d";
            $params_sel[] = $limit;
            $params_sel[] = $offset;
        }

        $rows = !empty($params_sel)
            ? $this->db->get_results($this->db->prepare($select_sql, $params_sel), ARRAY_A)
            : $this->db->get_results($select_sql, ARRAY_A);

        // Total sem LIMIT
        $count_sql = "
            SELECT COUNT(*)
            FROM {$p} p
            {$joins}
            {$where_sql}
        ";
        $total = !empty($params)
            ? (int)$this->db->get_var($this->db->prepare($count_sql, $params))
            : (int)$this->db->get_var($count_sql);

        // Normalizações
        foreach ($rows as &$r) {
            $r['author_id']    = (int) $r['author_id'];
            $r['manage_stock'] = ($r['manage_stock'] === 'yes') ? 'yes' : 'no';
            // Mantemos qty/low como string para edição livre na view ('' ou número)
            $r['stock_qty'] = ($r['stock_qty'] === '') ? '' : (string)(int)$r['stock_qty'];
            $r['low_stock'] = ($r['low_stock'] === '') ? '' : (string)(int)$r['low_stock'];
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total];
    }
}

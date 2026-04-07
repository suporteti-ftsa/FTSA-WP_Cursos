<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Relatório de Estoque
 * Colunas: Produto | Status do Produto | Gerenciar Estoque | Quantidade | Status
 * Filtros: Autor | Produto
 * Export: CSV, XLS (HTML compatível Excel), PDF (página de impressão)
 */

try {
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) { @set_time_limit(120); }

    // ===== Helpers =====
    function ftsa_rs_post_status_label($code) {
        $map = [
            'publish' => 'Publicado',
            'pending' => 'Revisão pendente',
            'draft'   => 'Rascunho',
            'private' => 'Privado',
            'future'  => 'Agendado',
            'trash'   => 'Lixeira',
        ];
        if (isset($map[$code])) return $map[$code];
        $obj = get_post_status_object($code);
        return ($obj && !empty($obj->label)) ? $obj->label : ucfirst($code ?: '-');
    }

    function ftsa_rs_fetch_rows($that, $filters, $lim, $off, $all) {
        // Reaproveita query do gerenciador de estoque
        if (!$that->stock) {
            return ['rows'=>[], 'total'=>0];
        }
        return $that->stock->query_stock($filters, $lim, $off, $all);
    }

    // ===== Dados para filtros =====
    $authors = get_posts([
        'post_type'      => FTSA_Woo_Suite::CPT_AUTHOR,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
    // Produtos (pais) para o filtro
    $products = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => 500,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => ['publish','pending','draft','private','future'],
    ]);

    // ====== Filtros recebidos ======
    $author_id  = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
    $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;

    // Paginação
    $per   = max(10, (int)($_GET['per_page'] ?? 50));
    $page  = max(1, (int)($_GET['paged'] ?? 1));
    $all   = !empty($_GET['all']);
    $off   = $all ? 0 : ($per * ($page - 1));
    $lim   = $all ? 200000 : $per;

    // Filtros que a store conhece
    $filters = [
        'author_id' => $author_id,
        'type'      => '',     // ambos: product + product_variation
        'status'    => '',     // status de estoque (não filtra por aqui)
        'q'         => '',     // sem texto livre
    ];

    // Export
    $export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';

    // Se filtra por produto, incluir variações
    $allowed_ids = [];
    if ($product_id > 0) {
        $allowed_ids[] = $product_id;
        $p = wc_get_product($product_id);
        if ($p && $p->is_type('variable')) {
            $children = $p->get_children(); // ids variações
            if (is_array($children)) {
                $allowed_ids = array_merge($allowed_ids, array_map('intval', $children));
            }
        }
    }

    // Estratégia:
    // - Com filtro por produto: carrega todos e filtra em PHP (para incluir variações).
    // - Sem filtro por produto: usa a paginação nativa.
    if ($product_id > 0) {
        $res_all     = ftsa_rs_fetch_rows($this, $filters, 200000, 0, true);
        $all_rows    = $res_all['rows'];
        $rows_filtered = array_values(array_filter($all_rows, function($r) use ($allowed_ids){
            return in_array((int)$r['ID'], $allowed_ids, true);
        }));
        $total = count($rows_filtered);
        $rows  = $all ? $rows_filtered : array_slice($rows_filtered, $off, $per);
    } else {
        $res   = ftsa_rs_fetch_rows($this, $filters, $lim, $off, $all);
        $rows  = $res['rows'];
        $total = (int) $res['total'];
    }

    // ===== Exportações =====
    $base_filename = 'ftsa_relatorio_estoque_' . date('Ymd_His');
    
    if (in_array($export, ['csv','xls','pdf'], true)) {
        // Para export, sempre todos itens filtrados
        if ($product_id > 0) {
            $to_export = $rows_filtered;
        } else {
            $to_export = ftsa_rs_fetch_rows($this, $filters, 200000, 0, true)['rows'];
        }

        // Monta as células (texto puro)
        $make_cells = function($r){
            $title = get_the_title($r['ID']);
            $wc_prod = wc_get_product($r['ID']);
            if ($r['post_type'] === 'product_variation' && $wc_prod) {
                $title = $wc_prod->get_formatted_name();
            }
            $post_status = ftsa_rs_post_status_label(get_post_status($r['ID']));
            $manage      = ($r['manage_stock'] === 'yes') ? 'Sim' : 'Não';
            $qty         = (string)($r['stock_qty'] ?? '');
            $stock_st    = $r['stock_status'] ?: '-';
            return [$title, $post_status, $manage, $qty, $stock_st];
        };

        if ($export === 'csv') {
            $filename = $base_filename . '.csv';
            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Produto','Status do Produto','Gerenciar Estoque','Quantidade','Status'], ';');
            foreach ($to_export as $r) {
                fputcsv($out, $make_cells($r), ';');
            }
            fclose($out);
            exit;
        }

        if ($export === 'xls') {
            $filename = $base_filename . '.xls';
            nocache_headers();
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><body>';
            echo '<table border="1" cellspacing="0" cellpadding="4">';
            echo '<tr><th>Produto</th><th>Status do Produto</th><th>Gerenciar Estoque</th><th>Quantidade</th><th>Status</th></tr>';
            foreach ($to_export as $r) {
                [$c1,$c2,$c3,$c4,$c5] = $make_cells($r);
                echo '<tr>';
                echo '<td>'.esc_html($c1).'</td>';
                echo '<td>'.esc_html($c2).'</td>';
                echo '<td>'.esc_html($c3).'</td>';
                echo '<td>'.esc_html($c4).'</td>';
                echo '<td>'.esc_html($c5).'</td>';
                echo '</tr>';
            }
            echo '</table></body></html>';
            exit;
        }

        if ($export === 'pdf') {
            // Página de impressão (Salvar como PDF)
            nocache_headers();
            header('Content-Type: text/html; charset=UTF-8');

            ob_start();
            ?>
            <!doctype html>
            <html>
            <head>
              <meta charset="utf-8">
              <title>FTSA - Relatório de Estoque</title>
              <style>
                body { font-family: Arial, sans-serif; font-size:12px; margin: 20px; }
                h2 { margin: 0 0 12px 0; }
                table { width:100%; border-collapse:collapse; }
                th, td { border:1px solid #ccc; padding:6px; }
                th { background:#f1f1f1; }
                .no-print { margin:10px 0; padding:8px; background:#fff3cd; border:1px solid #ffeeba; }
                @media print { .no-print { display:none; } }
              </style>
            </head>
            <body>
              <div class="no-print">
                <strong>Dica:</strong> clique em <em>Imprimir</em> e selecione <em>Salvar como PDF</em>.
              </div>
              <button class="no-print" onclick="window.print()" style="margin-bottom:10px;">Imprimir / Salvar como PDF</button>
              <h2>Relatório de Estoque</h2>
              <table>
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>Status do Produto</th>
                    <th>Gerenciar Estoque</th>
                    <th>Quantidade</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($to_export as $r):
                      [$c1,$c2,$c3,$c4,$c5] = $make_cells($r); ?>
                      <tr>
                        <td><?php echo esc_html($c1); ?></td>
                        <td><?php echo esc_html($c2); ?></td>
                        <td><?php echo esc_html($c3); ?></td>
                        <td><?php echo esc_html($c4); ?></td>
                        <td><?php echo esc_html($c5); ?></td>
                      </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </body>
            </html>
            <?php
            echo ob_get_clean();
            exit;
        }
    }

    $base_url = admin_url('admin.php?page=' . FTSA_Woo_Suite::MENU_STOCK_REPORT_SLUG);
    ?>
    <div class="wrap">
      <h1>Relatório de Estoque</h1>

      <form method="get" class="ftsa-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr(FTSA_Woo_Suite::MENU_STOCK_REPORT_SLUG); ?>">
        <table class="form-table">
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
            <th>Produto</th>
            <td>
              <select name="product_id" style="min-width:320px;">
                <option value="0">— Todos —</option>
                <?php foreach ($products as $p): ?>
                  <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($product_id, $p->ID); ?>>
                    <?php echo esc_html($p->post_title) . ' (#' . $p->ID . ')'; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">Se o produto for variável, o relatório inclui as variações automaticamente.</p>
            </td>
          </tr>
          <tr>
            <th>Página única</th>
            <td><label><input type="checkbox" name="all" value="1" <?php checked($all); ?>> Exibir tudo em uma página (todos)</label></td>
          </tr>
          <tr>
            <th>Por página</th>
            <td><input type="number" name="per_page" min="10" value="<?php echo esc_attr($per); ?>"></td>
          </tr>
        </table>
        <?php submit_button('Filtrar'); ?>

        <div class="ftsa-actions">
          <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export'=>'csv']), $base_url)); ?>">Exportar CSV</a>
          <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export'=>'xls']), $base_url)); ?>">Exportar XLS</a>
          <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export'=>'pdf']), $base_url)); ?>">Exportar PDF</a>
          <span class="description">Total de itens: <?php echo esc_html(number_format_i18n($total)); ?></span>
        </div>
      </form>

      <div class="ftsa-table-wrapper">
        <table class="widefat striped sticky-head">
          <thead>
            <tr>
              <th>Produto</th>
              <th>Status do Produto</th>
              <th>Gerenciar Estoque</th>
              <th>Quantidade</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5">Nenhum registro encontrado.</td></tr>
          <?php else: foreach ($rows as $r):
              $title = get_the_title($r['ID']);
              $wc_prod = wc_get_product($r['ID']);
              if ($r['post_type'] === 'product_variation' && $wc_prod) {
                  $title = $wc_prod->get_formatted_name();
              }
              $post_status = ftsa_rs_post_status_label(get_post_status($r['ID']));
              $manage      = ($r['manage_stock'] === 'yes') ? 'Sim' : 'Não';
              $qty         = (string)($r['stock_qty'] ?? '');
              $stock_st    = $r['stock_status'] ?: '-';
              $edit_url    = get_edit_post_link((int)$r['ID'], '') ?: admin_url('post.php?post='.(int)$r['ID'].'&action=edit');
          ?>
            <tr>
              <td><a href="<?php echo esc_url($edit_url); ?>" target="_blank"><?php echo esc_html($title); ?></a></td>
              <td><?php echo esc_html($post_status); ?></td>
              <td><?php echo esc_html($manage); ?></td>
              <td><?php echo esc_html($qty); ?></td>
              <td><?php echo esc_html($stock_st); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (!$all):
        $total_pages = max(1, (int) ceil($total / $per)); ?>
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

    <?php
} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Relatório de Estoque</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao carregar o relatório.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}

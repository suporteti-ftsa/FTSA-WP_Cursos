<?php
if (!defined('ABSPATH')) { exit; }

/** Helpers locais (labels em PT-BR) */
if (!function_exists('ftsa_stock_post_status_label')) {
    function ftsa_stock_post_status_label($status_code) {
        $map = [
            'publish' => 'Publicado',
            'pending' => 'Revisão pendente',
            'draft'   => 'Rascunho',
            'private' => 'Privado',
            'future'  => 'Agendado',
            'trash'   => 'Lixeira',
        ];
        if (isset($map[$status_code])) return $map[$status_code];
        $obj = get_post_status_object($status_code);
        if ($obj && !empty($obj->label)) return $obj->label;
        return ucfirst($status_code ?: '-');
    }
}
if (!function_exists('ftsa_stock_visibility_label')) {
    function ftsa_stock_visibility_label($vis_code) {
        $map = [
            'visible' => 'Catálogo e Pesquisa',
            'catalog' => 'Catálogo',
            'search'  => 'Pesquisa',
            'hidden'  => 'Oculto',
        ];
        return isset($map[$vis_code]) ? $map[$vis_code] : ($vis_code ?: '-');
    }
}

try {
    @ini_set('memory_limit', '512M');
    if (function_exists('set_time_limit')) { @set_time_limit(120); }

    // Autores para filtro
    $authors = get_posts([
        'post_type'      => FTSA_Woo_Suite::CPT_AUTHOR,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);

    // ====== Filtros recebidos ======
    $author_id = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
    $type      = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $status    = isset($_GET['stock_status']) ? sanitize_text_field($_GET['stock_status']) : '';
    $q         = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    // Paginação
    $per   = max(10, (int)($_GET['per_page'] ?? 50));
    $page  = max(1, (int)($_GET['paged'] ?? 1));
    $all   = !empty($_GET['all']);
    $off   = $all ? 0 : ($per * ($page - 1));
    $lim   = $all ? 200000 : $per;

    $filters = [
        'author_id' => $author_id,
        'type'      => in_array($type, ['product','product_variation'], true) ? $type : '',
        'status'    => in_array($status, ['instock','outofstock','onbackorder'], true) ? $status : '',
        'q'         => $q,
    ];

    // Export CSV?
    $export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';
    if (in_array($export, ['page','all'], true)) {
        $to_export = $this->stock->query_stock($filters, $export === 'all' ? 200000 : $lim, $export === 'all' ? 0 : $off, $export === 'all');
        $rows = $to_export['rows'];

        $scope = ($export === 'all') ? 'all' : 'page';
        $filename = 'ftsa_gestao_estoque_' . date('Ymd_His') . '_' . $scope . '.csv';

        $this->export_csv_generic($filename, [
            'ID','Tipo','Produto/Variação','SKU','Status do Produto','Visibilidade','Autor','Gerenciar Estoque','Qtd','Ponto de Reposição','Status'
        ], function($out) use ($rows) {
            foreach ($rows as $r) {
                $title = get_the_title($r['ID']);
                $wc_prod = wc_get_product($r['ID']);
                if ($r['post_type'] === 'product_variation' && $wc_prod) {
                    $title = $wc_prod->get_formatted_name();
                }
                $author_name = $r['author_id'] ? (get_the_title($r['author_id']) ?: ('#'.$r['author_id'])) : '-';

                $post_status_code = get_post_status($r['ID']);
                $post_status_label = ftsa_stock_post_status_label($post_status_code);

                $vis_code = $wc_prod ? $wc_prod->get_catalog_visibility() : '';
                $vis_label = ftsa_stock_visibility_label($vis_code);

                fputcsv($out, [
                    $r['ID'],
                    ($r['post_type'] === 'product_variation' ? 'Variação' : 'Produto'),
                    $title,
                    (string)($r['sku'] ?? ''),
                    $post_status_label,
                    $vis_label,
                    $author_name,
                    ($r['manage_stock'] === 'yes' ? 'Sim' : 'Não'),
                    (string)$r['stock_qty'],
                    (string)$r['low_stock'],
                    ($r['stock_status'] ?: '-'),
                ], ';');
            }
        });
        return;
    }

    $res   = $this->stock->query_stock($filters, $lim, $off, $all);
    $rows  = $res['rows'];
    $total = (int)$res['total'];

    $base_url = admin_url('admin.php?page=' . FTSA_Woo_Suite::MENU_STOCK_SLUG);
    ?>

    <div class="wrap">
      <h1>FTSA - Gestão de Estoque</h1>

      <?php if (!empty($_GET['smsg'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($_GET['smsg']); ?></p></div>
      <?php endif; ?>

      <form method="get" class="ftsa-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr(FTSA_Woo_Suite::MENU_STOCK_SLUG); ?>">
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
            <th>Tipo</th>
            <td>
              <select name="type">
                <option value="">Produtos e Variações</option>
                <option value="product" <?php selected($filters['type'], 'product'); ?>>Somente Produtos</option>
                <option value="product_variation" <?php selected($filters['type'], 'product_variation'); ?>>Somente Variações</option>
              </select>
            </td>
          </tr>
          <tr>
            <th>Status de estoque</th>
            <td>
              <select name="stock_status">
                <option value="">— Todos —</option>
                <option value="instock" <?php selected($filters['status'], 'instock'); ?>>Em estoque</option>
                <option value="outofstock" <?php selected($filters['status'], 'outofstock'); ?>>Sem estoque</option>
                <option value="onbackorder" <?php selected($filters['status'], 'onbackorder'); ?>>Sob encomenda</option>
              </select>
            </td>
          </tr>
          <tr>
            <th>Buscar (nome/SKU)</th>
            <td><input type="text" name="q" value="<?php echo esc_attr($q); ?>" style="min-width:320px;" placeholder="parte do nome ou SKU"></td>
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

      <form method="post">
        <?php wp_nonce_field('ftsa_stock_save', 'ftsa_stock_nonce'); ?>
        <input type="hidden" name="ftsa_stock_save" value="1" />

        <p>
          <button type="button" class="button" id="ftsa-stock-mark-all-yes">Gerenciar estoque: <strong>TODOS = SIM</strong></button>
          &nbsp;
          <button type="button" class="button" id="ftsa-stock-mark-all-no">Gerenciar estoque: <strong>TODOS = NÃO</strong></button>
          &nbsp;
          <button type="submit" class="button button-primary">Salvar</button>
        </p>

        <!-- Barra de ações em massa -->
        <div class="ftsa-bulk-bar">
          <label for="ftsa-stock-bulk-delta"><strong>Ajuste em massa (Qtd):</strong></label>
          <input type="number" id="ftsa-stock-bulk-delta" step="1" value="1" />
          <button type="button" class="button" id="ftsa-stock-bulk-plus">+ Adicionar</button>
          <button type="button" class="button" id="ftsa-stock-bulk-minus">− Subtrair</button>

          <span style="margin-left:12px;"></span>

          <button type="button" class="button" id="ftsa-stock-auto-now" title="Define status com base na Qtd atual (apenas Gerenciar=SIM)">
            Definir status automaticamente (agora)
          </button>

          <label style="margin-left:10px;">
            <input type="checkbox" name="auto_status_on_save" value="1">
            Auto status ao salvar (Qtd ≤ 0 → Sem estoque, > 0 → Em estoque)
          </label>

          <span style="margin:0 12px;"></span>

          <!-- Filtros rápidos no cliente -->
          <strong>Filtrar rápido:</strong>
          <label><input type="checkbox" id="ftsa-show-low"> Abaixo do ponto</label>
          <label><input type="checkbox" id="ftsa-show-zero"> Zerados</label>
          <span class="description">— marque para mostrar apenas essas situações</span>
        </div>

        <p class="ftsa-legend">
          <strong>Legenda:</strong>
          <span class="ftsa-example-low">Abaixo do ponto de reposição</span> &nbsp;|&nbsp;
          <span class="ftsa-example-zero">Qtd zerada</span>
        </p>

        <div class="ftsa-table-wrapper">
          <table class="widefat striped sticky-head sticky-cols">
            <thead>
              <tr>
                <th class="col-check"><input type="checkbox" id="ftsa-select-all" title="Selecionar todos"></th>
                <th class="col-id" style="width:90px">ID</th>
                <th>Produto/Variação</th>
                <th style="width:160px">SKU</th>
                <th style="width:140px">Status do Produto</th>
                <th style="width:180px">Visibilidade</th>
                <th style="width:220px">Autor</th>
                <th style="width:150px">Gerenciar estoque</th>
                <th style="width:120px">Qtd</th>
                <th style="width:160px">Ponto de reposição</th>
                <th style="width:160px">Status</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="11">Nenhum registro encontrado.</td></tr>
            <?php else:
              foreach ($rows as $r):
                $title = get_the_title($r['ID']);
                $wc_prod = wc_get_product($r['ID']);
                if ($r['post_type'] === 'product_variation' && $wc_prod) {
                    $title = $wc_prod->get_formatted_name();
                }
                $author_name = $r['author_id'] ? (get_the_title($r['author_id']) ?: ('#'.$r['author_id'])) : '-';

                // URL de edição do post (produto/variação)
                $edit_url = get_edit_post_link((int)$r['ID'], '');
                if (!$edit_url) {
                    $edit_url = admin_url('post.php?post=' . (int)$r['ID'] . '&action=edit');
                }

                // Status do produto & visibilidade (valores atuais)
                $post_status_code  = get_post_status($r['ID']);
                $vis_code  = $wc_prod ? $wc_prod->get_catalog_visibility() : '';

                // Classes de destaque (servidor)
                $manage = ($r['manage_stock'] === 'yes');
                $qtyVal = (float)($r['stock_qty']   !== '' ? $r['stock_qty']   : 0);
                $lowVal = (float)($r['low_stock']   !== '' ? $r['low_stock']   : 0);
                $row_classes = [];
                if ($manage) {
                    if ($qtyVal <= 0)             $row_classes[] = 'ftsa-row-zero';
                    elseif ($lowVal > 0 && $qtyVal <= $lowVal) $row_classes[] = 'ftsa-row-low';
                }
            ?>
              <tr class="<?php echo esc_attr(implode(' ', $row_classes)); ?>" data-id="<?php echo esc_attr($r['ID']); ?>">
                <td class="col-check">
                  <input type="checkbox" class="ftsa-row-select" />
                  <input type="hidden" name="stock_present[<?php echo esc_attr($r['ID']); ?>]" value="1" />
                </td>
                <td class="col-id">
                  <a href="<?php echo esc_url($edit_url); ?>" target="_blank" title="Editar #<?php echo esc_attr($r['ID']); ?>">
                    #<?php echo esc_html($r['ID']); ?>
                  </a>
                </td>
                <td><?php echo esc_html($title); ?></td>
                <td><?php echo esc_html((string)($r['sku'] ?? '')); ?></td>

                <!-- Status do Produto (EDITÁVEL) -->
                <td>
                  <select name="stock[<?php echo esc_attr($r['ID']); ?>][post_status]" class="ftsa-poststatus-select">
                    <option value="publish" <?php selected($post_status_code, 'publish'); ?>>Publicado</option>
                    <option value="pending" <?php selected($post_status_code, 'pending'); ?>>Revisão pendente</option>
                    <option value="draft"   <?php selected($post_status_code, 'draft');   ?>>Rascunho</option>
                  </select>
                </td>

                <!-- Visibilidade (EDITÁVEL) -->
                <td>
                  <select name="stock[<?php echo esc_attr($r['ID']); ?>][visibility]" class="ftsa-visibility-select">
                    <option value="visible" <?php selected($vis_code, 'visible'); ?>>Catálogo e Pesquisa</option>
                    <option value="catalog" <?php selected($vis_code, 'catalog'); ?>>Catálogo</option>
                    <option value="search"  <?php selected($vis_code, 'search');  ?>>Pesquisa</option>
                    <option value="hidden"  <?php selected($vis_code, 'hidden');  ?>>Oculto</option>
                  </select>
                </td>

                <td><?php echo esc_html($author_name); ?></td>

                <td>
                  <select name="stock[<?php echo esc_attr($r['ID']); ?>][manage]" class="ftsa-manage-select">
                    <option value="no"  <?php selected($r['manage_stock'], 'no');  ?>>NÃO</option>
                    <option value="yes" <?php selected($r['manage_stock'], 'yes'); ?>>SIM</option>
                  </select>
                </td>
                <td class="ftsa-cell-qty">
                  <input type="number" step="1" name="stock[<?php echo esc_attr($r['ID']); ?>][qty]"
                         value="<?php echo esc_attr($r['stock_qty']); ?>" style="width:110px" class="ftsa-qty-input">
                </td>
                <td class="ftsa-cell-low">
                  <input type="number" step="1" name="stock[<?php echo esc_attr($r['ID']); ?>][low]"
                         value="<?php echo esc_attr($r['low_stock']); ?>" style="width:130px" class="ftsa-low-input">
                </td>
                <td>
                  <select name="stock[<?php echo esc_attr($r['ID']); ?>][status]" class="ftsa-status-select">
                    <option value="" <?php selected($r['stock_status'], ''); ?>>—</option>
                    <option value="instock" <?php selected($r['stock_status'], 'instock'); ?>>Em estoque</option>
                    <option value="outofstock" <?php selected($r['stock_status'], 'outofstock'); ?>>Sem estoque</option>
                    <option value="onbackorder" <?php selected($r['stock_status'], 'onbackorder'); ?>>Sob encomenda</option>
                  </select>
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
                <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                  <?php echo esc_html($p); ?>
                </a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>

        <p style="margin-top:12px;">
          <button type="button" class="button" id="ftsa-stock-mark-all-yes-bottom">Gerenciar estoque: <strong>TODOS = SIM</strong></button>
          &nbsp;
          <button type="button" class="button" id="ftsa-stock-mark-all-no-bottom">Gerenciar estoque: <strong>TODOS = NÃO</strong></button>
          &nbsp;
          <button type="submit" class="button button-primary">Salvar</button>
        </p>
      </form>
    </div>

    <script>
    (function($){
      function parseIntSafe(v){ var n = parseInt(v, 10); return isNaN(n) ? 0 : n; }

      function selectedRows(){
        var $sel = $('.ftsa-row-select:checked').closest('tr');
        return $sel.length ? $sel : $('.ftsa-table-wrapper tbody tr');
      }

      function refreshRowIndicators($row){
        var manage = $row.find('select.ftsa-manage-select').val() === 'yes';
        var qty = parseIntSafe($row.find('.ftsa-qty-input').val());
        var low = parseIntSafe($row.find('.ftsa-low-input').val());

        $row.removeClass('ftsa-row-zero ftsa-row-low');

        if (manage) {
          if (qty <= 0) {
            $row.addClass('ftsa-row-zero');
          } else if (low > 0 && qty <= low) {
            $row.addClass('ftsa-row-low');
          }
        }
      }

      function refreshAll(){
        $('.ftsa-table-wrapper tbody tr').each(function(){ refreshRowIndicators($(this)); });
        applyQuickFilters();
      }

      function markAllManage(val, onlySelected){
        var $rows = onlySelected ? selectedRows() : $('.ftsa-table-wrapper tbody tr');
        $rows.find('select.ftsa-manage-select').val(val).trigger('change');
      }

      // Botões Gerenciar estoque SIM/NÃO (todos)
      $('#ftsa-stock-mark-all-yes, #ftsa-stock-mark-all-yes-bottom').on('click', function(){
        markAllManage('yes', false); refreshAll();
      });
      $('#ftsa-stock-mark-all-no, #ftsa-stock-mark-all-no-bottom').on('click', function(){
        markAllManage('no', false); refreshAll();
      });

      // Selecionar todos
      $('#ftsa-select-all').on('change', function(){
        $('.ftsa-row-select').prop('checked', this.checked);
      });

      // Ajuste em massa (Qtd)
      $('#ftsa-stock-bulk-plus').on('click', function(){
        var d = parseIntSafe($('#ftsa-stock-bulk-delta').val());
        selectedRows().find('.ftsa-qty-input').each(function(){
          this.value = parseIntSafe(this.value) + Math.abs(d);
        });
        refreshAll();
      });
      $('#ftsa-stock-bulk-minus').on('click', function(){
        var d = parseIntSafe($('#ftsa-stock-bulk-delta').val());
        selectedRows().find('.ftsa-qty-input').each(function(){
          this.value = parseIntSafe(this.value) - Math.abs(d);
        });
        refreshAll();
      });

      // Definir status automaticamente AGORA (somente selecionados; se nenhum, todos)
      $('#ftsa-stock-auto-now').on('click', function(){
        selectedRows().each(function(){
          var $row = $(this);
          var manage = $row.find('select.ftsa-manage-select').val() === 'yes';
          if (!manage) return;
          var qty = parseIntSafe($row.find('.ftsa-qty-input').val());
          var $st = $row.find('select.ftsa-status-select');
          $st.val( qty <= 0 ? 'outofstock' : 'instock' );
        });
        refreshAll();
      });

      // Mudanças individuais: atualiza destaque
      $(document).on('input change', '.ftsa-qty-input, .ftsa-low-input, .ftsa-manage-select', function(){
        refreshRowIndicators($(this).closest('tr'));
        applyQuickFilters();
      });

      // Filtros rápidos (cliente)
      function applyQuickFilters(){
        var showLow  = $('#ftsa-show-low').is(':checked');
        var showZero = $('#ftsa-show-zero').is(':checked');

        $('.ftsa-table-wrapper tbody tr').each(function(){
          var $tr = $(this);
          if (!showLow && !showZero) { $tr.show(); return; }
          var isLow  = $tr.hasClass('ftsa-row-low');
          var isZero = $tr.hasClass('ftsa-row-zero');
          var match = (showLow && isLow) || (showZero && isZero);
          $tr.toggle(match);
        });
      }
      $('#ftsa-show-low, #ftsa-show-zero').on('change', applyQuickFilters);

      // Primeira pintura
      refreshAll();
    })(jQuery);
    </script>

    <?php
} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Estoque</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao carregar o estoque.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}

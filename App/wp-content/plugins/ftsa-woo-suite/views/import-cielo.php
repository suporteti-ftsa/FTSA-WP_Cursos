<?php
if (!defined('ABSPATH')) { exit; }

try {
    if (!current_user_can('manage_woocommerce')) {
        echo '<div class="wrap"><h1>Conciliação CIELO (TXT/CSV)</h1><div class="notice notice-error"><p>Sem permissão.</p></div></div>';
        return;
    }

    $base_url = admin_url('admin.php?page=' . FTSA_Woo_Suite::MENU_IMPORT_SLUG);

    if (!function_exists('size_format')) {
        function size_format($bytes, $decimals = 0) {
            $sz = 'BKMGTP';
            $factor = floor((strlen($bytes) - 1) / 3);
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
        }
    }
    $max_upload = function_exists('wp_max_upload_size') ? size_format(wp_max_upload_size()) : '';

    ?>
    <div class="wrap">
      <h1>FTSA - Importador de arquivos EDI | TXT/CSV | Conciliação Cielo</h1>

      <?php if (!empty($_GET['imsg'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><?php echo esc_html($_GET['imsg']); ?></p>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" style="max-width:720px;">
        <?php wp_nonce_field('ftsa_cielo_import', 'ftsa_cielo_import_nonce'); ?>
        <input type="hidden" name="ftsa_cielo_import" value="1" />

        <table class="form-table">
          <tr>
            <th scope="row"><label for="cielo_file">Arquivo CIELO04D .TXT</label></th>
            <td>
              <input type="file" id="cielo_file" name="cielo_file" accept=".txt,text/plain" required>
              <?php if ($max_upload): ?>
                <p class="description">Tamanho máximo do upload: <?php echo esc_html($max_upload); ?>.</p>
              <?php endif; ?>
              <p class="description" style="margin-top:8px;">
                Aceita somente os arquivos oficiais <code>CIELO04D*.TXT</code> de liquidação/repasse. <code>CIELO03*</code> e <code>CIELO15*</code> são rejeitados nesta versão.
              </p>
            </td>
          </tr>
        </table>

        <?php submit_button('Importar e Conciliar'); ?>
      </form>

      <hr style="margin:24px 0;">

      <h2>Como funciona</h2>
      <p>O importador processa exclusivamente o TXT EDI v15 <strong>CIELO04D</strong>, com leitura por posições fixas das linhas de detalhe.</p>
      <p>A conciliação procura o pedido nesta ordem: <strong>Número do Pedido</strong> → <strong>TID</strong> → <strong>NSU/DOC</strong>. Metas gravadas: <code>cielo_tid</code>, <code>cielo_nsu</code> e <code>_cielo_settlement_date</code> (YYYY-MM-DD).</p>

      <h3>Idempotência</h3>
      <p>Cada par <code>(TID|NSU)</code> é gravado em <code>_ftsa_cielo_seen_pair</code> para evitar duplicidades em reimportações.</p>
    </div>
    <?php

} catch (\Throwable $e) {
    echo '<div class="wrap"><h1>Conciliação CIELO (TXT/CSV)</h1>';
    echo '<div class="notice notice-error"><p><strong>Falha ao carregar a página.</strong><br>Detalhe: '
         . esc_html($e->getMessage()) . '</p></div></div>';
}

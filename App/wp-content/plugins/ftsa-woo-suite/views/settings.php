<?php
if (!defined('ABSPATH')) { exit; }

/**
 * FTSA Plugins » Configurações
 * - Exclusões (permitir reprocessar) com PRÉ-TESTE
 * - Modos: por unique_key, por pedido, por arquivo e por período (data do pedido)
 * - Opção de escopo dos fees: "casar com as linhas" (mais seguro) ou "todos do pedido"
 */
?>
<div class="wrap">
  <h1>Configurações</h1>

  <?php if (!empty($_GET['smsg'])): ?>
    <div class="notice notice-success is-dismissible">
      <p><?php echo esc_html($_GET['smsg']); ?></p>
    </div>
  <?php endif; ?>

  <p class="description">Use o <strong>Pré-teste</strong> para conferir quantos registros/fees seriam afetados antes de executar.
    As remoções de fees <em>Taxa CIELO</em> são feitas via API do WooCommerce (mais seguro que SQL direto).</p>

  <hr>

  <h2>Exclusões (permitir reprocessar)</h2>

  <style>
    .ftsa-box { border:1px solid #ccd0d4; background:#fff; padding:16px; margin-bottom:16px; }
    .ftsa-row { display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end; }
    .ftsa-row .item { min-width: 260px; }
    .ftsa-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .ftsa-note { color:#646970; font-size:12px; margin-top:6px; }
    .ftsa-scope { margin-top:8px; }
  </style>

  <!-- por unique_key -->
  <div class="ftsa-box">
    <h3>Remover por Unique Key</h3>
    <form method="post">
      <?php wp_nonce_field('ftsa_settings_do', 'ftsa_settings_nonce'); ?>
      <input type="hidden" name="ftsa_settings_do" value="1">
      <input type="hidden" name="task" value="unique_key">

      <div class="ftsa-row">
        <div class="item">
          <label for="unique_key"><strong>Unique Key (SHA1)</strong></label><br>
          <input type="text" id="unique_key" name="unique_key" class="regular-text" required placeholder="cole aqui a unique_key exata">
          <p class="ftsa-note">Exclui a linha correspondente em <code>cielo_conciliacao_rows</code> e remove os fees “Taxa CIELO” com <code>_cielo_import_key</code> igual a esta chave.</p>
        </div>
      </div>

      <div class="ftsa-actions">
        <button type="submit" name="do" value="preview" class="button">Pré-teste</button>
        <button type="submit" name="do" value="execute" class="button button-primary"
                onclick="return confirm('Confirmar execução? Esta ação é irreversível.')">Executar</button>
      </div>
    </form>
  </div>

  <!-- por pedido -->
  <div class="ftsa-box">
    <h3>Remover por Nº do Pedido</h3>
    <form method="post">
      <?php wp_nonce_field('ftsa_settings_do', 'ftsa_settings_nonce'); ?>
      <input type="hidden" name="ftsa_settings_do" value="1">
      <input type="hidden" name="task" value="order_id">

      <div class="ftsa-row">
        <div class="item">
          <label for="order_id"><strong>Nº do Pedido</strong></label><br>
          <input type="number" id="order_id" name="order_id" min="1" required>
        </div>
        <div class="item">
          <label for="order_import_key"><strong>Filtrar fees por import_key (opcional)</strong></label><br>
          <input type="text" id="order_import_key" name="order_import_key" class="regular-text" placeholder="deixe vazio para remover todos os fees ‘Taxa CIELO’ do pedido">
        </div>
      </div>

      <div class="ftsa-actions">
        <button type="submit" name="do" value="preview" class="button">Pré-teste</button>
        <button type="submit" name="do" value="execute" class="button button-primary"
                onclick="return confirm('Confirmar execução? Esta ação é irreversível.')">Executar</button>
      </div>
    </form>
  </div>

  <!-- por arquivo -->
  <div class="ftsa-box">
    <h3>Remover por Arquivo (source_filename)</h3>
    <form method="post">
      <?php wp_nonce_field('ftsa_settings_do', 'ftsa_settings_nonce'); ?>
      <input type="hidden" name="ftsa_settings_do" value="1">
      <input type="hidden" name="task" value="file">

      <div class="ftsa-row">
        <div class="item" style="min-width:420px;">
          <label for="source_filename"><strong>Nome exato do arquivo</strong></label><br>
          <input type="text" id="source_filename" name="source_filename" class="regular-text" required placeholder="ex.: conciliacao_2025-01-31.csv" style="min-width:420px;">
          <p class="ftsa-note">Remove as linhas em <code>cielo_conciliacao_rows</code> desse arquivo e, conforme escopo abaixo, remove fees relacionados.</p>
        </div>
        <div class="item ftsa-scope">
          <label><strong>Escopo dos fees a remover</strong></label><br>
          <label><input type="radio" name="fees_scope" value="match" checked> Somente fees cujo <code>_cielo_import_key</code> case com as linhas desse arquivo (recomendado)</label><br>
          <label><input type="radio" name="fees_scope" value="all"> Todos os fees “Taxa CIELO” dos pedidos atingidos por esse arquivo</label>
        </div>
      </div>

      <div class="ftsa-actions">
        <button type="submit" name="do" value="preview" class="button">Pré-teste</button>
        <button type="submit" name="do" value="execute" class="button button-primary"
                onclick="return confirm('Confirmar execução? Esta ação é irreversível.')">Executar</button>
      </div>
    </form>
  </div>

  <!-- por período -->
  <div class="ftsa-box">
    <h3>Remover por Período (pela data do pedido)</h3>
    <form method="post">
      <?php wp_nonce_field('ftsa_settings_do', 'ftsa_settings_nonce'); ?>
      <input type="hidden" name="ftsa_settings_do" value="1">
      <input type="hidden" name="task" value="period">

      <div class="ftsa-row">
        <div class="item">
          <label for="start"><strong>Data Início</strong></label><br>
          <input type="date" id="start" name="start" required>
        </div>
        <div class="item">
          <label for="end"><strong>Data Término</strong></label><br>
          <input type="date" id="end" name="end" required>
        </div>
        <div class="item ftsa-scope">
          <label><strong>Escopo dos fees a remover</strong></label><br>
          <label><input type="radio" name="fees_scope" value="match" checked> Somente fees cujo <code>_cielo_import_key</code> case com as linhas desses pedidos</label><br>
          <label><input type="radio" name="fees_scope" value="all"> Todos os fees “Taxa CIELO” dos pedidos no período</label>
        </div>
      </div>

      <div class="ftsa-actions">
        <button type="submit" name="do" value="preview" class="button">Pré-teste</button>
        <button type="submit" name="do" value="execute" class="button button-primary"
                onclick="return confirm('Confirmar execução? Esta ação é irreversível.')">Executar</button>
      </div>

      <p class="ftsa-note">
        Dica: para volumes muito grandes, execute por faixas menores (ex.: semana a semana).
      </p>
    </form>
  </div>
</div>

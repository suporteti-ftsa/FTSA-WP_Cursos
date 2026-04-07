<?php

require_once(RDSM_SRC_DIR . '/events/rdsm_events_interface.php');

class RDSMSettingsPageLoaded implements RDSMEventsInterface {

  public function register_hooks() {
    add_action('wp_ajax_rdsm-authorization-check',  array($this, 'check_authorization'));
    add_action('admin_footer', array($this, 'print_log_filter_script'));
  }

  public function check_authorization() {
    if (!isset($_POST['rd_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rd_form_nonce'])), 'rd-form-nonce')) {
      wp_die( '0', 400 );
    }
    $response = array('token' => get_option('rdsm_access_token'));
    wp_send_json($response);
  }

  public function print_log_filter_script() {
    $screen = get_current_screen();
    if ($screen && $screen->id !== 'settings_page_rdstation-settings-page') return;
    ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        loadLogByFilter();

        const select = document.getElementById('rdsm_log_filter');
        if (select) {
          select.addEventListener('change', loadLogByFilter);
        }
      });

      function loadLogByFilter() {
        const filter = document.getElementById('rdsm_log_filter')?.value || 'all';
        const container = document.getElementById('rdsm_log_screen');

        container.innerHTML = '<em>Loading...</em>\n\n';

        fetch(ajaxurl + '?action=rdsm_get_log_by_filter&filter=' + encodeURIComponent(filter))
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              const lines = data.data.split('\n');
              const blocks = [];
              let currentBlock = [];

              lines.forEach(line => {
                if (line.trim().startsWith('#')) {
                  if (currentBlock.length > 0) {
                    blocks.push(currentBlock);
                  }
                  currentBlock = [line];
                } else {
                  currentBlock.push(line);
                }
              });

              if (currentBlock.length > 0) {
                blocks.push(currentBlock);
              }

              const html = blocks.map(blockLines => {
                const blockText = blockLines.join('').trim();

                // Determina a classe geral do bloco
                const cssClass = blockText.includes('[ERROR]')
                  ? 'rdsm-log-error'
                  : blockText.includes('[SUCCESS]')
                    ? 'rdsm-log-success'
                    : 'rdsm-log-neutral';

                // Separa a primeira linha (timestamp)
                const timestamp = escapeHtml(blockLines[0]);
                const contentLines = blockLines.slice(1).map(line => escapeHtml(line)).join('\n').trim();

                return `
                  <div class="${cssClass}">
                    <div class="rdsm-log-timestamp">${timestamp}</div>
                    <pre>${contentLines}</pre>
                  </div>
                `;
              }).join('').trim();

              container.innerHTML = html;
            } else {
              container.innerHTML = '<em>Failed to load log.</em>';
            }
          })
          .catch(() => {
            container.innerHTML = '<em>Error loading log.</em>';
          });
      }

      // Função para evitar XSS ao inserir texto como HTML
      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
    </script>
    <?php
  }
  
}

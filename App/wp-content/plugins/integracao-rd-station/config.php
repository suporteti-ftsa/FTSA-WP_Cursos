<?php

define('RDSM_ASSETS_URL', plugin_dir_url(__FILE__) . 'assets');
define('RDSM_SRC_DIR', dirname(__FILE__) . '/includes');

// URIs
define('RDSM_LEGACY_API_URL', 'https://app.rdstation.com.br/api/1.3');
define('RDSM_API_URL', 'https://api.rd.services');
define('RDSM_REFRESH_TOKEN_URL', 'https://wp.rd.services/prod/oauth/refresh');

// Endpoints
define('RDSM_CONVERSIONS', '/conversions');
define('RDSM_EVENTS', '/platform/events');
define('RDSM_CONTACTS', '/platform/contacts/');
define('RDSM_TRACKING_CODE', '/marketing/tracking_code');
define('RDSM_CONTACTS_FIELDS', '/platform/contacts/fields');

// File
// Define caminho do log com base na permissibilidade
if (!defined('RDSM_LOG_FILE_PATH')) {
    $default_path = WP_CONTENT_DIR . '/uploads/rdsm_logs';

    // Testa se pode escrever
    if (!is_dir($default_path)) {
        @wp_mkdir_p($default_path);
    }

    // Verifica se o caminho padrão é gravável, senão usa /tmp
    if (!is_writable($default_path)) {
        define('RDSM_LOG_FILE_PATH', '/tmp/rdsm_logs');
        if (!is_dir(RDSM_LOG_FILE_PATH)) {
            @mkdir(RDSM_LOG_FILE_PATH, 0777, true);
        }
    } else {
        define('RDSM_LOG_FILE_PATH', $default_path);
    }
}
define('RDSM_LOG_FILE_LIMIT', 20000);

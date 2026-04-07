<?php
if (!defined('ABSPATH')) { exit; }

class FTSA_Import_Logger {
    /** @var wpdb */
    private $db;
    private $tbl;
    private $id = 0;
    private $log_path = '';
    private $started_at = '';

    public function __construct($db) {
        $this->db  = $db;
        $this->tbl = $db->prefix . 'ftsa_cielo_imports';
    }

    public static function logs_dir() {
        $up = wp_upload_dir();
        return trailingslashit($up['basedir']) . 'ftsa-cielo-logs';
    }

    public static function ensure_env($db) {
        // pasta
        $dir = self::logs_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            @file_put_contents($dir.'/.htaccess', "Options -Indexes\n");
        }
        // tabela
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tbl = $db->prefix . 'ftsa_cielo_imports';
        $charset = $db->get_charset_collate();
        $sql = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            filename VARCHAR(255) NOT NULL,
            filehash CHAR(64) NOT NULL,
            filesize BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_lines INT UNSIGNED NOT NULL DEFAULT 0,
            processed INT UNSIGNED NOT NULL DEFAULT 0,
            matched_number INT UNSIGNED NOT NULL DEFAULT 0,
            matched_tid INT UNSIGNED NOT NULL DEFAULT 0,
            matched_nsu INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_duplicate INT UNSIGNED NOT NULL DEFAULT 0,
            not_found INT UNSIGNED NOT NULL DEFAULT 0,
            errors INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'running',
            log_path VARCHAR(255) NULL,
            KEY filehash (filehash),
            KEY started_at (started_at)
        ) $charset;";
        dbDelta($sql);
    }

    public function start($filename, $filehash, $filesize) {
        $this->started_at = current_time('mysql');
        $slug = gmdate('Ymd_His') . '-' . substr($filehash, 0, 8);
        $this->log_path = trailingslashit(self::logs_dir()) . $slug . '.jsonl';

        $this->db->insert($this->tbl, [
            'started_at' => $this->started_at,
            'filename'   => $filename,
            'filehash'   => $filehash,
            'filesize'   => (int)$filesize,
            'status'     => 'running',
            'log_path'   => $this->log_path,
        ], ['%s','%s','%s','%d','%s','%s']);

        $this->id = (int) $this->db->insert_id;

        // primeira linha do log (evento start)
        $this->append(['event'=>'start','time'=>$this->started_at,'filename'=>$filename,'hash'=>$filehash,'size'=>$filesize,'import_id'=>$this->id]);

        return $this->id;
    }

    public function append(array $obj) {
        if ($this->log_path === '') return;
        $obj['t'] = gmdate('c');
        @file_put_contents($this->log_path, json_encode($obj, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }

    public function log_row($line_no, array $payload, array $result) {
        $this->append([
            'event'    => 'row',
            'line'     => (int)$line_no,
            'payload'  => $payload,
            'result'   => $result,
        ]);
    }

    public function finalize(array $stats, $status='done', $message='') {
        $end = current_time('mysql');

        $this->append([
            'event'=>'end','time'=>$end,'status'=>$status,'message'=>$message,'stats'=>$stats
        ]);

        $upd = [
            'finished_at'       => $end,
            'status'            => $status,
            'message'           => $message,
            'total_lines'       => isset($stats['processed']) ? (int)$stats['processed'] : 0,
            'processed'         => (int)($stats['processed'] ?? 0),
            'matched_number'    => (int)($stats['matched_number'] ?? 0),
            'matched_tid'       => (int)($stats['matched_tid'] ?? 0),
            'matched_nsu'       => (int)($stats['matched_nsu'] ?? 0),
            'skipped_duplicate' => (int)($stats['skipped_duplicate'] ?? 0),
            'not_found'         => (int)($stats['not_found'] ?? 0),
            'errors'            => (int)($stats['errors'] ?? 0),
        ];
        $this->db->update($this->tbl, $upd, ['id'=>$this->id], ['%s','%s','%s','%d','%d','%d','%d','%d','%d','%d'], ['%d']);
    }

    public static function list_imports($db, $limit=100) {
        $tbl = $db->prefix . 'ftsa_cielo_imports';
        $limit = (int)$limit;
        return $db->get_results("SELECT * FROM {$tbl} ORDER BY id DESC LIMIT {$limit}", ARRAY_A);
    }

    public static function get_log_path($db, $id) {
        $tbl = $db->prefix . 'ftsa_cielo_imports';
        return $db->get_var($db->prepare("SELECT log_path FROM {$tbl} WHERE id=%d", (int)$id));
    }
}

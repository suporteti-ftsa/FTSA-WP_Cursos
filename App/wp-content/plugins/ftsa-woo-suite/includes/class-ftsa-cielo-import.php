<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Importador CIELO (TXT EDI v15 / CSV)
 * - TXT: CIELO04D (Liquidação) lendo REGISTRO 'E' (UR Analítica) - layout v15.
 * - Restrito a arquivos .TXT cujo nome inicie com CIELO04D.
 * - Versão 4.0.3 (fusão com boot seguro e fee de taxa CIELO).
 */
class FTSA_Cielo_Import {

    /** Meta keys adicionais em que tentaremos localizar TID/NSU (além de cielo_tid/cielo_nsu) */
    private $extra_tid_keys = [
        '_transaction_id','transaction_id','wc_cielo_tid','tid','payment_tid','_cielo_tid',
        'cielo_transaction_id','cieloecommerce_tid','_wc_cielo_payment_tid'
    ];
    private $extra_nsu_keys = [
        'nsu','doc','cielo_nsu','_cielo_nsu','cieloecommerce_nsu','nsu_doc','nsu/doc'
    ];

    /** Dados da sessão de importação (logger) */
    private $sess_id = 0;
    private $source_filename = '';
    private $source_ext = '';
    private $source_encoding = 'UTF-8';
    private $source_filesize = 0;
    /** @var FTSA_Import_Logger|null */
    private $logger = null;

    /* =========================== ENTRYPOINT ============================ */

    public function handle_import() {
        if (!is_admin() || !current_user_can('manage_woocommerce')) return;

        $on_page  = (isset($_GET['page']) && $_GET['page'] === FTSA_Woo_Suite::MENU_IMPORT_SLUG);
        $has_post = (!empty($_POST['ftsa_cielo_import']) || !empty($_FILES['cielo_file']['tmp_name']));
        if (!$on_page || !$has_post) return;

        if (empty($_POST['ftsa_cielo_import_nonce']) ||
            !wp_verify_nonce($_POST['ftsa_cielo_import_nonce'], 'ftsa_cielo_import')) {
            wp_die('Falha de verificação de segurança no importador CIELO.');
        }

        if (empty($_FILES['cielo_file']['tmp_name']) || !is_uploaded_file($_FILES['cielo_file']['tmp_name'])) {
            $this->redirect_with_msg('Nenhum arquivo enviado.');
        }

        // Coleta metadados do arquivo
        $tmp  = $_FILES['cielo_file']['tmp_name'];
        $name = isset($_FILES['cielo_file']['name']) ? (string) $_FILES['cielo_file']['name'] : '';
        $this->source_filename = $name;
        $this->source_filesize = (int) filesize($tmp);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $this->source_ext = $ext;

        if ($ext !== 'txt' || stripos(basename($name), 'CIELO04D') !== 0) {
            $this->redirect_with_msg('Somente arquivos CIELO04D .TXT são aceitos neste importador.');
        }

        $enc  = $this->detect_encoding($tmp);
        $this->source_encoding = $enc;

        // Abre sessão de LOG (se disponível)
        $this->open_log_session([
            'filename' => $this->source_filename,
            'filesize' => $this->source_filesize,
            'ext'      => $this->source_ext,
            'encoding' => $this->source_encoding,
            'version'  => '4.0.6',
        ]);

        // Loga início
        $this->log_event('import_start', [
            'filename' => $this->source_filename,
            'filesize' => $this->source_filesize,
            'ext'      => $this->source_ext,
            'encoding' => $this->source_encoding,
        ]);

        // Processa conforme extensão
        try {
            $rows = $this->parse_edi_txt_v15($tmp, $enc);
            if ($rows === null) {
                $this->finish_log_session('failed', $this->empty_stats(['errors'=>1]), [
                    'reason' => 'parse_txt_failed'
                ]);
                $this->redirect_with_msg('Falha ao ler TXT EDI CIELO04D (confirme o layout v15 do arquivo).');
            }
            $stats = $this->process_rows($rows);
        } catch (\Throwable $e) {
            $this->log_event('fatal_error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            $this->finish_log_session('failed', $this->empty_stats(['errors'=>1]), [
                'reason' => 'exception'
            ]);
            $this->redirect_with_msg('Erro inesperado ao processar o arquivo da CIELO.');
        }

        // Finaliza LOG
        $this->finish_log_session('success', $stats);

        // Mensagem final
        $msg = sprintf(
            'Importação CIELO: processadas %d linha(s). Localizado por Número: %d, por TID: %d, por NSU: %d. Duplicadas ignoradas: %d. Não localizadas: %d. Erros: %d.',
            $stats['processed'], $stats['matched_number'], $stats['matched_tid'], $stats['matched_nsu'],
            $stats['skipped_duplicate'], $stats['not_found'], $stats['errors']
        );
        $this->redirect_with_msg($msg);
    }

    private function redirect_with_msg($msg) {
        $url = add_query_arg([
            'page' => FTSA_Woo_Suite::MENU_IMPORT_SLUG,
            'imsg' => rawurlencode($msg),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    /* ============================ TXT EDI v15 ============================ */

    /**
     * TXT v15 REGISTRO 'E' (UR Analítica) – campos principais:
     * NSU/DOC 176–181; TID 192–211; Parcela 18–19;
     * Bruto 261 (sinal) + 262–274; Líquido 275 (sinal) + 276–288;
     * Tarifa/MDR 331 (sinal) + 332–344; Data pagamento 630–637 (DDMMAAAA).
     * Retorna linhas canônicas: [DOC,TID,NSU,PARCELA,BRUTO,TAXA,LIQUIDO,DATA_CREDITO].
     */
    private function parse_edi_txt_v15($filepath, $encoding='UTF-8') {
        $fh = fopen($filepath, 'r');
        if (!$fh) return null;

        $rows = [];
        $rows[] = ['doc','tid','nsu','parcela','bruto','taxa','liquido','data_credito'];

        $line_no = 0;
        while (($line = fgets($fh)) !== false) {
            $line_no++;
            if ($line === '') continue;

            // Normaliza encoding p/ UTF-8
            if (strtoupper($encoding) !== 'UTF-8') {
                if (function_exists('mb_convert_encoding')) {
                    $line = @mb_convert_encoding($line, 'UTF-8', $encoding);
                } elseif (function_exists('iconv')) {
                    $conv = @iconv($encoding, 'UTF-8//IGNORE', $line);
                    $line = ($conv !== false) ? $conv : $line;
                }
            }
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            // só Registro E
            if (substr($line, 0, 1) !== 'E') {
                // Opcional: logar ignoradas
                $this->log_event('txt_skip', ['line_no'=>$line_no, 'reason'=>'not_E']);
                continue;
            }

            // v15 tem 760 colunas (aceita >300 minimo para evitar header/outros)
            if (strlen($line) < 300) {
                $this->log_event('txt_skip', ['line_no'=>$line_no, 'reason'=>'short_line', 'len'=>strlen($line)]);
                continue;
            }

            $cut = function($ini, $fim) use ($line) {
                $start = max(0, $ini - 1);
                $len   = max(0, $fim - $ini + 1);
                return substr($line, $start, $len);
            };

            $parcela     = trim($cut(18,19));
            $nsu         = trim($cut(176,181));
            $tid         = trim($cut(192,211));
            $sign_bruto  = trim($cut(261,261));
            $valor_bruto = trim($cut(262,274));
            $sign_liq    = trim($cut(275,275));
            $valor_liq   = trim($cut(276,288));
            $sign_mdr    = trim($cut(289,289));
            $valor_mdr   = trim($cut(290,302));
            if ($valor_mdr === '' || preg_replace('/\D/', '', $valor_mdr) === '') {
                $sign_mdr  = trim($cut(331,331));
                $valor_mdr = trim($cut(332,344));
            }
            $dt_pag_raw  = trim($cut(630,637)); // DDMMAAAA

            $to_money = function($num13, $sign='+') {
                $n = preg_replace('/\D/', '', (string)$num13);
                if ($n === '') $n = '0';
                $v = (float) (intval($n) / 100.0);
                if ($sign === '-') $v = -$v;
                return number_format($v, 2, '.', '');
            };
            $bruto   = $to_money($valor_bruto, ($sign_bruto === '-') ? '-' : '+');
            $liquido = $to_money($valor_liq,   ($sign_liq   === '-') ? '-' : '+');
            $taxa    = $to_money($valor_mdr,   ($sign_mdr   === '-') ? '-' : '+');
            if ((float)$taxa == 0.0 && (float)$bruto != 0.0 && (float)$liquido != 0.0) {
                $taxa = number_format((float)$liquido - (float)$bruto, 2, '.', '');
            }

            $parcela_i = ctype_digit($parcela) ? intval($parcela) : 0;

            $data_credito = '';
            if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $dt_pag_raw, $m)) {
                $data_credito = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }

            $doc = $nsu; // DOC ← NSU

            $rows[] = [$doc, $tid, $nsu, $parcela_i, $bruto, $taxa, $liquido, $data_credito];

            // Log opcional da extração TXT
            $this->log_event('txt_parsed', [
                'line_no' => $line_no,
                'doc'     => $doc,
                'tid'     => $tid,
                'nsu'     => $nsu,
                'parcela' => $parcela_i,
                'bruto'   => $bruto,
                'taxa'    => $taxa,
                'liquido' => $liquido,
                'data_credito' => $data_credito,
            ]);
        }

        fclose($fh);
        return $rows;
    }

    /* ========================== CSV & PIPELINE ========================== */

    private function process_csv($filepath, $encoding='UTF-8') {
        $fh = fopen($filepath, 'r');
        if (!$fh) {
            $this->log_event('error', ['stage'=>'open_csv','message'=>'cannot_open']);
            return $this->empty_stats(['errors' => 1]);
        }

        $first = $this->read_first_line($fh);
        if ($first === null) { fclose($fh); $this->log_event('error', ['stage'=>'csv','message'=>'empty_file']); return $this->empty_stats(['errors' => 1]); }

        $delimiter = $this->guess_delimiter($first);
        fclose($fh);

        $fh = fopen($filepath, 'r');
        if (!$fh) { $this->log_event('error', ['stage'=>'csv','message'=>'reopen_failed']); return $this->empty_stats(['errors' => 1]); }

        $headers_raw = fgetcsv($fh, 0, $delimiter);
        if (!is_array($headers_raw)) { fclose($fh); $this->log_event('error', ['stage'=>'csv','message'=>'no_header']); return $this->empty_stats(['errors' => 1]); }
        $headers = array_map([$this, 'norm_header'], $headers_raw);
        $colmap  = $this->map_columns($headers);

        $stats = $this->empty_stats();
        $line_no = 1; // já leu cabeçalho

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $line_no++;

            // Ignora linhas totalmente vazias
            $all_empty = true;
            foreach ($row as $c) { if ((string)$c !== '') { $all_empty = false; break; } }
            if ($all_empty) {
                $this->log_event('row_skip', ['row_no'=>$line_no, 'reason'=>'empty']);
                continue;
            }

            if (count($row) < count($headers)) {
                $stats['errors']++;
                $this->log_event('row_error', ['row_no'=>$line_no, 'reason'=>'cols_lt_headers']);
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $h) {
                $val = isset($row[$i]) ? $row[$i] : '';
                if (strtoupper($encoding) !== 'UTF-8' && $val !== '') {
                    $val = $this->to_utf8($val, $encoding);
                }
                $assoc[$h] = is_string($val) ? trim($val) : '';
            }

            $numero_pedido = $this->val($assoc, $colmap, 'order_no');
            $tid_csv       = $this->normalize_tid_nsu($this->val($assoc, $colmap, 'tid'));
            $nsu_csv       = $this->normalize_tid_nsu($this->val($assoc, $colmap, 'nsu'));
            $repasse_raw   = $this->val($assoc, $colmap, 'settlement_date');
            $repasse_date  = $this->normalize_date_like($repasse_raw);

            $stats['processed']++;

            // LOG: leitura de linha
            $this->log_event('row_read', [
                'row_no'        => $line_no,
                'order_no'      => $numero_pedido,
                'tid'           => $tid_csv,
                'nsu'           => $nsu_csv,
                'settlement'    => $repasse_date,
            ]);

            try {
                list($order_id, $how) = $this->locate_order_smart([
                    'numero_pedido' => $numero_pedido,
                    'tid'           => $tid_csv,
                    'nsu'           => $nsu_csv,
                ]);

                if (!$order_id) {
                    $stats['not_found']++;
                    $this->log_event('row_not_found', [
                        'row_no'   => $line_no,
                        'order_no' => $numero_pedido,
                        'tid'      => $tid_csv,
                        'nsu'      => $nsu_csv,
                    ]);
                    continue;
                }

                $pair_hash = $this->make_pair_hash($tid_csv, $nsu_csv);
                if ($tid_csv !== '' || $nsu_csv !== '') {
                    if ($this->pair_already_seen($order_id, $pair_hash)) {
                        $stats['skipped_duplicate']++;
                        $this->log_event('row_duplicate', [
                            'row_no'   => $line_no,
                            'order_id' => $order_id,
                            'pair'     => $pair_hash,
                            'tid'      => $tid_csv,
                            'nsu'      => $nsu_csv,
                        ]);
                        continue;
                    }
                }

                // Atualiza metas
                if ($tid_csv !== '') $this->update_meta_if_empty_or_same($order_id, 'cielo_tid', $tid_csv);
                if ($nsu_csv !== '') $this->update_meta_if_empty_or_same($order_id, 'cielo_nsu', $nsu_csv);
                if ($repasse_date !== '') update_post_meta($order_id, '_cielo_settlement_date', $repasse_date);

                $taxa_csv = $this->normalize_money_like($assoc['taxa'] ?? '');
                if ($taxa_csv !== '') {
                    $this->upsert_cielo_fee($order_id, $taxa_csv, $tid_csv, $nsu_csv, $repasse_date);
                }

                $order = wc_get_order($order_id);
                if ($order) {
                    $order->add_order_note('Conciliação CIELO: localizado por ' . $how);
                }

                if ($tid_csv !== '' || $nsu_csv !== '') $this->mark_pair_seen($order_id, $pair_hash);

                if ($how === 'NUMBER') $stats['matched_number']++;
                elseif (strpos((string)$how, 'TID') === 0) $stats['matched_tid']++;
                elseif (strpos((string)$how, 'NSU') === 0) $stats['matched_nsu']++;

                // LOG: match ok
                $this->log_event('row_matched', [
                    'row_no'   => $line_no,
                    'order_id' => $order_id,
                    'how'      => $how,
                    'tid'      => $tid_csv,
                    'nsu'      => $nsu_csv,
                    'repasse'  => $repasse_date,
                ]);

            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->log_event('row_exception', [
                    'row_no'  => $line_no,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }
        }

        fclose($fh);
        return $stats;
    }

    /** Processa linhas canônicas (do TXT) como se fosse CSV **/
    private function process_rows(array $rows) {
        if (empty($rows) || count($rows[0]) < 8) {
            $this->log_event('error', ['stage'=>'rows','message'=>'header_invalid_or_empty']);
            return $this->empty_stats(['errors'=>1]);
        }

        $tmp = wp_tempnam('cielo-edi-csv');
        $fh  = fopen($tmp, 'w');
        // Cabeçalho
        fputcsv($fh, ['doc','tid','nsu','parcela','bruto','taxa','liquido','data_credito'], ';');

        for ($i=1; $i<count($rows); $i++) {
            $r = $rows[$i];
            if (!is_array($r) || count($r) < 8) {
                $this->log_event('row_skip', ['row_no'=>$i+1, 'reason'=>'row_len_lt_8']);
                continue;
            }
            fputcsv($fh, $r, ';');
        }
        fclose($fh);

        // Agora processa como CSV comum (mantém logging de linha)
        $stats = $this->process_csv($tmp, 'UTF-8');
        @unlink($tmp);
        return $stats;
    }

    /* ======================= LOCALIZAÇÃO & META ======================= */

    private function locate_order_smart($row) {
        $order_no = isset($row['numero_pedido']) ? (int) $row['numero_pedido'] : 0;
        $tid_csv  = $this->normalize_tid_nsu($row['tid'] ?? '');
        $nsu_csv  = $this->normalize_tid_nsu($row['nsu'] ?? '');

        // 1) Por Número do Pedido (se vier no CSV)
        if ($order_no > 0) {
            $order_id = $order_no;

            // Se TID/NSU também vierem, garanta que não conflita com outro pedido já marcado
            if ($order_id && $tid_csv !== '') {
                $tid_owner = $this->find_order_by_meta_exact('cielo_tid', $tid_csv);
                if ($tid_owner && $tid_owner !== $order_id) { $order_id = 0; }
            }
            if ($order_id && $nsu_csv !== '') {
                $nsu_owner = $this->find_order_by_meta_exact('cielo_nsu', $nsu_csv);
                if ($nsu_owner && $nsu_owner !== $order_id) { $order_id = 0; }
            }
            if ($order_id) return [$order_id, 'NUMBER'];
        }

        // 2) Meta exata (cielo_tid / cielo_nsu)
        if ($tid_csv !== '') {
            $tid_owner = $this->find_order_by_meta_exact('cielo_tid', $tid_csv);
            if ($tid_owner) {
                if ($nsu_csv !== '') {
                    $nsu_owner = $this->find_order_by_meta_exact('cielo_nsu', $nsu_csv);
                    if ($nsu_owner && $nsu_owner !== $tid_owner) { return [0, '']; }
                }
                return [$tid_owner, 'TID'];
            }
        }

        if ($nsu_csv !== '') {
            $nsu_owner = $this->find_order_by_meta_exact('cielo_nsu', $nsu_csv);
            if ($nsu_owner) return [$nsu_owner, 'NSU'];
        }

        // 3) Fuzzy por meta de gateways (lista comum) e LIKE em meta_key
        if ($tid_csv !== '') {
            $owner = $this->find_order_by_tid_guess($tid_csv);
            if ($owner) return [$owner, 'TID(meta-like)'];
        }
        if ($nsu_csv !== '') {
            $owner = $this->find_order_by_nsu_guess($nsu_csv);
            if ($owner) return [$owner, 'NSU(meta-like)'];
        }

        // 4) Buscar em notas do pedido (order notes)
        if ($tid_csv !== '') {
            $owner = $this->find_order_in_notes($tid_csv);
            if ($owner) return [$owner, 'TID(nota)'];
        }
        if ($nsu_csv !== '') {
            $owner = $this->find_order_in_notes($nsu_csv);
            if ($owner) return [$owner, 'NSU(nota)'];
        }

        return [0, ''];
    }

    private function find_order_by_meta_exact($meta_key, $value) {
        global $wpdb;
        $pm = $wpdb->postmeta;
        $value = (string) $value;

        static $has_md5 = null;
        if ($has_md5 === null) {
            $has_md5 = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$pm} LIKE 'meta_value_md5'");
        }

        if ($has_md5) {
            $sql = "
                SELECT post_id
                FROM {$pm}
                WHERE meta_key = %s
                  AND meta_value_md5 = UNHEX(MD5(%s))
                LIMIT 1
            ";
            return (int) $wpdb->get_var($wpdb->prepare($sql, $meta_key, $value));
        } else {
            $sql = "
                SELECT post_id
                FROM {$pm}
                WHERE meta_key = %s
                  AND meta_value = %s
                LIMIT 1
            ";
            return (int) $wpdb->get_var($wpdb->prepare($sql, $meta_key, $value));
        }
    }

    private function find_order_by_tid_guess($tid) {
        $tid = (string) $tid;
        if ($tid === '') return 0;
        global $wpdb; $pm = $wpdb->postmeta;

        // 3a) Meta keys conhecidas (IN)
        $in = implode("','", array_map('esc_sql', $this->extra_tid_keys));
        $sql = "
            SELECT post_id FROM {$pm}
            WHERE meta_value = %s
              AND meta_key IN ('{$in}')
            LIMIT 1
        ";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql, $tid));
        if ($id) return $id;

        // 3b) Fallback LIKE no nome da meta
        $sql2 = "
            SELECT post_id FROM {$pm}
            WHERE meta_value = %s
              AND meta_key LIKE '%%%s%%'
            LIMIT 1
        ";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql2, $tid, 'tid'));
        return $id;
    }

    private function find_order_by_nsu_guess($nsu) {
        $nsu = (string) $nsu;
        if ($nsu === '') return 0;
        global $wpdb; $pm = $wpdb->postmeta;

        // 3a) Meta keys conhecidas (IN)
        $in = implode("','", array_map('esc_sql', $this->extra_nsu_keys));
        $sql = "
            SELECT post_id FROM {$pm}
            WHERE meta_value = %s
              AND meta_key IN ('{$in}')
            LIMIT 1
        ";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql, $nsu));
        if ($id) return $id;

        // 3b) Fallback LIKE no nome da meta
        $sql2 = "
            SELECT post_id FROM {$pm}
            WHERE meta_value = %s
              AND meta_key LIKE '%%%s%%'
            LIMIT 1
        ";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql2, $nsu, 'nsu'));
        if ($id) return $id;

        // Alguns gateways usam 'doc'
        $id = (int) $wpdb->get_var($wpdb->prepare($sql2, $nsu, 'doc'));
        return $id;
    }

    private function find_order_in_notes($needle) {
        $needle = (string) $needle;
        if ($needle === '') return 0;
        global $wpdb; $c = $wpdb->comments;

        // Busca leve em notas
        $sql = "
            SELECT comment_post_ID
            FROM {$c}
            WHERE comment_type IN ('order_note','')
              AND comment_approved = '1'
              AND comment_content LIKE %s
            ORDER BY comment_ID DESC
            LIMIT 1
        ";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql, '%'.$wpdb->esc_like($needle).'%'));
        return $id;
    }

    private function make_pair_hash($tid, $nsu) {
        $tid = $this->normalize_tid_nsu($tid ?? '');
        $nsu = $this->normalize_tid_nsu($nsu ?? '');
        return md5($tid . '|' . $nsu);
    }

    private function pair_already_seen($order_id, $pair_hash) {
        $all = get_post_meta($order_id, '_ftsa_cielo_seen_pair', false);
        return is_array($all) && in_array($pair_hash, $all, true);
    }

    private function mark_pair_seen($order_id, $pair_hash) {
        add_post_meta($order_id, '_ftsa_cielo_seen_pair', $pair_hash, false);
    }

    /* ====================== COLUNAS & HEADERS ========================= */

    private function map_columns(array $headers) {
        $map = [
            'order_no' => ['numero_do_pedido','numero_pedido','num_pedido','pedido','order','order_id','order_number','numero'],
            'tid' => ['tid','cielo_tid','tid_cielo'],
            'nsu' => ['nsu','doc','nsu_doc','nsu/doc','nsu_doc_cielo','cielo_nsu'],
            'settlement_date' => ['data_repasse','data_de_repasse','data_repasse_cielo','data_credito','data_de_credito','data_do_credito','dt_repasse','repasse','data_liquidacao','data_liquidação'],
        ];
        $found = ['order_no'=>null,'tid'=>null,'nsu'=>null,'settlement_date'=>null];

        foreach ($headers as $i => $h) {
            foreach ($map as $key => $cands) {
                if ($found[$key] !== null) continue;
                if (in_array($h, $cands, true)) $found[$key] = $i;
            }
        }
        return $found;
    }

    private function norm_header($s) {
        $s = is_string($s) ? $s : '';
        if (function_exists('remove_accents')) $s = remove_accents($s);
        $s = strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '_', $s);
        $s = preg_replace('/_+/', '_', $s);
        $s = trim($s, '_');
        return $s;
    }

    private function val(array $assoc, array $colmap, $key) {
        $idx = isset($colmap[$key]) ? $colmap[$key] : null;
        if ($idx === null) return '';
        $keys = array_keys($assoc);
        if (!isset($keys[$idx])) return '';
        $h = $keys[$idx];
        return isset($assoc[$h]) ? $assoc[$h] : '';
    }

    /* ====================== SANITIZAÇÃO & DATES ======================= */

    private function normalize_tid_nsu($v) {
        $v = is_string($v) ? $v : '';
        $v = trim($v);
        $v = preg_replace('/\s+/u', '', $v);
        return $v;
    }

    private function normalize_date_like($v) {
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') return '';
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
            list($d,$m,$y) = explode('/', $v);
            return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $ts = @strtotime($v);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    private function update_meta_if_empty_or_same($post_id, $key, $new_val) {
        $old = get_post_meta($post_id, $key, true);
        if ($old === '' || $old === $new_val) update_post_meta($post_id, $key, $new_val);
    }

    private function normalize_money_like($v) {
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') return '';
        $negative = false;
        if (strpos($v, '(') !== false && strpos($v, ')') !== false) $negative = true;
        if (strpos($v, '-') === 0) $negative = true;
        $v = preg_replace('/[^0-9,.-]/', '', $v);
        if ($v === '' || $v === '-' || $v === ',') return '';
        if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
            if (strrpos($v, ',') > strrpos($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                $v = str_replace(',', '', $v);
            }
        } elseif (strpos($v, ',') !== false) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }
        $n = (float) $v;
        if ($negative && $n > 0) $n *= -1;
        return number_format($n, 2, '.', '');
    }

    private function upsert_cielo_fee($order_id, $taxa_csv, $tid_csv, $nsu_csv, $repasse_date) {
        if (!function_exists('wc_get_order')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $amount = (float) $taxa_csv;
        if ($amount > 0) $amount *= -1;
        if ($amount == 0.0) return;

        $pair_key = md5(($tid_csv ?: '') . '|' . ($nsu_csv ?: '') . '|' . ($repasse_date ?: ''));
        foreach ($order->get_items('fee') as $item) {
            if ((string) $item->get_meta('_cielo_import_key', true) === $pair_key) {
                $item->set_name('Taxa CIELO');
                $item->set_total($amount);
                $item->update_meta_data('_cielo_fee_item_amount', abs((float) $amount));
                if ($tid_csv !== '') $item->update_meta_data('cielo_tid', $tid_csv);
                if ($nsu_csv !== '') $item->update_meta_data('cielo_nsu', $nsu_csv);
                $item->save();
                $order->calculate_totals(false);
                $order->save();
                return;
            }
        }

        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Taxa CIELO');
        $fee->set_total($amount);
        $fee->add_meta_data('_cielo_import_key', $pair_key, true);
        $fee->add_meta_data('_cielo_fee_item_amount', abs((float) $amount), true);
        if ($tid_csv !== '') $fee->add_meta_data('cielo_tid', $tid_csv, true);
        if ($nsu_csv !== '') $fee->add_meta_data('cielo_nsu', $nsu_csv, true);
        $order->add_item($fee);
        $order->calculate_totals(false);
        $order->save();
    }

    /* ====================== CSV UTILITIES ============================= */

    private function read_first_line($fh) {
        $pos  = ftell($fh);
        $line = fgets($fh);
        if ($line === false) return null;
        fseek($fh, $pos);
        return $line;
    }

    private function guess_delimiter($line) {
        $candidates = [";", ",", "\t"];
        $best = ";"; $best_count = -1;
        foreach ($candidates as $d) {
            $cnt = substr_count($line, $d);
            if ($cnt > $best_count) { $best_count = $cnt; $best = $d; }
        }
        return $best;
    }

    private function detect_encoding($filepath) {
        $bytes = @file_get_contents($filepath, false, null, 0, 4096);
        if ($bytes === false) return 'UTF-8';

        if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) return 'UTF-8';
        if (strncmp($bytes, "\x00\x00\xFE\xFF", 4) === 0 || strncmp($bytes, "\xFF\xFE\x00\x00", 4) === 0) return 'UTF-32';
        if (strncmp($bytes, "\xFE\xFF", 2) === 0 || strncmp($bytes, "\xFF\xFE", 2) === 0) return 'UTF-16';
        if (strpos(substr($bytes, 0, 1000), "\x00") !== false) return 'UTF-16';
        if (function_exists('mb_detect_encoding')) {
            $enc = @mb_detect_encoding($bytes, ['UTF-8','Windows-1252','ISO-8859-1','ASCII'], true);
            if ($enc) return $enc;
        }
        return 'Windows-1252';
    }

    private function to_utf8($str, $encoding) {
        $enc = strtoupper($encoding);
        if ($enc === 'UTF-8') return $str;
        if (function_exists('mb_convert_encoding')) return @mb_convert_encoding($str, 'UTF-8', $enc);
        if (function_exists('iconv')) {
            $res = @iconv($enc, 'UTF-8//IGNORE', $str);
            return $res !== false ? $res : $str;
        }
        return $str;
    }

    private function empty_stats($override = []) {
        $base = [
            'processed'         => 0,
            'matched_number'    => 0,
            'matched_tid'       => 0,
            'matched_nsu'       => 0,
            'skipped_duplicate' => 0,
            'not_found'         => 0,
            'errors'            => 0,
        ];
        return array_merge($base, $override);
    }

    /* ====================== LOGGER HELPERS ============================= */

    private function logger_available() {
        return class_exists('FTSA_Import_Logger');
    }

    private function open_log_session(array $meta) {
        if (!$this->logger_available()) return;
        try {
            global $wpdb;
            $this->logger = new FTSA_Import_Logger($wpdb);
            $hash = '';
            if (!empty($_FILES['cielo_file']['tmp_name']) && @is_file($_FILES['cielo_file']['tmp_name'])) {
                $hash = @hash_file('sha256', $_FILES['cielo_file']['tmp_name']) ?: '';
            }
            $this->sess_id = (int) $this->logger->start(
                (string) ($meta['filename'] ?? $this->source_filename),
                $hash,
                (int) ($meta['filesize'] ?? $this->source_filesize)
            );
            $this->logger->append(['event' => 'meta', 'meta' => $meta]);
        } catch (\Throwable $e) {
            $this->logger = null;
            $this->sess_id = 0;
        }
    }

    private function log_event($type, array $payload = []) {
        if (!$this->sess_id || !$this->logger) return;
        $entry = array_merge([
            'event'    => (string) $type,
            'filename' => $this->source_filename,
        ], $payload);
        try {
            $this->logger->append($entry);
        } catch (\Throwable $e) {
            // silencia logger
        }
    }

    private function finish_log_session($status, array $stats = [], array $extra = []) {
        if (!$this->sess_id || !$this->logger) return;
        $message = '';
        if (!empty($extra['reason'])) $message = (string) $extra['reason'];
        try {
            if (!empty($extra)) {
                $this->logger->append(['event' => 'finish_extra', 'extra' => $extra]);
            }
            $this->logger->finalize($stats, (string) $status, $message);
        } catch (\Throwable $e) {
            // silencia logger
        } finally {
            $this->sess_id = 0;
            $this->logger = null;
        }
    }
}

/** Alias de compatibilidade */
if (!class_exists('FTSA_Cielo_Import_Service')) {
    class FTSA_Cielo_Import_Service extends FTSA_Cielo_Import {}
}

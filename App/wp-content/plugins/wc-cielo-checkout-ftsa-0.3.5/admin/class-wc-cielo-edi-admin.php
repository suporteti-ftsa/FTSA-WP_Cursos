<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class WC_Cielo_EDI_Admin_FTSA {
    public function __construct(){ add_action('admin_menu',array($this,'menu')); }
    public function menu(){
        add_submenu_page('woocommerce',__('Cielo EDI (Liquidação)','wc-cielo-checkout-ftsa'),__('Cielo EDI (Liquidação)','wc-cielo-checkout-ftsa'),'manage_woocommerce','wc-cielo-edi-ftsa',array($this,'screen'));
    }
    public function screen(){
        if(isset($_POST['submit']) && check_admin_referer('wc_cielo_edi_upload','wc_cielo_edi_nonce')) $this->process_upload();
        echo '<div class="wrap"><h1>Cielo EDI (Liquidação)</h1>';
        echo '<p>Importe um arquivo <strong>CSV</strong> com colunas: <code>DOC;TID;NSU;PARCELA;BRUTO;TAXA;LIQUIDO;DATA_CREDITO</code>.</p>';
        echo '<form method="post" enctype="multipart/form-data">'; wp_nonce_field('wc_cielo_edi_upload','wc_cielo_edi_nonce');
        echo '<input type="file" name="edi_file" accept=".csv,text/csv" required/> '; submit_button(__('Importar EDI','wc-cielo-checkout-ftsa')); echo '</form>';
        echo '<p><em>Dica:</em> os pedidos devem conter <code>_cielo_doc_number</code>, <code>_cielo_tid</code> ou <code>_cielo_nsu</code> (salvos pelo webhook) para casar corretamente.</p>';
        echo '</div>';
    }
    private function digits_only($v){ return preg_replace('/\D/','', is_string($v)? $v : (string)$v ); }
    private function parse_csv($path){
        $rows=array(); $sep=';';
        if(($h=fopen($path,'r'))!==false){ $sample=fgets($h); fclose($h); if(strpos($sample,';')===false && strpos($sample,',')!==false) $sep=','; }
        if(($f=fopen($path,'r'))!==false){
            $header=fgetcsv($f,0,$sep); $map=array(); foreach($header as $i=>$col){ $k=strtolower(trim(preg_replace('/[^a-z0-9_]/','',iconv('UTF-8','ASCII//TRANSLIT',$col)))); $map[$k]=$i; }
            while(($data=fgetcsv($f,0,$sep))!==false){
                if(count($data)<2) continue;
                $r = array(
                    'doc' => $data[$map['doc']] ?? '',
                    'tid' => $data[$map['tid']] ?? '',
                    'nsu' => $data[$map['nsu']] ?? '',
                    'parcela' => $data[$map['parcela']] ?? '',
                    'bruto' => $data[$map['bruto']] ?? '',
                    'taxa' => $data[$map['taxa']] ?? '',
                    'liquido' => $data[$map['liquido']] ?? '',
                    'data_credito' => $data[$map['data_credito']] ?? ($data[$map['datacredito']] ?? ''),
                );
                $rows[]=$r;
            }
            fclose($f);
        }
        return $rows;
    }
    private function uploads_dir(){
        $up = wp_upload_dir();
        $dir = trailingslashit($up['basedir']).'cielo-edi';
        if( ! file_exists($dir) ){ wp_mkdir_p($dir); }
        return $dir;
    }
    private function write_unmatched_csv($rows){
        if(empty($rows)) return '';
        $fn = $this->uploads_dir().'/unmatched-'.date('Ymd-His').'.csv';
        $h = fopen($fn,'w');
        fputcsv($h, array('DOC','TID','NSU','PARCELA','BRUTO','TAXA','LIQUIDO','DATA_CREDITO'), ';');
        foreach($rows as $r){ fputcsv($h, array($r['doc'],$r['tid'],$r['nsu'],$r['parcela'],$r['bruto'],$r['taxa'],$r['liquido'],$r['data_credito']), ';'); }
        fclose($h);
        return $fn;
    }
    private function process_upload(){
        if(empty($_FILES['edi_file']['tmp_name'])){ echo '<div class="notice notice-error"><p>Arquivo não enviado.</p></div>'; return; }
        $rows=$this->parse_csv($_FILES['edi_file']['tmp_name']); if(!$rows){ echo '<div class="notice notice-error"><p>Não foi possível ler o CSV.</p></div>'; return; }
        $matched=0; $updated=0; $unmatched=array();
        foreach($rows as $r){
            $doc=$this->digits_only($r['doc']); $tid=$this->digits_only($r['tid']); $nsu=$this->digits_only($r['nsu']);
            $liq=floatval(str_replace(',','.',$r['liquido'])); $tax=floatval(str_replace(',','.',$r['taxa'])); $brt=floatval(str_replace(',','.',$r['bruto']));
            $dtc=sanitize_text_field($r['data_credito']);
            if(!$doc && !$tid && !$nsu){ $unmatched[]=$r; continue; }

            $or = array('relation'=>'OR');
            if($doc){ $or[] = array('key'=>'_cielo_doc_number','value'=>$doc); $or[] = array('key'=>'_cielo_doc_number','value'=>ltrim($doc,'0')); }
            if($tid){ $or[] = array('key'=>'_cielo_tid','value'=>$tid); $or[] = array('key'=>'_cielo_tid','value'=>ltrim($tid,'0')); }
            if($nsu){ $or[] = array('key'=>'_cielo_nsu','value'=>$nsu); $or[] = array('key'=>'_cielo_nsu','value'=>ltrim($nsu,'0')); }

            $q=new WP_Query(array('post_type'=>'shop_order','meta_query'=>$or,'fields'=>'ids','posts_per_page'=>1,));
            $order_id = $q->have_posts() ? intval($q->posts[0]) : 0;
            wp_reset_postdata();

            if($order_id){
                $matched++;
                if($tax) update_post_meta($order_id,'_cielo_fee_amount',wc_format_decimal($tax,2));
                if($liq) update_post_meta($order_id,'_cielo_net_amount',wc_format_decimal($liq,2));
                if($brt) update_post_meta($order_id,'_cielo_gross_amount',wc_format_decimal($brt,2));
                if($dtc) update_post_meta($order_id,'_cielo_credit_date',$dtc);
                update_post_meta($order_id,'_cielo_edi_status','liquidado');
                if($order=wc_get_order($order_id)){ $order->add_order_note(sprintf('Cielo EDI: taxa %s, líquido %s, crédito %s.',wc_price($tax),wc_price($liq),esc_html($dtc))); }
                $updated++;
            } else {
                $unmatched[]=$r;
            }
        }
        $msg = '<div class="notice notice-success"><p>Linhas lidas: <strong>'.count($rows).'</strong>. Casadas: <strong>'.$matched.'</strong>. Atualizadas: <strong>'.$updated.'</strong>.</p></div>';
        if(count($unmatched)){
            $file = $this->write_unmatched_csv($unmatched);
            $url = trailingslashit( wp_upload_dir()['baseurl'] ) . 'cielo-edi/' . basename($file);
            $msg .= '<div class="notice notice-warning"><p>Não casadas: <strong>'.count($unmatched).'</strong>. Arquivo gerado para diagnóstico: <a href="'.esc_url($url).'" target="_blank">'.esc_html(basename($file)).'</a></p></div>';
        }
        echo $msg;
    }
}
new WC_Cielo_EDI_Admin_FTSA();

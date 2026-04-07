<?php
/**
 * Plugin Name: Cielo Loja5 – Logger/Enhancer (FTSA)
 * Description: Logger detalhado + notas amigáveis + e-mail de negativa + captura automática de TxID/EndToEndId e “Nº pedido (Cielo)” para WooCommerce (Checkout Cielo/Loja5). Inclui Backfill com filtros de data e Diagnóstico de credenciais (com varredura estendida). **Versão Checkout-only** (remove chamadas à API E-commerce).
 * Author: FTSA
 * Version: 2.3.0
 */

if ( ! defined('ABSPATH') ) { exit; }

/* ============================================================
 *  CONSTANTES / OPÇÕES (somente define se não existir)
 * ============================================================ */
if ( ! defined('FTSA_CIELO_EMAIL_DENIED') )       define('FTSA_CIELO_EMAIL_DENIED', true);
if ( ! defined('FTSA_CIELO_EMAIL_FROM') )         define('FTSA_CIELO_EMAIL_FROM', '');
if ( ! defined('FTSA_CIELO_EMAIL_BCC') )          define('FTSA_CIELO_EMAIL_BCC', '');
if ( ! defined('FTSA_CIELO_ENABLE_PIX_CAPTURE') ) define('FTSA_CIELO_ENABLE_PIX_CAPTURE', true);
if ( ! defined('FTSA_CIELO_HTTP_TIMEOUT') )       define('FTSA_CIELO_HTTP_TIMEOUT', 25);
if ( ! defined('FTSA_CIELO_BACKFILL_BATCH') )     define('FTSA_CIELO_BACKFILL_BATCH', 50);

/* ============================================================
 *  HELPERS GERAIS
 * ============================================================ */
function ftsa_cielo_status_label($n){
    $m=[1=>'Autorizado',2=>'Confirmado (Capturado)',3=>'Negado',10=>'Cancelado (Voided)',11=>'Estornado (Refunded)',12=>'Pendente',13=>'Abortado',20=>'Agendado'];
    $n=is_numeric($n)?intval($n):$n;return $m[$n]??(string)$n;
}
function ftsa_cielo_method_type_label($n){
    $m=[1=>'Cartão de Crédito',2=>'Boleto',3=>'Cartão de Débito',4=>'Pix',5=>'Carteira Digital'];
    $n=is_numeric($n)?intval($n):$n;return $m[$n]??(string)$n;
}
function ftsa_cielo_brand_label($n){
    $m=[1=>'Visa',2=>'Mastercard',3=>'American Express',4=>'Elo',5=>'Aura',6=>'JCB',7=>'Diners Club',8=>'Discover',9=>'Hipercard',10=>'Hiper'];
    $n=is_numeric($n)?intval($n):$n;return $m[$n]??(string)$n;
}
function ftsa_money_brl_from_cents($c){
    if($c===''||$c===null)return'';if(!is_numeric($c))return(string)$c;
    $v=intval($c)/100;return 'R$ '.number_format($v,2,',','.');
}
function ftsa_abecs_reason($c){
    $c=trim((string)$c);if($c==='')return'';
    $m=['00'=>'Aprovada','01'=>'Cartão com restrição. Contate o emissor.','02'=>'Cartão com restrição. Contate o emissor.','03'=>'Comerciante inválido.','04'=>'Retenha o cartão (suspeita de fraude).','05'=>'Não autorizado. Contate o emissor.','06'=>'Erro do emissor.','07'=>'Retenha o cartão. Contate o emissor.','08'=>'Aprovado com documento (raro).','12'=>'Transação inválida.','13'=>'Valor inválido.','14'=>'Cartão inválido.','15'=>'Instituição emissora inválida.','19'=>'Refaça a transação.','21'=>'Sem ação. Contate o emissor.','22'=>'Cartão expirado/erro de sequência.','23'=>'Transação não permitida.','24'=>'Transação não permitida.','25'=>'Transação não encontrada/permitida.','28'=>'Problema de comunicação. Tente novamente.','30'=>'Erro de formato.','34'=>'Suspeita de fraude.','36'=>'Cartão restrito.','38'=>'Senha/PIN excedeu tentativas.','41'=>'Cartão perdido.','43'=>'Cartão roubado.','51'=>'Saldo/limite insuficiente.','52'=>'Conta inexistente.','53'=>'Conta inexistente.','54'=>'Cartão vencido.','55'=>'Senha incorreta.','57'=>'Transação não permitida para o cartão.','58'=>'Transação não permitida para o estabelecimento.','59'=>'Suspeita de fraude.','61'=>'Excedeu limite de valor.','62'=>'Cartão restrito por região/uso.','63'=>'Violação de segurança.','65'=>'Excedeu limite de quant. de transações.','68'=>'Sem resposta do emissor no tempo.','75'=>'Excedeu tentativas de senha/PIN.','76'=>'Transação não localizada.','77'=>'Transação não localizada.','78'=>'Cartão não ativo.','80'=>'Erro de rede/processamento.','82'=>'Erro de segurança.','83'=>'Erro de segurança.','85'=>'Sem razão para recusar (teste).','86'=>'Falha biométrica.','91'=>'Emissor indisponível.','92'=>'Roteamento/Emissor inválido.','93'=>'Violação de regras.','94'=>'Transação duplicada.','96'=>'Erro de sistema.'];
    return $m[$c]??'';
}
function ftsa_cielo_log($msg,$ctx=[]){
    if(!function_exists('wc_get_logger'))return;
    $lg=wc_get_logger();$ctx=array_merge(['source'=>'cielo-checkout'],(array)$ctx);
    if(is_array($msg)||is_object($msg)){$msg=wp_json_encode($msg,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    $lg->info($msg,$ctx);
}
/** Normaliza 'YYYY-MM-DD' ou 'DD/MM/YYYY'/'DD-MM-YYYY' para 'YYYY-MM-DD' */
function ftsa_cielo_normalize_date($s){
    $s=trim((string)$s);if($s==='')return'';
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$s))return $s;
    if(preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/',$s,$m))return "{$m[3]}-{$m[2]}-{$m[1]}";
    if(preg_match('/^(\d{2})-(\d{2})-(\d{4})$/',$s,$m))return "{$m[3]}-{$m[2]}-{$m[1]}";
    $t=strtotime($s);if($t)return date('Y-m-d',$t);return'';
}

/* ============================================================
 *  META + NOTAS DO PEDIDO
 * ============================================================ */
function ftsa_cielo_store_order_meta_and_note($order_id,$payload,$prefix='cielo_'){
    if(empty($order_id))return; $order=wc_get_order($order_id); if(!$order)return;

    $n=$payload;
    if(isset($n['ReturnCode'])&&!isset($n['return_code']))$n['return_code']=$n['ReturnCode'];
    if(isset($n['ReturnMessage'])&&!isset($n['return_message']))$n['return_message']=$n['ReturnMessage'];
    if(isset($n['order_number'])&&!isset($n['OrderNumber']))$n['OrderNumber']=$n['order_number'];

    $map=[
        'tid'=>'tid','nsu'=>'nsu',
        'payment_status'=>'payment_status','payment_method_type'=>'payment_method_type','payment_method_brand'=>'payment_method_brand',
        'payment_installments'=>'payment_installments','checkout_cielo_order_number'=>'checkout_order',
        'order_number'=>'order_number','OrderNumber'=>'order_number_alt','amount'=>'amount','created_date'=>'created_date',
        'return_code'=>'return_code','return_message'=>'return_message','payment_maskedcreditcard'=>'masked_pan',
        'payment_id'=>'payment_id','acquirer_order_id'=>'pix_txid','end_to_end_id'=>'pix_endtoendid'
    ];
    foreach($map as $k=>$mk){
        if(isset($n[$k])&&$n[$k]!==''){
            $order->update_meta_data($prefix.$mk,is_scalar($n[$k])?sanitize_text_field($n[$k]):wp_json_encode($n[$k]));
        }
    }

    $sent='';
    if(!empty($n['checkout_cielo_order_number'])){ $sent=trim((string)$n['checkout_cielo_order_number']); }
    elseif(!empty($n['OrderNumber']) && !ctype_digit((string)$n['OrderNumber'])){ $sent=trim((string)$n['OrderNumber']); }
    if($sent){ $order->update_meta_data('cielo_sentorder_id',sanitize_text_field($sent)); }

    $order->save();

    $status_num=$n['payment_status']??''; $status_label=$status_num!==''?ftsa_cielo_status_label($status_num):'-';
    $method_num=$n['payment_method_type']??''; $method_label=$method_num!==''?ftsa_cielo_method_type_label($method_num):'-';
    $brand_num=$n['payment_method_brand']??''; $brand_label=$brand_num!==''?ftsa_cielo_brand_label($brand_num):'-';
    $amount_brl=isset($n['amount'])?ftsa_money_brl_from_cents($n['amount']):''; $installments=isset($n['payment_installments'])?intval($n['payment_installments']):1;
    $masked_pan=$n['payment_maskedcreditcard']??''; $tid=!empty($n['tid'])?$n['tid']:'-'; $nsu=!empty($n['nsu'])?$n['nsu']:'-';
    $rc=isset($n['return_code'])?trim((string)$n['return_code']):''; $rm=isset($n['return_message'])?trim((string)$n['return_message']):'';
    $rc_text=$rc!==''?ftsa_abecs_reason($rc):'';

    $p=[];
    if($amount_brl)$p[]="Valor: {$amount_brl}".($installments>1?" ({$installments}x)":'');
    if($method_label!=='-')$p[]="Meio: {$method_label}";
    if($brand_label!=='-') $p[]="Bandeira: {$brand_label}".($masked_pan?" ({$masked_pan})":'');
    $p[]="Status: {$status_label}".($status_num!==''?" [{$status_num}]":'');
    $p[]="TID: {$tid}";
    $p[]="NSU: {$nsu}";
    if($sent)$p[]="Nº pedido (Cielo): {$sent}";
    if($rc!=='')$p[]="ReturnCode: {$rc}".($rc_text?" ({$rc_text})":'');
    if($rm!=='')$p[]="ReturnMessage: {$rm}";

    $order->add_order_note('Cielo (Resumo): '.implode(' | ',$p));

    if(intval($status_num)===3 && FTSA_CIELO_EMAIL_DENIED){
        ftsa_cielo_send_denied_email($order,[
            'amount_brl'=>$amount_brl,'installments'=>$installments,'brand_label'=>$brand_label,'masked_pan'=>$masked_pan,
            'return_code'=>$rc,'abecs_text'=>$rc_text,'return_msg'=>$rm,'tid'=>$tid,'nsu'=>$nsu
        ]);
    }
}

/* ============================================================
 *  E-MAIL DE NEGATIVA
 * ============================================================ */
function ftsa_cielo_send_denied_email($order,$info){
    if( ! $order || ! function_exists('wc_mail') ) return;
    $to=is_object($order)?$order->get_billing_email():'';
    if(!$to)return;

    $subject=sprintf('[FTSA] Pagamento não aprovado para o pedido #%s',$order->get_order_number());
    $headers=['Content-Type: text/html; charset=UTF-8'];
    if(FTSA_CIELO_EMAIL_FROM)$headers[]='From: '.FTSA_CIELO_EMAIL_FROM;
    if(FTSA_CIELO_EMAIL_BCC)$headers[]='Bcc: '.FTSA_CIELO_EMAIL_BCC;

    $lines=[];
    $lines[]='<p>Olá, '.esc_html($order->get_billing_first_name()).'.</p>';
    $lines[]='<p>Seu pagamento não foi aprovado pelo emissor do cartão. Detalhes:</p><ul>';
    $lines[]='<li><strong>Pedido:</strong> #'.esc_html($order->get_order_number()).'</li>';
    if(!empty($info['amount_brl'])){ $parc=($info['installments']>1)?' ('.intval($info['installments']).'x)':''; $lines[]='<li><strong>Valor:</strong> '.esc_html($info['amount_brl'].$parc).'</li>'; }
    if(!empty($info['brand_label'])){ $pan=$info['masked_pan']?' '.esc_html($info['masked_pan']):''; $lines[]='<li><strong>Cartão:</strong> '.esc_html($info['brand_label']).$pan.'</li>'; }
    if(!empty($info['return_code'])){ $desc=!empty($info['abecs_text'])?' — '.esc_html($info['abecs_text']):''; $lines[]='<li><strong>Código do emissor:</strong> '.esc_html($info['return_code']).$desc.'</li>'; }
    if(!empty($info['return_msg'])){ $lines[]='<li><strong>Mensagem adicional:</strong> '.esc_html($info['return_msg']).'</li>'; }
    if(!empty($info['tid'])){ $lines[]='<li><strong>TID:</strong> '.esc_html($info['tid']).'</li>'; }
    if(!empty($info['nsu'])){ $lines[]='<li><strong>NSU:</strong> '.esc_html($info['nsu']).'</li>'; }
    $lines[]='</ul><p><strong>Como resolver?</strong><br/>Frequentemente, o emissor recusa por limite insuficiente, bloqueio de segurança ou transação não permitida. Recomendamos:</p><ol><li>Verificar com seu banco/emissor o motivo da recusa (mencionando o código acima).</li><li>Tentar novamente em alguns minutos, preferindo conexão segura/estável.</li><li>Usar outro cartão ou forma de pagamento disponível.</li></ol><p>Se precisar de ajuda, basta responder este e-mail. 💬</p><p>Equipe FTSA</p>';

    wc_mail($to,$subject,implode("\n",$lines),$headers);
    ftsa_cielo_log(['event'=>'email_denied_sent','order'=>$order->get_id(),'to'=>$to,'info'=>$info]);
}

/* ============================================================
 *  WEBHOOK / RETORNO – Loja5
 * ============================================================ */
add_action('wp_ajax_nopriv_notificacao_loja5_woo_cielo_integrado','ftsa_cielo_hook_notificacao',1);
add_action('wp_ajax_notificacao_loja5_woo_cielo_integrado','ftsa_cielo_hook_notificacao',1);
function ftsa_cielo_hook_notificacao(){
    $payload=$_POST;
    ftsa_cielo_log(['event'=>'webhook_notificacao_recebido','payload'=>$payload,'server'=>['REMOTE_ADDR'=>$_SERVER['REMOTE_ADDR']??'','REQUEST_URI'=>$_SERVER['REQUEST_URI']??'']]);

    $order_id=0;
    if(!empty($payload['order_number']) && is_numeric($payload['order_number'])){
        $order_id=absint($payload['order_number']);
    } elseif(!empty($payload['checkout_cielo_order_number'])){
        $orders=wc_get_orders(['limit'=>1,'meta_key'=>'cielo_checkout_order','meta_value'=>sanitize_text_field($payload['checkout_cielo_order_number']),'meta_compare'=>'=']);
        if($orders)$order_id=$orders[0]->get_id();
    }

    if($order_id){
        ftsa_cielo_store_order_meta_and_note($order_id,$payload,'cielo_');
        if(!empty($payload['checkout_cielo_order_number'])){ update_post_meta($order_id,'cielo_sentorder_id',sanitize_text_field($payload['checkout_cielo_order_number'])); }
        elseif(!empty($payload['OrderNumber']) && !ctype_digit((string)$payload['OrderNumber'])){ update_post_meta($order_id,'cielo_sentorder_id',sanitize_text_field($payload['OrderNumber'])); }

        $method=isset($payload['payment_method_type'])?intval($payload['payment_method_type']):0;
        if(FTSA_CIELO_ENABLE_PIX_CAPTURE && $method===4){ ftsa_cielo_capture_pix_ids_checkout_only($order_id); }
    } else {
        ftsa_cielo_log(['event'=>'warning','msg'=>'Não foi possível associar a notificação a um pedido Woo.','payload_keys'=>array_keys($payload)]);
    }
}

add_action('wp_ajax_nopriv_retorno_loja5_woo_cielo_integrado','ftsa_cielo_hook_retorno',1);
add_action('wp_ajax_retorno_loja5_woo_cielo_integrado','ftsa_cielo_hook_retorno',1);
function ftsa_cielo_hook_retorno(){
    $payload=array_merge($_GET,$_POST);
    ftsa_cielo_log(['event'=>'returnurl_recebido','payload'=>$payload]);

    if(!empty($payload['id']) && is_numeric($payload['id'])){
        $order_id=absint($payload['id']);
        ftsa_cielo_store_order_meta_and_note($order_id,$payload,'cielo_return_');

        if(!empty($payload['checkout_cielo_order_number'])){ update_post_meta($order_id,'cielo_sentorder_id',sanitize_text_field($payload['checkout_cielo_order_number'])); }
        elseif(!empty($payload['OrderNumber']) && !ctype_digit((string)$payload['OrderNumber'])){ update_post_meta($order_id,'cielo_sentorder_id',sanitize_text_field($payload['OrderNumber'])); }

        $method=isset($payload['payment_method_type'])?intval($payload['payment_method_type']):0;
        if(FTSA_CIELO_ENABLE_PIX_CAPTURE && $method===4){ ftsa_cielo_capture_pix_ids_checkout_only($order_id); }
    }
}

/* ============================================================
 *  SINALIZAR CHECKOUT CRIADO
 * ============================================================ */
add_action('woocommerce_new_order',function($order_id){
    $order=wc_get_order($order_id); if(!$order)return;
    $checkout_url=$order->get_meta('_cielo_checkout_url');
    if($checkout_url){ ftsa_cielo_log(['event'=>'checkout_criado_detectado','order_id'=>$order_id,'checkout_url'=>$checkout_url]); }
},20);

/* ============================================================
 *  COLUNAS + BUSCA
 * ============================================================ */
add_filter('manage_edit-shop_order_columns',function($c){
    $c['cielo_status']=__('Cielo','ftsa');$c['cielo_sentorder']=__('Nº Cielo','ftsa');return $c;
},20);
add_action('manage_shop_order_posts_custom_column',function($col,$post_id){
    if($col==='cielo_status'){
        $o=wc_get_order($post_id); if(!$o){echo'—';return;}
        $sn=$o->get_meta('cielo_payment_status');$tid=$o->get_meta('cielo_tid');$rc=$o->get_meta('cielo_return_code');
        $lbl=$sn?ftsa_cielo_status_label($sn):'-';
        printf('<span title="%s">%s</span> | TID:%s | RC:%s',esc_attr('Status traduzido automaticamente'),esc_html($lbl),esc_html($tid?:'-'),esc_html($rc?:'-'));
    } elseif($col==='cielo_sentorder'){
        $o=wc_get_order($post_id); if(!$o){echo'—';return;}
        $sent=$o->get_meta('cielo_sentorder_id'); echo esc_html($sent?:'—');
    }
},10,2);
add_filter('woocommerce_shop_order_list_table_columns',function($c){
    $c['cielo_status']=__('Cielo','ftsa');$c['cielo_sentorder']=__('Nº Cielo','ftsa');return $c;
},20);
add_action('woocommerce_shop_order_list_table_custom_column',function($col,$order){
    if(!$order)return;
    $is_wc_order = is_object($order) && method_exists($order,'get_meta');
    if($col==='cielo_status' && $is_wc_order){
        $sn=$order->get_meta('cielo_payment_status');$tid=$order->get_meta('cielo_tid');$rc=$order->get_meta('cielo_return_code');
        $lbl=$sn?ftsa_cielo_status_label($sn):'-';
        printf('<span title="%s">%s</span> | TID:%s | RC:%s',esc_attr('Status traduzido automaticamente'),esc_html($lbl),esc_html($tid?:'-'),esc_html($rc?:'-'));
    } elseif($col==='cielo_sentorder' && $is_wc_order){
        $sent=$order->get_meta('cielo_sentorder_id'); echo esc_html($sent?:'—');
    }
},10,2);
add_filter('woocommerce_shop_order_search_fields',function($f){$f[]='cielo_sentorder_id';return $f;},10,1);
add_filter('woocommerce_orders_table_search_query_meta_keys',function($k){$k[]='cielo_sentorder_id';return $k;},10,1);
add_action('pre_get_posts',function($q){
    if(!is_admin()||!$q->is_main_query())return; if($q->get('post_type')!=='shop_order')return;
    $s=$q->get('s'); if(!$s)return; $t=trim($s); if(strlen($t)<4)return;
    $mq=(array)$q->get('meta_query'); $mq[] = ['key'=>'cielo_sentorder_id','value'=>$t,'compare'=>'LIKE']; $q->set('meta_query',$mq);
});

/* ============================================================
 *  BLOCO “Cielo Pix” no pedido (Admin)
 * ============================================================ */
add_action('woocommerce_admin_order_data_after_order_details',function($o){
    if(!is_object($o) || !method_exists($o,'get_meta'))return;
    $pid=$o->get_meta('cielo_payment_id'); $tx=$o->get_meta('cielo_pix_txid'); $e2e=$o->get_meta('cielo_pix_endtoendid'); $sent=$o->get_meta('cielo_sentorder_id');
    if(!$pid&&!$tx&&!$e2e&&!$sent)return;
    echo '<div class="order_data_column"><h3>Cielo Pix</h3>';
    if($sent)echo '<p><strong>Nº pedido (Cielo):</strong> '.esc_html($sent).'</p>';
    if($tx)  echo '<p><strong>TxID:</strong> '.esc_html($tx).'</p>';
    if($e2e) echo '<p><strong>EndToEndId:</strong> '.esc_html($e2e).'</p>';
    if($pid) echo '<p><strong>PaymentId:</strong> '.esc_html($pid).'</p>';
    echo '</div>';
});

/* ============================================================
 *  CHECKOUT CIELO (OAuth + Orders) – **Checkout-only**
 * ============================================================ */

/** Endpoints de base (prod/sbx) — ambos usam o host cieloecommerce.cielo.com.br */
function ftsa_checkout_bases($sandbox=false){
    $host = 'https://cieloecommerce.cielo.com.br';
    return [
        'base'   => $host,
        'token'  => $host.'/api/public/v2/token',
        'orders' => $host.'/api/public/v2/orders',
    ];
}

/** Resolve credenciais do Checkout (client_id / client_secret) + sandbox */
function ftsa_checkout_resolve_credentials(){
    $cid = defined('CIELO_CHECKOUT_CLIENT_ID')     ? trim((string)CIELO_CHECKOUT_CLIENT_ID)     : '';
    $sec = defined('CIELO_CHECKOUT_CLIENT_SECRET') ? trim((string)CIELO_CHECKOUT_CLIENT_SECRET) : '';
    $sbx = defined('FTSA_CHECKOUT_SANDBOX')        ? (bool)FTSA_CHECKOUT_SANDBOX : false;
    if ($cid && $sec) return [$cid, $sec, $sbx, 'wp-config(Checkout)'];

    $opt = get_option('woocommerce_cielo_checkout_ftsa_settings', []);
    if (is_array($opt)) {
        $cid2 = isset($opt['client_id'])     ? trim((string)$opt['client_id'])     : '';
        $sec2 = isset($opt['client_secret']) ? trim((string)$opt['client_secret']) : '';
        $sbx2 = false;
        foreach ($opt as $k=>$v){
            if (preg_match('/(sandbox|homolog|test)/i', (string)$k)){
                $vv = strtolower((string)$v);
                if ($vv==='1'||$vv==='yes'||$vv==='on'||$vv==='sandbox'||$vv==='homolog'||$vv==='test'){ $sbx2 = true; }
            }
        }
        if ($cid2 && $sec2) return [$cid2, $sec2, $sbx2, 'gateway:woocommerce_cielo_checkout_ftsa_settings'];
    }

    return ['', '', false, 'none'];
}

/** Token (access_token) — Basic Auth (ClientId:ClientSecret) */
function ftsa_checkout_get_token(&$src_out=null){
    list($cid,$sec,$sbx,$src) = ftsa_checkout_resolve_credentials();
    $src_out = $src;
    if (!$cid || !$sec) {
        ftsa_cielo_log(['event'=>'checkout_token_skipped','reason'=>'missing_client_id_secret','src'=>$src]);
        return ['', null];
    }
    $u = ftsa_checkout_bases($sbx)['token'];

    $auth = base64_encode($cid.':'.$sec);
    $args = [
        'headers' => [
            'Accept'        => 'application/json',
            'Authorization' => 'Basic '.$auth,
        ],
        'timeout'   => defined('FTSA_CIELO_HTTP_TIMEOUT') ? FTSA_CIELO_HTTP_TIMEOUT : 20,
        'sslverify' => true,
    ];

    $r = wp_remote_post($u, $args);
    if (is_wp_error($r)){
        ftsa_cielo_log(['event'=>'checkout_token_error','err'=>$r->get_error_message()]);
        return ['', 'http_error'];
    }
    $code = wp_remote_retrieve_response_code($r);
    $body = wp_remote_retrieve_body($r);
    $js   = json_decode($body,true);

    if ($code>=200 && $code<300 && is_array($js) && !empty($js['access_token'])){
        ftsa_cielo_log(['event'=>'checkout_token_ok','src'=>$src,'masked'=>substr($js['access_token'],0,6).'…']);
        return [$js['access_token'], null];
    }
    ftsa_cielo_log(['event'=>'checkout_token_bad','code'=>$code,'body'=>substr((string)$body,0,400)]);
    return ['', 'bad_status_'.$code];
}

/** GET no endpoint de Orders do Checkout + fallback por merchantOrderNumber (query) */
function ftsa_checkout_fetch_order($needle, $token, $sbx=false){
    if (!$needle) return [null,'empty_needle'];

    $B = ftsa_checkout_bases($sbx);
    $H = [
        'Authorization' => 'Bearer '.$token,
        'Accept'        => 'application/json',
    ];
    $baseOrders = $B['orders'];

    // 1) Tenta direto: /orders/{checkout_cielo_order_number}
    $urls = [ trailingslashit($baseOrders).rawurlencode($needle) ];

    // 2) Tenta variações em query (inclui merchantOrderNumber)
    foreach (['orderNumber','OrderNumber','order_number','CheckoutOrderNumber','merchantOrderNumber'] as $q){
        $urls[] = $baseOrders.'?'.$q.'='.rawurlencode($needle);
    }

    foreach ($urls as $u){
        $r = wp_remote_get($u, ['headers'=>$H,'timeout'=>defined('FTSA_CIELO_HTTP_TIMEOUT') ? FTSA_CIELO_HTTP_TIMEOUT : 20, 'sslverify'=>true]);
        if (is_wp_error($r)) continue;
        $code = wp_remote_retrieve_response_code($r);
        $body = wp_remote_retrieve_body($r);
        $js   = json_decode($body,true);
        if ($code>=200 && $code<300 && !empty($js)){
            ftsa_cielo_log(['event'=>'checkout_order_found','url_tried'=>$u,'keys'=>is_array($js)?array_slice(array_keys($js),0,10):[]]);
            return [$js, null];
        }
    }

    return [null,'not_found'];
}

/** Extratores do payload do Checkout */
function ftsa_checkout_extract_core($d){
    $out = ['payment_id'=>'','txid'=>'','endtoendid'=>'','sentorder'=>''];
    if (!is_array($d)){
        $t = json_decode((string)$d,true);
        if (json_last_error()===JSON_ERROR_NONE) $d = (array)$t;
    }
    foreach (['CheckoutOrderNumber','checkoutOrderNumber','orderNumber','number','OrderNumber'] as $k){
        if (!empty($d[$k])) { $v=trim((string)$d[$k]); if($v!=='') { $out['sentorder']=$v; break; } }
    }
    $payments = [];
    if (!empty($d['payments']) && is_array($d['payments'])) $payments = $d['payments'];
    if (!$payments && !empty($d['payment']) && is_array($d['payment'])) $payments = [$d['payment']];

    foreach ($payments as $p){
        foreach (['paymentId','PaymentId','id','Id'] as $k){
            if (!empty($p[$k])) { $out['payment_id'] = (string)$p[$k]; break; }
        }
        if (!$out['payment_id'] && !empty($p['payment']) && is_array($p['payment'])){
            foreach (['paymentId','PaymentId','id','Id'] as $k){
                if (!empty($p['payment'][$k])) { $out['payment_id'] = (string)$p['payment'][$k]; break; }
            }
        }
        foreach ([['acquirerOrderId','AcquirerOrderId'],['endToEndId','EndToEndId']] as $pair){
            foreach ($pair as $k){
                if (!empty($p[$k])) { if(stripos($k,'acquirer')!==false){$out['txid']=(string)$p[$k];} else {$out['endtoendid']=(string)$p[$k];} }
                if (!empty($p['payment'][$k])) { if(stripos($k,'acquirer')!==false){$out['txid']=(string)$p['payment'][$k];} else {$out['endtoendid']=(string)$p['payment'][$k];} }
            }
        }
        if ($out['payment_id'] && ($out['txid']||$out['endtoendid'])) break;
    }
    return $out;
}

/** Extrai candidatos de ID do pedido do Checkout a partir do pedido Woo */
function ftsa_cielo_checkout_candidates($o){
    $c = [];
    $add = function($v) use (&$c){
        $v = trim((string)$v);
        if($v !== '' && !in_array($v,$c,true)) $c[] = $v;
    };

    // Metas comuns usadas por plugins/integrações
    $metas = [
        'cielo_sentorder_id',
        'cielo_checkout_order',
        'cielo_checkout_order_number',
        'checkout_cielo_order_number',
        '_cielo_checkout_order_number',
        '_cielo_checkout_id',
    ];
    foreach ($metas as $m){ $add( method_exists($o,'get_meta') ? $o->get_meta($m) : '' ); }

    // Extrai do URL salvo (/_cielo_checkout_url), se existir: .../orders/{ID}
    $url = method_exists($o,'get_meta') ? $o->get_meta('_cielo_checkout_url') : '';
    if($url && preg_match('~/orders/([A-Za-z0-9]+)~', (string)$url, $mm)){ $add($mm[1]); }

    // MerchantOrderNumber (Woo costuma mandar order_number)
    $add( (string)$o->get_order_number() );
    $add( (string)$o->get_id() );

    return array_values(array_filter($c));
}

/** Fluxo de captura via Checkout (retorna true se conseguiu preencher algo) */
function ftsa_checkout_capture_for_order($order_id){
    $o = wc_get_order($order_id); if(!$o) return false;

    $src = ''; list($token,$err) = ftsa_checkout_get_token($src);
    if(!$token){
        ftsa_cielo_log(['event'=>'checkout_capture_skip_token','order'=>$order_id,'err'=>$err,'src'=>$src]);
        return false;
    }

    $cands = ftsa_cielo_checkout_candidates($o);
    list($_cid,$_sec,$sandbox,$_src) = ftsa_checkout_resolve_credentials();

    foreach ($cands as $needle){
        list($pay, $e1) = ftsa_checkout_fetch_order($needle, $token, $sandbox);
        if ($e1 || !$pay) continue;

        $x = ftsa_checkout_extract_core($pay);
        $changed = false;

        if ($x['sentorder'])   { $o->update_meta_data('cielo_sentorder_id', $x['sentorder']); $changed = true; }
        if ($x['txid'])        { $o->update_meta_data('cielo_pix_txid',     $x['txid']);      $changed = true; }
        if ($x['endtoendid'])  { $o->update_meta_data('cielo_pix_endtoendid',$x['endtoendid']); $changed = true; }
        if ($x['payment_id'])  { $o->update_meta_data('cielo_payment_id',   $x['payment_id']);  $changed = true; }

        if ($changed){
            $o->save();
            $note_parts=[];
            if($x['sentorder'])   $note_parts[]='Nº pedido (Cielo): '.$x['sentorder'];
            if($x['txid'])        $note_parts[]='TxID: '.$x['txid'];
            if($x['endtoendid'])  $note_parts[]='EndToEndId: '.$x['endtoendid'];
            if($x['payment_id'])  $note_parts[]='PaymentId: '.$x['payment_id'];
            if($note_parts) $o->add_order_note('Checkout Cielo (capturado): '.implode(' | ',$note_parts));
            ftsa_cielo_log(['event'=>'checkout_capture_ok','order'=>$order_id,'sentorder'=>$x['sentorder'],'txid'=>$x['txid'],'endtoend'=>$x['endtoendid'],'paymentId'=>$x['payment_id']]);
            return true;
        }
        ftsa_cielo_log(['event'=>'checkout_capture_found_but_empty','order'=>$order_id,'needle'=>$needle,'keys'=>array_slice(array_keys((array)$pay),0,10)]);
    }

    ftsa_cielo_log(['event'=>'checkout_capture_not_found','order'=>$order_id]);
    return false;
}

/* ============================================================
 *  CAPTURA AUTOMÁTICA (Pix) – **Checkout-only**
 * ============================================================ */
function ftsa_cielo_capture_pix_ids_checkout_only($order_id){
    if(!FTSA_CIELO_ENABLE_PIX_CAPTURE)return;
    $o=wc_get_order($order_id); if(!$o)return;

    if($o->get_meta('cielo_pix_endtoendid') && $o->get_meta('cielo_sentorder_id'))return;

    $lock='cielo_pix_capture_lock_'.$order_id; if(get_transient($lock))return; set_transient($lock,1,120);

    // Apenas via Checkout (sem fallback API E-commerce)
    $ok = ftsa_checkout_capture_for_order($order_id);
    if(!$ok){
        ftsa_cielo_log(['event'=>'pix_capture_checkout_only_miss','order'=>$order_id,'hint'=>'Verifique client_id/secret e se o pedido existe no Checkout.']);
    }
}

/* ============================================================
 *  FERRAMENTA: BACKFILL PIX (Checkout-only) + safe date handling
 * ============================================================ */
add_action('admin_menu',function(){
    add_management_page('Cielo Backfill Pix','Cielo Backfill Pix','manage_woocommerce','ftsa-cielo-backfill','ftsa_cielo_backfill_page');
});

function ftsa_cielo_order_in_range($order, $after, $before){
    if(!is_object($order) || !method_exists($order,'get_date_created')) return false;
    $dt = $order->get_date_created(); if(!$dt) return false;
    $ts = $dt->getTimestamp();
    if($after){  $a_ts = strtotime($after.' 00:00:00'); if($a_ts && $ts < $a_ts) return false; }
    if($before){ $b_ts = strtotime($before.' 23:59:59'); if($b_ts && $ts > $b_ts) return false; }
    return true;
}

function ftsa_cielo_backfill_page(){
    if(!current_user_can('manage_woocommerce'))return;

    $after_raw  = isset($_POST['ftsa_after'])  ? sanitize_text_field($_POST['ftsa_after'])  : '';
    $before_raw = isset($_POST['ftsa_before']) ? sanitize_text_field($_POST['ftsa_before']) : '';
    $after  = ftsa_cielo_normalize_date($after_raw);
    $before = ftsa_cielo_normalize_date($before_raw);

    $order  = isset($_POST['ftsa_order']) ? strtoupper(sanitize_text_field($_POST['ftsa_order'])) : 'DESC';
    if(!in_array($order,['ASC','DESC'],true)) $order = 'DESC';
    $batch  = isset($_POST['ftsa_batch']) ? max(1,intval($_POST['ftsa_batch'])) : FTSA_CIELO_BACKFILL_BATCH;

    $only_pix = !empty($_POST['ftsa_only_pix']);
    $force    = !empty($_POST['ftsa_force']);
    $diag     = !empty($_POST['ftsa_diag']);

    $all_statuses = wc_get_order_statuses();
    $chosen = isset($_POST['ftsa_status']) && is_array($_POST['ftsa_status']) ? array_map('sanitize_text_field', $_POST['ftsa_status']) : ['processing','completed'];
    $chosen = array_values(array_filter(array_map(function($s){$s=trim(str_replace('wc-','',$s));return $s!==''?$s:null;}, $chosen)));

    echo '<div class="wrap"><h1>Cielo Backfill Pix</h1>';
    echo '<p>Preenche <strong>TxID</strong>, <strong>EndToEndId</strong> e <strong>Nº Cielo</strong> para pedidos pagos com Pix (via <em>Checkout</em> apenas). Você pode filtrar por <strong>Data de Criação do Pedido</strong>. Aceita <code>YYYY-MM-DD</code> ou <code>DD/MM/YYYY</code>.</p>';

    if(isset($_POST['ftsa_do_backfill']) || $diag){
        check_admin_referer('ftsa_cielo_backfill');

        if($diag){
            $tested = ftsa_cielo_backfill_batch($batch, [
                'after'    => $after,'before'=>$before,'order'=>$order,
                'only_pix' => $only_pix,'force'=>$force,'statuses'=>$chosen,'dry_run'=>true
            ]);
            printf('<div class="updated notice"><p><strong>Diagnóstico:</strong> candidatos estimados: <strong>%d</strong> (veja também o <code>debug.log</code> para a amostra de IDs).</p></div>', $tested);
        } else {
            $processed = ftsa_cielo_backfill_batch($batch, [
                'after'    => $after,'before'=>$before,'order'=>$order,
                'only_pix' => $only_pix,'force'=>$force,'statuses'=>$chosen
            ]);
            printf('<div class="updated notice"><p>Processados <strong>%d</strong> pedidos neste lote.</p></div>', $processed);
        }
    }

    echo '<form method="post" style="margin-top:12px;display:grid;gap:10px;max-width:820px;">';
    wp_nonce_field('ftsa_cielo_backfill');

    echo '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">';
    echo '<label for="ftsa_after"><strong>Data inicial:</strong></label>';
    echo '<input type="text" placeholder="YYYY-MM-DD ou DD/MM/YYYY" name="ftsa_after" id="ftsa_after" value="'.esc_attr($after_raw).'">';
    echo '<label for="ftsa_before"><strong>Data final:</strong></label>';
    echo '<input type="text" placeholder="YYYY-MM-DD ou DD/MM/YYYY" name="ftsa_before" id="ftsa_before" value="'.esc_attr($before_raw).'">';
    echo '</div>';

    echo '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">';
    echo '<label for="ftsa_order"><strong>Ordem:</strong></label>';
    echo '<select name="ftsa_order" id="ftsa_order">';
    echo '<option value="ASC"'.selected($order,'ASC',false).'>Mais antigo primeiro (ASC)</option>';
    echo '<option value="DESC"'.selected($order,'DESC',false).'>Mais recente primeiro (DESC)</option>';
    echo '</select>';
    echo '<label for="ftsa_batch"><strong>Tamanho do lote:</strong></label>';
    echo '<input type="number" min="1" step="1" name="ftsa_batch" id="ftsa_batch" value="'.esc_attr($batch).'">';
    echo '</div>';

    echo '<fieldset style="border:1px solid #ddd;padding:10px;border-radius:8px;"><legend><strong>Status dos pedidos</strong></legend>';
    echo '<div style="display:flex;gap:18px;flex-wrap:wrap;">';
    foreach($all_statuses as $wc_key => $label){
        $short = str_replace('wc-','',$wc_key);
        echo '<label><input type="checkbox" name="ftsa_status[]" value="'.esc_attr($short).'" '.checked(in_array($short,$chosen,true), true, false).'> '.esc_html($label).'</label>';
    }
    echo '</div></fieldset>';

    echo '<div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;margin-top:6px">';
    echo '<label><input type="checkbox" name="ftsa_only_pix" value="1" '.checked($only_pix,true,false).'> Somente pedidos Pix</label>';
    echo '<label><input type="checkbox" name="ftsa_force" value="1" '.checked($force,true,false).'> Forçar reprocessar (mesmo já preenchidos)</label>';
    echo '<label><input type="checkbox" name="ftsa_diag" value="1" '.checked($diag,true,false).'> Executar modo Diagnóstico (não altera nada)</label>';
    echo '</div>';

    echo '<div style="display:flex;gap:12px;margin-top:8px">';
    submit_button('Processar próximos pedidos', 'primary', 'ftsa_do_backfill', false);
    submit_button('Rodar Diagnóstico', 'secondary', 'ftsa_do_diag', false);
    echo '</div>';

    echo '<p class="description">Dica: se retornar 0, rode o <strong>Diagnóstico</strong> para ver no <code>debug.log</code> uma amostra dos IDs considerados e ajustar os filtros.</p>';
    echo '</form></div>';
}

function ftsa_cielo_backfill_batch($limit=50, $opts=[]){
    $after    = isset($opts['after'])    ? trim($opts['after'])    : '';
    $before   = isset($opts['before'])   ? trim($opts['before'])   : '';
    $order    = isset($opts['order'])    ? strtoupper($opts['order']) : 'DESC';
    $only_pix = !empty($opts['only_pix']);
    $force    = !empty($opts['force']);
    $statuses = isset($opts['statuses']) && is_array($opts['statuses']) ? $opts['statuses'] : ['processing','completed'];
    $dry_run  = !empty($opts['dry_run']);

    if(!in_array($order,['ASC','DESC'],true)) $order='DESC';

    // SAFE: não passar date_created (evita exceptions de strtotime(array))
    $args = ['status'=>$statuses,'limit'=>max($limit*2, 200),'orderby'=>'date','order'=>$order];
    try { $orders = wc_get_orders($args); }
    catch (\Throwable $e) {
        ftsa_cielo_log(['event'=>'backfill_wc_get_orders_exception_safe','message'=>$e->getMessage(),'args'=>$args]);
        $orders = [];
    }

    // Filtro de data manual (em memória)
    if($orders && ($after || $before)){
        $orders = array_values(array_filter($orders, function($o) use ($after,$before){ return ftsa_cielo_order_in_range($o,$after,$before); }));
    }

    // Ordenação final conforme solicitado
    if($orders){
        usort($orders, function($a,$b){
            $ta = method_exists($a,'get_date_created') && $a->get_date_created() ? $a->get_date_created()->getTimestamp() : 0;
            $tb = method_exists($b,'get_date_created') && $b->get_date_created() ? $b->get_date_created()->getTimestamp() : 0;
            return $tb <=> $ta; // DESC por padrão
        });
        if($order === 'ASC'){ $orders = array_reverse($orders); }
    }

    $sample = array_slice(array_map(function($o){ return $o->get_id(); }, $orders), 0, 20);
    ftsa_cielo_log(['event'=>'backfill_candidates','count'=>count($orders),'sample_ids'=>$sample,'only_pix'=>$only_pix,'force'=>$force,'statuses'=>$statuses,'after'=>$after,'before'=>$before]);

    $count = 0;
    foreach($orders as $o){
        if(!is_object($o) || !method_exists($o,'get_id')) continue;

        if($only_pix){
            $mt = intval($o->get_meta('cielo_payment_method_type'));
            $pm_id    = method_exists($o,'get_payment_method') ? (string)$o->get_payment_method() : '';
            $pm_title = method_exists($o,'get_payment_method_title') ? (string)$o->get_payment_method_title() : '';
            $looks_pix = false;
            if($mt === 4) $looks_pix = true;
            if(stripos($pm_id,'pix')!==false || stripos($pm_title,'pix')!==false) $looks_pix = true;
            if(!$looks_pix) continue;
        }

        if(!$force){
            $has_e2e  = (bool) $o->get_meta('cielo_pix_endtoendid');
            $has_sent = (bool) $o->get_meta('cielo_sentorder_id');
            if($has_e2e && $has_sent){ continue; }
        }

        if($dry_run){
            $count++;
        }else{
            ftsa_cielo_capture_pix_ids_checkout_only($o->get_id());
            $count++;
            if($count >= $limit) break;
        }
    }

    return $count;
}

/* ============================================================
 *  DIAGNÓSTICO DE CREDENCIAIS (com Varredura Estendida)
 * ============================================================ */
function ftsa_mask_guid($v){ $v=trim((string)$v); if($v==='')return''; return substr($v,0,8).'…'; }
function ftsa_mask_key($v){ $v=trim((string)$v); if($v==='')return''; return strlen($v)<=8 ? substr($v,0,2).'…'.substr($v,-2) : substr($v,0,4).'…'.substr($v,-4); }
function ftsa_bool_yn($b){ return $b ? 'Sim' : 'Não'; }
function ftsa_cielo_guess_is_sandbox($settings){
    $cands=['sandbox','testmode','ambiente','environment','mode'];
    foreach ($cands as $k){
        foreach ($settings as $key=>$val){
            if (preg_match('/'.$k.'/i',(string)$key)){
                $v=strtolower((string)$val);
                if ($v==='1'||$v==='yes'||$v==='on'||$v==='sandbox'||$v==='test'||$v==='homolog'){ return true; }
            }
        }
    }
    return false;
}
function ftsa_cielo_scan_gateways_for_credentials(){
    $out=[]; if ( ! class_exists('WC_Payment_Gateways') ) return $out;
    $gws = WC_Payment_Gateways::instance()->payment_gateways();
    foreach ((array)$gws as $gw){
        $id = isset($gw->id)?(string)$gw->id:''; $title = method_exists($gw,'get_title')?(string)$gw->get_title():(isset($gw->method_title)?(string)$gw->method_title:$id);
        if ($id==='') continue;
        $opt_key='woocommerce_'.$id.'_settings'; $settings=get_option($opt_key,[]);
        if(!is_array($settings))$settings=[];
        $looks_cielo = stripos($id,'cielo')!==false || stripos($title,'cielo')!==false;
        if (!$looks_cielo && !preg_grep('/cielo/i', array_keys($settings))) continue;

        // Checkout: procurar client_id/client_secret
        $client_id=''; $client_secret=''; $client_id_key=''; $client_secret_key='';
        foreach ($settings as $k=>$v){
            if($client_id==='' && preg_match('/client.*id/i',(string)$k)){ $client_id=trim((string)$v); $client_id_key=(string)$k; }
            if($client_secret==='' && preg_match('/client.*secret/i',(string)$k)){ $client_secret=trim((string)$v); $client_secret_key=(string)$k; }
        }
        $is_sandbox = ftsa_cielo_guess_is_sandbox($settings);

        $out[]=[
            'gateway_id'=>$id,'gateway_title'=>$title,'option_key'=>$opt_key,
            'client_id'=>$client_id,'client_secret'=>$client_secret,
            'client_id_key'=>$client_id_key,'client_secret_key'=>$client_secret_key,
            'is_sandbox'=>$is_sandbox,'settings_sample'=>array_slice(array_keys($settings),0,12)
        ];
    }
    return $out;
}
function ftsa_cielo_log_loja5_credentials_diag(){
    $gw_rows = ftsa_cielo_scan_gateways_for_credentials();

    $const_cid  = defined('CIELO_CHECKOUT_CLIENT_ID')     ? (string)CIELO_CHECKOUT_CLIENT_ID     : '';
    $const_csec = defined('CIELO_CHECKOUT_CLIENT_SECRET') ? (string)CIELO_CHECKOUT_CLIENT_SECRET : '';

    $log=['event'=>'cielo_loja5_credentials',
        'wp_config'=>[
            'client_id_masked'=>ftsa_mask_guid($const_cid),
            'client_secret_masked'=>ftsa_mask_key($const_csec),
            'endpoint'=>'Checkout OAuth'
        ],
        'resolver'=>[
            'source'=>'wp-config(Checkout)',
            'client_id_masked'=>ftsa_mask_guid($const_cid),
            'client_secret_masked'=>ftsa_mask_key($const_csec)
        ],
        'gateways'=>[]
    ];

    foreach ($gw_rows as $row){
        $match_id  = ($const_cid  && $row['client_id']    && strcasecmp(trim($const_cid),trim($row['client_id']))===0);
        $match_key = ($const_csec && $row['client_secret']&& trim($const_csec)===trim($row['client_secret']));
        $log['gateways'][]=[
            'id'=>$row['gateway_id'],'title'=>$row['gateway_title'],'option_key'=>$row['option_key'],
            'client_id_masked'=>ftsa_mask_guid($row['client_id']),
            'client_secret_masked'=>ftsa_mask_key($row['client_secret']),
            'client_id_option'=>$row['client_id_key'],
            'client_secret_option'=>$row['client_secret_key'],
            'is_sandbox'=>$row['is_sandbox'],'matches_wp_config'=>['id'=>$match_id,'key'=>$match_key],
            'settings_sample'=>$row['settings_sample'],
        ];
    }
    ftsa_cielo_log($log);
    return array($gw_rows, $const_cid, $const_csec);
}
function ftsa_cielo_diag_admin_page(){
    if ( ! current_user_can('manage_woocommerce') ) return;

    list($gw_rows, $const_cid, $const_csec) = ftsa_cielo_log_loja5_credentials_diag();

    echo '<div class="wrap"><h1>Cielo – Diagnóstico de Credenciais</h1>';
    echo '<p>Checkout-only: este diagnóstico mostra credenciais encontradas nos gateways do WooCommerce e as constantes do Checkout (OAuth) no <code>wp-config.php</code>.</p>';

    echo '<h2>wp-config.php (Checkout OAuth)</h2>';
    echo '<table class="widefat striped" style="max-width:900px"><tbody>';
    echo '<tr><td><strong>CIELO_CHECKOUT_CLIENT_ID</strong></td><td>'. esc_html( ftsa_mask_guid($const_cid) ) .'</td></tr>';
    echo '<tr><td><strong>CIELO_CHECKOUT_CLIENT_SECRET</strong></td><td>'. esc_html( ftsa_mask_key($const_csec) ) .'</td></tr>';
    echo '</tbody></table>';

    echo '<h2>Gateways WooCommerce (possíveis Loja5/Cielo)</h2>';
    if (!$gw_rows){
        echo '<p>Nenhum gateway que pareça “Cielo” foi detectado. Verifique se o plugin está ativo.</p></div>';
        return;
    }
    echo '<table class="widefat striped" style="max-width:1100px">';
    echo '<thead><tr><th>Gateway ID</th><th>Título</th><th>Client Id</th><th>Client Secret</th><th>Chaves (opção)</th><th>Sandbox?</th><th>Igual ao wp-config?</th></tr></thead><tbody>';
    foreach ($gw_rows as $row){
        $match_id  = ($const_cid  && $row['client_id']    && strcasecmp(trim($const_cid),trim($row['client_id']))===0);
        $match_key = ($const_csec && $row['client_secret']&& trim($const_csec)===trim($row['client_secret']));
        echo '<tr>';
        echo '<td>'.esc_html($row['gateway_id']).'</td>';
        echo '<td>'.esc_html($row['gateway_title']).'</td>';
        echo '<td>'.esc_html( ftsa_mask_guid($row['client_id']) ).'</td>';
        echo '<td>'.esc_html( ftsa_mask_key($row['client_secret']) ).'</td>';
        echo '<td><small>'.esc_html($row['client_id_key']).' / '.esc_html($row['client_secret_key']).'</small></td>';
        echo '<td>'.esc_html( ftsa_bool_yn($row['is_sandbox']) ).'</td>';
        echo '<td>'. ($match_id && $match_key ? '✅' : '❌') .'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<p style="margin-top:12px">Para o <strong>Checkout OAuth</strong>, ajuste <code>CIELO_CHECKOUT_CLIENT_ID</code> e <code>CIELO_CHECKOUT_CLIENT_SECRET</code> (produção vs. sandbox) e revalide. Se o log mostrar <em>invalid_client</em>, é credencial incorreta ou ambiente trocado.</p>';
    echo '</div>';
}
add_action('admin_menu', function(){
    add_management_page('Cielo – Diagnóstico de Credenciais','Cielo – Diagnóstico','manage_woocommerce','ftsa-cielo-diag','ftsa_cielo_diag_admin_page');
}, 20);

/* ============ Varredura Estendida (constantes + wp_options) ============ */
function ftsa_mask_any($v){ $v=trim((string)$v); if($v==='')return''; return strlen($v)<=6 ? substr($v,0,1).'…'.substr($v,-1) : substr($v,0,4).'…'.substr($v,-4); }
function ftsa_cielo_collect_constants_matches(){
    $out=[]; $consts=get_defined_constants(true); $all=[];
    foreach ($consts as $grp=>$arr){ $all=array_merge($all,(array)$arr); }
    foreach ($all as $k=>$v){
        if (preg_match('/(CIELO|MERCHANT|LOJA5|CHECKOUT)/i',(string)$k)) {
            $vs=is_scalar($v)?(string)$v:json_encode($v);
            $out[]=['name'=>$k,'value_masked'=>ftsa_mask_any($vs)];
        }
    }
    return $out;
}
function ftsa_cielo_extract_kv_candidates($arr,$prefix=''){
    $hits=[];
    foreach ((array)$arr as $k=>$v){
        $path=$prefix?$prefix.'.'.$k:$k;
        if (is_array($v)||is_object($v)){ $hits=array_merge($hits,ftsa_cielo_extract_kv_candidates((array)$v,$path)); }
        else{
            if (preg_match('/merchant.*id/i',(string)$k) || preg_match('/merchant.*key/i',(string)$k) || preg_match('/client.*id/i',(string)$k) || preg_match('/client.*secret/i',(string)$k)){
                $hits[]=['key'=>$path,'value_masked'=>ftsa_mask_any((string)$v)];
            }
        }
    }
    return $hits;
}
function ftsa_cielo_scan_options_rows(){
    global $wpdb;
    $like=['%cielo%','%loja5%','%checkout%'];
    $sql="SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s LIMIT 200";
    $rows=$wpdb->get_results($wpdb->prepare($sql,$like[0],$like[1],$like[2]),ARRAY_A);
    $out=[];
    foreach ((array)$rows as $r){
        $name=$r['option_name']; $val=maybe_unserialize($r['option_value']); $hits=[];
        if(is_array($val)){ $hits=ftsa_cielo_extract_kv_candidates($val); }
        else{
            $j=json_decode((string)$val,true);
            if(is_array($j)){ $hits=ftsa_cielo_extract_kv_candidates($j); }
            else{
                if(preg_match('/merchant.*id/i',(string)$val) || preg_match('/merchant.*key/i',(string)$val) || preg_match('/client.*id/i',(string)$val) || preg_match('/client.*secret/i',(string)$val)){
                    $hits[]=['key'=>'(string)','value_masked'=>ftsa_mask_any((string)$val)];
                }
            }
        }
        $out[]=['option_name'=>$name,'hits'=>$hits,'sample_keys'=>is_array($val)?array_slice(array_keys($val),0,8):[]];
    }
    return $out;
}
function ftsa_cielo_diag_render_scans(){
    $consts=ftsa_cielo_collect_constants_matches();
    ftsa_cielo_log(['event'=>'cielo_diag_constants','count'=>count($consts),'consts'=>$consts]);

    echo '<h2>Constantes relacionadas</h2>';
    if (!$consts){ echo '<p>Nenhuma constante suspeita encontrada.</p>'; }
    else{
        echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Nome</th><th>Valor (mascarado)</th></tr></thead><tbody>';
        foreach ($consts as $c){
            echo '<tr><td>'.esc_html($c['name']).'</td><td>'.esc_html($c['value_masked']).'</td></tr>';
        }
        echo '</tbody></table>';
    }

    $rows=ftsa_cielo_scan_options_rows(); $log_rows=[];
    foreach ($rows as $r){ if(!empty($r['hits'])){ $log_rows[]=['option_name'=>$r['option_name'],'hits'=>$r['hits'],'sample_keys'=>$r['sample_keys']]; } }
    ftsa_cielo_log(['event'=>'cielo_diag_options','matches'=>$log_rows]);

    echo '<h2>wp_options contendo “cielo / loja5 / checkout”</h2>';
    if (!$rows){ echo '<p>Nada encontrado.</p>'; }
    else{
        echo '<table class="widefat striped"><thead><tr><th>option_name</th><th>Possíveis credenciais (mascaradas)</th><th>Amostra de chaves</th></tr></thead><tbody>';
        foreach ($rows as $r){
            echo '<tr>';
            echo '<td><code>'.esc_html($r['option_name']).'</code></td>';
            if (!empty($r['hits'])){
                echo '<td><ul style="margin:0;padding-left:18px">';
                foreach ($r['hits'] as $h){ echo '<li><strong>'.esc_html($h['key']).'</strong>: '.esc_html($h['value_masked']).'</li>'; }
                echo '</ul></td>';
            } else { echo '<td>—</td>'; }
            echo '<td><small>'.esc_html( implode(', ',$r['sample_keys']) ).'</small></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<p class="description">Dica: no Checkout OAuth, ajuste <code>CIELO_CHECKOUT_CLIENT_ID</code>/<code>_SECRET</code> corretos. Se o log exibir <em>checkout_token_bad invalid_client</em>, é credencial incorreta ou ambiente trocado (sandbox vs. produção).</p>';
}
add_action('admin_menu', function(){
    add_action('load-tools_page_ftsa-cielo-diag', function(){
        add_action('admin_print_footer_scripts', function(){
            echo '<div class="wrap" style="margin-top:24px">';
            echo '<hr/><h1>Varredura Estendida</h1>';
            ftsa_cielo_diag_render_scans();
            echo '</div>';
        });
    });
}, 30);

?>

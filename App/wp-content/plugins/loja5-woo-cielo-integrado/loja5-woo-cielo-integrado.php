<?php
/*
Plugin Name: Checkout Cielo REST - Loja5
Description: Integra&ccedil;&atilde;o ao Checkout Cielo via REST.
Version: 3.0
Author: Bruno Alencar
Author URI: http://loja5.com.br/
Copyright: © 2009-2018 Loja5.
License: Commercial
*/

//define a pasta do modulo
define('CIELO_INTEGRADO_WOO_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ));

//atalhos
function plugin_action_links_cielo_integrado( $links ) {
    $plugin_links = array();
    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
        $plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=loja5_woo_cielo_integrado' ) ) . '">' . __( 'Configura Checkout Cielo', 'loja5-woo-cielo-webservice' ) . '</a>';
    } else {
        $plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_loja5_woo_cielo_integrado' ) ) . '">' . __( 'Configura Checkout Cielo', 'loja5-woo-cielo-webservice' ) . '</a>';
    }
    return array_merge( $plugin_links, $links );
}
if(is_admin()) {
    add_filter('plugin_action_links_'.plugin_basename( __FILE__ ),'plugin_action_links_cielo_integrado');
}

//funcao de inicializacao
function loja5_woo_cielo_integrado_init() {
	
	//chama as classes do modulo
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	if ( !class_exists( 'WC_Gateway_Loja5_Woo_Cielo_Integrado' ) ){
        include_once(CIELO_INTEGRADO_WOO_PATH.'/classes/class.loja5.php' );
        include_once(CIELO_INTEGRADO_WOO_PATH.'/classes/class.cielo_integrado.php' );
	}
	
	//class validar cpf/cnpj
	if ( !class_exists( 'ValidaCPFCNPJ' ) ){
        include_once(CIELO_INTEGRADO_WOO_PATH.'/classes/class.fiscal.php' );
	}
	
	//adiciona o plugin ao woocommerce
	function woocommerce_add_loja5_woo_cielo_integrado($methods) {
        $methods[] = 'WC_Gateway_Loja5_Woo_Cielo_Integrado';
        return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_loja5_woo_cielo_integrado' );
}

//inicializa o modulo no wordpress
add_action('plugins_loaded', 'loja5_woo_cielo_integrado_init', 0);

//retorno cielo integrado
add_action( 'wp_ajax_retorno_loja5_woo_cielo_integrado', 'retorno_loja5_woo_cielo_integrado');
add_action( 'wp_ajax_nopriv_retorno_loja5_woo_cielo_integrado','retorno_loja5_woo_cielo_integrado');
function retorno_loja5_woo_cielo_integrado(){
	//api
    $api = new WC_Gateway_Loja5_Woo_Cielo_Integrado();
	//pega o pedido
	if(isset($_GET['id'])){
		$order = wc_get_order((int)($_GET['id']));
		$id = $order->get_id();
		$link = add_query_arg(array('pedido'=>$id,'hash'=>md5($id.$id)),$api->get_return_url( $order ));
		wp_redirect($link);
		exit;
	}else{
		$link = get_permalink( get_option('woocommerce_view_order_page_id') );
		wp_redirect($link);
		exit;
	}
}

//status cielo integrado
add_action( 'wp_ajax_status_loja5_woo_cielo_integrado', 'status_loja5_woo_cielo_integrado');
add_action( 'wp_ajax_nopriv_status_loja5_woo_cielo_integrado','status_loja5_woo_cielo_integrado');
function status_loja5_woo_cielo_integrado(){
	//api
    $api = new WC_Gateway_Loja5_Woo_Cielo_Integrado();
	
	//debug
	if(isset($_GET['debug'])){
		$log = new WC_Logger();
		$log->add( $api->id, 'Debug status checkout cielo: ' . print_r($_REQUEST,true) );
	}
	
	//checa o post
	if (isset($_POST['order_number']) && isset($_POST['amount']) && isset($_POST['payment_status'])) {
		$order_id = $_POST['order_number'];
	} else {
		die('=(');
	}
	
	//pega o pedido
	$order = wc_get_order((int)($order_id));
	
	switch($_POST['payment_status']) {
				
	case '1':
	$order->update_status($api->settings['aguardando']);
	break;
	
	case '2':
	$order->update_status($api->settings['pago']);
	$order->reduce_order_stock();
	break;
		
	case '3':
	$order->update_status($api->settings['negado']);
	break;
		
	case '5':
	$order->update_status($api->settings['cancelado']);
	break;
		
	case '7':
	$order->update_status($api->settings['autorizado']);
	$order->reduce_order_stock();
	break;
		
	}
	
	die('<status>OK</status>');
}

//notificacao cielo integrado
add_action( 'wp_ajax_notificacao_loja5_woo_cielo_integrado', 'notificacao_loja5_woo_cielo_integrado');
add_action( 'wp_ajax_nopriv_notificacao_loja5_woo_cielo_integrado','notificacao_loja5_woo_cielo_integrado');
function notificacao_loja5_woo_cielo_integrado(){
	//api
    $api = new WC_Gateway_Loja5_Woo_Cielo_Integrado();
	
	//debug
	if($api->settings['debug']=='yes'){
		$log = new WC_Logger();
		$log->add( $api->id, 'Debug notificacao checkout cielo: ' . print_r($_REQUEST,true) );
	}
	
	//checa o post
	if (isset($_POST['order_number']) && isset($_POST['amount']) && isset($_POST['payment_status'])) {
		$order_id = $_POST['order_number'];
	} else {
		die('=(');
	}
	
	//pega o pedido
	$order = wc_get_order((int)($order_id));
	
	//meios de pagamento
	$meios = $api->arrayMeiosPagamento();
	
	//se cartao
	$tipo = $bin = $par = $meio = $tid = '';
	$tid = isset($_REQUEST['tid'])?'TID: '.$_REQUEST['tid'].'':'';
	if(isset($_REQUEST['payment_method_type']) && $_REQUEST['payment_method_type']==1){
		$tipo = 'CC';
		$bin = isset($_REQUEST['payment_maskedcredicard'])?$_REQUEST['payment_maskedcredicard']:'';
		$par = isset($_REQUEST['payment_installments'])?$_REQUEST['payment_installments']:'';
		$meio = isset($meios[$_REQUEST['payment_method_brand']])?$meios[$_REQUEST['payment_method_brand']]:'';
		$tid = "".$tid." - (".$bin.") dividido em ".$par."x<br>";
	//se boleto
	}elseIf($_REQUEST['payment_method_type']==2){
		$tipo = 'BB';
		$meio = '';	
	//se tef
	}elseif($_REQUEST['payment_method_type']==3){
		$tipo = 'TO';
		$meio = isset($_REQUEST['payment_method_bank'])?$_REQUEST['payment_method_bank']:'';	
	//se debito	
	}elseif($_REQUEST['payment_method_type']==4){
		$tipo = 'CD';
		$meio = isset($_REQUEST['payment_method_bank'])?$_REQUEST['payment_method_bank']:'';	
	}
	
	//monta o log
	$tipo_nome = $api->tipoPagamento($tipo);
	
	$tipo_nome = $tipo_nome.' '.$meio.' '.$tid;
	
	switch($_POST['payment_status']) {
				
		case '1':
		$order->update_status($api->settings['aguardando'],$tipo_nome);
		break;
		
		case '2':
		$order->update_status($api->settings['pago'],$tipo_nome);
		//$order->reduce_order_stock();
		break;
			
		case '3':
		$order->update_status($api->settings['negado'],$tipo_nome);
		break;
			
		case '5':
		$order->update_status($api->settings['cancelado'],$tipo_nome);
		break;
			
		case '7':
		$order->update_status($api->settings['autorizado'],$tipo_nome);
		//$order->reduce_order_stock();
		break;
		
	}
	
	die('<status>OK</status>');
}
?>
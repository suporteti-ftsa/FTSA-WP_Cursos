<?php   
	class WC_Gateway_Loja5_Woo_Cielo_Integrado extends WC_Payment_Gateway {
	
        public function __construct() {
            global $woocommerce;
            $this->id           = 'loja5_woo_cielo_integrado';
            $this->icon         = apply_filters( 'woocommerce_loja5_woo_cielo_integrado', home_url( '/' ).'wp-content/plugins/loja5-woo-cielo-integrado/images/cielo.png' );
            $this->has_fields   = false;
            $this->supports   = array('products');
            $this->description = true;
            $this->method_title = 'Checkout Cielo';
            $this->init_settings();
            $this->init_form_fields();
			if(!isset($this->title) || empty($this->title)){
			$this->title = 'informe o titulo';
		}
            
            foreach ( $this->settings as $key => $val ) $this->$key = $val;
            
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ){
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            }else{
                add_action('woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
            
            if ( !$this->is_valid_for_use() ) $this->enabled = false;
        }
        
        public function thankyou_page( $order_id ) {
            global $wpdb;
            //pega o pedido
            $order = wc_get_order((int)($order_id));   
            //layout
            include_once(dirname(__FILE__) . '/cupom.php'); 
        }
        
        public function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_loja5_woo_cielo_webservice_supported_currencies', array( 'BRL' ) ) ) ) return false;
            return true;
        }
        
        public function get_status_pagamento(){
            if(function_exists('wc_get_order_statuses')){
                return wc_get_order_statuses();
            }else{
                $taxonomies = array( 
                    'shop_order_status',
                );
                $args = array(
                    'orderby'       => 'name', 
                    'order'         => 'ASC',
                    'hide_empty'    => false, 
                    'exclude'       => array(), 
                    'exclude_tree'  => array(), 
                    'include'       => array(),
                    'number'        => '', 
                    'fields'        => 'all', 
                    'slug'          => '', 
                    'parent'         => '',
                    'hierarchical'  => true, 
                    'child_of'      => 0, 
                    'get'           => '', 
                    'name__like'    => '',
                    'pad_counts'    => false, 
                    'offset'        => '', 
                    'search'        => '', 
                    'cache_domain'  => 'core'
                ); 
                foreach(get_terms( $taxonomies, $args ) AS $status){
                    $s[$status->slug] = __( $status->slug, 'woocommerce' );
                }
                return $s;
            }
        }
        
        public function admin_options() {
            ?>
            <?php if ( $this->is_valid_for_use() ) : ?>
                <table class="form-table">
                <?php
                    $this->generate_settings_html();
                ?>
                </table>
            <?php else : ?>
                <div class="inline error"><p><strong><?php _e( 'Gateway Desativado', 'woocommerce' ); ?></strong>: <?php _e( 'Checkout Cielo n&atilde;o aceita o tipo e moeda de sua loja, apenas BRL.', 'woocommerce' ); ?></p></div>
            <?php
                endif;
        }
        
        public function init_form_fields() {
			include(dirname(__FILE__).'/config.php');
			$this->form_fields = $config;
        }
        
        public function payment_fields() {
            global $woocommerce;
            if(!isset($_GET['pay_for_order'])){
				$total_cart = number_format($this->get_order_total(), 2, '.', '');
			}else{
				$order_id = woocommerce_get_order_id_by_order_key($_GET['key']);
				$order = wc_get_order( $order_id );
				$total_cart = number_format($order->get_total(), 2, '.', '');
			}
            include(dirname(__FILE__) . '/layout_integrado.php'); 
        }
        
        public function validate_fields() {
			global $woocommerce;
			$erros = 0;
			$cpf_cnpj = new ValidaCPFCNPJ($this->get_post('fiscal'));
			if($this->get_post('fiscal')==''){
				$this->tratar_erro("Informe um CPF/CNPJ v&aacute;lido!");
				$erros++;
			}elseif(!$cpf_cnpj->valida()){
				$this->tratar_erro("O CPF/CNPJ n&atilde;o &eacute; v&aacute;lido!");
				$erros++;
			}
			if($erros>0){
				return false;
			}
			return true;
		}
		
		public function tipoPagamento($tipo){
			switch($tipo) {
				case '1':
				case 'CC':
				return 'Cartão de crédito';
				break;
				case '2':
				case 'BB':
				return 'Boleto bancário';
				break;
				case '3':
				case 'TO':
				return 'Transferência online';
				break;
				case '4':
				case 'CD':
				return 'Cartão de débito';
				break;
				default:
				return 'Cartão de crédito';
			}
		}
		
		public function arrayTipos(){
			return array('CC'=>'Cart&atilde;o de cr&eacute;dito','BB'=>'Boleto banc&aacute;rio','TO'=>'Transfer&ecirc;ncia online','CD'=>'Cart&atilde;o  de d&eacute;bito');
		}
		
		public function arrayStatus(){
			return array(1=>'Pendente',2=>'Pago',3=>'Negado',5=>'Cancelado',6=>'N&atilde;o finalizado',7=>'Autorizado');
		}
		
		public function arrayMeiosPagamento(){
			return array(1=>'Visa',2=>'Mastercard',3=>'Amex',4=>'Diners',5=>'Elo',6=>'Aura',7=>'JCB');
		}
		
		public function arrayAntiFraude(){
			return array(1=>'Baixo Risco',2=>'Alto Risco',3=>'N&atilde;o finalizado',4=>'Risco moderado');
		}
        
        private function get_post( $name ) {
                if (isset($_POST['checkout_cielo_api'][$name])) {
                    return $_POST['checkout_cielo_api'][$name];
                }
                return null;
        }
        
        public function tratar_erro($erro){
            global $woocommerce;
            if(function_exists('wc_add_notice')){
                wc_add_notice($erro,$notice_type = 'error' );
            }else{
                $woocommerce->add_error($erro);
            }
        }
        
        public function somente_letras_cielo($t){
            return preg_replace("/[^0-9a-zA-Z]+/", "", $t);
        }
        
        public function process_payment($order_id) {
            global $woocommerce,$wpdb;
            $order = wc_get_order( $order_id );
            
            //inicio
            $json = array();
            $json['OrderNumber'] = $order->get_id();
            $json['SoftDescriptor'] = $this->somente_letras_cielo(strtoupper($this->settings['soft']));
			
			//custom cobranca
			$celular = $order->get_meta( '_billing_cellphone' );
			$num_cob = $order->get_meta( '_billing_number' );
			$bairro_cob = $order->get_meta( '_billing_neighborhood' );
			$num_ent = $order->get_meta( '_shipping_number' );
			$bairro_ent = $order->get_meta( '_shipping_neighborhood' );
			$cpf_cob = preg_replace('/\D/', '', $order->get_meta( '_billing_cpf' ));
			$cnpj_cob = preg_replace('/\D/', '', $order->get_meta( '_billing_cnpj' ));
            
            //desconto
            //$json['Cart']['Discount'] = array('Type'=>'Amount','Value'=>number_format($order->get_total_discount(), 2, '', ''));
			$json['Cart']['Discount'] = array('Type'=>'Amount','Value'=>number_format(0, 2, '', ''));
            
            //produtos
            $total = 0;
            foreach($order->get_items() AS $item){
                if(isset($item['quantity']) || isset($item['qty'])){
                    $qtd = isset($item['quantity'])?$item['quantity']:$item['qty'];
                    $json['Cart']['Items'][] = array('Name'=>$item['name'],'UnitPrice'=>number_format(($item['total']/$qtd), 2, '', ''),'Quantity'=>$qtd,'Type'=>'Asset');
                    $total += $item['total'];
                }
            }
            
            //frete
            $frete = $order->get_shipping_total();
            $total += $frete;
            if($frete > 0){
                $json['Shipping']['Type'] = 'FixedAmount';
            }else{
                $json['Shipping']['Type'] = 'Free';
            }
            
            //endereco de entrega se nao houver entrega
            if(!empty($order->get_shipping_state())){
                $rua = trim(str_replace(',','',preg_replace('/\d+/','',$order->get_shipping_address_1())));
                $numero = !empty($num_ent)?$num_ent:'*';
                $complemento = '';
                $bairro = !empty($bairro_ent)?$bairro_ent:$order->get_shipping_address_2();
                if(empty($bairro)){
                    $bairro = 'Centro';
                }
                $cep = preg_replace('/\D/', '', $order->get_shipping_postcode());
                $cidade = $order->get_shipping_city();
                $uf = $order->get_shipping_state();	
            }else{
                $rua = trim(str_replace(',','',preg_replace('/\d+/','',$order->get_billing_address_1())));
                $numero = !empty($num_cob)?$num_cob:'*';
                $complemento = '';
                $bairro = !empty($bairro_cob)?$bairro_cob:$order->get_billing_address_2();
                if(empty($bairro)){
                    $bairro = 'Centro';
                }
                $cep = preg_replace('/\D/', '', $order->get_billing_postcode());
                $cidade = $order->get_billing_city();
                $uf = $order->get_billing_state();		
            }
            
            $json['Shipping']['TargetZipCode'] = $cep; 
            $json['Shipping']['Address'] = array(
                'Street' => $rua,
                'Number' => $numero,
                'Complement' => $complemento,
                'District' => $bairro,
                'City' => $cidade,
                'State' => $uf,
            );
            $json['Shipping']['Services'][] = array('Name'=>'Envio da Loja','Price'=>number_format($frete, 2, '', ''));
            
            //cliente
            $fiscal = preg_replace('/\D/', '', $this->get_post('fiscal'));
            if(isset($cpf_cob) && !empty($cpf_cob)){
               $fiscal = preg_replace('/\D/', '', $cpf_cob); 
            }elseif(isset($cnpj_cob) && !empty($cnpj_cob)){
               $fiscal = preg_replace('/\D/', '', $cnpj_cob); 
            }
            $telefone = !empty($order->get_billing_phone())?$order->get_billing_phone():$celular;
            $telefone = preg_replace('/\D/', '',$telefone);
            if(strlen($telefone)==13){
				$telefone = substr($telefone,-10);
            }elseif(strlen($telefone)==12){
				$telefone = substr($telefone,-10);
            }elseif(strlen($telefone)==11 || strlen($telefone)==10){
				$telefone = $telefone;
            }else{
				$telefone = '';
            }
            $json['Customer'] = array(
                'Identity' => ((strlen($fiscal)==11 || strlen($fiscal)==14)?$fiscal:''), 
                'FullName' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                'Email' => $order->get_billing_email(),
                'Phone' => $telefone,
            );
            
            //anti fraude
            $json['Options']['AntifraudEnabled'] = 'false';
            $anti_fraude = $this->settings['antifraude'];
            $anti_fraude_total = (float)$this->settings['minimo'];
            if($anti_fraude=='yes' && $total>=$anti_fraude_total){
                $json['Options']['AntifraudEnabled'] = 'true';
            }
			$json['Options']['ReturnUrl'] = admin_url('admin-ajax.php').'?action=retorno_loja5_woo_cielo_integrado&id='.$order->get_id();
            
            $url = 'https://cieloecommerce.cielo.com.br/api/public/v1/orders';
            
            $data_string = json_encode($json);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'MerchantId: '.trim(trim($this->settings['chave'])).''
            ));
            $results = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $json = @json_decode($results, true);
			
			//se log ativado 
			if($this->settings['debug']=='yes'){
				$log = new WC_Logger();
				$log->add($this->id,'Debug Pagamento #'.$order_id.' ('.$httpcode.')');
				$log->add($this->id,$data_string);
				$log->add($this->id,'Resultado:');
				$log->add($this->id,$results);
			}

			$result = null;
            if(is_array($json)){
                if(array_key_exists('settings', $json)){
                    $result = $json['settings'];
                }elseif(array_key_exists('message', $json)){
                    $result = $json['message'];
                }else{
                    $result = $json; 
                }
				if(isset($json['modelState'])){
					$result .= ' '.print_r($json['modelState'], TRUE);
				}
            }else{
                $result = $results;
            }

            $res = array('status'=>$httpcode,'data'=>$result);
            
			//se ok
			if(($res['status']==200 || $res['status']==201) && isset($res['data']['checkoutUrl'])){
				return array(
					'result' 	=> 'success',
					'redirect'	=>  $res['data']['checkoutUrl']
				);
			}else{
				$this->tratar_erro($res['data']);
                return false;
			}
            
        }
        
        private function get_user_login() {
            global $user_login;
            get_currentuserinfo();
            return $user_login;
        }
    }
?>
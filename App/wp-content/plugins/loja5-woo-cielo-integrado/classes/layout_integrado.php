<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}
//se pf ou pj
$fiscal = '';
if(isset($_REQUEST['post_data']) && !empty($_REQUEST['post_data'])){
	parse_str(trim($_REQUEST['post_data']), $output);
	if(isset($output['billing_persontype']) && $output['billing_persontype']==1 && isset($output['billing_cpf']) && !empty($output['billing_cpf'])){
		$fiscal = preg_replace('/\D/', '',$output['billing_cpf']);
	}elseif(isset($output['billing_persontype']) && $output['billing_persontype']==2 && isset($output['billing_cnpj']) && !empty($output['billing_cnpj'])){
		$fiscal = preg_replace('/\D/', '',$output['billing_cnpj']);
	}
}
?>
<div id="tela-getnet-api" style="width:100%;">

<fieldset class="wc-credit-card-form wc-payment-form">

<p class="form-row form-row-wide woocommerce-validated">
<label style="padding: 5px 0 5px 5px;">CPF/CNPJ:</label>
<input style="box-shadow: inset 2px 0 0 #0f834d;height:40px;" onfocus="jQuery(this).mask('99999999999999')" type="text" class="input-text" placeholder="CPF ou CNPJ" name="checkout_cielo_api[fiscal]" value="<?php echo preg_replace('/\D/', '',$fiscal);?>">
</p>

</fieldset>

</div>	
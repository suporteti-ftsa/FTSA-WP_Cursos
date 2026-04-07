<?php
/**
* Plugin Name:          Preenche endereço CEP
* Plugin URI:           https://gitlab.com/anhaabaete
* Description:          Preenche o endereço automaticamente no formulário do woocommerce baseado no CEP
* Author:               anhagaabaete
* Author URI:           https://gitlab.com/anhaabaete
* Version:              1.5.1
* License:              GPLv3 or later
*
* Preenche endereço CEP for WooCommerce is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* any later version.
*
* Preenche endereço CEP for WooCommerce is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with QR Code PicPay for WooCommerce. If not, see
* <https://www.gnu.org/licenses/gpl-3.0.txt>.
*
*/


function woo_preenche_cep_tn()
{
  if (
      is_checkout_pay_page() ||
      is_cart() ||
      is_checkout() ||
      is_account_page()
    )
    {
      wp_enqueue_script('preenche-cep-tn-js', plugin_dir_url( __FILE__ ).'js/ceppreenche.js',array('jquery'));
    }
  }

  add_action( 'wp_enqueue_scripts', 'woo_preenche_cep_tn' , 999);
  
  
add_filter( 'woocommerce_checkout_fields', 'melhor_lugardocampo',9999 );

function melhor_lugardocampo($fields_array) {
    
    $fields_array['billing']['billing_postcode']['priority']=45;
    $fields_array['billing']['billing_postcode']['class'][]='address-field';

        
    return $fields_array;
}



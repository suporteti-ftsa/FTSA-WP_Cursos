<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_Gateway_Cielo_FTSA extends WC_Payment_Gateway {

    protected $debug;
    protected $client_id;
    protected $client_secret;
    protected $soft_desc;
    protected $max_install;
    protected $pix_discount;

    public function __construct() {
        $this->id                 = 'cielo_checkout_ftsa';
        $this->method_title       = __( 'Cielo Checkout (FTSA)', 'wc-cielo-checkout-ftsa' );
        $this->method_description = __( 'Redireciona o cliente para a página segura da Cielo (CheckoutUrl) e atualiza o pedido por notificações. Conciliação via EDI (CIELO04).', 'wc-cielo-checkout-ftsa' );
        $this->icon               = WC_CIELO_FTSA_URL . '/assets/LogoCielo.png';
        $this->has_fields         = false;
        $this->supports           = array( 'products', 'refunds' );

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled        = $this->get_option( 'enabled', 'no' );
        $this->title          = $this->get_option( 'title', 'Cartões Cielo' );
        $this->description    = $this->get_option( 'description', 'Sistema de Cobrança Gateway Cielo – Cartões de Crédito, Débito e PIX.' );
        $this->client_id      = $this->get_option( 'client_id', '' );
        $this->client_secret  = $this->get_option( 'client_secret', '' );
        $this->soft_desc      = $this->get_option( 'soft_descriptor', '' );
        $this->max_install    = absint( $this->get_option( 'max_installments', 1 ) );
        $this->pix_discount   = floatval( str_replace(',', '.', $this->get_option( 'pix_discount_percent', '0' ) ) );
        $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Ativar/Desativar', 'wc-cielo-checkout-ftsa' ),
                'type'    => 'checkbox',
                'label'   => __( 'Ativar Cielo Checkout', 'wc-cielo-checkout-ftsa' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Título', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'text',
                'default'     => 'Cartões Cielo',
            ),
            'description' => array(
                'title'       => __( 'Descrição', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'textarea',
                'default'     => 'Sistema de Cobrança Gateway Cielo – Cartões de Crédito, Débito e PIX.',
            ),
            'client_id' => array(
                'title'       => __( 'Client ID (OAuth2)', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'text',
                'default'     => '',
            ),
            'client_secret' => array(
                'title'       => __( 'Client Secret (OAuth2)', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'password',
                'default'     => '',
            ),
            'soft_descriptor' => array(
                'title'       => __( 'Soft Descriptor (opcional)', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'text',
                'default'     => '',
            ),
            'max_installments' => array(
                'title'       => __( 'Limite de parcelas', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'number',
                'default'     => '1',
            ),
            'pix_discount_percent' => array(
                'title'       => __( 'Pix: desconto % (aplicado pós-pagamento)', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'text',
                'default'     => '0',
            ),
            'debug' => array(
                'title'       => __( 'Log de depuração', 'wc-cielo-checkout-ftsa' ),
                'type'        => 'checkbox',
                'label'       => __( 'Gravar logs em WooCommerce > Status > Logs', 'wc-cielo-checkout-ftsa' ),
                'default'     => 'yes',
            ),
        );
    }

    public function is_available() {
        return 'yes' === $this->enabled && $this->client_id && $this->client_secret;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $api   = new WC_Cielo_API_FTSA();

        // OrderNumber: A–Z/0–9 máx 20
        $order_number = preg_replace( '/[^A-Za-z0-9]/', '', (string) $order->get_order_number() );
        $order_number = substr( $order_number, 0, 20 );

        // Itens
        $items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $unit    = wc_get_price_including_tax( $product, array( 'qty' => 1 ) );
            $items[] = array(
                'Name'        => $item->get_name(),
                'Description' => $item->get_name(),
                'UnitPrice'   => intval( round( $unit * 100 ) ),
                'Quantity'    => $item->get_quantity(),
                'Type'        => 'Asset',
                'Sku'         => (string) $product->get_sku(),
                'Weight'      => 0,
            );
        }

        $shipping_total = intval( round( $order->get_shipping_total() * 100 ) );
        $wc_discount    = intval( round( abs( $order->get_discount_total() ) * 100 ) );

        $soft = preg_replace('/[^0-9A-Za-z]/', '', $this->soft_desc );
        $soft = substr( $soft, 0, 13 );

        $cart = array( 'Items' => $items );
        if ( $wc_discount > 0 ) {
            $cart['Discount'] = array( 'Type' => 'Amount', 'Value' => $wc_discount );
        }

        $payload = array(
            'OrderNumber'    => $order_number,
            'Options'        => new stdClass(), // precisa ser OBJETO {}
            'Cart'           => $cart,
            'Shipping'       => array(
                'Type'   => $shipping_total > 0 ? 'FixedAmount' : 'Free',
                'Price'  => $shipping_total,
            ),
            'Payment' => array( 'MaxNumberOfInstallments' => max( 1, $this->max_install ) ),
            'Customer' => array(
                'Name'     => $order->get_formatted_billing_full_name(),
                'Email'    => $order->get_billing_email(),
                'Identity' => preg_replace('/\D+/', '', $order->get_meta('_billing_cpf') ?: $order->get_meta('_billing_cnpj') ),
                'Phone'    => preg_replace('/\D+/', '', $order->get_billing_phone() ),
            ),
        );

        if ( $soft ) {
            $payload['SoftDescriptor'] = $soft;
        }

        // Token fresco por pedido
        $token = $api->get_access_token( $this->client_id, $this->client_secret );
        if ( is_wp_error( $token ) ) {
            wc_add_notice( __( 'Falha ao iniciar o pagamento (token).', 'wc-cielo-checkout-ftsa' ), 'error' );
            return array( 'result' => 'failure' );
        }

        $res = $api->create_checkout_order( $token, $payload );

        if ( is_wp_error( $res ) && $res->get_error_code() === 'cielo_401' ) {
            // renova token e tenta 1x
            $token = $api->get_access_token( $this->client_id, $this->client_secret );
            if ( ! is_wp_error( $token ) ) {
                $res = $api->create_checkout_order( $token, $payload );
            }
        }

        if ( is_wp_error( $res ) ) {
            wc_add_notice( __( 'Não foi possível criar a página de pagamento no provedor.', 'wc-cielo-checkout-ftsa' ), 'error' );
            return array( 'result' => 'failure' );
        }

        $order->update_status( 'pending', __( 'Aguardando pagamento via Cielo Checkout.', 'wc-cielo-checkout-ftsa' ) );
        wc_reduce_stock_levels( $order_id );
        WC()->cart->empty_cart();

        return array( 'result' => 'success', 'redirect' => $res['CheckoutUrl'] );
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return new WP_Error( 'cielo_refund', __( 'Estorno deve ser solicitado no painel da Cielo (Checkout/Link).', 'wc-cielo-checkout-ftsa' ) );
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_Cielo_API_FTSA {

    const TOKEN_ENDPOINT_V2  = 'https://cieloecommerce.cielo.com.br/api/public/v2/token';
    const TOKEN_ENDPOINT_V1  = 'https://cieloecommerce.cielo.com.br/api/public/v1/token';
    const ORDERS_ENDPOINT    = 'https://cieloecommerce.cielo.com.br/api/public/v1/orders/';

    protected $logger;

    public function __construct() {
        $this->logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
    }

    protected function log( $level, $message, $ctx = array() ) {
        if ( $this->logger ) {
            $this->logger->log( $level, $message, array_merge( array( 'source' => 'wc-cielo-ftsa' ), $ctx ) );
        }
    }

    public function get_access_token( $client_id, $client_secret ) {
        $token = $this->request_token( self::TOKEN_ENDPOINT_V2, $client_id, $client_secret );
        if ( is_wp_error( $token ) ) {
            $token = $this->request_token( self::TOKEN_ENDPOINT_V1, $client_id, $client_secret );
        }
        return $token;
    }

    protected function request_token( $endpoint, $client_id, $client_secret ) {
        $auth = base64_encode( $client_id . ':' . $client_secret );
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'wc-cielo-checkout-ftsa/' . WC_CIELO_FTSA_VERSION,
            ),
            'timeout' => 30,
            'body'    => '',
        );
        $r = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $r ) ) {
            $this->log( 'error', 'Token WP_Error', array( 'endpoint' => $endpoint, 'error' => $r->get_error_message() ) );
            return $r;
        }
        $code = wp_remote_retrieve_response_code( $r );
        $body = wp_remote_retrieve_body( $r );
        $data = json_decode( $body, true );
        if ( $code >= 200 && $code < 300 && ! empty( $data['access_token'] ) ) {
            $mask = strlen( $data['access_token'] ) > 10 ? substr($data['access_token'],0,6).'...'.substr($data['access_token'],-4) : '***';
            $this->log( 'info', 'Token OK', array( 'endpoint' => $endpoint, 'token_mask' => $mask ) );
            return $data['access_token'];
        }
        $this->log( 'error', 'Falha token', array( 'http' => $code, 'endpoint' => $endpoint, 'body' => $body ) );
        return new WP_Error( 'cielo_token_error', 'Falha ao obter token (HTTP ' . $code . ')' );
    }

    public function create_checkout_order( $access_token, $payload ) {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'wc-cielo-checkout-ftsa/' . WC_CIELO_FTSA_VERSION,
            ),
            'timeout' => 30,
            'body'    => wp_json_encode( $payload ),
        );

        $this->log( 'info', 'Enviando pedido para Cielo', array(
            'endpoint' => self::ORDERS_ENDPOINT,
            'payload_preview' => wp_json_encode( array(
                'OrderNumber' => $payload['OrderNumber'],
                'ItemsCount'  => isset($payload['Cart']['Items']) ? count($payload['Cart']['Items']) : 0,
                'HasDiscount' => isset($payload['Cart']['Discount']),
                'ShippingType'=> $payload['Shipping']['Type'],
                'Payment'     => $payload['Payment'],
            ) )
        ) );

        $r = wp_remote_post( self::ORDERS_ENDPOINT, $args );
        if ( is_wp_error( $r ) ) return $r;
        $code = wp_remote_retrieve_response_code( $r );
        $body = wp_remote_retrieve_body( $r );
        $data = json_decode( $body, true );

        if ( $code == 401 ) {
            $this->log( 'warning', '401 ao criar pedido - tentando renovar token', array( 'body' => $body ) );
            return new WP_Error( 'cielo_401', 'Unauthorized' );
        }

        if ( $code >= 200 && $code < 300 && ! empty( $data['CheckoutUrl'] ) ) {
            $this->log( 'info', 'Pedido criado com sucesso', array( 'checkout_url' => $data['CheckoutUrl'] ) );
            return $data;
        }

        $this->log( 'error', 'Falha criar CheckoutUrl', array( 'http' => $code, 'body' => $body ) );
        return new WP_Error( 'cielo_create_error', 'Falha ao criar CheckoutUrl (HTTP ' . $code . ')' );
    }
}

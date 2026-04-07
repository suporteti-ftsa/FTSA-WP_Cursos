<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_Cielo_Webhook_FTSA {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_admin_cols' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_admin_cols' ), 10, 2 );
    }

    public function routes() {
        register_rest_route( 'wc-cielo-checkout/v1', '/notify', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function add_admin_cols( $cols ) {
        $cols['cielo_net'] = __('Cielo (Líquido EDI)','wc-cielo-checkout-ftsa');
        return $cols;
    }

    public function render_admin_cols( $col, $post_id ) {
        if ( $col === 'cielo_net' ) {
            $net = get_post_meta( $post_id, '_cielo_net_amount', true );
            $status = get_post_meta( $post_id, '_cielo_edi_status', true );
            if ( $net !== '' ) {
                echo wc_price( $net ) . ' <small>(' . esc_html( $status ?: '—' ) . ')</small>';
            } else {
                echo '—';
            }
        }
    }

    public function handle( WP_REST_Request $request ) {
        $params = $request->get_params();

        $order_number  = isset( $params['OrderNumber'] ) ? $params['OrderNumber'] : ( $params['order_number'] ?? '' );
        $status_code   = isset( $params['Status'] ) ? intval( $params['Status'] ) : ( isset($params['payment_status']) ? intval( $params['payment_status'] ) : null );
        $tid           = $params['Tid'] ?? $params['tid'] ?? '';
        $checkout_id   = $params['CheckoutCieloOrderNumber'] ?? $params['checkout_cielo_order_number'] ?? '';

        if ( empty( $order_number ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'message' => 'OrderNumber ausente' ), 400 );
        }

        $order = wc_get_order( $order_number );
        if ( ! $order ) {
            $order_id = wc_get_order_id_by_order_key( $order_number );
            $order    = $order_id ? wc_get_order( $order_id ) : null;
        }
        if ( ! $order ) {
            return new WP_REST_Response( array( 'ok' => false, 'message' => 'Pedido não encontrado' ), 404 );
        }

        if ( $status_code !== null ) {
            switch ( $status_code ) {
                case 2:
                    $order->payment_complete( $tid );
                    $order->update_meta_data( '_cielo_checkout_id', sanitize_text_field( $checkout_id ) );
                    if ( $tid ) $order->update_meta_data( '_cielo_tid', sanitize_text_field( $tid ) );
                    $order->add_order_note( 'Cielo: pagamento confirmado.' );
                    $order->save();
                    break;
                case 7: $order->update_status( 'on-hold', 'Cielo: autorizado, aguardando captura.' ); break;
                case 3: $order->update_status( 'failed', 'Cielo: negado.' ); break;
                case 5: $order->update_status( 'cancelled', 'Cielo: cancelado.' ); break;
                default:$order->update_status( 'pending', 'Cielo: pendente/aguardando.' ); break;
            }
        } else {
            $order->add_order_note( 'Cielo: notificação recebida.' );
        }

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }
}
new WC_Cielo_Webhook_FTSA();

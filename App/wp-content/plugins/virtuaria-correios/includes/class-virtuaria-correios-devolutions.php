<?php
/**
 * Description: Send request devolution product.
 *
 * @package virtuaria.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Correios_Devolutions {
	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
		add_action( 'wp_enqueue_scripts', array( $this, 'styles_scripts' ) );
		// add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles_scripts' ) );
		add_action( 'wp_ajax_send_devolution_request', array( $this, 'send_devolution_request' ) );
		add_action( 'wp_ajax_nopriv_send_devolution_request', array( $this, 'send_devolution_request' ) );
		// add_action( 'woocommerce_admin_order_item_headers', array( $this, 'column_devolution_product' ) );
		// add_action( 'woocommerce_admin_order_item_values', array( $this, 'column_devolution_product_value' ), 10, 2 );
		// add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'myaccount_orders_button_devolution' ), 10, 2 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'customer_order_button_devolution' ), 10, 2 );
	}

	/**
	 * Add styles and scripts.
	 *
	 * @return void
	 */
	public function styles_scripts() {
		if ( is_wc_endpoint_url( 'view-order' ) || is_wc_endpoint_url( 'orders' ) ) {
			wp_enqueue_style(
				'devolution-styles',
				VIRTUARIA_CORREIOS_URL . 'public/css/devolution.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/css/devolution.css' )
			);

			wp_enqueue_script(
				'devolution-script',
				VIRTUARIA_CORREIOS_URL . 'public/js/devolution.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/devolution.js' ),
				true
			);

			wp_localize_script(
				'devolution-script',
				'info',
				array(
					'admin_url' => admin_url( 'admin-ajax.php' ),
					'customer'  => get_current_user_id(),
					'nonce'     => wp_create_nonce( 'confirm_send_devolution_form' ),
				)
			);

			wp_enqueue_style( 'dashicons' );
		}
	}

	/**
	 * Add admin styles and scripts.
	 *
	 * @param string $hook the hook current page.
	 */
	public function admin_styles_scripts( $hook ) {
		if ( 'post.php' === $hook && ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) ) {
			wp_enqueue_style(
				'admin-devolution-styles',
				VIRTUARIA_CORREIOS_URL . 'admin/css/devolution.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/css/devolution.css' )
			);

			wp_enqueue_script(
				'admin-devolution-script',
				VIRTUARIA_CORREIOS_URL . 'admin/js/devolution.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/js/devolution.js' ),
				true
			);

			wp_localize_script(
				'admin-devolution-script',
				'info',
				array(
					'admin_url' => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'confirm_send_devolution_form' ),
				)
			);
		}
	}

	/**
	 * Send devolution request.
	 */
	public function send_devolution_request() {
		if ( isset( $_POST['nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'confirm_send_devolution_form' ) ) {
			$order_id    = false;
			$product_id  = false;
			$customer_id = false;
			if ( isset( $_POST['order_id'] ) ) {
				$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
			}
			if ( isset( $_POST['product_id'] ) ) {
				$product_id = sanitize_text_field( wp_unslash( $_POST['product_id'] ) );
			}
			if ( isset( $_POST['customer_id'] ) ) {
				$customer_id = intval( sanitize_text_field( wp_unslash( $_POST['customer_id'] ) ) );
			}
			if ( ! $customer_id ) {
				$customer_id = get_current_user_id();
			}

			if ( $order_id && ( is_admin() || $customer_id ) ) {
				$order = wc_get_order( $order_id );
				if ( $order && ( is_admin() || $order->get_customer_id() === $customer_id ) ) {
					$requested = $order->get_meta( 'devolution_products_requested' );
					if ( ! is_array( $requested ) ) {
						$requested = array();
					}

					if ( $product_id ) {
						$product = wc_get_product( $product_id );
					}

					$msg_note      = '';
					$customer_note = '';
					if ( $product ) {
						$requested[] = $product_id;
						$msg_note    = sprintf(
							/* translators: $1$s is the customer name, $2$s is the product name, $3$s is the order link and $4$s is the order id. */
							__( 'O cliente %1$s, solicitou a devolução do produto %2$s no pedido <a target="_blank" href="%3$s">#%4$s</a>.', 'virtuaria-correios' ),
							$order->get_formatted_billing_full_name(),
							$product->get_title(),
							get_edit_post_link( $order_id ),
							$order_id,
						);

						$customer_note = sprintf(
							/* translators: %s product title */
							__( 'Solicitação de devolução do produto %s recebida.', 'virtuaria-correios' ),
							$product->get_title()
						);
					} else {
						$requested[] = $order_id;
						$msg_note    = 'Solicitação de devolução do pedido ' . $order_id . ' recebida.';
					}

					if ( $customer_note ) {
						$order->add_order_note( $customer_note, 1 );
					}
					$order->update_meta_data( 'devolution_products_requested', $requested );
					$order->save();

					wp_mail(
						get_option( 'admin_email' ),
						sprintf(
							/* translators: %s is the order id. */
							__( 'Solicitação de Devolução - Pedido #%s', 'virtuaria-correios' ),
							$order_id
						),
						$msg_note,
						array( 'Content-Type: text/html; charset=UTF-8' )
					);

					wp_die( 'sended' );
				}
			}
		}
		wp_die();
	}

	/**
	 * Add new column devolution th element.
	 *
	 * @param wc_order $order the order.
	 */
	public function column_devolution_product( $order ) {
		if ( $order && in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			echo '<th class="devolution"></th>';
		}
	}

	/**
	 * Add value to column devolution.
	 *
	 * @param wc_product    $product the product.
	 * @param WC_Order_Item $item the item.
	 */
	public function column_devolution_product_value( $product, $item ) {
		$order = $item->get_order();
		if ( $order && $product ) {
			echo '<td class="devolution"><div class="view">';
			$this->display_devolution_button( $order, $product );
			echo '</div></td>';
		}
	}

	/**
	 * Display debolution button.
	 *
	 * @param wc_order   $order the order.
	 * @param wc_product $product the product.
	 * @return void
	 */
	public function display_devolution_button( $order, $product = null ) {
		if ( $order && in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			$requests = $order->get_meta( 'devolution_products_requested' );

			if ( isset( $this->settings['hide_devolution_button'] )
				&& $this->settings['hide_devolution_button'] > 0 ) {
				$created_date = $order->get_date_created()->modify( '+' . $this->settings['hide_devolution_button'] . ' days' );
				$created_date = $created_date->format( 'Y-m-d' );

				$should_display = $created_date >= gmdate( 'Y-m-d' );
			} else {
				$should_display = true;
			}
			if ( $product ) {
				if ( is_array( $requests )
					&& in_array( $product->get_id(), $requests, false ) ) {
					echo '<button class="devolution-item-requested" title="Devolução Solicitada"  disabled="disabled" style="font-size: 14px">';
					echo '<span class="dashicons dashicons-hourglass"></span> Devolução Solicitada';
					echo '</button>';
				} elseif ( ! is_admin() && $should_display ) {
					echo '<button title="Solicitar Devolução" class="devolution-item-request" data-order_id="' . esc_attr( $order->get_id() ) . '" data-product_id="' . esc_attr( $product->get_id() ) . '" style="font-size: 14px">';
					echo '<span class="dashicons dashicons-remove"></span> Devolução';
					echo '</button>';
				}
			}
		}
	}

	/**
	 * Add devolution button in my account order list.
	 *
	 * @param array    $actions the order.
	 * @param wc_order $order   the order.
	 */
	public function myaccount_orders_button_devolution( $actions, $order ) {
		$actions['devolution-item-requested'] = array(
			'url'  => '',
			'name' => 'Devolver',
		);

		return $actions;
	}

	/**
	 * Display devolution button product name.
	 *
	 * @param int           $item_id the item id.
	 * @param WC_order_item $item    the item.
	 */
	public function customer_order_button_devolution( $item_id, $item ) {
		$this->display_devolution_button( $item->get_order(), $item->get_product() );
	}
}

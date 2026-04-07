<?php
/**
 * Handle trakking from order at Correios.
 *
 * @package virtuaria/integrations/correios.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Handle trakking from order at Correios.
 */
class Virtuaria_Correios_Trakking {
	/**
	 * API.
	 *
	 * @var Virtuaria_Correios_API
	 */
	protected $api;
	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->settings = Virtuaria_WPMU_Correios_Settings::get_settings();

		if ( ! isset( $this->settings['parcel_tracking'] ) ) {
			return;
		}

		$enviroment = isset( $this->settings['enviroment'] )
			? $this->settings['enviroment']
			: 'production';

		$this->api = new Virtuaria_Correios_API(
			isset( $this->settings['debug'] )
			? wc_get_logger()
			: null,
			$enviroment
		);

		add_action(
			'init',
			array( $this, 'enqueue_actions_metabox' ),
			20
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'save_trakking_code' )
		);

		if ( isset( $this->settings['parcel_tracking'] ) ) {
			add_action( 'woocommerce_after_order_details', array( $this, 'trakking_metabox_content' ) );
		}

		add_action(
			'manage_shop_order_posts_custom_column',
			array( $this, 'display_trakking_code' ),
			100,
			2
		);

		add_action(
			'woocommerce_shop_order_list_table_custom_column',
			array( $this, 'display_trakking_code' ),
			100,
			2
		);

		add_action(
			'manage_' . $this->get_meta_boxes_screen() . '_custom_column',
			array( $this, 'display_trakking_code' ),
			100,
			2
		);

		add_filter(
			'woocommerce_email_classes',
			array( $this, 'register_trakking_mail' )
		);

		add_filter(
			'woocommerce_order_actions',
			array( $this, 'add_order_actions' ),
			10,
			2
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'resend_track_order_code' ),
			51
		);

		add_action(
			'woocommerce_order_actions_end',
			array( $this, 'add_resend_trakking_nonce' )
		);

		add_filter(
			'woocommerce_order_get__virt_correios_trakking_code',
			array( $this, 'trakking_code_compatibility' ),
			10,
			2
		);
	}

	/**
	 * A function to enqueue actions metabox.
	 */
	public function enqueue_actions_metabox() {
		if ( isset( $this->settings['parcel_tracking'] ) ) {
			add_action(
				'add_meta_boxes_' . $this->get_meta_boxes_screen(),
				array( $this, 'trakking_code_meta_box' ),
			);
			add_action(
				'add_meta_boxes_' . $this->get_meta_boxes_screen(),
				array( $this, 'trakking_metabox' ),
			);
		}
	}

	/**
	 * Retrieve the screen ID for meta boxes.
	 *
	 * @return string
	 */
	private function get_meta_boxes_screen() {
		return class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	/**
	 * Add metabox to add trakking object code.
	 */
	public function trakking_code_meta_box() {
		add_meta_box(
			'virtuaria-correios-trakking-code',
			'Código de rastreamento',
			array( $this, 'trakking_code_meta_box_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);
	}

	/**
	 * Content metabox to add trakking object code.
	 *
	 * @param wp_post $post the post.
	 */
	public function trakking_code_meta_box_content( $post ) {
		$post_id = $post instanceof WC_Order ? $post->get_id() : $post->ID;
		?>
		<input type="text" name="trakking_code" id="trakking_code" />
		<input type="hidden" name="trakking_order_id" id="trakking_order_id">
		<small>Informe o código de rastreamento do pacote.</small>
		<button style="margin-top: 10px;" class="button button-primary add-tracking-code">Adicionar código</button>
		<script>
			jQuery(document).ready(function ($) {
				$('.add-tracking-code').on('click', function () {
					$('#trakking_order_id').val('<?php echo esc_attr( $post_id ); ?>');
				});
			})
		</script>
		<?php
		wp_nonce_field( 'virtuaria-correios-trakking-code', 'trakking_code_nonce' );
	}

	/**
	 * Save trakking object code.
	 */
	public function save_trakking_code() {
		if ( isset( $_POST['trakking_code_nonce'], $_POST['trakking_order_id'], $_POST['trakking_code'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['trakking_code_nonce'] ),
				),
				'virtuaria-correios-trakking-code'
			)
		) {
			$order_id = sanitize_text_field( wp_unslash( $_POST['trakking_order_id'] ) );
			$order    = wc_get_order( $order_id );

			if ( $order ) {
				$trakking_code = sanitize_text_field( wp_unslash( $_POST['trakking_code'] ) );
				$order->update_meta_data(
					'_virt_correios_trakking_code',
					$trakking_code
				);

				$order->add_order_note(
					sprintf(
						/* translators: %1$s: trakking code. */
						__( 'Virtuaria Correios: Novo código de rastreamento (%1$s) adicionado ao pedido.', 'virtuaria-correios' ),
						$trakking_code
					),
					false,
					true
				);

				$order->save();

				self::send_trakking_notification( $order, $trakking_code );
				do_action( 'virtuaria_correios_trakking_updated', $order->get_id(), $order, $trakking_code );
			}
		}
	}

	/**
	 * Add meta box.
	 */
	public function trakking_metabox() {
		add_meta_box(
			'virtuaria-correios-trakking',
			'Rastreamento',
			array( $this, 'trakking_metabox_content' ),
			$this->get_meta_boxes_screen(),
			'normal',
			'low'
		);
	}

	/**
	 * Content metabox.
	 *
	 * @param wp_post $post the post.
	 */
	public function trakking_metabox_content( $post ) {
		$order = $post instanceof WP_Post ?
			wc_get_order( $post->ID )
			: $post;

		$has_events = false;

		$trakking_code = '';

		if ( $order ) {
			$trakking_code = $order->get_meta( '_virt_correios_trakking_code' );
			if ( $trakking_code ) {
				if ( ! is_admin() ) {
					echo '<div class="virtuaria-correios-code" style="margin-bottom: 20px";>';
					printf(
						/* translators: %1$s: trakking code. %2$s: trakking code */
						wp_kses_post( __( 'Seu pedido foi enviado usando o Código de rastreamento: <a href="https://rastreamento.correios.com.br/app/index.php?objetos=%1$s" target="_blank">%1$s</a>.', 'virtuaria-correios' ) ),
						esc_html( $trakking_code ),
						esc_html( $trakking_code ),
					);
					echo '</div>';
				}

				printf(
					'<div class="modal-header"><span class="title">Entrega via Correios</span><span class="subtitle">Código de rastreamento: %s</span></div>',
					esc_html( $trakking_code )
				);

				$data = array(
					'username'  => $this->settings['username'] ?? '',
					'password'  => $this->settings['password'] ?? '',
					'post_card' => $this->settings['post_card'] ?? '',
					'trakking'  => $trakking_code,
				);

				if ( isset( $this->settings['easy_mode'] ) ) {
					$data['easy_mode'] = 'yes';
				}

				$trakking = $this->api->get_trakking_by_code(
					$data
				);

				$events_history = $order->get_meta(
					'_virtuaria_correios_trakking_history'
				);

				if ( ! isset( $trakking['objetos'][0]['eventos'] )
					|| ( $events_history
					&& count( $trakking['objetos'][0]['eventos'] ) < count( $events_history['objetos'][0]['eventos'] ) ) ) {
					$trakking = $events_history;
				}

				if ( $trakking && isset( $trakking['objetos'][0]['eventos'] ) ) {
					$has_events = true;
					echo '<section class="virtuaria-correios">';

					if ( $post instanceof WC_Order ) {
						echo '<h2 class="woocommerce-column__title">Rastreamento da entrega</h2>';
					}

					if ( isset( $trakking['objetos'][0]['dtPrevista'] ) ) {
						$event_date = str_replace(
							'T',
							' ',
							$trakking['objetos'][0]['dtPrevista']
						);
						echo '<p class="delivery-time">Previsão de entrega: ' . esc_html( $event_date ) . '</p>';
					}

					$events = array_reverse( $trakking['objetos'][0]['eventos'] );

					$order->update_meta_data(
						'_virtuaria_correios_trakking_history',
						$trakking
					);

					$order->save();

					echo '<table class="virt-correios-trakking">';
					echo '<tr><th>Data</th><th>Evento</th></tr>';
					foreach ( $events as $event ) {
						echo '<tr><td>' . esc_html(
							str_replace(
								'T',
								' ',
								$event['dtHrCriado']
							)
						) . '</td>';
						echo '<td>' . esc_html( $event['descricao'] );
						if ( isset( $event['unidade']['endereco']['cidade'] ) ) {
							echo '<span class="locally">Unidade: ' . esc_html( $event['unidade']['endereco']['cidade'] ) . '</span>';
						}
						echo '</td></tr>';
					}
					echo '</table>';
					echo '</section>';
					?>
					<style>
						.virtuaria-correios .virt-correios-trakking {
							width: 100%;
							font-size: 14px;
						}
						.virtuaria-correios th {
							background-color: #cfcdcd;
							font-size: 16px;
						}
						.virtuaria-correios tr:nth-child(odd) {
							background-color: #eee;
						}
						.virtuaria-correios td,
						.virtuaria-correios th {
							text-align: center;
							padding: 5px 0;
							border: none;
						}
						.virtuaria-correios .locally {
							display: block;
							font-size: 80%;
						}
						.virtuaria-correios .delivery-time {
							font-size: 16px;
						}
						.virtuaria-correios {
							border: 1px solid #eee;
							padding: 10px 20px;
							margin: 20px 0;
						}
						.virtuaria-correios tr:nth-child(even) {
							background-color: #fafafa;
						}
					</style>
					<?php
				}
			}
		}

		echo '<div class="modal-content">';
		if ( ! $has_events && ( is_admin() || ! is_checkout() ) ) {
			echo '<p>Nenhum evento de Rastreio localizado para este pedido.</p>';
		}

		printf(
			'<a href="https://rastreamento.correios.com.br/app/index.php?objetos=%s" id="trakking-correios" target="_blank" class="button button-primary">Ver detalhes nos Correios</a>',
			esc_attr( $trakking_code )
		);

		if ( isset( $_POST['action'] ) && 'trakking_order' === $_POST['action'] ) {
			printf(
				'<a href="#" id="send-email-trakking" class="button button-primary">Enviar email de rastreio</a>',
			);
		}

		echo '</div>';
	}

	/**
	 * Display tracking code on order list.
	 *
	 * @param string $column   Column name.
	 * @param int    $order_id The ID of the order.
	 * @return void
	 */
	public function display_trakking_code( $column, $order_id ) {
		if ( 'shipping_address' === $column ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$code = $order->get_meta( '_virt_correios_trakking_code' );
			if ( ! empty( $code ) ) {
				echo '<div class="virtuaria-trakking-code" style="color:#999";>';
				printf(
					'Código de rastreamento: <a target="_blank" class="trakking-code" href="%s">%s</a>',
					'https://rastreamento.correios.com.br/app/index.php?objetos=' . esc_attr( $code ),
					esc_html( $code )
				);
				echo '</div>';
			}
		}
	}

	/**
	 * Send e-mail trakking order to customer.
	 *
	 * @param wc_order $order         the order id.
	 * @param string   $trakking_code the trakking code.
	 */
	public static function send_trakking_notification( $order, $trakking_code ) {
		$wc_emails = WC()->mailer()->get_emails();

		if (
			isset( $wc_emails['WC_Virtuaria_Correios_Tracking'] )
			&& $wc_emails['WC_Virtuaria_Correios_Tracking']
			&& $wc_emails['WC_Virtuaria_Correios_Tracking']->is_enabled()
		) {
			$wc_emails['WC_Virtuaria_Correios_Tracking']->trigger( $order->get_id(), $order, $trakking_code );

			$order->add_order_note(
				__( 'Virtuaria Correios: E-mail de rastreamento enviado para o cliente.', 'virtuaria-correios' ),
				false,
				true
			);
		}
	}

	/**
	 * Register trakking mail.
	 *
	 * @param array $emails the emails.
	 * @return array
	 */
	public function register_trakking_mail( $emails ) {
		$emails['WC_Virtuaria_Correios_Tracking'] = include VIRTUARIA_CORREIOS_DIR . 'includes/emails/class-virtuaria-correios-trakking-email.php';

		return $emails;
	}

	/**
	 * Adds resend tracking code action to order actions if tracking code is not set and order method is either 'virtuaria-correios-sedex' or 'virtuaria-correios-pac'.
	 *
	 * @param array    $actions List of order actions.
	 * @param wc_order $order   WC order object.
	 * @return array Updated list of order actions.
	 */
	public function add_order_actions( $actions, $order ) {
		if ( $order->get_meta( '_virt_correios_trakking_code' )
			&& $this->is_order_correios( $order )
		) {
			$actions['resend_virtuaria_correios_trakking_code'] = __(
				'Reenviar Código de Rastreio',
				'virtuaria-correios'
			);
		}
		return $actions;
	}

	/**
	 * Checks if a given order is using Correios shipping method.
	 *
	 * @param wc_order $order WC order object.
	 * @return bool True if the order is using Correios shipping method, false otherwise.
	 */
	private function is_order_correios( $order ) {
		foreach ( $order->get_shipping_methods() as $shipping_method ) {
			if ( in_array(
				$shipping_method->get_method_id(),
				array(
					'virtuaria-correios-sedex',
					'virtuaria-correios-pac',
				),
				true
			) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resends the tracking order code for a given order.
	 *
	 * @param int $order_id The ID of the order to resend the tracking code for.
	 * @return void
	 */
	public function resend_track_order_code( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order
			&& isset( $_POST['wc_order_action'], $_POST['resend_trakking_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['resend_trakking_nonce'] ) ), 'virtuaria_resend_trakking' )
			&& 'resend_virtuaria_correios_trakking_code' === $_POST['wc_order_action']
			&& $order->get_meta( '_virt_correios_trakking_code' )
			&& $this->is_order_correios( $order ) ) {
			$this->send_trakking_notification(
				$order,
				$order->get_meta( '_virt_correios_trakking_code' )
			);
		}
	}

	/**
	 * Adds a nonce field for resending tracking code.
	 *
	 * This function generates a nonce field for resending tracking code. The nonce field is used for security purposes to verify that the request is coming from a trusted source.
	 *
	 * @return void
	 */
	public function add_resend_trakking_nonce() {
		wp_nonce_field( 'virtuaria_resend_trakking', 'resend_trakking_nonce' );
	}

	/**
	 * Ensures tracking code compatibility by retrieving the tracking code from order metadata.
	 *
	 * This function checks if a provided value is empty and attempts to retrieve the
	 * tracking code from the '_correios_tracking_code' metadata. If it's still empty,
	 * it retrieves the tracking code from '_infixs_correios_automatico_tracking_code' metadata.
	 *
	 * @param string   $value Initial value of the tracking code.
	 * @param wc_order $order WooCommerce order object.
	 * @return mixed The tracking code from order metadata, if available.
	 */
	public function trakking_code_compatibility( $value, $order ) {
		if ( isset( $this->settings['compatibility_trakking_code'] )
			&& 'yes' === $this->settings['compatibility_trakking_code']
			&& ! $value ) {
			$value = $order->get_meta( '_correios_tracking_code' );
			if ( ! $value ) {
				$value = $order->get_meta( '_infixs_correios_automatico_tracking_code' );
			}

			if ( $value ) {
				$aux       = explode( ',', $value );
				$last_elem = count( $aux ) - 1;
				if ( isset( $aux[ $last_elem ] ) && ! empty( $aux[ $last_elem ] ) ) {
					$value = $aux[ $last_elem ];
				}
			}
		}
		return $value;
	}
}

new Virtuaria_Correios_Trakking();

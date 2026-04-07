<?php
/**
 * Handle Correios Pré Post.
 *
 * @package virtuaria.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pre-Post Correios.
 */
class Virtuaria_Correios_Prepost {
	use Virtuaria_Correios_PrePost_Functions;
	use Virtuaria_Correios_HPO;

	/**
	 * Forbidden order status to be preposted.
	 *
	 * @var array
	 */
	private $forbidden_order_status = array(
		'pending',
		'cancelled',
		'failed',
	);

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		add_action(
			'init',
			array( $this, 'enqueue_actions_metabox' ),
			20
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'order_page_create_prepost' )
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'order_page_print_label' )
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'order_page_print_dace' )
		);

		add_action(
			'woocommerce_admin_order_actions_end',
			array( $this, 'preposted_order_actions_icon' )
		);

		add_action(
			'woocommerce_order_status_changed',
			array( $this, 'generate_automatic_prepost' ),
			10,
			4
		);
	}

	/**
	 * A function to enqueue actions metabox.
	 */
	public function enqueue_actions_metabox() {
		$screen = $this->get_meta_boxes_screen();
		add_action(
			'add_meta_boxes_' . $screen,
			array( $this, 'prepost_meta_box' ),
		);

		if ( 'shop_order' !== $screen ) {
			add_action(
				'add_meta_boxes_shop_order',
				array( $this, 'prepost_meta_box' ),
			);
		}

		if ( 'woocommerce_page_wc-orders' !== $screen ) {
			add_action(
				'add_meta_boxes_woocommerce_page_wc-orders',
				array( $this, 'prepost_meta_box' ),
			);
		}
	}

	/**
	 * Add meta box.
	 */
	public function prepost_meta_box() {
		add_meta_box(
			'virtuaria-correios-prepost',
			'Correios Etiqueta com NF',
			array( $this, 'prepost_meta_box_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);

		add_meta_box(
			'virtuaria-correios-prepost-content-declare',
			'Correios Etiqueta sem NF',
			array( $this, 'declare_meta_box_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);

		add_meta_box(
			'virtuaria-correios-prepost-print-label',
			'Correios Imprimir Etiqueta',
			array( $this, 'print_label_meta_box_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);

		add_meta_box(
			'virtuaria-correios-dace',
			'Correios DACE (DC-e)',
			array( $this, 'dace_meta_box_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);
	}

	/**
	 * A function to display meta box content for pre-postage creation.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function prepost_meta_box_content( $post ) {
		$order = $post instanceof WC_Order
			? $post
			: wc_get_order( $post->ID );

		if ( $order
			&& ! in_array(
				$order->get_status(),
				$this->forbidden_order_status,
				true
			)
		) {
			$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
				? array_values( $order->get_shipping_methods() )[0]
				: false;

			$methods_allowed = array(
				'virtuaria-correios-sedex',
				'virtuaria-correios-pac',
			);
			if ( $shipping_method instanceof WC_Order_Item_Shipping
				&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) {
				?>
				<div class="shipping-prepost ticket">
					<p>
						Preencha <b>um dos campos</b> abaixo:
					</p>
					<p>
						<input type="text" name="nf_number" id="nf-number" placeholder="Número da nota fiscal" />
					</p>
					<p>
						<input type="text" name="nf_key" id="nf-key" placeholder="Chave da nota fiscal" maxlength="44" />
					</p>
					<button
						class="prepost button button-primary ticket"
						id="prepost-<?php echo esc_attr( $order->get_id() ); ?>"
						data-orderid="<?php echo esc_attr( $order->get_id() ); ?>"
						data-instanceid="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>"
						style="margin-bottom: 10px;"
						data-nfkey=""
						data-nfnumber=")">
							Criar Etiqueta ( Pré-Postagem )
					</button>
					<small
						style="display:block;
						background-color: #f8f8f8;
						border-left: 4px solid #ffca28;
						padding: 10px;
						font-size: 0.9em;
						color: #555;
						margin-bottom: 20px;" class="avisodeclaracao">
						Cria etiqueta de pré-postagem <b>com informações da nota fiscal</b>. O link da etiqueta ( pré-postagem ) será salvo nas notas do pedido.
					</small>
					<input type="hidden" name="correios_order_id_ticket" id="correios_order_id"/>
					<input type="hidden" name="correios_instance_id" id="correios_instance_id" value="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>" />
					<script>
						jQuery(document).ready(function($) {
							$('#prepost-<?php echo esc_attr( $order->get_id() ); ?>.ticket').on('click', function(e) {
								if ( $('#nf-key').val() || $('#nf-number').val() ) {
									console.log('clicked');
									$(this).parent().find('#correios_order_id').val('<?php echo esc_attr( $order->get_id() ); ?>');
								} else {
									alert('Preencha pelo menos um dos campos com dados da NFe.');
									$('#nf-key,#nf-number').css('border', '1px solid red');
									e.preventDefault();
								}
							});
							$('#nf-key').on('keydown', function() {
								$(this).val($(this).val().replace(/\D/, ''));
							});
							$('#nf-number').on('keydown', function() {
								$(this).val($(this).val().replace(/\D/, ''));
							});

						});
					</script>
				</div>
				<?php
				wp_nonce_field( 'create_prepost', 'create_prepost_nonce' );

				return;
			}
		}

		printf(
			esc_html(
				__(
					'A emissão de pré-postagens não está disponível para pedidos pendentes, falha ou cancelados. Somente pedidos cujo método de envio seja Virtuaria Correios serão aceitos.',
					'virtuaria-correios'
				)
			)
		);
	}

	/**
	 * A function to display meta box content for pre-postage declare content creation.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function declare_meta_box_content( $post ) {
		$order = $post instanceof WC_Order
			? $post
			: wc_get_order( $post->ID );

		if ( $order
			&& ! in_array(
				$order->get_status(),
				$this->forbidden_order_status,
				true
			)
		) {
			$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
				? array_values( $order->get_shipping_methods() )[0]
				: false;

			$methods_allowed = array(
				'virtuaria-correios-sedex',
				'virtuaria-correios-pac',
			);
			if ( $shipping_method instanceof WC_Order_Item_Shipping
				&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) {
				?>
				<div class="shipping-prepost declare">
					<button
						class="prepost button button-primary declare"
						id="prepost-<?php echo esc_attr( $order->get_id() ); ?>"
						data-orderid="<?php echo esc_attr( $order->get_id() ); ?>"
						data-instanceid="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>"
						style="margin-bottom: 10px;">
						Criar Etiqueta ( Pré-Postagem )
					</button>
					<small
						style="display:block;
						background-color: #f8f8f8;
						border-left: 4px solid #ffca28;
						padding: 10px;
						font-size: 0.9em;
						color: #555;
						margin-bottom: 20px;" class="avisodeclaracao">
						Cria a pré-postagem <strong>sem Nota Fiscal</strong> e solicita a emissão da DC-e pelos Correios. A etiqueta poderá ficar pendente; use a meta box <strong>Imprimir Etiqueta</strong> para gerar o PDF quando liberado.
					</small>
					<input type="hidden" name="correios_order_id" id="correios_order_id" />
					<input type="hidden" name="correios_instance_id" id="correios_instance_id" value="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>" />
					<script>
						jQuery(document).ready(function($) {
							$('#prepost-<?php echo esc_attr( $order->get_id() ); ?>.declare').on('click', function(e) {
								$(this).parent().find('#correios_order_id').val('<?php echo esc_attr( $order->get_id() ); ?>');
								$('#prepost-<?php echo esc_attr( $order->get_id() ); ?>.ticket').find('#nf-key').val('');
								$('#prepost-<?php echo esc_attr( $order->get_id() ); ?>.ticket').find('#nf-number').val('');
							});
						});
					</script>
				</div>
				<?php
				wp_nonce_field( 'create_prepost', 'create_prepost_nonce' );

				return;
			}
		}

		printf(
			esc_html(
				__(
					'A emissão de pré-postagens não está disponível para pedidos pendentes, falha ou cancelados. Somente pedidos cujo método de envio seja Virtuaria Correios serão aceitos',
					'virtuaria-correios'
				)
			)
		);
	}

	/**
	 * A function to display meta box content for print label.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function print_label_meta_box_content( $post ) {
		$order = $post instanceof WC_Order
			? $post
			: wc_get_order( $post->ID );

		if ( ! $order
			|| in_array(
				$order->get_status(),
				$this->forbidden_order_status,
				true
			)
		) {
			printf(
				esc_html(
					__(
						'A impressão da etiqueta não está disponível para pedidos pendentes, falha ou cancelados.',
						'virtuaria-correios'
					)
				)
			);
			return;
		}

		$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
			? array_values( $order->get_shipping_methods() )[0]
			: false;

		$methods_allowed = array(
			'virtuaria-correios-sedex',
			'virtuaria-correios-pac',
		);
		if ( ! ( $shipping_method instanceof WC_Order_Item_Shipping
			&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) ) {
			printf(
				esc_html(
					__(
						'A impressão de etiqueta está disponível somente para pedidos cujo método de envio seja Virtuaria Correios.',
						'virtuaria-correios'
					)
				)
			);
			return;
		}

		$ticket_url = $order->get_meta( '_virtuaria_correios_ticket_url' );
		if ( $ticket_url ) {
			printf(
				'<a href="%s" class="print-ticket" target="_blank">%s</a>',
				esc_url( $ticket_url ),
				esc_html__( 'Imprimir etiqueta', 'virtuaria-correios' )
			);
			return;
		}

		if ( ! $order->get_meta( '_virtuaria_correios_prepost_id' ) ) {
			printf(
				esc_html(
					__(
						'A pré-postagem ainda não foi criada para este pedido.',
						'virtuaria-correios'
					)
				)
			);
			return;
		}
		?>
		<div class="shipping-prepost-print">
			<button
				class="print-prepost-label button button-primary"
				id="print-prepost-label-<?php echo esc_attr( $order->get_id() ); ?>"
				data-orderid="<?php echo esc_attr( $order->get_id() ); ?>"
				data-instanceid="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>"
				style="margin-bottom: 10px;">
					Imprimir Etiqueta
			</button>
			<small
				style="display:block;
				background-color: #f8f8f8;
				border-left: 4px solid #ffca28;
				padding: 10px;
				font-size: 0.9em;
				color: #555;
				margin-bottom: 20px;">
				A etiqueta só poderá ser gerada quando o status da pré-postagem estiver <strong>Pré-postado</strong>.
			</small>
			<input type="hidden" name="correios_print_label_order_id" id="correios_print_label_order_id" />
			<input type="hidden" name="correios_print_label_instance_id" id="correios_print_label_instance_id" value="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>" />
			<script>
				jQuery(document).ready(function($) {
					$('#print-prepost-label-<?php echo esc_attr( $order->get_id() ); ?>').on('click', function() {
						$('#correios_print_label_order_id').val('<?php echo esc_attr( $order->get_id() ); ?>');
					});
				});
			</script>
		</div>
		<?php
		wp_nonce_field( 'print_prepost_label', 'print_prepost_label_nonce' );
	}

	/**
	 * A function to display meta box content for DACE printing.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function dace_meta_box_content( $post ) {
		$order = $post instanceof WC_Order
			? $post
			: wc_get_order( $post->ID );

		if ( ! $order
			|| in_array(
				$order->get_status(),
				$this->forbidden_order_status,
				true
			)
		) {
			printf(
				esc_html(
					__(
						'A impressão da DACE não está disponível para pedidos pendentes, falha ou cancelados.',
						'virtuaria-correios'
					)
				)
			);
			return;
		}

		$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
			? array_values( $order->get_shipping_methods() )[0]
			: false;

		$methods_allowed = array(
			'virtuaria-correios-sedex',
			'virtuaria-correios-pac',
		);
		if ( ! ( $shipping_method instanceof WC_Order_Item_Shipping
			&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) ) {
			printf(
				esc_html(
					__(
						'A impressão da DACE está disponível somente para pedidos cujo método de envio seja Virtuaria Correios.',
						'virtuaria-correios'
					)
				)
			);
			return;
		}

		$emite_dce = 'yes' === $order->get_meta( '_virtuaria_correios_prepost_emite_dce' );
		$dace_url  = $order->get_meta( '_virtuaria_correios_dace_url' );

		if ( $dace_url ) {
			printf(
				'<a href="%s" class="print-dace" target="_blank">%s</a>',
				esc_url( $dace_url ),
				esc_html__( 'Imprimir DACE', 'virtuaria-correios' )
			);
			return;
		}

		if ( ! $emite_dce ) {
			printf(
				esc_html(
					__(
						'A DACE só pode ser impressa quando a DC-e é gerada pelos Correios (pré-postagem sem NF).',
						'virtuaria-correios'
					)
				)
			);
			return;
		}

		if ( ! $order->get_meta( '_virtuaria_correios_prepost_id' ) ) {
			printf(
				esc_html(
					__(
						'A pré-postagem ainda não foi criada para este pedido.',
						'virtuaria-correios'
					)
				)
			);
			return;
		}
		?>
		<div class="shipping-dace">
			<button
				class="print-dace button button-primary"
				id="print-dace-<?php echo esc_attr( $order->get_id() ); ?>"
				data-orderid="<?php echo esc_attr( $order->get_id() ); ?>"
				data-instanceid="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>"
				style="margin-bottom: 10px;">
					Imprimir DACE
			</button>
			<small
				style="display:block;
				background-color: #f8f8f8;
				border-left: 4px solid #ffca28;
				padding: 10px;
				font-size: 0.9em;
				color: #555;
				margin-bottom: 20px;">
				A DACE é obrigatória para postagem de pedidos sem NF. Imprima e fixe no objeto.
			</small>
			<input type="hidden" name="correios_dace_order_id" id="correios_dace_order_id" />
			<input type="hidden" name="correios_dace_instance_id" id="correios_dace_instance_id" value="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>" />
			<script>
				jQuery(document).ready(function($) {
					$('#print-dace-<?php echo esc_attr( $order->get_id() ); ?>').on('click', function() {
						$('#correios_dace_order_id').val('<?php echo esc_attr( $order->get_id() ); ?>');
					});
				});
			</script>
		</div>
		<?php
		wp_nonce_field( 'print_dace', 'print_dace_nonce' );
	}

	/**
	 * Create prepost from order page for the given post ID.
	 *
	 * @param int $post_id The ID of the post.
	 */
	public function order_page_create_prepost( $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $order
			&& isset(
				$_POST['create_prepost_nonce'],
				$_POST['correios_instance_id'],
			)
			&& (
				isset( $_POST['correios_order_id_ticket'] )
				|| isset( $_POST['correios_order_id'] )
			)
			&& is_admin()
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['create_prepost_nonce']
					)
				),
				'create_prepost'
			)
			&& (
				intval( $_POST['correios_order_id'] ) > 0
				|| intval( $_POST['correios_order_id_ticket'] ) > 0
			)
			&& intval( $_POST['correios_instance_id'] )
		) {
			$this->create_prepost( $order );
		}
	}

	/**
	 * Print label from order page for the given post ID.
	 *
	 * @param int $post_id The ID of the post.
	 */
	public function order_page_print_label( $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $order
			&& isset(
				$_POST['print_prepost_label_nonce'],
				$_POST['correios_print_label_order_id'],
				$_POST['correios_print_label_instance_id']
			)
			&& is_admin()
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['print_prepost_label_nonce']
					)
				),
				'print_prepost_label'
			)
			&& intval( $_POST['correios_print_label_instance_id'] ) > 0
			&& intval( $_POST['correios_print_label_order_id'] )
		) {
			$this->print_prepost_label( $order );
		}
	}

	/**
	 * Print DACE from order page for the given post ID.
	 *
	 * @param int $post_id The ID of the post.
	 */
	public function order_page_print_dace( $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $order
			&& isset(
				$_POST['print_dace_nonce'],
				$_POST['correios_dace_order_id'],
				$_POST['correios_dace_instance_id']
			)
			&& is_admin()
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['print_dace_nonce']
					)
				),
				'print_dace'
			)
			&& intval( $_POST['correios_dace_instance_id'] ) > 0
			&& intval( $_POST['correios_dace_order_id'] )
		) {
			$this->print_dace( $order );
		}
	}

	/**
	 * Create prepost data for the given post ID.
	 *
	 * @param wc_order $order the order.
	 * @return bool
	 */
	public function create_prepost( $order ) {
		if ( ! $order ) {
			return false;
		}

		$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
			? array_values( $order->get_shipping_methods() )[0]
			: false;

		$methods_allowed = array(
			'virtuaria-correios-sedex',
			'virtuaria-correios-pac',
		);
		if ( $shipping_method instanceof WC_Order_Item_Shipping
			&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) {
			$shipping = new Virtuaria_Correios_Sedex( $shipping_method->get_instance_id() );

			if ( ! $shipping ) {
				$order->add_order_note(
					__( 'Virtuaria Correios: Pré-Postagem não criada. Método de entrega não disponível.', 'virtuaria-correios' )
				);
				return;
			}

			$api = new Virtuaria_Correios_API(
				$shipping->log ? $shipping->log : null,
				$shipping->enviroment
			);

			$args   = $this->get_formatted_prepost( $order, $shipping );
			$has_nf = isset( $args['chaveNFe'] ) || isset( $args['numeroNotaFiscal'] );

			if ( ! $has_nf && $order->get_meta( '_virtuaria_correios_prepost_id' ) ) {
				$order->add_order_note(
					__( 'Virtuaria Correios: A pré-postagem já foi criada. Utilize a meta box "Imprimir Etiqueta" para gerar o PDF quando estiver liberado.', 'virtuaria-correios' )
				);
				return true;
			}

			$prepost = $api->create_prepost( $args );

			if ( $prepost ) {
				$settings  = Virtuaria_WPMU_Correios_Settings::get_settings();
				$emite_dce = isset( $args['emiteDCe'] ) && 'S' === $args['emiteDCe'];

				if ( isset( $prepost['id'] ) ) {
					$order->update_meta_data( '_virtuaria_correios_prepost_id', $prepost['id'] );
				}
				if ( isset( $prepost['descStatusAtual'] ) ) {
					$order->update_meta_data( '_virtuaria_correios_prepost_status', $prepost['descStatusAtual'] );
				}
				if ( isset( $prepost['idCorreios'] ) ) {
					$order->update_meta_data( '_virtuaria_correios_prepost_id_correios', $prepost['idCorreios'] );
				}
				if ( isset( $prepost['numeroCartaoPostagem'] ) ) {
					$order->update_meta_data( '_virtuaria_correios_prepost_cartao', $prepost['numeroCartaoPostagem'] );
				}

				$order->update_meta_data( '_virtuaria_correios_prepost_has_nf', $has_nf ? 'yes' : 'no' );
				$order->update_meta_data( '_virtuaria_correios_prepost_emite_dce', $emite_dce ? 'yes' : 'no' );

				if ( isset( $prepost['codigoObjeto'] ) ) {
					$order->update_meta_data(
						'_virt_correios_trakking_code',
						$prepost['codigoObjeto']
					);

					$order->add_order_note(
						sprintf(
							/* translators: %1$s: trakking code. */
							__( 'Virtuaria Correios: Novo código de rastreamento (%1$s) adicionado ao pedido.', 'virtuaria-correios' ),
							$prepost['codigoObjeto']
						),
						false,
						true
					);

					if ( isset( $settings['serial'], $settings['authenticated'] )
						&& $settings['serial']
						&& $settings['authenticated']
						&& (
							! isset( $settings['disable_email_tracking_code'] )
							|| 'yes' !== $settings['disable_email_tracking_code']
						)
					) {
						Virtuaria_Correios_Trakking::send_trakking_notification(
							$order,
							$prepost['codigoObjeto']
						);
					}
				}

				$order->save();

				if ( ! $has_nf ) {
					$order->add_order_note(
						__( 'Virtuaria Correios: Pré-postagem criada com sucesso. A DC-e será emitida pelos Correios e a etiqueta ficará disponível quando o status estiver Pré-postado. Use a meta box "Imprimir Etiqueta" para gerar o PDF e "Imprimir DACE" para fixar no objeto.', 'virtuaria-correios' )
					);
					return true;
				}

				$gen_label = $api->generate_label(
					array(
						'idCorreios'           => isset( $prepost['idCorreios'] )
							? $prepost['idCorreios']
							: $shipping->username,
						'numeroCartaoPostagem' => isset( $prepost['numeroCartaoPostagem'] )
							? $prepost['numeroCartaoPostagem']
							: $shipping->post_card,
						'tipoRotulo'           => 'P',
						'formatoRotulo'        => 'ET',
						'imprimeRemetente'     => 'S',
						'idsPrePostagem'       => array(
							$prepost['id'],
						),
						'layoutImpressao'      => isset( $settings['print_format'] ) && $settings['print_format']
							? $settings['print_format']
							: 'PADRAO',
						'username'             => $shipping->username,
						'password'             => $shipping->password,
						'post_card'            => $shipping->post_card,
					)
				);

				if ( $gen_label ) {
					sleep( 2 ); // Delay to waiting for the API generation Label from idRecibo.
					$label = $api->get_label(
						array(
							'username'  => $shipping->username,
							'password'  => $shipping->password,
							'post_card' => $shipping->post_card,
							'idRecibo'  => $gen_label,
						)
					);

					if ( $label ) {
						$order->update_meta_data( '_virtuaria_correios_ticket_label', $label );

						$file_url = $this->upload_label( $label, $order->get_id() );

						$order->update_meta_data( '_virtuaria_correios_ticket_url', $file_url );
						$order->update_meta_data(
							'_virtuaria_correios_ticket_nfe_key',
							isset( $args['chaveNFe'] )
								? $args['chaveNFe']
								: false
						);
						$order->update_meta_data(
							'_virtuaria_correios_ticket_nfe_number',
							isset( $args['numeroNotaFiscal'] )
								? $args['numeroNotaFiscal']
								: false
						);

						$order->save();

						if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
							remove_filter(
								'woocommerce_new_order_note_data',
								'\\order\\limit_characters_order_note'
							);
						}
						$order->add_order_note(
							sprintf(
								/* translators: %1$s: chave da NFe, %2$s: número da NFe, %3$s: ticket link. */
								__(
									'Virtuaria Correios: Sua etiqueta foi gerada com sucesso.<br><br>Chave da NFe: %1$s<br><br>Número da NFe: %2$s<br><br><a href="%3$s" target="_blank">Imprimir etiqueta</a>',
									'virtuaria-correios'
								),
								isset( $args['chaveNFe'] ) ? $args['chaveNFe'] : 'Não informado',
								isset( $args['numeroNotaFiscal'] ) ? $args['numeroNotaFiscal'] : 'Não informado',
								$file_url
							),
							0,
							true
						);

						if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
							add_filter(
								'woocommerce_new_order_note_data',
								'\\order\\limit_characters_order_note'
							);
						}

						return true;
					}
				}
			}

			if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
				remove_filter(
					'woocommerce_new_order_note_data',
					'\\order\\limit_characters_order_note'
				);
			}

			$erros = get_transient( 'virtuaria_correios_prepost_error' );
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: errors, %2$s: ticket link, %3$s: chave da NFe, %4$s: número da NFe. */
					__( 'Virtuaria Correios: Falha ao gerar etiqueta de postagem: %1$s Consulte o %2$s para mais informações.<br><br>Chave da NFe: %3$s<br><br>Número da NFe: %4$s', 'virtuaria-correios' ),
					'<br><br>-' . ( $erros && is_array( $erros ) ? implode( '<br>-', $erros ) : '' ) . '</b><br><br>',
					'<a target="_blank" href="' . admin_url( 'admin.php?page=wc-status&tab=logs&source=virtuaria-correios' ) . '">log</a>',
					isset( $args['chaveNFe'] ) ? $args['chaveNFe'] : 'Não informado',
					isset( $args['numeroNotaFiscal'] ) ? $args['numeroNotaFiscal'] : 'Não informado',
				),
				0,
				true
			);

			if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
				add_filter(
					'woocommerce_new_order_note_data',
					'\\order\\limit_characters_order_note'
				);
			}

			return false;
		}
	}

	/**
	 * Generate label PDF for an existing prepost.
	 *
	 * @param wc_order $order the order.
	 * @return bool
	 */
	public function print_prepost_label( $order ) {
		if ( ! $order ) {
			return false;
		}

		if ( $order->get_meta( '_virtuaria_correios_ticket_url' ) ) {
			return true;
		}

		$prepost_id = $order->get_meta( '_virtuaria_correios_prepost_id' );
		if ( ! $prepost_id ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a etiqueta porque a pré-postagem ainda não foi criada.', 'virtuaria-correios' )
			);
			return false;
		}

		$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
			? array_values( $order->get_shipping_methods() )[0]
			: false;

		$methods_allowed = array(
			'virtuaria-correios-sedex',
			'virtuaria-correios-pac',
		);
		if ( ! ( $shipping_method instanceof WC_Order_Item_Shipping
			&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a etiqueta. Método de entrega não disponível.', 'virtuaria-correios' )
			);
			return false;
		}

		$shipping = new Virtuaria_Correios_Sedex( $shipping_method->get_instance_id() );
		if ( ! $shipping ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a etiqueta. Método de entrega não disponível.', 'virtuaria-correios' )
			);
			return false;
		}

		$api = new Virtuaria_Correios_API(
			$shipping->log ? $shipping->log : null,
			$shipping->enviroment
		);

		$settings = Virtuaria_WPMU_Correios_Settings::get_settings();

		$gen_label = $api->generate_label(
			array(
				'idCorreios'           => $order->get_meta( '_virtuaria_correios_prepost_id_correios' )
					? $order->get_meta( '_virtuaria_correios_prepost_id_correios' )
					: $shipping->username,
				'numeroCartaoPostagem' => $order->get_meta( '_virtuaria_correios_prepost_cartao' )
					? $order->get_meta( '_virtuaria_correios_prepost_cartao' )
					: $shipping->post_card,
				'tipoRotulo'           => 'P',
				'formatoRotulo'        => 'ET',
				'imprimeRemetente'     => 'S',
				'idsPrePostagem'       => array(
					$prepost_id,
				),
				'layoutImpressao'      => isset( $settings['print_format'] ) && $settings['print_format']
					? $settings['print_format']
					: 'PADRAO',
				'username'             => $shipping->username,
				'password'             => $shipping->password,
				'post_card'            => $shipping->post_card,
			)
		);

		if ( $gen_label ) {
			sleep( 2 ); // Delay to waiting for the API generation Label from idRecibo.
			$label = $api->get_label(
				array(
					'username'  => $shipping->username,
					'password'  => $shipping->password,
					'post_card' => $shipping->post_card,
					'idRecibo'  => $gen_label,
				)
			);

			if ( $label ) {
				$order->update_meta_data( '_virtuaria_correios_ticket_label', $label );

				$file_url = $this->upload_label( $label, $order->get_id() );

				$order->update_meta_data( '_virtuaria_correios_ticket_url', $file_url );
				$order->save();

				if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
					remove_filter(
						'woocommerce_new_order_note_data',
						'\\order\\limit_characters_order_note'
					);
				}
				$order->add_order_note(
					sprintf(
						/* translators: %1$s: ticket link. */
						__( 'Virtuaria Correios: Etiqueta liberada e pronta para impressão. <a href="%1$s" target="_blank">Imprimir etiqueta</a>', 'virtuaria-correios' ),
						$file_url
					),
					0,
					true
				);

				if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
					add_filter(
						'woocommerce_new_order_note_data',
						'\\order\\limit_characters_order_note'
					);
				}

				return true;
			}
		}

		if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
			remove_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
		}

		$erros = get_transient( 'virtuaria_correios_prepost_error' );
		if ( $erros && is_array( $erros ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: errors. */
					__( 'Virtuaria Correios: Falha ao imprimir etiqueta: %1$s', 'virtuaria-correios' ),
					'<br><br>-' . implode( '<br>-', $erros ) . '</b><br><br>'
				),
				0,
				true
			);
		}

		if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
			add_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
		}

		return false;
	}

	/**
	 * Generate DACE PDF for an existing DC-e.
	 *
	 * @param wc_order $order the order.
	 * @return bool
	 */
	public function print_dace( $order ) {
		if ( ! $order ) {
			return false;
		}

		if ( $order->get_meta( '_virtuaria_correios_dace_url' ) ) {
			return true;
		}

		if ( 'yes' !== $order->get_meta( '_virtuaria_correios_prepost_emite_dce' ) ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a DACE porque a DC-e não foi gerada pelos Correios.', 'virtuaria-correios' )
			);
			return false;
		}

		$prepost_id = $order->get_meta( '_virtuaria_correios_prepost_id' );
		$codigo_objeto = $order->get_meta( '_virt_correios_trakking_code' );
		if ( ! $prepost_id && ! $codigo_objeto ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a DACE porque não há pré-postagem vinculada ao pedido.', 'virtuaria-correios' )
			);
			return false;
		}

		$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
			? array_values( $order->get_shipping_methods() )[0]
			: false;

		$methods_allowed = array(
			'virtuaria-correios-sedex',
			'virtuaria-correios-pac',
		);
		if ( ! ( $shipping_method instanceof WC_Order_Item_Shipping
			&& in_array( $shipping_method->get_method_id(), $methods_allowed, true ) ) ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a DACE. Método de entrega não disponível.', 'virtuaria-correios' )
			);
			return false;
		}

		$shipping = new Virtuaria_Correios_Sedex( $shipping_method->get_instance_id() );
		if ( ! $shipping ) {
			$order->add_order_note(
				__( 'Virtuaria Correios: Não foi possível imprimir a DACE. Método de entrega não disponível.', 'virtuaria-correios' )
			);
			return false;
		}

		$api = new Virtuaria_Correios_API(
			$shipping->log ? $shipping->log : null,
			$shipping->enviroment
		);

		$tipo_dace = apply_filters( 'virtuaria_correios_dace_tipo', 'C', $order );

		$request = array(
			'tipoDace'       => $tipo_dace,
			'codigosObjetos' => $codigo_objeto ? array( $codigo_objeto ) : array(),
			'idsPrePostagens' => $prepost_id ? array( $prepost_id ) : array(),
			'username'       => $shipping->username,
			'password'       => $shipping->password,
			'post_card'      => $shipping->post_card,
		);

		$response = $api->print_dace( $request );
		if ( $response && is_array( $response ) ) {
			$dados = null;
			if ( isset( $response['dados'] ) ) {
				$dados = $response['dados'];
			} else {
				$first = reset( $response );
				if ( is_array( $first ) && isset( $first['dados'] ) ) {
					$dados = $first['dados'];
				}
			}

			if ( $dados ) {
				$file_url = $this->upload_dace( $dados, $order->get_id() );
				if ( $file_url ) {
					$order->update_meta_data( '_virtuaria_correios_dace_url', $file_url );
					$order->save();

					if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
						remove_filter(
							'woocommerce_new_order_note_data',
							'\\order\\limit_characters_order_note'
						);
					}
					$order->add_order_note(
						sprintf(
							/* translators: %1$s: DACE link. */
							__( 'Virtuaria Correios: DACE gerada com sucesso. <a href="%1$s" target="_blank">Imprimir DACE</a>', 'virtuaria-correios' ),
							$file_url
						),
						0,
						true
					);

					if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
						add_filter(
							'woocommerce_new_order_note_data',
							'\\order\\limit_characters_order_note'
						);
					}

					return true;
				}
			}
		}

		if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
			remove_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
		}

		$erros = get_transient( 'virtuaria_correios_prepost_error' );
		if ( $erros && is_array( $erros ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: errors. */
					__( 'Virtuaria Correios: Falha ao imprimir DACE: %1$s', 'virtuaria-correios' ),
					'<br><br>-' . implode( '<br>-', $erros ) . '</b><br><br>'
				),
				0,
				true
			);
		}

		if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
			add_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
		}

		return false;
	}

	/**
	 * Upload label and generate link to print.
	 *
	 * @param string $label    the label.
	 * @param string $filename file name.
	 */
	public function upload_label( $label, $filename ) {
		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'virtuaria_correios';
		wp_mkdir_p( $uploads_dir );

		$bin = base64_decode( $label, true );

		$file_path = $uploads_dir . '/' . $filename . '.pdf';

		WP_Filesystem();
		global $wp_filesystem;

		$wp_filesystem->put_contents( $file_path, $bin );

		return wp_upload_dir()['baseurl'] . '/virtuaria_correios/' . $filename . '.pdf';
	}

	/**
	 * Upload DACE and generate link to print.
	 *
	 * @param string $dace     the DACE (base64).
	 * @param string $filename file name.
	 * @return string|false
	 */
	public function upload_dace( $dace, $filename ) {
		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'virtuaria_correios';
		wp_mkdir_p( $uploads_dir );

		$clean_dace = preg_replace( '/\s+/', '', $dace );
		$bin        = base64_decode( $clean_dace, true );
		if ( ! $bin ) {
			$bin = base64_decode( $clean_dace, false );
		}
		if ( ! $bin ) {
			return false;
		}

		$file_path = $uploads_dir . '/dace-' . $filename . '.pdf';

		WP_Filesystem();
		global $wp_filesystem;

		$wp_filesystem->put_contents( $file_path, $bin );

		return wp_upload_dir()['baseurl'] . '/virtuaria_correios/dace-' . $filename . '.pdf';
	}

	/**
	 * Display icon in order who generated the prepost.
	 *
	 * @param wc_order $order order.
	 */
	public function preposted_order_actions_icon( $order ) {
		$ticket_url = $order->get_meta( '_virtuaria_correios_ticket_url' );
		if ( $ticket_url ) {
			echo '<a href="' . esc_url( $ticket_url );
			echo '" target="_blank" style="display:inline-block;vertical-align:middle;margin-left:3px;border:1px solid #2271b1;border-radius:3px;line-height:initial;">';
			echo '<img src="' . esc_url( VIRTUARIA_CORREIOS_URL );
			echo 'admin/images/ticket.png" alt="Etiqueta de entrega gerada"';
			echo ' title="Etiqueta de entrega gerada" width="26" height="26" />';
			echo '</a>';
		}
	}

	/**
	 * Create prepost automaticaly when order reach processing or completed status.
	 *
	 * @param int      $order_id   The order ID.
	 * @param string   $old_status The old status.
	 * @param string   $new_status The new status.
	 * @param WC_Order $order      The order object.
	 */
	public function generate_automatic_prepost( $order_id, $old_status, $new_status, $order ) {
		$settings = Virtuaria_WPMU_Correios_Settings::get_settings();
		if ( in_array( $new_status, array( 'processing', 'completed' ), true )
			&& isset( $settings['serial'], $settings['automatic_prepost'], $settings['authenticated'] )
			&& $settings['serial']
			&& 'yes' === $settings['automatic_prepost']
			&& $settings['authenticated']
			&& ! $order->get_meta( '_virtuaria_correios_ticket_url' ) ) {
			$this->create_prepost( $order );
		}
	}
}

new Virtuaria_Correios_PrePost();

<?php
/**
 * Handle Correios content declaration.
 *
 * @package virtuaria/correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ckass definition.
 */
class Virtuaria_Correios_Content_Declaration {
	use Virtuaria_Correios_HPO;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action(
			'init',
			array( $this, 'enqueue_actions_metabox' ),
			20
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'create_declaration' )
		);

		add_action(
			'woocommerce_admin_order_actions_end',
			array( $this, 'declaration_order_actions_icon' )
		);
	}

	/**
	 * A function to enqueue actions metabox.
	 */
	public function enqueue_actions_metabox() {
		add_action(
			'add_meta_boxes_' . $this->get_meta_boxes_screen(),
			array( $this, 'declaration_meta_box' ),
		);
	}

	/**
	 * Add meta box.
	 */
	public function declaration_meta_box() {
		add_meta_box(
			'virtuaria-correios-declaration',
			'Correios Declaração de Conteúdo',
			array( $this, 'declaration_meta_box_content' ),
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
	public function declaration_meta_box_content( $post ) {
		$order = $post instanceof WC_Order
			? $post
			: wc_get_order( $post->ID );

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
			<div class="shipping-declaration ticket">
				<button
					class="declaration button button-primary"
					id="declaration-<?php echo esc_attr( $order->get_id() ); ?>"
					data-orderid="<?php echo esc_attr( $order->get_id() ); ?>"
					data-instanceid="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>"
					style="margin-bottom: 10px;">
						Gerar Declaração
				</button>
				<small
					style="display:block;
					background-color: #f8f8f8;
					border-left: 4px solid #ffca28;
					padding: 10px;
					font-size: 0.9em;
					color: #555;
					margin-bottom: 20px;" class="avisodeclaracao">
					Gera uma declaração de conteúdo com os itens do pedido. O link da declaração será salvo nas notas do pedido.
				</small>
				<input type="hidden" name="correios_declaration_order_id" id="correios_declaration_order_id" />
				<input type="hidden" name="correios_declaration_instance_id" id="correios_declaration_instance_id" value="<?php echo esc_attr( $shipping_method->get_instance_id() ); ?>" />
			</div>
			<script>
				jQuery( document ).ready( function() {
					jQuery( '#declaration-<?php echo esc_attr( $order->get_id() ); ?>' ).on( 'click', function() {
						var orderid = jQuery( this ).data( 'orderid' );
						var instanceid = jQuery( this ).data( 'instanceid' );
						jQuery( '#correios_declaration_order_id' ).val( orderid );	
					});
				});
			</script>
			<?php
			wp_nonce_field( 'create_declaration', 'create_declaration_nonce' );
		} else {
			printf(
				esc_html(
					__(
						'A emissão de declaração de conteúdo está disponível somente para pedidos cujo método de envio seja Virtuaria Correios.',
						'virtuaria-correios'
					)
				)
			);
		}
	}

	/**
	 * Create declaration data for the given post ID.
	 *
	 * @param int $post_id The ID of the post.
	 * @return bool
	 */
	public function create_declaration( $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $order
			&& isset(
				$_POST['create_declaration_nonce'],
				$_POST['correios_declaration_order_id'],
				$_POST['correios_declaration_instance_id']
			)
			&& is_admin()
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['create_declaration_nonce']
					)
				),
				'create_declaration'
			)
			&& intval( $_POST['correios_declaration_instance_id'] ) > 0
			&& intval( $_POST['correios_declaration_order_id'] )
		) {
			$instance_id = sanitize_text_field( wp_unslash( $_POST['correios_declaration_order_id'] ) );
			$shipping    = new Virtuaria_Correios_Sedex( $instance_id );

			if ( ! $shipping ) {
				$order->add_order_note(
					__( 'Virtuaria Correios: Declaração de Conteúdo não criada. Método de entrega não disponível.', 'virtuaria-correios' )
				);
				return;
			}

			require_once VIRTUARIA_CORREIOS_DIR . 'vendor/autoload.php';
			require_once 'class-virtuaria-correios-content-declaration-pdf.php';

			$products = array();
			/**
			 * Get receivers info.
			 *
			 * @var WC_Order_Item_Product $item item from order.
			 */
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product || $product->is_virtual() ) {
					continue;
				}

				$products['itens'][] = array(
					'descricao'  => $product->get_title(),
					'valor'      => 'R$ ' . number_format( $item->get_total(), 2, ',', '.' ),
					'quantidade' => $item->get_quantity(),
				);

				$products['total']  += $item->get_total();
				$products['weight'] += wc_get_weight(
					(float) $product->get_weight(),
					'kg'
				) * $item->get_quantity();
			}

			$products['total'] = 'R$ ' . number_format( $products['total'], 2, ',', '.' );

			$pdf = new Virtuaria_Correios_Content_Declaration_PDF(
				array(
					'nome'        => $shipping->get_setting( 'full_name' ),
					'cpf'         => $shipping->get_setting( 'cpfcnpj' ),
					'endereco'    => $this->get_formated_origin_address( $shipping ),
					'cidade'      => $shipping->get_setting( 'cidade' ),
					'uf'          => $shipping->get_setting( 'estado' ),
					'cep'         => $shipping->get_setting( 'origin' ),
					'bairro'      => $shipping->get_setting( 'bairro' ),
					'complemento' => $shipping->get_setting( 'complemento' ),
				),
				array(
					'nome'        => $order->get_formatted_shipping_full_name(),
					'cpf'         => preg_replace( '/\D/', '', $order->get_meta( '_billing_cpf' ) ),
					'endereco'    => $this->get_formated_destination_address( $order ),
					'cidade'      => $order->get_shipping_city(),
					'uf'          => $order->get_shipping_state(),
					'cep'         => preg_replace( '/\D/', '', $order->get_shipping_postcode() ),
					'bairro'      => $order->get_meta( '_billing_neighborhood' ),
					'complemento' => $order->get_shipping_address_2(),
				),
				$products
			);

			$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'virtuaria_correios';
			wp_mkdir_p( $uploads_dir );

			$filename  = 'declaracao-conteudo-' . $order->get_id();
			$file_path = $uploads_dir . '/' . $filename . '.pdf';

			$pdf->build_pdf(
				VIRTUARIA_CORREIOS_DIR . 'templates/declaracao-de-conteudo.pdf',
				$file_path
			);

			$output_url = wp_upload_dir()['baseurl'] . '/virtuaria_correios/' . $filename . '.pdf?t=' . time();
			$order->update_meta_data(
				'_virtuaria_correios_declaration_url',
				$output_url
			);

			if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
				remove_filter(
					'woocommerce_new_order_note_data',
					'\\order\\limit_characters_order_note'
				);
			}
			$order->add_order_note(
				sprintf(
					/* translators: %s: output url. */
					__( 'Virtuaria Correios: Declaração de Conteúdo criada. <a href="%s" target="_blank">Visualizar PDF</a>', 'virtuaria-correios' ),
					$output_url
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
			$order->save();
			return true;
		}
		return false;
	}

	/**
	 * Format the origin address in the format required by the Correios' API.
	 *
	 * @param Virtuaria_Correios_Sedex $shipping The shipping method.
	 * @return string The formatted address.
	 */
	private function get_formated_origin_address( $shipping ) {
		return $shipping->get_setting( 'logradouro' ) . ', '
			. $shipping->get_setting( 'numero' );
	}

	/**
	 * Format the destination address in the format required by the Correios' API.
	 *
	 * @param WC_Order $order The order.
	 * @return string The formatted address.
	 */
	private function get_formated_destination_address( $order ) {
		return $order->get_shipping_address_1() . ', '
			. $order->get_meta( '_billing_number' );
	}

	/**
	 * Display icon in order who generated the declaration.
	 *
	 * @param wc_order $order order.
	 */
	public function declaration_order_actions_icon( $order ) {
		$declaration_url = $order->get_meta( '_virtuaria_correios_declaration_url' );
		if ( $declaration_url ) {
			echo '<a href="' . esc_url( $declaration_url );
			echo '" target="_blank" style="display:inline-block;vertical-align:middle;margin-left:3px;border:1px solid #2271b1;border-radius:3px;line-height:initial;">';
			echo '<img src="' . esc_url( VIRTUARIA_CORREIOS_URL );
			echo 'admin/images/declaration.png" alt="Declaração de conteúdo gerada"';
			echo ' title="Declaração de conteúdo gerada" width="26" height="26" />';
			echo '</a>';
		}
	}
}

new Virtuaria_Correios_Content_Declaration();

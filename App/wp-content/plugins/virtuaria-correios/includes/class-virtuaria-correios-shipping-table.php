<?php
/**
 * Hanble Shipping Table.
 *
 * @package Virtuaria/Shipping/Correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class to handle Shipping Table.
 */
class Virtuaria_Correios_Shipping_Table extends WP_List_Table {
	/**
	 * Max order item per page.
	 *
	 * @var int
	 */
	private const PER_PAGE = 20;

	/**
	 * Prepost instance.
	 *
	 * @var Virtuaria_Correios_Prepost
	 */
	private $prepost;

	/**
	 * Declaration instance.
	 *
	 * @var Virtuaria_Correios_Content_Declaration
	 */
	private $declaration;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Pedido', 'virtuaria-correios' ),
				'plural'   => __( 'Pedidos', 'virtuaria-correios' ),
				'ajax'     => false,
				'screen'   => 'virtuaria-correios-shipping',
			)
		);

		if ( class_exists( 'Virtuaria_Correios_Prepost' ) ) {
			$this->prepost = new Virtuaria_Correios_Prepost();
		}

		$this->declaration = new Virtuaria_Correios_Content_Declaration();

		$this->settings = Virtuaria_WPMU_Correios_Settings::get_settings();
	}

	/**
	 * Prepares the list of items for displaying.
	 */
	public function prepare_items() {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$orderby = isset( $_GET['orderby'] ) && 'order' === $_GET['orderby']
			? 'order'
			: 'date';

		$order = isset( $_GET['order'] ) && 'asc' === $_GET['order']
			? 'asc'
			: 'desc';

		$orders = $this->get_orders(
			$orderby,
			$order
		);

		$this->set_pagination_args(
			array(
				'total_items' => $this->get_orders(
					$orderby,
					$order,
					true
				),
				'per_page'    => self::PER_PAGE,
			)
		);

		$this->items = $orders;
	}

	/**
	 * Gets a list of columns.
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'order'     => __( 'Pedido', 'virtuaria-correios' ),
			'customer'  => __( 'Cliente', 'virtuaria-correios' ),
			'method'    => __( 'Método', 'virtuaria-correios' ),
			'status'    => __( 'Status', 'virtuaria-correios' ),
			'create_at' => __( 'Data', 'virtuaria-correios' ),
			'actions'   => __( 'Ações', 'virtuaria-correios' ),
		);
		return $columns;
	}

	/**
	 * Output the checkbox column.
	 *
	 * @param array $item A single order item.
	 */
	protected function column_cb( $item ) {
		if ( isset( $item['cb'] ) ) {
			$allowed_html = array(
				'input' => array(
					'type'    => array(),
					'name'    => array(),
					'value'   => array(),
					'checked' => array(),
				),
			);

			echo wp_kses( $item['cb'], $allowed_html );
		}
	}

	/**
	 * Define the sortable columns.
	 *
	 * @return Array
	 */
	public function get_sortable_columns() {
		return array(
			'order'     => array( 'order', true ),
			'create_at' => array( 'date', true ),
		);
	}

	/**
	 * Get all orders.
	 *
	 * @param string $orderby    Order by param.
	 * @param string $order_type DESC or ASC.
	 * @param bool   $only_count Only count.
	 * @return array
	 */
	private function get_orders( $orderby, $order_type, $only_count = false ) {
		$items = array();

		$page   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$search = false;
		if ( isset( $_POST['form_nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['form_nonce'] )
				),
				'bulk_actions'
			)
		) {
			if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
				$search = sanitize_text_field( wp_unslash( $_POST['s'] ) );
			}
		}

		$args = array(
			'status'  => $this->get_search_order_status(
				isset( $_GET['status'] )
					? sanitize_text_field( wp_unslash( $_GET['status'] ) )
					: 'paid'
			),
			'type'    => 'shop_order',
			'orderby' => 'order' === $orderby ? 'ID' : $orderby,
			'order'   => $order_type,
		);

		if ( $search ) {
			$orders = $this->search_orders(
				$search,
				$orderby,
				$order_type,
				$page,
				$only_count
			);
		} else {
			if ( ! $only_count ) {
				$args['limit'] = self::PER_PAGE;
				$args['paged'] = $page;
			} else {
				$args['limit']  = -1;
				$args['return'] = 'ids';
			}
			$orders = wc_get_orders( $args );
		}

		if ( $only_count ) {
			return count( $orders );
		}

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $order ) {
				$items[] = $this->get_formatted_order( $order );
			}
		}

		return $items;
	}

	/**
	 * Return the order status to use when searching for orders.
	 *
	 * If status is 'paid', return array('wc-processing', 'wc-completed').
	 * If status is 'all', return all order statuses except 'wc-pending', 'wc-cancelled', 'wc-failed'.
	 * Otherwise, return the sanitized status.
	 *
	 * @param string $status Order status.
	 *
	 * @return array
	 */
	private function get_search_order_status( $status ) {
		switch ( $status ) {
			case 'paid':
				return array( 'wc-processing', 'wc-completed' );
			case 'all':
				$statuses = wc_get_order_statuses();
				unset( $statuses['wc-pending'] );
				unset( $statuses['wc-cancelled'] );
				unset( $statuses['wc-failed'] );
				return array_keys( $statuses );
			default:
				return array( $status );
		}
	}

	/**
	 * Define what data to show on each column of the table.
	 *
	 * @param array  $item        column data.
	 * @param string $column_name current column name.
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'cb':
			case 'order':
			case 'customer':
			case 'method':
			case 'status':
			case 'create_at':
			case 'actions':
				return $item[ $column_name ];
			default:
				return '-';
		}
	}

	/**
	 * Returns an array with formatted order information.
	 *
	 * @param wc_order $order The order object to format.
	 * @return array An array with the following keys:
	 *               - 'order': The edit post link for the order.
	 *               - 'customer': The formatted shipping address of the order, or '-' if not available.
	 *               - 'method': The tracking code of the order, or empty string if not available.
	 *               - 'create_at': The creation date and time of the order in the format 'd/m/Y H:i:s'.
	 *               - 'status': The status of the order.
	 *               - 'actions': An empty string.
	 */
	private function get_formatted_order( $order ) {
		$order_column = sprintf(
			'<a href="%1$s" title="%2$s">%3$s</a>',
			get_edit_post_link(
				$order->get_id(),
			),
			__( 'Gere, regere e imprima etiquetas na página de detalhes do pedido', 'virtuaria-correios' ),
			'#' . $order->get_id()
		);

		$status_label = wc_get_order_statuses();
		if ( isset( $status_label[ 'wc-' . $order->get_status() ] ) ) {
			$order_column .= '<span class="order-status"><i>Status:</i> ' . $status_label[ 'wc-' . $order->get_status() ] . '</span>';
		}

		if ( $order->get_meta( '_virtuaria_correios_ticket_nfe_key' ) ) {
			$order_column .= '<span class="nfe-key"><i>Chave NFe:</i> ';
			$order_column .= $order->get_meta( '_virtuaria_correios_ticket_nfe_key' ) . '</span>';
		}
		if ( $order->get_meta( '_virtuaria_correios_ticket_nfe_number' ) ) {
			$order_column .= '<span class="nfe-number"><i>Nº NFe:</i> ';
			$order_column .= $order->get_meta( '_virtuaria_correios_ticket_nfe_number' ) . '</span>';
		}

		$order_column .= '<span class="dashicons dashicons-visibility see-products" title="Ver produtos">Produtos</span>';
		$order_column .= '<div class="product-list"><table class="products">';
		$order_column .= '<thead><tr><th>Produtos</th><th>QTD</th></tr></thead>';
		foreach ( $order->get_items() as $item ) {
			$order_column .= sprintf(
				'<tr class="product"><td><span class="title">%s</span><span class="sku">SKU: %s</span></td><td>%s</td></tr>',
				$item->get_name(),
				$item->get_product()
					? $item->get_product()->get_sku()
					: '',
				$item->get_quantity()
			);
		}
		$order_column .= '</table></div>';

		$address = '-';
		if ( method_exists( $order, 'get_formatted_shipping_address' )
			&& $order->get_formatted_shipping_address() ) {
			$address = $order->get_formatted_shipping_address();
		} elseif ( method_exists( $order, 'get_formatted_billing_address' )
			&& $order->get_formatted_billing_address() ) {
			$address = $order->get_formatted_billing_address();
		}

		$trakking_code = $order->get_meta( '_virt_correios_trakking_code' );

		$status = sprintf(
			'<span class="status-icon status-a-enviar">📦 %s</span>',
			__( 'A_ENVIAR', 'virtuaria-correios' )
		);

		if ( 'completed' === $order->get_status() ) {
			$status = sprintf(
				'<span class="status-icon status-entregue">📬 %s</span>',
				__( 'ENTREGUE', 'virtuaria-correios' )
			);
		}

		$is_correios = $order->get_meta( '_virtuaria_correios_shipping_method_info' );
		if ( $is_correios && $trakking_code ) {
			$status = sprintf(
				'<span class="status-icon status-enviado">✓ %s</span>',
				__( 'ENVIADO', 'virtuaria-correios' )
			);
		} elseif ( ! $is_correios && 'completed' !== $order->get_status() ) {
			$status = '-';
		}

		$status = sprintf(
			'<span class="status">%s</span>',
			$status
		);

		if ( isset( $this->settings['parcel_tracking'] ) ) {
			if ( $trakking_code ) {
				$status .= '<a href="https://rastreamento.correios.com.br/app/index.php?objetos=' . esc_attr( $trakking_code ) . '" data-order-id="' . esc_attr( $order->get_id() ) . '"';
				$status .= ' target="_blank" class="tracking-code">' . $trakking_code . '</a>';
				$status .= '<span class="dashicons dashicons-location see-trakking" data-order-id="' . esc_attr( $order->get_id() ) . '" title="Ver rastreamento">Rastreio</span>';
				$status .= '<div class="trakking-order" data-orderid="' . esc_attr( $order->get_id() ) . '"></div>';
				$status .= '<div class="trakking-bg" style="display: none"></div>';
				$status .= wp_nonce_field(
					'trakking-order',
					'trakking-order-nonce',
					false,
					false
				);
			} elseif ( $is_correios && 'completed' !== $order->get_status() ) {
				$status .= '<a href="#"class="add-tracking-code">Adicionar Rastreio</span>';
				$status .= '<div class="new-trakking-code" style="display: none">';
				$status .= '<input minlength="8" type="text" class="input-trakking-code" placeholder="Código" />';
				$status .= '<input type="hidden" class="trakking-order-id" value="' . esc_attr( $order->get_id() ) . '">';
				$status .= wp_nonce_field( 'add-trakking-code', 'trakking-nonce', false, false );
				$status .= '<Button class="button button-primary submit-trakking-code">Adicionar</Button>';
				$status .= '</div>';
			}
		}

		$action = ' - ';
		if ( isset(
			$this->settings['serial'],
			$this->settings['authenticated']
		) && $this->settings['authenticated'] ) {
			$ticket_url  = $order->get_meta( '_virtuaria_correios_ticket_url' );
			$prepost_id  = $order->get_meta( '_virtuaria_correios_prepost_id' );
			$emite_dce   = 'yes' === $order->get_meta( '_virtuaria_correios_prepost_emite_dce' );
			$dace_url    = $order->get_meta( '_virtuaria_correios_dace_url' );
			$instance_id = 0;

			$shipping_method = isset( array_values( $order->get_shipping_methods() )[0] )
				? array_values( $order->get_shipping_methods() )[0]
				: false;
			if ( $shipping_method instanceof WC_Order_Item_Shipping ) {
				$instance_id = $shipping_method->get_instance_id();
			}

			if ( $ticket_url ) {
				$ticket_type = ( $order->get_meta( '_virtuaria_correios_ticket_nfe_number' )
					|| $order->get_meta( '_virtuaria_correios_ticket_nfe_key' ) )
					? 'Etiqueta (com NF)'
					: 'Etiqueta (sem NF)';

				$action = sprintf(
					'<a href="%s" class="print-ticket %s" target="_blank">Imprimir %s</a>',
					$ticket_url,
					strpos( $ticket_type, 'com' ) !== false ? 'with-nfe' : 'without-nfe',
					$ticket_type
				);
			} elseif ( $prepost_id && $instance_id ) {
				$action = sprintf(
					'<a href="%s" class="print-prepost-label" data-orderid="%s" data-instanceid="%s" data-nonce="%s">%s</a>',
					'#',
					$order->get_id(),
					$instance_id,
					wp_create_nonce( 'print_prepost_label' ),
					__( 'Imprimir etiqueta', 'virtuaria-correios' )
				);
			} elseif ( $this->prepost ) {
				ob_start();

				$this->prepost->prepost_meta_box_content( $order );

				$prepost_content = ob_get_clean();

				ob_start();

				$this->prepost->declare_meta_box_content( $order );

				$declare_content = ob_get_clean();

				if ( strpos( $prepost_content, 'shipping-prepost' ) !== false ) {
					$action = sprintf(
						'<a href="%s" class="generate-ticket">%s</a>',
						'#',
						__( 'Gerar etiqueta (com NF)', 'virtuaria-correios' )
					)
					. $prepost_content
					. sprintf(
						'<a href="%s" class="generate-declaration-content" data-orderid="%s">%s</a>',
						'#',
						$order->get_id(),
						__( 'Gerar etiqueta (sem NF)', 'virtuaria-correios' )
					)
					. $declare_content;
				} else {
					$action = '<span class="unavailable">Não disponível<br>para este método</span>';
				}
			}

			if ( strpos( $action, 'unavailable' ) === false ) {
				if ( $emite_dce && $prepost_id && $instance_id ) {
					if ( $dace_url ) {
						$action .= sprintf(
							'<a href="%s" class="print-dace" target="_blank">Imprimir DACE</a>',
							$dace_url
						);
					} else {
						$action .= sprintf(
							'<a href="%s" class="print-dace" data-orderid="%s" data-instanceid="%s" data-nonce="%s">%s</a>',
							'#',
							$order->get_id(),
							$instance_id,
							wp_create_nonce( 'print_dace' ),
							__( 'Imprimir DACE', 'virtuaria-correios' )
						);
					}
				}

				$declaration_url = $order->get_meta( '_virtuaria_correios_declaration_url' );
				if ( $declaration_url ) {
					$action .= sprintf(
						'<a href="%s" class="print-declaration" target="_blank">Imprimir Declaração</a>',
						$declaration_url
					);
				} else {
					ob_start();

					$this->declaration->declaration_meta_box_content( $order );

					$action .= sprintf(
						'<a href="%s" class="generate-declaration">%s</a>',
						'#',
						__( 'Gerar Declaração', 'virtuaria-correios' )
					) . ob_get_clean();
				}
			}
		} else {
			// $action  = '<span class="print-ticket">Imprimir Etiqueta</span>';
			$action = '<span class="generate-ticket">Gere e imprima etiquetas instantaneamente</span>';
			// $action .= '<span class="generate-ticket">Gerar etiqueta <b>(com NF)</b></span>';
			// $action .= '<span class="generate-declaration-content">Gerar etiqueta <b>(sem NF)</b></span>';
			// $action .= '<span class="generate-declaration-content">Gerar Declaração de Conteúdo</span>';
			// $action .= '<span class="only-pro">Disponível na versão PRO</span>';
			$action .= '<a class="pro-link" href="https://virtuaria.com.br/loja/virtuaria-correios/" target="_blank">🌟 Seja Premium</a>';
		}

		$method_text = $this->get_formatted_method( $order );

		$cb = sprintf(
			'<input type="checkbox" name="orders[]" value="%s" />',
			$order->get_id()
		);

		return array(
			'cb'        => $cb,
			'order'     => $order_column,
			'customer'  => $address,
			'method'    => $method_text,
			'create_at' => $order->get_date_created()->date( 'd/m/Y H:i:s' ),
			'status'    => $status,
			'actions'   => $action,
		);
	}

	/**
	 * Search for orders based on the specified criteria.
	 *
	 * @param string $term The search term to filter the results.
	 * @param string $orderby The column to order the results by. Defaults to 'order'.
	 * @param string $order_type The order type for the results. Defaults to 'desc'.
	 * @param int    $current_page The current page number.
	 * @param bool   $only_count Only count.
	 * @return array The array of formatted order items.
	 */
	private function search_orders( $term, $orderby, $order_type, $current_page, $only_count = false ) {
		global $wpdb;

		$items  = array();
		$orders = array();

		$status = "'wc-processing', 'wc-completed'";
		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$status = "'"
				. implode(
					"', '",
					$this->get_search_order_status(
						isset( $_GET['status'] )
							? sanitize_text_field( wp_unslash( $_GET['status'] ) )
							: 'paid'
					)
				) . "'";
		}

		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
			$orderby    = 'order' === $orderby ? 'o.id' : 'o.date_created_gmt';
			$order_type = 'asc' === $order_type ? 'asc' : 'desc';

			$orders = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT(o.id) FROM {$wpdb->prefix}wc_orders AS o
					INNER JOIN {$wpdb->prefix}wc_orders_meta as m
					ON o.id = m.order_id
					AND ( ( m.meta_key = '_shipping_address_index' AND m.meta_value <> '' )
     				OR ( m.meta_key = '_billing_address_index' AND m.meta_value <> '' ) )
					WHERE o.status IN ($status)
					AND o.type = 'shop_order'
					AND ( o.id = %d
					OR m.meta_value LIKE %s )
					ORDER BY $orderby $order_type
					LIMIT %d, %d",
					intval( $term ),
					is_numeric( $term )
						? '||NAO_PESQUISAVEL||'
						: '%' . $wpdb->esc_like( $term ) . '%',
					! $only_count
						? ( $current_page - 1 ) * self::PER_PAGE
						: 0,
					! $only_count
						? self::PER_PAGE
						: 1000000 // max itens.
				)
			);
		} else {
			$orderby    = 'order' === $orderby ? 'p.ID' : 'p.post_date_gmt';
			$order_type = 'asc' === $order_type ? 'asc' : 'desc';

			$orders = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT(p.ID) FROM $wpdb->posts AS p
					INNER JOIN $wpdb->postmeta as m
					ON p.ID = m.post_id
					AND ( ( m.meta_key = '_shipping_address_index' AND m.meta_value <> '' )
     				OR ( m.meta_key = '_billing_address_index' AND m.meta_value <> '' ) )
					WHERE p.post_status IN ($status)
					AND p.post_type = 'shop_order'
					AND ( p.ID = %d
					OR m.meta_value LIKE %s )
					ORDER BY $orderby $order_type
					LIMIT %d, %d",
					intval( $term ),
					is_numeric( $term )
						? '||NAO_PESQUISAVEL||'
						: '%' . $wpdb->esc_like( $term ) . '%',
					! $only_count
						? ( $current_page - 1 ) * self::PER_PAGE
						: 0,
					! $only_count
						? self::PER_PAGE
						: 1000000 // max itens.
				)
			);
		}

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $id ) {
				$items[] = wc_get_order( $id );
			}
		}

		return $items;
	}

	/**
	 * Retrieves the formatted shipping method information for a given order.
	 *
	 * @param WC_Order $order The order object.
	 * @return string The formatted shipping method information.
	 */
	private function get_formatted_method( $order ) {
		$method = $order->get_meta( '_virtuaria_correios_shipping_method_info' );

		$dimensions = array();

		if ( $method && isset( $method['dimensions'] ) ) {
			$dimensions = json_decode( $method['dimensions'], true );

			if ( isset( $dimensions['weight'] ) ) {
				$dimensions['weight'] /= 1000; // Convert to kg.
			}
		} else {
			$product_length = 0;
			$product_width  = 0;
			$product_height = 0;
			$product_weight = 0;

			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();

				if ( ! $product ) {
					continue;
				}

				$product_height += wc_get_dimension( (float) $product->get_height(), 'cm' );
				$product_width  += wc_get_dimension( (float) $product->get_width(), 'cm' );
				$product_length += wc_get_dimension( (float) $product->get_length(), 'cm' );
				$product_weight += wc_get_dimension( (float) $product->get_weight(), 'kg' );
			}
			$dimensions['length'] = $product_length;
			$dimensions['width']  = $product_width;
			$dimensions['height'] = $product_height;
			$dimensions['weight'] = $product_weight;
		}

		$args_format = array(
			'title'       => isset( $method['title'] )
				? $method['title']
				: $order->get_shipping_method(),
			'service_cod' => isset( $method['service_cod'] )
				? '(' . $method['service_cod'] . ')'
				: '',
			'total'       => isset( $method['total'] ) && $method['total']
				? number_format( $method['total'], 2, ',', '.' )
				: $order->get_shipping_total(),
			'dimensions'  => $dimensions,
		);

		$method_text = sprintf(
			'<span class="method">%s %s</span>',
			$args_format['title'],
			$args_format['service_cod']
		);

		$method_text .= sprintf(
			'<span class="dimensions">%1$sx%2$sx%3$scm / %4$skg</span>',
			isset( $dimensions['length'] ) ? $dimensions['length'] : '0',
			isset( $dimensions['width'] ) ? $dimensions['width'] : '0',
			isset( $dimensions['height'] ) ? $dimensions['height'] : '0',
			isset( $dimensions['weight'] ) ? $dimensions['weight'] : '0',
		);

		$total = is_string( $args_format['total'] )
			? floatval( str_replace( array( ',', '.' ), '', $args_format['total'] ) ) / 100 // Converte centavos.
			: $args_format['total'];

		$method_text .= sprintf(
			'<span class="total">Total: R$ %s</span>',
			number_format( $total, 2, ',', '.' )
		);

		if ( isset( $method['services'] )
			&& $method['services'] ) {

			$services = json_decode( $method['services'], true );

			$available_services = array(
				'019' => __( '(019) Valor Declarado Nacional Premium e Expresso (use para SEDEX)', 'virtuaria-correios' ),
				'064' => __( '(064) Valor Declarado Nacional Standard (use para PAC)', 'virtuaria-correios' ),
				'065' => __( '(065) Valor Declarado Correios Mini Envios (use para SEDEX Mini)', 'virtuaria-correios' ),
				'075' => __( '(075) Valor Declarado Expresso RFID (SEDEX)', 'virtuaria-correios' ),
				'076' => __( '(076) Valor Declarado Standard RFID (PAC)', 'virtuaria-correios' ),
				'001' => __( '(001) Aviso de Recebimento', 'virtuaria-correios' ),
				'002' => __( '(002) Mãos Próprias', 'virtuaria-correios' ),
				'057' => __( '(002) Grandes Formatos', 'virtuaria-correios' ),
				'004' => __( '(004) Registro Módico', 'virtuaria-correios' ),
				'025' => __( '(025) Registro Nacional', 'virtuaria-correios' ),
			);

			if ( $services ) {
				$service_text = '<ul class="services">';
				foreach ( $services as $service ) {
					$service_text .= '<li>' . $available_services[ $service ] . '</li>';
				}

				$method_text .= $service_text . '</ul>';
			}
		}

		if ( isset(
			$this->settings['serial'],
			$this->settings['authenticated']
		) && $this->settings['authenticated'] ) {
			$shipping_address = $order->get_address( 'shipping' );
			$delivery_zone    = WC_Shipping_Zones::get_zone_matching_package(
				array(
					'destination' => array(
						'country'   => $shipping_address['country'],
						'state'     => $shipping_address['state'],
						'postcode'  => $shipping_address['postcode'],
						'city'      => $shipping_address['city'],
						'address'   => $shipping_address['address_1'],
						'address_2' => $shipping_address['address_2'],
					),
				)
			);

			$available_shipping_methods = $delivery_zone->get_shipping_methods();
			if ( $available_shipping_methods ) {
				$method_text .= '<div class="change-method">';
				$method_text .= '<select class="select-method" data-order="' . $order->get_id() . '" data-nonce="' . wp_create_nonce( 'virtuaria_correios_change_method' ) . '">';
				$method_text .= '<option value="">' . __( 'Mudar Método de Entrega', 'virtuaria-correios' ) . '</option>';
				/**
				 * Loop to display avaliable shipping method to this order.
				 *
				 * @var WC_Shipping_Method $shipping_method shipping method.
				 */
				foreach ( $available_shipping_methods as $shipping_method ) {
					$method_text .= '<option value="' . $shipping_method->instance_id . '">' . $shipping_method->get_title() . '</option>';
				}

				$method_text .= '</select>';
				$method_text .= '<div class="spinner"></div>';
				$method_text .= '</div>';
			}
		}

		return $method_text;
	}

	/**
	 * Gets a list of views filters.
	 */
	protected function get_views() {
		$status = isset( $_GET['status'] )
			? sanitize_text_field( wp_unslash( $_GET['status'] ) )
			: 'paid';

		return $this->get_views_links(
			array(
				'paid'       => array(
					'label'   => __( 'Padrão', 'virtuaria-correios' ),
					'url'     => add_query_arg( 'status', 'paid' ),
					'current' => 'paid' === $status,
				),
				'processing' => array(
					'label'   => __( 'Processando', 'virtuaria-correios' ),
					'url'     => add_query_arg( 'status', 'wc-processing' ),
					'current' => 'wc-processing' === $status,
				),
				'completed'  => array(
					'label'   => __( 'Concluído', 'virtuaria-correios' ),
					'url'     => add_query_arg( 'status', 'wc-completed' ),
					'current' => 'wc-completed' === $status,
				),
				'all'        => array(
					'label'   => __( 'Todos', 'virtuaria-correios' ),
					'url'     => add_query_arg( 'status', 'all' ),
					'current' => 'all' === $status,
				),
			)
		);
	}

	/**
	 * Gets a list of bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();
		if ( isset(
			$this->settings['serial'],
			$this->settings['authenticated']
		) && $this->settings['authenticated'] ) {
			$actions = array(
				'print_tickets' => __( 'Imprimir Etiquetas', 'virtuaria-correios' ),
			);
		}
		return $actions;
	}
}

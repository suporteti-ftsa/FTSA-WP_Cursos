<?php
/**
 * Handle shipping screen.
 *
 * @package Virtuaria/Shipping/Correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class to handle shipping screen.
 */
class Virtuaria_Correios_Shipping_Screen {
	/**
	 * Initialize functions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_shipping' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_create_prepost', array( $this, 'create_prepost' ) );
		add_action( 'wp_ajax_print_prepost_label', array( $this, 'print_prepost_label' ) );
		add_action( 'wp_ajax_print_dace', array( $this, 'print_dace' ) );
		add_action( 'wp_ajax_create_declaration', array( $this, 'create_declaration' ) );
		add_action( 'wp_ajax_add_trakking_code', array( $this, 'add_trakking_code' ) );
		add_action( 'wp_ajax_trakking_order', array( $this, 'display_trakking_order' ) );
		add_action( 'wp_ajax_change_shipping_method', array( $this, 'change_shipping_method' ) );
		add_action( 'wp_ajax_send_trakking_email', array( $this, 'send_trakking_email' ) );
	}

	/**
	 * Add submenu shipping.
	 */
	public function add_menu_shipping() {
		add_submenu_page(
			'virtuaria-settings',
			__( 'Entregas', 'virtuaria-correios' ),
			__( 'Entregas', 'virtuaria-correios' ),
			'remove_users',
			'virtuaria-correios-shipping',
			array( $this, 'shipping_screen_content' )
		);
	}

	/**
	 * Display content from shipping screen.
	 */
	public function shipping_screen_content() {
		echo '<h1 class="screen-heading">Correios Entregas</h1>';
		echo '<p class="screen-description">Gerencie entregas de pedidos de forma rápida e simplificada.</p>';

		require_once 'class-virtuaria-correios-shipping-table.php';

		$table = new Virtuaria_Correios_Shipping_Table();

		echo '<form id="order_search_form" method="post">';
		$table->prepare_items();
		$this->process_bulk_action();
		if ( isset( $_GET['page'] ) ) {
			printf(
				'<input type="hidden" name="page" value="%s" />',
				esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) )
			);
		}
		echo '<div class="table-top">';
		$table->search_box( __( 'Buscar', 'virtuaria-correios' ), 'order' );
		$table->views();
		echo '</div>';
		$table->display();
		wp_nonce_field( 'bulk_actions', 'form_nonce' );
		if ( isset( $_GET['status'] ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET['status'] ) ) ) . '" />';
		}
		echo '</form>';
	}

	/**
	 * Add styles and scripts to shipping screen.
	 *
	 * @param string $hook page identifier.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'virtuaria-correios_page_virtuaria-correios-shipping' === $hook ) {
			wp_enqueue_style(
				'shipping-screen',
				VIRTUARIA_CORREIOS_URL . 'admin/css/shipping-screen.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/css/shipping-screen.css' )
			);

			wp_enqueue_script(
				'shipping-screen',
				VIRTUARIA_CORREIOS_URL . 'admin/js/shipping-screen.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/js/shipping-screen.js' ),
				true
			);

			wp_localize_script(
				'shipping-screen',
				'setting',
				array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) )
			);
		}
	}

	/**
	 * Creates a prepost and returns the ticket URL if successful.
	 *
	 * @return void
	 */
	public function create_prepost() {
		if ( isset(
			$_POST['correios_order_id'],
			$_POST['correios_instance_id'],
			$_POST['create_prepost_nonce']
		) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['create_prepost_nonce'] ) ), 'create_prepost' ) ) {
			$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_order_id'] ) ) );

			if ( ! $this->is_valid_correios_contract() ) {
				wp_send_json_error(
					__( 'Por favor, configure as informações do seu contrato com os correios, antes de tentar gerar etiquetas', 'virtuaria-correios' )
				);
				wp_die();
			}

			if ( $order ) {
				$prepost = new Virtuaria_Correios_Prepost();
				$success = $prepost->create_prepost(
					$order
				);
				if ( $success ) {
					$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_order_id'] ) ) );
					if ( $order ) {
						$ticket_url    = $order->get_meta( '_virtuaria_correios_ticket_url' );
						$pending_label = ( ! $ticket_url && $order->get_meta( '_virtuaria_correios_prepost_id' ) );
						wp_send_json_success(
							array(
								'ticket_url'    => $ticket_url,
								'pending_label' => $pending_label,
								'dace_available' => 'yes' === $order->get_meta( '_virtuaria_correios_prepost_emite_dce' ),
								'print_label_nonce' => wp_create_nonce( 'print_prepost_label' ),
								'print_dace_nonce'  => wp_create_nonce( 'print_dace' ),
								'trakking_code' => $order->get_meta( '_virt_correios_trakking_code' ),
							)
						);
					}
				} else {
					$error = get_transient( 'virtuaria_correios_prepost_error' );
					if ( $error ) {
						delete_transient( 'virtuaria_correios_prepost_error' );
						wp_send_json_error(
							$error
						);
					} else {
						echo 'Fail';
					}
				}
			}
		} else {
			echo 'Fail';
		}
		wp_die();
	}

	/**
	 * Prints a prepost label and returns the ticket URL if successful.
	 *
	 * @return void
	 */
	public function print_prepost_label() {
		if ( isset(
			$_POST['correios_order_id'],
			$_POST['correios_instance_id'],
			$_POST['print_prepost_label_nonce']
		) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['print_prepost_label_nonce'] ) ), 'print_prepost_label' ) ) {
			$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_order_id'] ) ) );

			if ( ! $this->is_valid_correios_contract() ) {
				wp_send_json_error(
					__( 'Por favor, configure as informações do seu contrato com os correios, antes de tentar imprimir etiquetas', 'virtuaria-correios' )
				);
				wp_die();
			}

			if ( $order ) {
				$prepost = new Virtuaria_Correios_Prepost();
				$success = $prepost->print_prepost_label( $order );
				if ( $success ) {
					$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_order_id'] ) ) );
					if ( $order ) {
						wp_send_json_success(
							array(
								'ticket_url' => $order->get_meta( '_virtuaria_correios_ticket_url' ),
							)
						);
					}
				} else {
					$error = get_transient( 'virtuaria_correios_prepost_error' );
					if ( $error ) {
						delete_transient( 'virtuaria_correios_prepost_error' );
						$pending = false;
						foreach ( $error as $msg ) {
							if ( false !== stripos( $msg, 'liberada' )
								|| false !== stripos( $msg, 'pendente' ) ) {
								$pending = true;
								break;
							}
						}
						if ( $pending ) {
							wp_send_json_success(
								array(
									'pending_label' => true,
									'errors'        => $error,
								)
							);
						} else {
							wp_send_json_error( $error );
						}
					} else {
						echo 'Fail';
					}
				}
			}
		} else {
			echo 'Fail';
		}
		wp_die();
	}

	/**
	 * Prints DACE and returns the URL if successful.
	 *
	 * @return void
	 */
	public function print_dace() {
		if ( isset(
			$_POST['correios_dace_order_id'],
			$_POST['correios_dace_instance_id'],
			$_POST['print_dace_nonce']
		) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['print_dace_nonce'] ) ), 'print_dace' ) ) {
			$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_dace_order_id'] ) ) );

			if ( ! $this->is_valid_correios_contract() ) {
				wp_send_json_error(
					__( 'Por favor, configure as informações do seu contrato com os correios, antes de tentar imprimir DACE', 'virtuaria-correios' )
				);
				wp_die();
			}

			if ( $order ) {
				$prepost = new Virtuaria_Correios_Prepost();
				$success = $prepost->print_dace( $order );
				if ( $success ) {
					$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_dace_order_id'] ) ) );
					if ( $order ) {
						wp_send_json_success(
							array(
								'dace_url' => $order->get_meta( '_virtuaria_correios_dace_url' ),
							)
						);
					}
				} else {
					$error = get_transient( 'virtuaria_correios_prepost_error' );
					if ( $error ) {
						delete_transient( 'virtuaria_correios_prepost_error' );
						wp_send_json_error( $error );
					} else {
						echo 'Fail';
					}
				}
			}
		} else {
			echo 'Fail';
		}
		wp_die();
	}

	/**
	 * Check if the Correios contract is valid.
	 *
	 * @return bool True if the contract is valid, false otherwise.
	 */
	private function is_valid_correios_contract() {
		$settings = Virtuaria_WPMU_Correios_Settings::get_settings();

		if ( isset( $settings['username'], $settings['password'], $settings['post_card'] )
			&& ! empty( $settings['username'] )
			&& ! empty( $settings['password'] )
			&& ! empty( $settings['post_card'] )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Creates a content declaration and returns the ticket URL if successful.
	 *
	 * @return void
	 */
	public function create_declaration() {
		if ( isset(
			$_POST['correios_declaration_order_id'],
			$_POST['correios_declaration_instance_id'],
			$_POST['create_declaration_nonce']
		) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['create_declaration_nonce'] ) ), 'create_declaration' ) ) {
			$declaration = new Virtuaria_Correios_Content_Declaration();
			$success     = $declaration->create_declaration(
				sanitize_text_field( wp_unslash( $_POST['correios_declaration_order_id'] ) ),
			);

			if ( $success ) {
				$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['correios_declaration_order_id'] ) ) );
				if ( $order ) {
					wp_send_json_success(
						array(
							'declaration_url' => $order->get_meta( '_virtuaria_correios_declaration_url' ),
						)
					);
				}
			} else {
				$error = get_transient( 'virtuaria_correios_declaration_error' );
				if ( $error ) {
					delete_transient( 'virtuaria_correios_declaration_error' );
					wp_send_json_error(
						$error
					);
				} else {
					echo 'Fail';
				}
			}
		} else {
			echo 'Fail';
		}
		wp_die();
	}

	/**
	 * Add tracking code.
	 */
	public function add_trakking_code() {
		if ( isset(
			$_POST['order_id'],
			$_POST['trakking_nonce'],
			$_POST['trakking_code'],
		) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trakking_nonce'] ) ), 'add-trakking-code' )
		&& ! empty( $_POST['order_id'] )
		&& ! empty( $_POST['trakking_code'] ) ) {

			$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) );
			$code  = sanitize_text_field( wp_unslash( $_POST['trakking_code'] ) );
			if ( $order && $code ) {
				$order->update_meta_data(
					'_virt_correios_trakking_code',
					sanitize_text_field( wp_unslash( $_POST['trakking_code'] ) )
				);
				$order->save();

				$order->add_order_note(
					sprintf(
						/* translators: %1$s: trakking code. */
						__( 'Virtuaria Correios: Novo código de rastreamento (%1$s) adicionado ao pedido.', 'virtuaria-correios' ),
						$code
					),
					false,
					true
				);

				Virtuaria_Correios_Trakking::send_trakking_notification( $order, $code );
				do_action( 'virtuaria_correios_trakking_updated', $order->get_id(), $order, $code );

				printf(
					'<a href="%s" target="_blank" class="tracking-code">%s</a>',
					'https://rastreamento.correios.com.br/app/index.php?objetos=' . esc_attr( $code ),
					esc_html( $code )
				);
			} else {
				echo 'Fail';
			}
		} else {
			echo 'Fail';
		}
		wp_die();
	}

	/**
	 * Display trakking order.
	 */
	public function display_trakking_order() {
		if ( isset(
			$_POST['order_id'],
			$_POST['trakking_nonce']
		) && wp_verify_nonce(
			sanitize_text_field(
				wp_unslash(
					$_POST['trakking_nonce']
				)
			),
			'trakking-order'
		) ) {

			$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) );
			if ( $order ) {
				$trakking = new Virtuaria_Correios_Trakking();
				$trakking->trakking_metabox_content( $order );
			} else {
				echo 'Fail';
			}
		} else {
			echo 'Fail';
		}
		wp_die();
	}

	/**
	 * Process bulk actions.
	 *
	 * @return void
	 */
	private function process_bulk_action() {
		if ( isset( $_POST['form_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['form_nonce'] ) ), 'bulk_actions' ) ) {
			$orders = isset( $_POST['orders'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['orders'] ) )
				: false;

			if ( $orders
				&& isset( $_POST['action'] )
				&& 'print_tickets' === $_POST['action'] ) {
				$this->print_tickets( $orders );
			}
		}
	}

	/**
	 * Print shipping tickets for multiple orders.
	 *
	 * @param array $orders List of order IDs.
	 */
	private function print_tickets( $orders ) {
		$ticket_url = '';
		$count      = 0;
		foreach ( $orders as $order ) {
			$order = wc_get_order( $order );
			if ( $order ) {
				$label = $order->get_meta( '_virtuaria_correios_ticket_label' );
				$url   = $order->get_meta( '_virtuaria_correios_ticket_url' );
				if ( $label || $url ) {
					$ticket_url = $this->upload_label(
						$label,
						$order->get_id(),
						$count++
					);
				}
			}
		}

		if ( $ticket_url ) {
			printf(
				'<a href="%s?vt=%s" target="_blank" class="multi-ticket">%s</a>',
				esc_url( $ticket_url ),
				esc_attr( time() ),
				esc_html__( 'Imprimir etiquetas', 'virtuaria-correios' )
			);
		}
	}

	/**
	 * Upload label and generate link to print.
	 *
	 * @param string $label    the label.
	 * @param int    $order_id the order id.
	 * @param int    $interactions number of interactions.
	 */
	private function upload_label( $label, $order_id, $interactions ) {
		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'virtuaria_correios';
		wp_mkdir_p( $uploads_dir );

		$filename = 'multi-ticket';

		$multi_ticket_path = wp_upload_dir()['baseurl'] . '/virtuaria_correios/' . $filename . '.pdf';

		WP_Filesystem();
		global $wp_filesystem;

		if ( $label ) {
			$bin = base64_decode( $label, true );
		} else {
			$bin = $wp_filesystem->get_contents( $uploads_dir . '/' . $order_id . '.pdf' );
		}

		if ( ! $bin ) {
			return $multi_ticket_path;
		}

		$file_path = $uploads_dir . '/' . $filename . '.pdf';

		if ( 0 === $interactions && file_exists( $file_path ) ) {
			$wp_filesystem->delete( $file_path, true );
		}

		$temp = $uploads_dir . '/temp_ticket.pdf';

		if ( file_exists( $file_path ) ) {
			$wp_filesystem->put_contents( $temp, $bin );

			require_once VIRTUARIA_CORREIOS_DIR . 'vendor/autoload.php';

			$pdf = new setasign\Fpdi\Fpdi();

			$settings = Virtuaria_WPMU_Correios_Settings::get_settings();

			$single_page_format = isset( $settings['print_format'] )
				&& in_array( $settings['print_format'], array( 'LINEAR_100_80', 'LINEAR_100_150' ), true );

			$max_page  = 4;
			$half_page = $max_page / 2;

			$posx   = 0;
			$posy   = 0;
			$width  = 209;
			$height = 297;

			if ( ! $single_page_format ) {
				$width = $width / ( $max_page / 4 );
			}

			$page_count = $pdf->setSourceFile( $file_path );
			for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
				$pdf->AddPage();

				$page = $pdf->importPage( $page_number );
				$pdf->useTemplate( $page, $posx, $posy, $width, $height );
			}

			$page_count = $pdf->setSourceFile( $temp );
			if ( $single_page_format || 0 === ( $interactions % $max_page ) ) {
				if ( ! $single_page_format ) {
					$pdf->AddPage();
				}
				$posy = 0;
				$posx = 0;
			} elseif ( 0 === ( $interactions % $half_page ) ) {
				$posx = 0;
				$posy = $height / $half_page;
			} else {
				$posx = $width / $half_page;
			}

			if ( ! $single_page_format && 0 === ( $interactions % ( $half_page + 1 ) ) ) {
				$posy = $height / $half_page;
			}

			if ( $single_page_format ) {
				for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
					$pdf->AddPage();

					$page = $pdf->importPage( $page_number );
					$pdf->useTemplate( $page, $posx, $posy, $width, $height );
				}
			} else {
				$page = $pdf->importPage( 1 );
				$pdf->useTemplate( $page, $posx, $posy, $width, $height );
			}

			$pdf->Output( $file_path, 'F' );

			$wp_filesystem->delete( $temp, true );
		} else {
			$wp_filesystem->put_contents( $file_path, $bin );
		}

		return $multi_ticket_path;
	}

	/**
	 * Change shipping method in order.
	 */
	public function change_shipping_method() {
		if (
			isset(
				$_POST['order_id'],
				$_POST['shipping_method'],
				$_POST['shipping_title'],
				$_POST['shipping_method_nonce']
			) && wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['shipping_method_nonce']
					)
				),
				'virtuaria_correios_change_method'
			)
		) {
			$order        = wc_get_order(
				sanitize_text_field( wp_unslash( $_POST['order_id'] ) )
			);
			$instance_id  = sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) );
			$method_title = sanitize_text_field( wp_unslash( $_POST['shipping_title'] ) );
			if ( $order && $instance_id && $method_title ) {
				if ( $order->get_items( 'shipping' ) ) {
					/**
					 * Change order shipping.
					 *
					 * @var WC_Order_Item_Shipping $shipping_item shipping item.
					 */
					foreach ( $order->get_items( 'shipping' ) as $item_id => $shipping_item ) {
						$shipping_item->set_method_id( 'virtuaria-correios-sedex' );
						$shipping_item->set_method_title( $method_title );
						$shipping_item->set_instance_id( $instance_id );

						$shipping_item->save();
					}
				} else {
					$new_shipping = new WC_Order_Item_Shipping();
					$new_shipping->set_method_id( 'virtuaria-correios-sedex' );
					$new_shipping->set_method_title( $method_title );
					$new_shipping->set_instance_id( $instance_id );

					$order->add_item(
						$new_shipping
					);
				}

				$order->save();

				$order->add_order_note(
					sprintf(
						/* translators: %1$s: shipping method. */
						__( 'Virtuaria Correios: Método de envio alterado para %1$s.', 'virtuaria-correios' ),
						$method_title
					),
					false,
					true
				);

				echo 'success';
			}
		}
		wp_die();
	}

	/**
	 * Sends a tracking email notification to the customer.
	 *
	 * Verifies the nonce and retrieves the order based on the provided order ID.
	 * If the order and tracking code are valid, it triggers the tracking notification email.
	 *
	 * @return void
	 */
	public function send_trakking_email() {
		if ( isset(
			$_POST['order_id'],
			$_POST['trakking_nonce']
		) && wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['trakking_nonce'] ) ),
			'trakking-order'
		) && ! empty( $_POST['order_id'] ) ) {
			$order = wc_get_order( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) );

			if ( $order ) {
				$trakking_code = $order->get_meta( '_virt_correios_trakking_code' );
				if ( $trakking_code ) {
					Virtuaria_Correios_Trakking::send_trakking_notification( $order, $trakking_code );
					echo 'success';
				}
			}
		}
		wp_die();
	}
}

new Virtuaria_Correios_Shipping_Screen();

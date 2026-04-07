<?php
/**
 * Handle order extra fields.
 *
 * @package Virtuaria/Integrations/Correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Correios_Extra_Fields_Order {
	use Virtuaria_Correios_Fields;

	/**
	 * Extra fields settings.
	 *
	 * @var array
	 */
	protected $fields_settings;

	/**
	 * Initialize functions.
	 *
	 * @param array $fields_settings Extra fields settings.
	 */
	public function __construct( $fields_settings ) {
		$this->fields_settings = $fields_settings;

		if ( $this->is_checkout_block() ) {
			return;
		}

		add_action(
			'woocommerce_admin_order_data_after_billing_address',
			array( $this, 'display_admin_order_meta' ),
			10,
			1
		);

		add_filter(
			'woocommerce_admin_billing_fields',
			array( $this, 'display_order_billing_fields' )
		);

		add_filter(
			'woocommerce_admin_shipping_fields',
			array( $this, 'display_order_shipping_fields' )
		);

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_scripts' ),
			20
		);
	}

	/**
	 * Displays order meta data in the admin panel.
	 *
	 * This function retrieves and displays the following order meta data:
	 * - CPF
	 * - RG
	 * - Data de Nascimento
	 * - Gênero
	 * - Celular
	 * - Bairro
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 */
	public function display_admin_order_meta( $order ) {
		$infos = array(
			'cpf'    => __( 'CPF', 'virtuaria-correios' ),
			'rg'     => __( 'RG', 'virtuaria-correios' ),
			'cnpj'   => __( 'CNPJ', 'virtuaria-correios' ),
			'ie'     => __( 'Inscrição Estadual', 'virtuaria-correios' ),
			'date'   => __( 'Data de Nascimento', 'virtuaria-correios' ),
			'gender' => __( 'Gênero', 'virtuaria-correios' ),
		);

		echo '<div class="customer-info">';
		echo '<b class="title">' . esc_html__( 'Informações do cliente', 'virtuaria-correios' ) . '</b><br><br>';

		$gender_translated = array(
			'male'   => __( 'Masculino', 'virtuaria-correios' ),
			'female' => __( 'Feminino', 'virtuaria-correios' ),
			'other'  => __( 'Outro', 'virtuaria-correios' ),
			'notsay' => __( 'Prefiro não dizer', 'virtuaria-correios' ),
		);

		foreach ( $infos as $key => $label ) {
			$value = $order->get_meta( "_billing_{$key}" );

			if ( 'gender' === $key ) {
				$value = isset( $gender_translated[ $value ] )
					? $gender_translated[ $value ]
					: '';
			}

			if ( $value ) {
				printf(
					'<b>%s:</b> %s<br>',
					esc_html( $label ),
					esc_html( $value ),
				);
			}
		}
		echo '</div>';
	}

	/**
	 * Custom shop order billing fields.
	 *
	 * @param  array $data Default order billing fields.
	 *
	 * @return array       Custom order billing fields.
	 */
	public function display_order_billing_fields( $data ) {
		$pos  = 2;
		$show = false;

		if ( isset( $this->fields_settings['person_type'] )
			&& 'none' !== $this->fields_settings['person_type'] ) {
			if ( 'both' === $this->fields_settings['person_type'] ) {
				$this->add_elem_specific_position(
					$data,
					array(
						'type'    => 'select',
						'label'   => __( 'Tipo de Pessoa', 'virtuaria-correios' ),
						'show'    => $show,
						'options' => array(
							''   => __( 'Selecionar', 'virtuaria-correios' ),
							'pf' => __( 'Física', 'virtuaria-correios' ),
							'pj' => __( 'Juridica', 'virtuaria-correios' ),
						),
					),
					$pos++,
					'persontype'
				);
			}

			if ( in_array(
				$this->fields_settings['person_type'],
				array( 'pf', 'both' ),
				true
			) ) {
				$this->add_elem_specific_position(
					$data,
					array(
						'label' => __( 'CPF', 'virtuaria-correios' ),
						'show'  => $show,
					),
					$pos++,
					'cpf'
				);

				if ( isset( $this->fields_settings['rg'] )
					&& 'yes' === $this->fields_settings['rg'] ) {
					$this->add_elem_specific_position(
						$data,
						array(
							'label' => __( 'RG', 'virtuaria-correios' ),
							'show'  => $show,
						),
						$pos++,
						'rg'
					);
				}
			}

			if ( in_array(
				$this->fields_settings['person_type'],
				array( 'pj', 'both' ),
				true
			) ) {
				$this->add_elem_specific_position(
					$data,
					array(
						'label' => __( 'CNPJ', 'virtuaria-correios' ),
						'show'  => $show,
					),
					$pos++,
					'cnpj'
				);

				if ( isset( $this->fields_settings['ie'] )
					&& 'yes' === $this->fields_settings['ie'] ) {
					$this->add_elem_specific_position(
						$data,
						array(
							'label' => __( 'Inscrição Estadual', 'virtuaria-correios' ),
							'show'  => $show,
						),
						$pos++,
						'ie'
					);
				}
				++$pos;
			} else {
				unset( $data['company'] );
				--$pos;
			}

			if ( isset( $this->fields_settings['birthday_date'] )
				&& 'yes' === $this->fields_settings['birthday_date'] ) {
				$this->add_elem_specific_position(
					$data,
					array(
						'label' => __( 'Data de Nascimento', 'virtuaria-correios' ),
						'show'  => $show,
						'type'  => 'date',
					),
					$pos++,
					'date'
				);
			}

			if ( isset( $this->fields_settings['gender'] )
				&& 'yes' === $this->fields_settings['gender'] ) {
				$this->add_elem_specific_position(
					$data,
					array(
						'label'   => __( 'Data de Nascimento', 'virtuaria-correios' ),
						'show'    => $show,
						'type'    => 'select',
						'options' => array(
							''       => __( 'Selecione o gênero', 'virtuaria-correios' ),
							'notsay' => __( 'Prefiro não informar', 'virtuaria-correios' ),
							'male'   => __( 'Masculino', 'virtuaria-correios' ),
							'female' => __( 'Feminino', 'virtuaria-correios' ),
							'other'  => __( 'Outro', 'virtuaria-correios' ),
						),
					),
					$pos++,
					'gender'
				);
			}

			++$pos; // Jump address_line_1.

			$this->add_elem_specific_position(
				$data,
				array(
					'label' => __( 'Número', 'virtuaria-correios' ),
					'show'  => $show,
					'type'  => 'number',
				),
				$pos++,
				'number'
			);

			++$pos; // Jump address_line_2.

			$this->add_elem_specific_position(
				$data,
				array(
					'label' => __( 'Bairro', 'virtuaria-correios' ),
					'show'  => $show,
				),
				$pos++,
				'neighborhood'
			);
		}

		return apply_filters( 'virtuaria_correios_order_billing_fields', $data );
	}

	/**
	 * Enqueues scripts and styles for the order edit screen.
	 *
	 * Enqueues the necessary scripts and styles for the order edit screen,
	 * specifically for the Virtuaria Correios plugin.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			wp_enqueue_script(
				'virtuaria-correios-extra-fields',
				VIRTUARIA_CORREIOS_URL . 'admin/js/extra-fields-order.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/js/extra-fields-order.js' ),
				true
			);

			wp_enqueue_style(
				'virtuaria-correios-extra-fields',
				VIRTUARIA_CORREIOS_URL . 'admin/css/extra-fields-order.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/css/extra-fields-order.css' )
			);
		}
	}

	/**
	 * Custom shop order shipping fields.
	 *
	 * If the person type is not 'pj' or 'both', unset the company field and reduce the position.
	 * Add the number field and the neighborhood field at the respective positions.
	 *
	 * @param array $data Default order shipping fields.
	 * @return array       Custom order shipping fields.
	 */
	public function display_order_shipping_fields( $data ) {
		$pos  = 4;
		$show = false;

		if ( isset( $this->fields_settings['person_type'] )
			&& ! in_array(
				$this->fields_settings['person_type'],
				array( 'pj', 'both' ),
				true
			)
		) {
			unset( $data['company'] );
			--$pos;
		}

		$this->add_elem_specific_position(
			$data,
			array(
				'label' => __( 'Número', 'virtuaria-correios' ),
				'show'  => $show,
				'type'  => 'number',
			),
			$pos++,
			'number'
		);

		++$pos; // Jump address_line_2.

		$this->add_elem_specific_position(
			$data,
			array(
				'label' => __( 'Bairro', 'virtuaria-correios' ),
				'show'  => $show,
			),
			$pos++,
			'neighborhood'
		);

		return apply_filters( 'virtuaria_correios_order_shipping_fields', $data );
	}
}

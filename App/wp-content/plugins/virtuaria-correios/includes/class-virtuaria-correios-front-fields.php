<?php
/**
 * Handle checkout extra fields.
 *
 * @package Virtuaria/Integrations/Checkout-Fields.
 */

defined( 'ABSPATH' ) || exit;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

/**
 * Class definition
 */
class Virtuaria_Correios_Front_Fields {
	use Virtuaria_Correios_Fields;
	use Virtuaria_Correios_International;

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

		if ( ! $this->is_checkout_block() && $this->is_international_shipping() ) {
			return;
		}

		$this->load_dependencies();

		if ( ! $this->is_checkout_block() ) {
			add_filter( 'woocommerce_billing_fields', array( $this, 'add_extra_billing_fields' ), 15 );
			add_filter( 'woocommerce_shipping_fields', array( $this, 'add_extra_shipping_fields' ), 15 );
		}
		add_filter( 'woocommerce_email_order_meta_keys', array( $this, 'email_order_meta_keys' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_scripts' ) );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'reorder_default_fields' ) );
		add_action( 'woocommerce_customer_save_address', array( $this, 'save_myaccount_extra_fields' ), 10, 2 );
		add_action( 'woocommerce_init', array( $this, 'add_checkout_blocks_fields' ) );
		add_filter( 'woocommerce_set_additional_field_value', array( $this, 'save_block_extra_fields' ), 10, 4 );
	}

	/**
	 * Loads the necessary dependencies for the Virtuaria Correios plugin.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once 'class-virtuaria-correios-extra-fields-validations.php';
		new Virtuaria_Correios_Extra_Fields_Validations(
			$this->fields_settings
		);
	}

	/**
	 * Modifies the WooCommerce checkout fields based on the Virtuaria Correios Extra Fields settings.
	 *
	 * @param array $fields The WooCommerce checkout fields.
	 * @return array The modified WooCommerce checkout fields.
	 */
	public function add_extra_billing_fields( $fields ) {
		$extra_fields = $this->get_extra_fields( $fields );

		$extra_fields = $extra_fields + $fields;

		$this->define_style_fields( $extra_fields );

		$extra_fields = apply_filters( 'virtuaria_correios_billing_fields', $extra_fields );

		return $extra_fields;
	}

	/**
	 * Adds extra shipping fields to the given fields array.
	 *
	 * @param array $fields The fields array to be modified.
	 * @return array The modified fields array with extra shipping fields.
	 */
	public function add_extra_shipping_fields( $fields ) {
		$fields['shipping_neighborhood'] = array(
			'type'        => 'text',
			'label'       => __( 'Bairro', 'virtuaria-correios' ),
			'placeholder' => _x( 'Bairro', 'placeholder', 'virtuaria-correios' ),
			'class'       => array( 'form-row-first' ),
			'required'    => true,
			'clear'       => true,
			'priority'    => 66,
		);

		$fields['shipping_number'] = array(
			'type'        => 'text',
			'label'       => __( 'Número', 'virtuaria-correios' ),
			'placeholder' => _x( 'Número', 'placeholder', 'virtuaria-correios' ),
			'class'       => array( 'form-row-first' ),
			'clear'       => true,
			'priority'    => 65,
		);

		if ( isset( $this->fields_settings['person_type'] )
			&& in_array( $this->fields_settings['person_type'], array( 'pf', 'none' ), true ) ) {
			unset( $fields['billing_company'] );
		}

		$this->define_style_fields( $fields );

		return $fields;
	}

	/**
	 * Returns an array of extra billing fields based on the Virtuaria Correios settings.
	 *
	 * The extra fields include:
	 * - Person type (PF or PJ)
	 * - CPF
	 * - RG
	 * - CNPJ
	 * - IE
	 * - Birthdate
	 * - Gender
	 * - Cell phone
	 *
	 * @param array $fields The billing fields.
	 * @return array The extra billing fields.
	 */
	private function get_extra_fields( &$fields = array() ) {
		$options = $this->fields_settings;

		$first_class = 'form-row-first';
		$last_class  = 'form-row-last';
		$wide_class  = 'form-row-wide';

		$extra_fields = array(
			'billing_person_type'  => array(
				'type'     => 'select',
				'label'    => __( 'Tipo de Pessoa', 'virtuaria-correios' ),
				'required' => true,
				'class'    => array( $wide_class ),
				'options'  => array(
					'pf' => __( 'Pessoa Física', 'virtuaria-correios' ),
					'pj' => __( 'Pessoa Jurídica', 'virtuaria-correios' ),
				),
				'clear'    => true,
				'priority' => 22,
			),
			'billing_cpf'          => array(
				'type'        => 'text',
				'label'       => __( 'CPF', 'virtuaria-correios' ),
				'placeholder' => _x( 'CPF', 'placeholder', 'virtuaria-correios' ),
				'class'       => array( $first_class ),
				'clear'       => true,
				'priority'    => 23,
			),
			'billing_rg'           => array(
				'type'        => 'text',
				'label'       => __( 'RG', 'virtuaria-correios' ),
				'placeholder' => _x( 'RG', 'placeholder', 'virtuaria-correios' ),
				'class'       => array( $last_class ),
				'clear'       => true,
				'priority'    => 23,
			),
			'billing_cnpj'         => array(
				'type'        => 'text',
				'label'       => __( 'CNPJ', 'virtuaria-correios' ),
				'placeholder' => _x( 'CNPJ', 'placeholder', 'virtuaria-correios' ),
				'class'       => array( $first_class ),
				'clear'       => true,
				'priority'    => 26,
			),
			'billing_ie'           => array(
				'type'        => 'text',
				'label'       => __( 'Inscrição Estadual', 'virtuaria-correios' ),
				'placeholder' => _x( 'Inscrição Estadual', 'placeholder', 'virtuaria-correios' ),
				'class'       => array( $last_class ),
				'clear'       => true,
				'priority'    => 27,
			),
			'billing_birthdate'    => array(
				'type'        => 'text',
				'label'       => __( 'Data de Nascimento', 'virtuaria-correios' ),
				'placeholder' => _x( 'Data de Nascimento', 'placeholder', 'virtuaria-correios' ),
				'required'    => false,
				'class'       => array( $first_class ),
				'clear'       => true,
				'priority'    => 31,
			),
			'billing_gender'       => array(
				'type'     => 'select',
				'label'    => __( 'Gênero', 'virtuaria-correios' ),
				'required' => false,
				'class'    => array( $last_class ),
				'options'  => array(
					''       => __( 'Selecione o gênero', 'virtuaria-correios' ),
					'notsay' => __( 'Prefiro não informar', 'virtuaria-correios' ),
					'male'   => __( 'Masculino', 'virtuaria-correios' ),
					'female' => __( 'Feminino', 'virtuaria-correios' ),
					'other'  => __( 'Outro', 'virtuaria-correios' ),
				),
				'clear'    => true,
				'priority' => 32,
			),
			'billing_cellphone'    => array(
				'type'        => 'tel',
				'label'       => __( 'Celular', 'virtuaria-correios' ),
				'placeholder' => _x( 'Celular', 'placeholder', 'virtuaria-correios' ),
				'required'    => false,
				'class'       => array( $last_class ),
				'clear'       => true,
				'priority'    => 105,
			),
			'billing_neighborhood' => array(
				'type'        => 'text',
				'label'       => __( 'Bairro', 'virtuaria-correios' ),
				'placeholder' => _x( 'Bairro', 'placeholder', 'virtuaria-correios' ),
				'class'       => array( $first_class ),
				'required'    => true,
				'clear'       => true,
				'priority'    => 66,
			),
			'billing_number'       => array(
				'type'        => 'text',
				'label'       => __( 'Número', 'virtuaria-correios' ),
				'placeholder' => _x( 'Número', 'placeholder', 'virtuaria-correios' ),
				'class'       => array( $first_class ),
				'clear'       => true,
				'priority'    => 65,
			),
		);

		if ( isset( $options['person_type'] ) ) {
			if ( 'pf' === $options['person_type'] ) {
				unset( $extra_fields['billing_company'] );
				unset( $fields['billing_company'] );
				unset( $extra_fields['billing_cnpj'] );
				unset( $extra_fields['billing_ie'] );
			} elseif ( 'pj' === $options['person_type'] ) {
				unset( $extra_fields['billing_cpf'] );
				unset( $extra_fields['billing_rg'] );
			} elseif ( 'none' === $options['person_type'] ) {
				unset( $extra_fields['billing_company'] );
				unset( $fields['billing_company'] );
				unset( $extra_fields['billing_cnpj'] );
				unset( $extra_fields['billing_ie'] );
				unset( $extra_fields['billing_cpf'] );
				unset( $extra_fields['billing_rg'] );
				unset( $extra_fields['billing_person_type'] );
			}

			if ( 'both' !== $options['person_type'] ) {
				unset( $extra_fields['billing_person_type'] );
			}

			if ( isset( $extra_fields['billing_rg'] )
				&& ( ! isset( $options['rg'] )
				|| 'yes' !== $options['rg'] ) ) {
				unset( $extra_fields['billing_rg'] );
			}

			if ( isset( $extra_fields['billing_ie'] )
				&& ( ! isset( $options['ie'] )
				|| 'yes' !== $options['ie'] ) ) {
				unset( $extra_fields['billing_ie'] );
			}
		}

		if ( ! isset( $options['birthday_date'] )
			|| 'yes' !== $options['birthday_date'] ) {
			unset( $extra_fields['billing_birthdate'] );
		}

		if ( ! isset( $options['gender'] )
			|| 'yes' !== $options['gender'] ) {
			unset( $extra_fields['billing_gender'] );
		}

		if ( isset( $options['cell_phone'] ) ) {
			if ( 'cel' === $options['cell_phone'] ) {
				$fields['billing_phone']['label'] = __( 'Celular', 'virtuaria-correios' );
			}

			$fields['billing_phone']['class'] = array( $first_class );

			if ( in_array( $options['cell_phone'], array( 'cel', 'disabled' ), true ) ) {
				unset( $extra_fields['billing_cellphone'] );
			}
		}
		$fields['billing_phone']['class'] = array( $first_class );

		if ( ! isset( $extra_fields['billing_rg'] ) ) {
			$extra_fields['billing_cpf']['class'] = array( 'form-row-first', 'clear-right' );
		}

		return $extra_fields;
	}

	/**
	 * Enqueue custom scripts for the checkout fields.
	 *
	 * This function enqueues the 'checkout-field-script' and localizes it with the 'mask' option.
	 *
	 * @return void
	 */
	public function enqueue_custom_scripts() {
		if ( is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {
			wp_enqueue_style(
				'extra-fields',
				VIRTUARIA_CORREIOS_URL . 'public/css/extra-fields.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/css/extra-fields.css' )
			);

			wp_enqueue_script(
				'extra-fields',
				VIRTUARIA_CORREIOS_URL . 'public/js/extra-fields.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/extra-fields.js' ),
				true
			);

			wp_localize_script(
				'extra-fields',
				'options',
				array(
					'mask'           => isset( $this->fields_settings['mask'] )
						&& 'yes' === $this->fields_settings['mask'],
					'phone_required' => isset( $this->fields_settings['cell_phone'] )
						&& 'required' === $this->fields_settings['cell_phone'],
					'is_block'       => $this->is_checkout_block() && is_checkout(),
				)
			);
		}
	}

	/**
	 * Adds additional meta keys to the given array of keys for email order.
	 *
	 * @param array $keys The array of keys for email order.
	 * @return array The updated array of keys for email order.
	 */
	public function email_order_meta_keys( $keys ) {
		$keys[] = 'CPF';
		$keys[] = 'RG';
		$keys[] = 'Data de Nascimento';
		$keys[] = 'Gênero';
		$keys[] = 'Celular';
		$keys[] = 'Bairro';
		return $keys;
	}

	/**
	 * Reorders the default address fields to match the plugin's requirements.
	 *
	 * @param array $address_fields The array of address fields to reorder.
	 * @return array The reordered array of address fields.
	 */
	public function reorder_default_fields( $address_fields ) {
		if ( isset( $address_fields['postcode'] ) ) {
			$address_fields['postcode']['priority'] = 45;
			$address_fields['postcode']['clear']    = false;
			$address_fields['postcode']['class']    = array( 'form-row-first', 'address-field' );
		}

		if ( isset( $address_fields['address_1'] ) ) {
			$address_fields['address_1']['clear'] = false;
			$address_fields['address_1']['class'] = array( 'form-row-last' );
		}

		if ( isset( $address_fields['address_2'] ) ) {
			$address_fields['address_2']['clear']    = false;
			$address_fields['address_2']['class']    = array( 'form-row-last' );
			$address_fields['address_2']['priority'] = 65;
			$address_fields['address_2']['label']    = __( 'Complemento', 'virtuaria-correios' );

			$address_fields['address_2']['placeholder'] = __( 'Apartamento, suíte, unidade e etc', 'virtuaria-correios' );
			$address_fields['address_2']['label_class'] = array();
		}

		if ( isset( $address_fields['city'] ) ) {
			$address_fields['city']['clear'] = false;
			$address_fields['city']['class'] = array( 'form-row-last' );
		}

		if ( $this->is_checkout_block() ) {
			$address_fields['address_1']['class'] = array( 'form-row-first' );
			$address_fields['city']['priority']   = 45;
		}

		$this->define_style_fields( $address_fields );

		return $address_fields;
	}

	/**
	 * Saves the extra fields for the my account page.
	 *
	 * This function is responsible for updating the customer meta data with the extra fields
	 * provided in the request. It checks the nonce and the person type before updating the data.
	 *
	 * @param int    $user_id      The ID of the user.
	 * @param string $address_type The type of address (billing or shipping).
	 *
	 * @return void
	 */
	public function save_myaccount_extra_fields( $user_id, $address_type ) {
		if ( isset( $_POST['woocommerce-edit-address-nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['woocommerce-edit-address-nonce'] ),
				),
				'woocommerce-edit_address'
			)
		) {
			$customer = new WC_Customer( $user_id );

			$person_type = isset( $_POST['billing_person_type'] )
				? sanitize_text_field( wp_unslash( $_POST['billing_person_type'] ) )
				: '';

			if ( 'pf' === $person_type
				|| (
					isset( $this->fields_settings['person_type'] )
					&& 'pf' === $this->fields_settings['person_type']
				)
			) {
				if ( isset( $_POST[ $address_type . '_cpf' ] ) ) {
					$customer->update_meta_data(
						"_{$address_type}_cpf",
						sanitize_text_field(
							wp_unslash( $_POST[ $address_type . '_cpf' ] )
						)
					);
				}

				if ( isset( $_POST[ $address_type . '_rg' ] ) ) {
					$customer->update_meta_data(
						"_{$address_type}_rg",
						sanitize_text_field(
							wp_unslash( $_POST[ $address_type . '_rg' ] )
						)
					);
				}
			} elseif ( 'pj' === $person_type
				|| (
					isset( $this->fields_settings['person_type'] )
					&& 'pj' === $this->fields_settings['person_type']
				)
			) {
				if ( isset( $_POST[ $address_type . '_cnpj' ] ) ) {
					$customer->update_meta_data(
						"_{$address_type}_cnpj",
						sanitize_text_field(
							wp_unslash( $_POST[ $address_type . '_cnpj' ] )
						)
					);
				}

				if ( isset( $_POST[ $address_type . '_ie' ] ) ) {
					$customer->update_meta_data(
						"_{$address_type}_ie",
						sanitize_text_field(
							wp_unslash( $_POST[ $address_type . '_ie' ] )
						)
					);
				}
			}

			if ( isset( $_POST[ $address_type . '_birthdate' ] ) ) {
				$customer->update_meta_data(
					"_{$address_type}_birthdate",
					sanitize_text_field(
						wp_unslash( $_POST[ $address_type . '_birthdate' ] )
					)
				);
			}

			if ( isset( $_POST[ $address_type . '_gender' ] ) ) {
				$customer->update_meta_data(
					"_{$address_type}_gender",
					sanitize_text_field(
						wp_unslash( $_POST[ $address_type . '_gender' ] )
					)
				);
			}

			if ( isset( $_POST[ $address_type . '_number' ] ) ) {
				$customer->update_meta_data(
					"_{$address_type}_number",
					sanitize_text_field(
						wp_unslash( $_POST[ $address_type . '_number' ] )
					)
				);
			}

			if ( isset( $_POST[ $address_type . '_neighborhood' ] ) ) {
				$customer->update_meta_data(
					"_{$address_type}_neighborhood",
					sanitize_text_field(
						wp_unslash( $_POST[ $address_type . '_neighborhood' ] )
					)
				);
			}

			$customer->save();
		}
	}

	/**
	 * Sets the wide class for the given array of classes.
	 *
	 * This function is used to ensure that the field is displayed in a wide format.
	 * It will remove any existing first or last class from the array, and then add
	 * the wide class.
	 *
	 * @param array $classes The array of classes to modify.
	 */
	private function set_wide_class( &$classes ) {
		$wide_class = 'form-row-wide';
		foreach ( $classes as $index => $class ) {
			if ( in_array( $class, array( 'form-row-first', 'form-row-last' ), true ) ) {
				unset( $classes[ $index ] );
			}
		}

		$classes[] = $wide_class;
	}

	/**
	 * Modifies the given fields array to add the wide class to each field.
	 *
	 * This function is used to ensure that the fields are displayed in a wide format.
	 * It checks the 'style_field' option from the given options array and if it is
	 * set to 'wide', it will remove any existing first or last class from the array,
	 * and then add the wide class.
	 *
	 * @param array $fields The array of fields to modify.
	 */
	private function define_style_fields( &$fields ) {
		if ( isset( $this->fields_settings['style_field'] )
			&& 'wide' === $this->fields_settings['style_field']
			&& ! empty( $fields ) ) {
			foreach ( $fields as $key => $field ) {
				$this->set_wide_class( $fields[ $key ]['class'] );
			}
		}
	}

	/**
	 * Adds the extra fields defined in the Virtuaria Correios settings as additional fields
	 * in the Checkout page using the WooCommerce Checkout Blocks feature.
	 *
	 * This function is hooked into the 'woocommerce_init' action and will create a new
	 * field for each extra field defined in the settings. The field will be added to the
	 * address section of the Checkout page and will be required if the 'required' option
	 * is set to true in the settings.
	 */
	public function add_checkout_blocks_fields() {
		if ( ! $this->is_checkout_block() ) {
			return;
		}

		$fields = $this->get_extra_fields();

		if ( $fields ) {
			foreach ( $fields as $key => $field ) {
				$new_field = array(
					'id'       => 'virtuaria-correios/' . str_replace( 'billing_', '', $key ),
					'label'    => $field['label'],
					'required' => isset( $field['required'] )
						? $field['required']
						: ( isset( $this->fields_settings['person_type'] ) && 'both' !== $this->fields_settings['person_type']
							? true
							: false
						),
					'location' => 'address',
					'type'     => 'text',
				);

				if ( 'select' === $field['type'] ) {
					$new_field['type'] = 'select';

					$options = array();
					foreach ( $field['options'] as $index => $option ) {
						$options[] = array(
							'label' => $option,
							'value' => $index,
						);
					}

					$new_field['options'] = $options;
				}

				woocommerce_register_additional_checkout_field( $new_field );
			}
		}
	}



	/**
	 * Saves the extra fields for a given order if they match the Virtuaria Correios key pattern.
	 *
	 * This function checks if the provided key contains 'virtuaria-correios' and updates the
	 * order meta data with the value, using the group and modified key name.
	 *
	 * @param string   $key   The key for the extra field.
	 * @param string   $value The value of the extra field.
	 * @param string   $group The group name to be prefixed to the key.
	 * @param WC_Order $order The order.
	 */
	public function save_block_extra_fields( $key, $value, $group, $order ) {
		if ( strpos( $key, 'virtuaria-correios' ) !== false ) {
			$order->update_meta_data(
				'_' . $group . '_' . str_replace( 'virtuaria-correios/', '', $key ),
				$value
			);
		}
	}
}

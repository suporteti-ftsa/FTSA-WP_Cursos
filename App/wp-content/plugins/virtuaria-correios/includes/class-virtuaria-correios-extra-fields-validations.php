<?php
/**
 * Handle extra checkout fields validations.
 *
 * @package Virtuaria/Integrations/Correios/Checkout-Fields.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Correios_Extra_Fields_Validations {
	use Virtuaria_Correios_Fields;

	/**
	 * Extra fields settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialize functions.
	 *
	 * @param array $settings The extra fields settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;

		if ( ! $this->is_checkout_block() ) {
			add_action(
				'woocommerce_checkout_process',
				array( $this, 'validate_custom_checkout_fields' )
			);
			add_action(
				'woocommerce_after_save_address_validation',
				array( $this, 'validadate_shipping_account_fields' )
			);
			add_action(
				'woocommerce_after_save_address_validation',
				array( $this, 'validadate_billing_account_fields' )
			);
		} else {
			add_action(
				'woocommerce_blocks_validate_location_address_fields',
				array( $this, 'validate_block_checkout_fields' ),
				10,
				3
			);
		}
	}

	/**
	 * Validates the custom checkout fields based on the options set in the 'virtuaria_correios_extra_settings' option.
	 *
	 * Checks for required fields such as CPF, RG, CNPJ, company name, IE, birthday date, gender, and district, and adds error notices if any of these fields are empty or invalid.
	 */
	public function validate_custom_checkout_fields() {
		if ( isset( $_POST['woocommerce-process-checkout-nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['woocommerce-process-checkout-nonce'] )
				),
				'woocommerce-process_checkout'
			)
		) {
			$person_type = isset( $_POST['billing_person_type'] )
				? sanitize_text_field( wp_unslash( $_POST['billing_person_type'] ) )
				: '';

			if ( 'pf' === $person_type
				|| (
					isset( $this->settings['person_type'] )
					&& 'pf' === $this->settings['person_type']
				)
			) {
				$this->valid_natural_person(
					'billing',
					'woocommerce-process-checkout-nonce',
					'woocommerce-process_checkout'
				);
			} elseif ( 'pj' === $person_type
				|| (
					isset( $this->settings['person_type'] )
					&& 'pj' === $this->settings['person_type']
				)
			) {
				$this->valid_legal_person(
					'billing',
					'woocommerce-process-checkout-nonce',
					'woocommerce-process_checkout'
				);
			}

			$this->valid_gender_and_birthdate(
				'billing',
				'woocommerce-process-checkout-nonce',
				'woocommerce-process_checkout'
			);

			$this->valid_neighborhood_and_number(
				'billing',
				'woocommerce-process-checkout-nonce',
				'woocommerce-process_checkout'
			);

			$this->valid_cellphone(
				'billing',
				'woocommerce-process-checkout-nonce',
				'woocommerce-process_checkout'
			);
		} else {
			wc_add_notice(
				__( 'Falha ao validar nonce', 'virtuaria-correios' ),
				'error'
			);
		}
	}

	/**
	 * Validate shipping account fields.
	 *
	 * @return void
	 */
	public function validadate_shipping_account_fields() {
		$this->valid_neighborhood_and_number(
			'shipping',
			'woocommerce-edit-address-nonce',
			'woocommerce-edit_address'
		);
	}

	/**
	 * Validate shipping account fields.
	 *
	 * @return void
	 */
	public function validadate_billing_account_fields() {
		if ( ! isset( $_POST['billing_postcode'] ) ) {
			return;
		}

		$person_type = '';
		if ( isset( $_POST['woocommerce-edit-address-nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['woocommerce-edit-address-nonce'] ),
				),
				'woocommerce-edit_address'
			)
		) {
			$person_type = isset( $_POST['billing_person_type'] )
				? sanitize_text_field( wp_unslash( $_POST['billing_person_type'] ) )
				: '';
		}

		if ( 'pf' === $person_type
			|| (
				isset( $this->settings['person_type'] )
				&& 'pf' === $this->settings['person_type']
			)
		) {
			$this->valid_natural_person(
				'billing',
				'woocommerce-edit-address-nonce',
				'woocommerce-edit_address'
			);
		} elseif ( 'pj' === $person_type
			|| (
				isset( $this->settings['person_type'] )
				&& 'pj' === $this->settings['person_type']
			)
		) {
			$this->valid_legal_person(
				'billing',
				'woocommerce-edit-address-nonce',
				'woocommerce-edit_address'
			);
		}

		$this->valid_gender_and_birthdate(
			'billing',
			'woocommerce-edit-address-nonce',
			'woocommerce-edit_address'
		);

		$this->valid_neighborhood_and_number(
			'billing',
			'woocommerce-edit-address-nonce',
			'woocommerce-edit_address'
		);

		$this->valid_cellphone(
			'billing',
			'woocommerce-edit-address-nonce',
			'woocommerce-edit_address'
		);
	}

	/**
	 * Validate neighborhood and number fields for a given type.
	 *
	 * Verifies the nonce and checks if the neighborhood and number fields are set.
	 * If the district setting is enabled, it also checks for the neighborhood field.
	 * If any of the required fields are empty, it adds a notice to the WooCommerce session.
	 *
	 * @param string $type The type of fields to validate (e.g. 'billing').
	 * @param string $nonce_name The name of the nonce field.
	 * @param string $nonce_action The action of the nonce field.
	 *
	 * @return void
	 */
	private function valid_neighborhood_and_number( $type, $nonce_name, $nonce_action ) {
		if ( isset( $_POST[ $nonce_name ] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST[ $nonce_name ] ),
				),
				$nonce_action
			)
			&& isset( $_POST[ "{$type}_postcode" ] )
		) {
			if ( isset( $_POST[ "{$type}_number" ] )
				&& empty( $_POST[ "{$type}_number" ] ) ) {
				wc_add_notice(
					__(
						'Número é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}

			if ( ! isset( $_POST[ "{$type}_neighborhood" ] )
				|| empty( $_POST[ "{$type}_neighborhood" ] ) ) {
				wc_add_notice(
					__(
						'Bairro é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}
		}
	}

	/**
	 * Validates the gender and birthdate fields based on the provided settings.
	 *
	 * @param string $type The type of the fields to be validated.
	 * @param string $nonce_name The name of the nonce field.
	 * @param string $nonce_action The action of the nonce field.
	 *
	 * @return void
	 */
	private function valid_gender_and_birthdate( $type, $nonce_name, $nonce_action ) {
		if ( isset( $_POST[ $nonce_name ] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST[ $nonce_name ] ),
				),
				$nonce_action
			)
		) {
			if ( isset( $this->settings['birthday_date'] )
				&& 'yes' === $this->settings['birthday_date']
				&& (
					! isset( $_POST[ "{$type}_birthdate" ] )
					|| empty( $_POST[ "{$type}_birthdate" ] )
				)
			) {
				wc_add_notice(
					__(
						'Data de nascimento é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}

			if ( isset( $this->settings['gender'] )
				&& 'yes' === $this->settings['gender']
				&& (
					! isset( $_POST[ "{$type}_gender" ] )
					|| empty( $_POST[ "{$type}_gender" ] )
				)
			) {
				wc_add_notice(
					__(
						'Gênero é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}
		}
	}

	/**
	 * Validates natural person data.
	 *
	 * Checks if the CPF is valid and if the RG is filled (if required).
	 *
	 * @param string $type        Type of person (e.g. 'shipping', 'billing').
	 * @param string $nonce_name  Name of the nonce field.
	 * @param string $nonce_action Action of the nonce field.
	 *
	 * @return void
	 */
	private function valid_natural_person( $type, $nonce_name, $nonce_action ) {
		if ( isset( $_POST[ $nonce_name ] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST[ $nonce_name ] ),
				),
				$nonce_action
			)
		) {
			if ( ! isset( $_POST[ "{$type}_cpf" ] )
				|| empty( $_POST[ "{$type}_cpf" ] )
				|| (
					isset( $this->settings['validate_cpf'] )
					&& 'yes' === $this->settings['validate_cpf']
					&& (
						! isset( $_POST[ "{$type}_cpf" ] )
						|| ! $this->is_valid_cpf(
							sanitize_text_field(
								wp_unslash(
									$_POST[ "{$type}_cpf" ]
								)
							)
						)
					)
				)
			) {
				wc_add_notice(
					__(
						'CPF inválido.',
						'virtuaria-correios'
					),
					'error'
				);
			}

			if ( isset( $this->settings['rg'] )
				&& 'yes' === $this->settings['rg']
				&& ( ! isset( $_POST[ "{$type}_rg" ] )
				|| empty( $_POST[ "{$type}_rg" ] ) ) ) {
				wc_add_notice(
					__(
						'RG é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}
		}
	}

	/**
	 * Validates the legal person information.
	 *
	 * Checks if the CNPJ is valid and if the Inscrição Estadual is provided when required.
	 *
	 * @param string $type         The type of person (e.g. billing, shipping).
	 * @param string $nonce_name   The name of the nonce field.
	 * @param string $nonce_action The action of the nonce field.
	 *
	 * @return void
	 */
	private function valid_legal_person( $type, $nonce_name, $nonce_action ) {
		if ( isset( $_POST[ $nonce_name ] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST[ $nonce_name ] ),
				),
				$nonce_action
			)
		) {
			if ( ! isset( $_POST[ "{$type}_cnpj" ] )
				|| empty( $_POST[ "{$type}_cnpj" ] )
				|| (
					isset( $this->settings['validate_cnpj'] )
					&& 'yes' === $this->settings['validate_cnpj']
					&& ! $this->is_valid_cnpj(
						sanitize_text_field(
							wp_unslash( $_POST[ "{$type}_cnpj" ] )
						)
					)
			) ) {
				wc_add_notice(
					__(
						'CNPJ inválido.',
						'virtuaria-correios'
					),
					'error'
				);
			}

			if ( isset( $this->settings['ie'] )
				&& 'yes' === $this->settings['ie']
				&& ( ! isset( $_POST[ "{$type}_ie" ] )
				|| empty( $_POST['billing_ie'] ) ) ) {
				wc_add_notice(
					__(
						'A Inscrição Estadual é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}
		}
	}

	/**
	 * Valid cellphone.
	 *
	 * @param string $type        field type.
	 * @param string $nonce_name  the nonce name.
	 * @param string $nonce_action the action from nonce.
	 */
	private function valid_cellphone( $type, $nonce_name, $nonce_action ) {
		if ( isset( $_POST[ $nonce_name ] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST[ $nonce_name ] ),
				),
				$nonce_action
			)
		) {
			if ( isset( $this->settings['cell_phone'] )
				&& 'required' === $this->settings['cell_phone']
				&& (
					! isset( $_POST[ "{$type}_cellphone" ] )
					|| empty( $_POST[ "{$type}_cellphone" ] )
				)
			) {
				wc_add_notice(
					__(
						'Celular é um campo obrigatório.',
						'virtuaria-correios'
					),
					'error'
				);
			}
		}
	}

	/**
	 * Validates the custom checkout fields for checkout block.
	 *
	 * @param \WP_Error $errors    The WP_Error object.
	 * @param array     $fields    The fields from checkout block.
	 * @param string    $group     The type of the fields (e.g. 'shipping', 'billing').
	 */
	public function validate_block_checkout_fields( \WP_Error $errors, $fields, $group ) {
		foreach ( $fields as $key => $field ) {
			if ( ! isset( $fields['virtuaria-correios/person_type'] )
				|| 'pf' === $fields['virtuaria-correios/person_type'] ) {

				if ( 'virtuaria-correios/cpf' === $key ) {
					if ( empty( $field )
						|| ( isset( $this->settings['validate_cpf'] )
							&& 'yes' === $this->settings['validate_cpf']
							&& ! $this->is_valid_cpf( $field )
						)
					) {
						$errors->add( 'invalid_cpf', __( 'CPF inválido.', 'virtuaria-correios' ) );
					}
				}

				if ( 'virtuaria-correios/rg' === $key && empty( $field ) ) {
					$errors->add( 'invalid_rg', __( 'RG inválido.', 'virtuaria-correios' ) );
				}
			}

			if ( ! isset( $fields['virtuaria-correios/person_type'] )
				|| 'pj' === $fields['virtuaria-correios/person_type'] ) {

				if ( 'virtuaria-correios/cnpj' === $key
					&& (
						empty( $field )
						|| ( isset( $this->settings['validatecnpj'] )
							&& 'yes' === $this->settings['validate_cnpj']
							&& ! $this->is_valid_cnpj( $field )
						)
					)
				) {
					$errors->add( 'invalid_cnpj', __( 'CNPJ inválido.', 'virtuaria-correios' ) );
				}

				if ( 'virtuaria-correios/ie' === $key && empty( $field ) ) {
					$errors->add( 'invalid_ie', __( 'Inscrição Estadual inválida.', 'virtuaria-correios' ) );
				}
			}

			if ( 'virtuaria-correios/birthdate' === $key && ! $field ) {
				$errors->add(
					'required_field',
					__( 'Data de Nascimento é um campo obrigatório.', 'virtuaria-correios' )
				);
			} elseif ( 'virtuaria-correios/gender' === $key && ! $field ) {
				$errors->add(
					'required_field',
					__( 'Gênero é um campo obrigatório.', 'virtuaria-correios' )
				);
			} elseif ( 'virtuaria-correios/cellphone' === $key
				&& isset( $this->settings['cell_phone'] )
				&& 'required' === $this->settings['cell_phone']
				&& ! $field
			) {
				$errors->add(
					'required_field',
					__( 'Celular é um campo obrigatório.', 'virtuaria-correios' )
				);
			}
		}
	}

	/**
	 * Validates a Brazilian CPF (Cadastro de Pessoas Físicas) number.
	 *
	 * This function checks if the provided CPF number is valid according to the Brazilian government's rules.
	 * It removes any non-numeric characters, checks the length, and verifies the check digits.
	 *
	 * @param string $cpf The CPF number to be validated.
	 * @return bool True if the CPF is valid, false otherwise.
	 */
	private function is_valid_cpf( $cpf ) {
		$cpf = preg_replace( '/[^0-9]/', '', $cpf );

		if ( 11 !== strlen( $cpf ) || preg_match( '/^([0-9])\1+$/', $cpf ) ) {
			return false;
		}

		$digit = substr( $cpf, 0, 9 );

		for ( $j = 10; $j <= 11; $j++ ) {
			$sum = 0;

			for ( $i = 0; $i < $j - 1; $i++ ) {
				$sum += ( $j - $i ) * intval( $digit[ $i ] );
			}

			$summod11        = $sum % 11;
			$digit[ $j - 1 ] = $summod11 < 2 ? 0 : 11 - $summod11;
		}

		return intval( $digit[9] ) === intval( $cpf[9] )
			&& intval( $digit[10] ) === intval( $cpf[10] );
	}

	/**
	 * Validates a Brazilian CNPJ (Cadastro Nacional da Pessoa Jurídica) number.
	 *
	 * @param string $cnpj The CNPJ number to validate.
	 * @return bool Returns true if the CNPJ is valid, false otherwise.
	 */
	private function is_valid_cnpj( $cnpj ) {
		$cnpj = sprintf(
			'%014s',
			preg_replace( '{\\D}', '', $cnpj )
		);

		if ( 14 !== strlen( $cnpj ) || 0 === intval( substr( $cnpj, -4 ) ) ) {
			return false;
		}

		for ( $t = 11; $t < 13; ) {
			for ( $d = 0, $p = 2, $c = $t; $c >= 0; $c--, ( $p < 9 ) ? $p++ : $p = 2 ) {
				$d += $cnpj[ $c ] * $p;
			}

			$d = ( ( 10 * $d ) % 11 ) % 10;
			if ( intval( $cnpj[ ++$t ] ) !== $d ) {
				return false;
			}
		}

		return true;
	}
}

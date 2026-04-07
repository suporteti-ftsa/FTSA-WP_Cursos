<?php
/**
 * Handle extra fields for users in admin
 *
 * @package Virtuaria/integrations/correios
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add extra profile fields for users in admin
 */
class Virtuaria_Correios_Extra_Fields_Profile {
	use Virtuaria_Correios_Fields;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialize functions.
	 *
	 * @param array $field_settings extra field settings.
	 */
	public function __construct( $field_settings ) {
		$this->settings = $field_settings;
		add_filter( 'woocommerce_customer_meta_fields', array( $this, 'add_customer_meta_fields' ) );
	}

	/**
	 * Add extra fields to the user profile in the admin panel.
	 *
	 * Adds the following fields:
	 * - CPF
	 * - RG
	 * - CNPJ
	 * - IE
	 * - Data de nascimento
	 * - Gênero
	 * - Bairro
	 *
	 * @param array $fields The array of fields for the user profile.
	 * @return array The updated array of fields for the user profile.
	 */
	public function add_customer_meta_fields( $fields ) {
		$pos = 2;

		if ( isset( $this->settings['person_type'] )
			&& 'none' !== $this->settings['person_type'] ) {
			if ( in_array( $this->settings['person_type'], array( 'pf', 'both' ), true ) ) {
				$this->add_elem_specific_position(
					$fields['billing']['fields'],
					array(
						'label'       => __( 'CPF', 'virtuaria-correios' ),
						'description' => '',
					),
					$pos++,
					'billing_cpf'
				);
				if ( isset( $this->settings['birthday_rg'] )
					&& 'yes' === $this->settings['birthday_rg'] ) {
					$this->add_elem_specific_position(
						$fields['billing']['fields'],
						array(
							'label'       => __( 'RG', 'virtuaria-correios' ),
							'description' => '',
						),
						$pos++,
						'billing_rg'
					);
				}
			}

			if ( in_array( $this->settings['person_type'], array( 'pj', 'both' ), true ) ) {
				$this->add_elem_specific_position(
					$fields['billing']['fields'],
					array(
						'label'       => __( 'CNPJ', 'virtuaria-correios' ),
						'description' => '',
					),
					$pos++,
					'billing_cnpj'
				);
				if ( isset( $this->settings['birthday_ie'] )
					&& 'yes' === $this->settings['birthday_ie'] ) {
					$this->add_elem_specific_position(
						$fields['billing']['fields'],
						array(
							'label'       => __( 'Inscrição Estadual', 'virtuaria-correios' ),
							'description' => '',
						),
						$pos++,
						'billing_ie'
					);
				}
				++$pos; // Company position.
			} else {
				unset( $fields['billing']['fields']['billing_company'] );
				unset( $fields['shipping']['fields']['shipping_company'] );
				--$pos;
			}
		}

		if ( isset( $this->settings['birthday_date'] )
			&& 'yes' === $this->settings['birthday_date'] ) {
			$this->add_elem_specific_position(
				$fields['billing']['fields'],
				array(
					'label'       => __( 'Data de Nascimento', 'virtuaria-correios' ),
					'description' => '',
				),
				$pos++,
				'billing_birthdate'
			);
		}

		if ( isset( $this->settings['gender'] )
			&& 'yes' === $this->settings['gender'] ) {
			$this->add_elem_specific_position(
				$fields['billing']['fields'],
				array(
					'label'       => __( 'Gênero', 'virtuaria-correios' ),
					'description' => '',
				),
				$pos++,
				'billing_gender'
			);
		}

		$this->add_elem_specific_position(
			$fields['billing']['fields'],
			array(
				'label'       => __( 'Número', 'virtuaria-correios' ),
				'description' => '',
			),
			1 + $pos++,
			'billing_number'
		);

		$this->add_elem_specific_position(
			$fields['billing']['fields'],
			array(
				'label'       => __( 'Bairro', 'virtuaria-correios' ),
				'description' => '',
			),
			1 + $pos++,
			'billing_neighborhood'
		);

		$pos = 4;

		if ( ! isset( $fields['shipping']['fields']['shipping_company'] ) ) {
			--$pos;
		}

		$this->add_elem_specific_position(
			$fields['shipping']['fields'],
			array(
				'label'       => __( 'Número', 'virtuaria-correios' ),
				'description' => '',
			),
			$pos++,
			'shipping_number'
		);

		$this->add_elem_specific_position(
			$fields['shipping']['fields'],
			array(
				'label'       => __( 'Bairro', 'virtuaria-correios' ),
				'description' => '',
			),
			1 + $pos++,
			'shipping_neighborhood'
		);

		return $fields;
	}
}

<?php
/**
 * Correios SEDEX shipping method.
 *
 * @package Virtuaria/Integrations/Shipping.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sedex shipping method class.
 */
class Virtuaria_Correios_Sedex extends Virtuaria_Correios_Shipping {

	/**
	 * Service code.
	 * 03220 - SEDEX CONTRATO AG.
	 *
	 * @var string
	 */
	protected $code = '03220';

	/**
	 * Initialize Sedex.
	 *
	 * @param int $instance_id Shipping zone instance.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = 'virtuaria-correios-sedex';
		$this->method_title = __( 'Virtuaria Correios', 'woocommerce-correios' );

		parent::__construct( $instance_id );
	}
}

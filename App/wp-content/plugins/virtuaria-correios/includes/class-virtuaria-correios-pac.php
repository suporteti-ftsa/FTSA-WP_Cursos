<?php
/**
 * Correios PAC shipping method.
 *
 * @package Virtuaria/Integrations/Shipping.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pac shipping method class.
 */
class Virtuaria_Correios_Pac extends Virtuaria_Correios_Shipping {

	/**
	 * Service code.
	 * 03298 - PAC CONTRATO AG.
	 *
	 * @var string
	 */
	protected $code = '03298';

	/**
	 * Initialize Pac.
	 *
	 * @param int $instance_id Shipping zone instance.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = 'virtuaria-correios-pac';
		$this->method_title = __( 'Correios Pac', 'woocommerce-correios' );

		parent::__construct( $instance_id );
	}
}

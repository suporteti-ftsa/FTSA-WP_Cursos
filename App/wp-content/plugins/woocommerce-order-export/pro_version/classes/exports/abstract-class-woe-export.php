<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WOE_Export {
	var $destination;
	var $finished_successfully = false;

	public function __construct( $destination ) {
		$this->destination = $destination;
		$this->destination = apply_filters( 'woe_exporter_destination', $this->destination, $this );
	}

	//must be imlemented
	abstract public function run_export( $filename, $filepath, $num_retries, $is_last_order = true );


	public function get_num_of_retries() {
		return 1;
	}
}

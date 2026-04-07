<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WOE_Export_Folder extends WOE_Export {

	public function run_export( $filename, $filepath, $num_retries, $is_last_order = true ) {
		if ( empty( $this->destination['path'] ) ) {
			$this->destination['path'] = ABSPATH;
		}
		$folder = apply_filters("wc_order_export_folder", $this->destination['path'],$filename);


		if ( preg_match( '#\.php$#i', $filename ) ) {
			return __( "Creating PHP files is prohibited.", 'woocommerce-order-export' );
		}

		if ( ! file_exists( $folder ) ) {
            //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			if ( @ ! mkdir( $folder, 0777, true ) ) {
                /* translators: Inability to create a folder */
				return sprintf( __( "Can't create folder '%s'. Check permissions.", 'woocommerce-order-export' ),
					$folder );
			}
		}
        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! is_writable( $folder ) ) {
            /* translators: Inability to write to a folder */
			return sprintf( __( "Folder '%s' is not writable. Check permissions.", 'woocommerce-order-export' ),
				$folder );
		}

		$output_filepath = $folder . "/" . $filename;
		if( file_exists($output_filepath ) and has_filter("woe_folder_file_append_function")) {
			if( !apply_filters("woe_folder_file_append_function", true, $filepath, $output_filepath) )
                /* translators: Inability to add records to a folder */
				return sprintf( __( "Can't append records to '%s'. Check permissions.", 'woocommerce-order-export' ),
					$folder );
		}
		elseif ( @ ! copy( $filepath, $output_filepath ) ) {
            /* translators: Inability to export file to a folder */
			return sprintf( __( "Can't export file to '%s'. Check permissions.", 'woocommerce-order-export' ),
				$folder );
		}

		$this->finished_successfully = true;
		do_action("woe_folder_file_created", $output_filepath);

        /* translators: Creating a file in a folder */
        return sprintf( __( "File '%1\$s' has been created in folder '%2\$s'", 'woocommerce-order-export' ), $filename,
			$folder );
	}

}

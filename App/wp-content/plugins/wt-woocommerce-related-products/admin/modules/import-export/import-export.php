<?php
/**
 * Related products - Import Export
 *
 * @link
 * @since 1.4.2
 *
 * @package  Custom_Related_Products
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Custom related product import export.
 *
 * @package WooCommerce Related Products
 * @since 1.0.0
 */
class Custom_Related_Product_Import_Export {

	/**
	 * Initialize the import/export functionality.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_dependents' ) );
	}

	/**
	 * Initialize the import/export functionality.
	 *
	 * @since 1.4.2
	 */
	public function init() {
		$this->load_import_export_vendors();
		$this->remove_existing_filters();
	}

	/**
	 * Loading supported vendors for import/export
	 *
	 * @since 1.4.2
	 */
	public function load_import_export_vendors() {
		$this->load_woocommerce_default_import_export();
	}

	/**
	 * To remove the previously added filters for import/export through theme or external plugins.
	 *
	 * @since 1.4.2
	 */
	public function remove_existing_filters() {
		remove_filter( 'woocommerce_product_export_meta_value', 'webtoffee_related_products_export' );
		remove_filter( 'woocommerce_product_importer_parsed_data', 'woocommerce_product_importer_parsed_data' );
	}

	/**
	 * Support for woocommerce's default import/export
	 *
	 * @since 1.4.2
	 */
	public function load_woocommerce_default_import_export() {

		add_filter( 'woocommerce_product_export_meta_value', array( $this, 'process_woocommerce_default_export' ), 11, 2 );
		add_filter( 'woocommerce_product_importer_parsed_data', array( $this, 'process_woocommerce_default_import' ), 10, 1 );
		add_filter( 'wt_batch_product_export_row_data', array( $this, 'process_webtoffee_export' ), 10, 1 );
		add_filter( 'wt_woocommerce_product_import_process_item_data', array( $this, 'process_webtoffee_import' ), 10, 1 );
	}

	/**
	 * Process data of woocommerce's default export
	 *
	 * @since 1.4.2
	 * @param string $value Meta value.
	 * @param object $meta Meta object.
	 * @return string
	 */
	public function process_woocommerce_default_export( $value, $meta ) {
		$wt_crp_meta_keys = $this->get_crp_meta_keys();
		if ( in_array( $meta->key, $wt_crp_meta_keys, true ) ) {
			if ( '_crp_related_product_attr' === $meta->key ) {
				$value = $this->process_related_attr_for_export( $value );
			}
			if ( '_crp_excluded_cats' === $meta->key || '_crp_related_product_cats' === $meta->key || '_crp_related_product_tags' === $meta->key ) {
				$value = $this->process_term_id_for_export( $value );
			}
			return implode( ',', $value );
		} else {
			return $value;
		}
	}

	/**
	 * Process data of webtoffee export
	 *
	 * @since 1.4.2
	 * @param array $row Row data.
	 * @return array
	 */
	public function process_webtoffee_export( $row ) {
		$wt_crp_meta_keys = $this->get_crp_meta_keys();
		foreach ( $wt_crp_meta_keys as $crp_key => $crp_value ) {
			$crp_value = 'meta:' . $crp_value;
			if ( array_key_exists( $crp_value, $row ) && ! empty( $row[ $crp_value ] ) ) {
				$value = json_decode( $row[ $crp_value ] );

				if ( 'meta:_crp_related_product_attr' === $crp_value ) {
					$value = $this->process_related_attr_for_export( $value );
				}
				if ( 'meta:_crp_excluded_cats' === $crp_value || 'meta:_crp_related_product_cats' === $crp_value || 'meta:_crp_related_product_tags' === $crp_value ) {
					$value = $this->process_term_id_for_export( $value );
				}

				$row[ $crp_value ] = implode( ',', $value );
			}
		}
		return $row;
	}


	/**
	 * Process data of all_export export
	 *
	 * @since 1.4.2
	 * @param array $rows Rows data.
	 * @return array
	 */
	public function process_all_export_export( $rows ) {
		$wt_crp_meta_keys = $this->get_crp_meta_keys();
		foreach ( $wt_crp_meta_keys as $crp_key => $crp_value ) {
			foreach ( $rows as $r_key => $row ) {
				if ( array_key_exists( $crp_value, $row ) && ! empty( $row[ $crp_value ] ) ) {
					$value = wt_unserialize_safe( $row[ $crp_value ] );
					if ( '_crp_related_product_attr' === $crp_value ) {
						$value = $this->process_related_attr_for_export( $value );
					}
					if ( '_crp_excluded_cats' === $crp_value || '_crp_related_product_cats' === $crp_value || '_crp_related_product_tags' === $crp_value ) {
						$value = $this->process_term_id_for_export( $value );
					}
					if ( ! empty( $value ) ) {
						$rows[ $r_key ][ $crp_value ] = implode( ',', $value );
					}
				}
			}
		}
		return $rows;
	}

	/**
	 * Process data of woocommerce's default import
	 *
	 * @since 1.4.2
	 * @param array $data Parsed data.
	 * @return array
	 */
	public function process_woocommerce_default_import( $data ) {

		$wt_crp_meta_keys = $this->get_crp_meta_keys();
		if ( ! empty( $data['meta_data'] ) ) {
			foreach ( $data['meta_data'] as $mkey => $mvalue ) {

				if ( in_array( $mvalue['key'], $wt_crp_meta_keys, true ) && is_string( $mvalue['key'] ) ) {
					$custom_meta = explode( ',', $mvalue['value'] );
					if ( '_crp_related_product_attr' === $mvalue['key'] ) {
						$custom_meta = $this->process_related_attr_for_import( $custom_meta );
					}

					if ( '_crp_excluded_cats' === $mvalue['key'] || '_crp_related_product_cats' === $mvalue['key'] || '_crp_related_product_tags' === $mvalue['key'] ) {
						$custom_meta = $this->process_term_id_for_import( $custom_meta, $mvalue['key'] );
					}

					if ( '_crp_related_skus' === $mvalue['key'] ) {
						$custom_meta                       = $this->get_product_id_from_sku( $custom_meta );
						$data['meta_data'][ $mkey ]['key'] = '_crp_related_ids';
					}

					$en_value                            = wp_json_encode( $custom_meta, JSON_NUMERIC_CHECK );
					$custom_meta_data                    = json_decode( $en_value, true );
					$data['meta_data'][ $mkey ]['value'] = $custom_meta_data;
				}
			}
		}

		return $data;
	}

	/**
	 * Process data of webtoffee import
	 *
	 * @since 1.4.2
	 * @param array $meta Meta data.
	 * @return array
	 */
	public function process_webtoffee_import( $meta ) {

		$wt_crp_meta_keys = $this->get_crp_meta_keys();
		if ( isset( $meta['meta_data'] ) && is_array( $meta['meta_data'] ) ) {
			foreach ( $meta['meta_data'] as $key => $meta_data ) {
				if ( in_array( $meta_data['key'], $wt_crp_meta_keys, true ) ) {
						$custom_meta = explode( ',', $meta_data['value'] );
					if ( '_crp_related_product_attr' === $meta_data['key'] ) {
						$custom_meta = $this->process_related_attr_for_import( $custom_meta );
					}
					if ( '_crp_excluded_cats' === $meta_data['key'] || '_crp_related_product_cats' === $meta_data['key'] || '_crp_related_product_tags' === $meta_data['key'] ) {
						$custom_meta = $this->process_term_id_for_import( $custom_meta, $meta_data['key'] );
					}
					if ( '_crp_related_skus' === $meta_data['key'] ) {
						$custom_meta                      = $this->get_product_id_from_sku( $custom_meta );
						$meta['meta_data'][ $key ]['key'] = '_crp_related_ids';
					}
						$meta['meta_data'][ $key ]['value'] = $custom_meta;

				}
			}
		}
		return $meta;
	}

	/**
	 * Process data of all_import import
	 *
	 * @since 1.4.2
	 * @param int    $pid Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $meta_value Meta value.
	 * @return void
	 */
	public function process_all_import_import( $pid, $meta_key, $meta_value ) {

		$wt_crp_meta_keys = $this->get_crp_meta_keys();
		if ( in_array( $meta_key, $wt_crp_meta_keys, true ) ) {
			$custom_meta = explode( ',', $meta_value );
			if ( '_crp_related_product_attr' === $meta_key ) {
				$custom_meta = $this->process_related_attr_for_import( $custom_meta );
			}

			if ( '_crp_excluded_cats' === $meta_key || '_crp_related_product_cats' === $meta_key || '_crp_related_product_tags' === $meta_key ) {
				$custom_meta = $this->process_term_id_for_import( $custom_meta, $meta_key );
			}

			if ( '_crp_related_skus' === $meta_key ) {
				$custom_meta = $this->get_product_id_from_sku( $custom_meta );
				$meta_key    = '_crp_related_ids';
			}
			update_post_meta( $pid, $meta_key, $custom_meta );
		}
	}
	/**
	 * Get meta keys of the plugin
	 *
	 * @since 1.4.2
	 * @return array
	 */
	public function get_crp_meta_keys() {
		return array(
			'_crp_related_ids',
			'_crp_related_product_tags',
			'_crp_related_product_cats',
			'_crp_related_product_attr',
			'_crp_excluded_cats',
			'_crp_related_skus',
		);
	}

	/**
	 * Process related attributes values for export
	 *
	 * @since 1.4.2
	 * @param array $attr_data Attribute data.
	 * @return array
	 */
	public function process_related_attr_for_export( $attr_data ) {

		$processed_attr = array();
		if ( ! empty( $attr_data ) ) {
			foreach ( $attr_data as $slug => $term_id_list ) {
				foreach ( $term_id_list as $term_id ) {
					$term             = get_term( $term_id );
					$term_id          = $term->slug;
					$processed_attr[] = "$slug:$term_id";
				}
			}
		}

		return $processed_attr;
	}

	/**
	 * Process related term values for export
	 *
	 * @since 1.4.2
	 * @param array $term_data Term data.
	 * @return array
	 */
	public function process_term_id_for_export( $term_data ) {

		$processed_term = array();
		if ( ! empty( $term_data ) ) {
			foreach ( $term_data as $term_id ) {
				$term             = get_term( $term_id );
				$term_id          = $term->slug;
				$processed_term[] = $term_id;
			}
		}

		return $processed_term;
	}

	/**
	 * Process related attributes values for import
	 *
	 * @since 1.4.2
	 * @param array $attr_data Attribute data.
	 * @return array
	 */
	public static function process_related_attr_for_import( $attr_data ) {

		$processed_attr = array();
		if ( ! empty( $attr_data ) ) {
			foreach ( $attr_data as $slug_termid ) {
				$exploded = explode( ':', $slug_termid );
				if ( ! empty( $exploded[0] ) && ! empty( $exploded[1] ) ) {
					$tax_type                         = 'pa_' . $exploded[0];
					$term_data                        = get_term_by( 'slug', $exploded[1], $tax_type );
					$processed_attr[ $exploded[0] ][] = $term_data->term_id;
				}
			}
		}

		return $processed_attr;
	}


	/**
	 * Process related attributes values for import
	 *
	 * @since 1.4.2
	 * @param array  $term_data  Term data.
	 * @param string $type Type.
	 * @return array
	 */
	public static function process_term_id_for_import( $term_data, $type ) {

		$processed_term = array();
		if ( ! empty( $term_data ) ) {
			foreach ( $term_data as $slug_termid ) {
				if ( ! empty( $slug_termid ) ) {
					if ( '_crp_excluded_cats' === $type || '_crp_related_product_cats' === $type ) {
						$tax_type = 'product_cat';
					}

					if ( '_crp_related_product_tags' === $type ) {
						$tax_type = 'product_tag';
					}
					$term_data        = get_term_by( 'slug', $slug_termid, $tax_type );
					$processed_term[] = $term_data->term_id;
				}
			}
		}

		return $processed_term;
	}

	/**
	 * Get product id from sku
	 *
	 * @since 1.4.2
	 * @param array $product_skus Product SKUs.
	 * @return array
	 */
	public function get_product_id_from_sku( $product_skus ) {

		$product_ids = array();
		foreach ( $product_skus as $sku ) {
			$product_ids[] = wc_get_product_id_by_sku( trim( $sku ) );
		}

		return $product_ids;
	}

	/**
	 * Load dependent functions for import export
	 *
	 * @since 1.4.2
	 * @return void
	 */
	public function load_dependents() {
		add_action( 'pmxi_update_post_meta', array( $this, 'process_all_import_import' ), 1, 3 );
		add_filter( 'wp_all_export_csv_rows', array( $this, 'process_all_export_export' ), 10, 1 );
	}

	/**
	 * Safe custom unserialize function that handles only basic types
	 *
	 * @since 1.7.4
	 * @param string $data Serialized data.
	 * @return mixed Unserialized data (only int, string, bool, array)
	 */
	public static function wt_unserialize_safe( $data ) {

		if ( empty( $data ) ) {
			return false;
		}
		$offset = 0;

		// Recursive function to handle different types.
		$unserialize_value = function ( &$offset ) use ( $data, &$unserialize_value ) {
			$type = $data[ $offset ];
			++$offset;

			switch ( $type ) {
				case 's': // String.
					preg_match( '/:(\d+):"/', $data, $matches, 0, $offset );
					$length  = (int) $matches[1];
					$offset += strlen( $matches[0] );
					$value   = substr( $data, $offset, $length );
					$offset += $length + 2; // Skip closing quotes and semicolon.
					return $value;

				case 'i': // Integer.
					preg_match( '/:(-?\d+);/', $data, $matches, 0, $offset );
					$offset += strlen( $matches[0] );
					return (int) $matches[1];

				case 'd': // Float/Double.
					preg_match( '/:(-?\d+(\.\d+)?);/', $data, $matches, 0, $offset );
					$offset += strlen( $matches[0] );
					return (float) $matches[1];

				case 'b': // Boolean.
					preg_match( '/:(\d);/', $data, $matches, 0, $offset );
					$offset += strlen( $matches[0] );
					return (bool) $matches[1];

				case 'N': // NULL.
					++$offset; // Move past ';'.
					return false;

				case 'a': // Array.
					preg_match( '/:(\d+):{/', $data, $matches, 0, $offset );
					$num_elements = (int) $matches[1];
					$offset      += strlen( $matches[0] );

					$result = array();
					for ( $i = 0; $i < $num_elements; $i++ ) {
						$key            = $unserialize_value( $offset );
						$value          = $unserialize_value( $offset );
						$result[ $key ] = $value;
					}

					++$offset; // Move past closing '}'.
					return $result;

				case 'O': // Object (Convert to Array).
					preg_match( '/:(\d+):"([^"]+)":(\d+):{/', $data, $matches, 0, $offset );
					$num_properties = (int) $matches[3];
					$offset        += strlen( $matches[0] );

					$result = array();
					for ( $i = 0; $i < $num_properties; $i++ ) {
						$key            = $unserialize_value( $offset );
						$value          = $unserialize_value( $offset );
						$result[ $key ] = $value;
					}

					++$offset; // Move past closing '}'.
					return $result; // Object converted into an array.

				default:
					// Skip unsupported type.
					return false;
			}
		};

		return $unserialize_value( $offset );
	}
}
new Custom_Related_Product_Import_Export();

<?php
/**
 * Handle installation.
 *
 * @package Virtuaria/Correios
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle installation process.
 */
class Virtuaria_Correios_Install {
	/**
	 * Constructor for the Virtuaria_Correios_Install class.
	 *
	 * Initializes the installation process for the Virtuaria Correios plugin.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_display_import_screen' ) );
		add_action( 'admin_footer', array( $this, 'maybe_display_template_install' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_disable_wizard_install', array( $this, 'disable_wizard_install' ) );
		add_action( 'wp_ajax_import_settings', array( $this, 'import_settings' ) );
	}

	/**
	 * Check if plugin is installed and redirect to wizard if settings are not filled.
	 *
	 * If plugin is installed and settings are not filled, redirect to wizard page.
	 *
	 * @since 1.8.2
	 */
	public function maybe_display_import_screen() {
		if ( get_option( 'virtuaria_correios_installed' )
			&& ! $this->install_valid()
			&& wp_safe_redirect( admin_url( 'admin.php?page=virtuaria-settings&action=install' ) )
		) {
			delete_option( 'virtuaria_correios_installed' );
			add_option( 'virtuaria_correios_display_install', true );
			exit;
		}
	}

	/**
	 * Validates the installation by checking if the necessary settings
	 * (username, password, and post card) are set and not empty.
	 *
	 * @return bool Returns true if all required settings are set and not empty, false otherwise.
	 */
	private function install_valid() {
		$setting = Virtuaria_WPMU_Correios_Settings::get_settings();

		if ( isset( $settting['username'], $setting['password'], $setting['post_card'] )
			&& ! empty( $setting['username'] )
			&& ! empty( $setting['password'] )
			&& ! empty( $setting['post_card'] )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if the "install" page should be displayed.
	 *
	 * Returns true if the "virtuaria_correios_display_install" option is set
	 * and the 'action' query string parameter is set to 'install'.
	 *
	 * @return bool
	 */
	private function should_display_install() {
		return get_option( 'virtuaria_correios_display_install' )
			&& isset( $_GET['action'], $_GET['page'] )
			&& (
				$this->has_old_correios_settings()
				|| $this->get_shipping_method_count( 'melhor-envios' ) > 0
			)
			&& 'virtuaria-settings' === $_GET['page']
			&& 'install' === $_GET['action'];
	}

	/**
	 * Conditionally displays the install template.
	 *
	 * Checks if the install page should be displayed using the
	 * `should_display_install` method. If true, it requires the
	 * `html-correios-install.php` template located in the templates
	 * directory of the plugin.
	 */
	public function maybe_display_template_install() {
		if ( $this->should_display_install() ) {
			$available_imports = array();

			$claudio_count = $this->get_shipping_method_count();
			$menvios_count = $this->get_shipping_method_count( 'melhor-envios' );
			if ( $claudio_count > 0 || $this->has_old_correios_settings() ) {
				$available_imports[] = array(
					'plugin_title' => __( 'Claudio Sanches - Correios for WooCommerce', 'virtuaria-correios' ),
					'count_itens'  => $claudio_count,
					'class'        => 'woocommerce-correios',
				);
			}

			if ( $menvios_count > 0 ) {
				$available_imports[] = array(
					'plugin_title' => __( 'Melhor Envios', 'virtuaria-correios' ),
					'count_itens'  => $menvios_count,
					'class'        => 'melhor-envios',
				);
			}
			require_once VIRTUARIA_CORREIOS_DIR . 'templates/html-correios-install.php';
		}
	}

	/**
	 * Admin enqueue styles and scripts.
	 *
	 * @param string $hook page description.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'toplevel_page_virtuaria-settings' === $hook
			&& $this->should_display_install() ) {
			$dir = VIRTUARIA_CORREIOS_DIR . 'admin/';
			$url = VIRTUARIA_CORREIOS_URL . 'admin/';

			wp_enqueue_script(
				'virtuaria-correios-install',
				VIRTUARIA_CORREIOS_URL . 'admin/js/install.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/js/install.js' ),
				true
			);

			wp_localize_script(
				'virtuaria-correios-install',
				'install',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'disable-wizard-install' ),
				)
			);

			wp_enqueue_style(
				'virtuaria-correios-install',
				$url . 'css/modal-install.css',
				array(),
				filemtime( $dir . 'css/modal-install.css' )
			);
		}
	}

	/**
	 * AJAX callback to disable wizard install page.
	 *
	 * @since 1.8.2
	 */
	public function disable_wizard_install() {
		if ( isset( $_POST['nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['nonce'] )
				),
				'disable-wizard-install'
			)
		) {
			delete_option( 'virtuaria_correios_display_install' );
			echo esc_url( admin_url( 'admin.php?page=virtuaria-settings' ) );
		}
		wp_die();
	}

	/**
	 * Check if old Correios Integration plugin settings are available.
	 *
	 * @return bool True if the old plugin settings are available, false otherwise.
	 */
	private function has_old_correios_settings() {
		$settings = get_option( 'woocommerce_correios-integration_settings' );
		return isset( $settings['cws_username'], $settings['cws_access_code'], $settings['cws_posting_card'] );
	}

	/**
	 * Count the number of enabled shipping methods in all shipping zones.
	 *
	 * @param string $plugin Optional. The plugin to count shipping methods for. Defaults to 'claudio'.
	 *                        If 'claudio', counts WC_Correios_Shipping_Cws instances.
	 *                        If 'melhor-envios', counts WC_Melhor_Envio_Shipping_Correios_Pac, WC_Melhor_Envio_Shipping_Correios_Sedex, and WC_Melhor_Envio_Shipping_Correios_Mini instances.
	 *
	 * @return int The number of enabled shipping methods.
	 */
	public function get_shipping_method_count( $plugin = 'claudio' ) {
		$shipping_zones = \WC_Shipping_Zones::get_zones();

		$count = 0;

		foreach ( $shipping_zones as $zone ) {
			/**
			 * Current Instance of WC_Shipping_Method.
			 *
			 * @var \WC_Shipping_Method $shipping_method
			 */
			foreach ( $zone['shipping_methods'] as $shipping_method ) {
				if ( ( 'claudio' === $plugin && ! $shipping_method instanceof WC_Correios_Shipping_Cws )
					|| (
						'claudio' !== $plugin
						&& (
							! $shipping_method instanceof WC_Melhor_Envio_Shipping_Correios_Pac
							&& ! $shipping_method instanceof WC_Melhor_Envio_Shipping_Correios_Sedex
							&& ! $shipping_method instanceof WC_Melhor_Envio_Shipping_Correios_Mini
						)
					)
					|| ! $shipping_method->is_enabled() ) {
					continue;
				}

				++$count;
			}
		}

		return $count;
	}

	/**
	 * AJAX callback to import settings from WC_Correios_Shipping_Cws to Virtuaria_Correios_Shipping.
	 *
	 * Iterates through all shipping zones and their shipping methods to find instances of WC_Correios_Shipping_Cws
	 * and clone their settings to a Virtuaria_Correios_Shipping instance in the same shipping zone.
	 *
	 * @since 1.8.2
	 */
	public function import_settings() {
		if ( isset( $_POST['nonce'], $_POST['allow_import'], $_POST['inactive_methods'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['nonce'] )
				),
				'disable-wizard-install'
			)
		) {
			$disable_imported_methods = 'true' === sanitize_text_field( wp_unslash( $_POST['inactive_methods'] ) );

			$allow_import = array_map(
				'sanitize_text_field',
				wp_unslash( $_POST['allow_import'] )
			);

			$methods_imported = false;

			$this->import_woocommerce_correios_preferences();

			$need_import_methods = false;
			if ( is_array( $allow_import ) && ! empty( $allow_import ) ) {
				foreach ( $allow_import as $value ) {
					if ( 'true' === $value ) {
						$need_import_methods = true;
						break;
					}
				}
			}

			if ( $need_import_methods ) {
				$shipping_zones = \WC_Shipping_Zones::get_zones();

				foreach ( $shipping_zones as $zone ) {
					/**
					 * Current Instance of WC_Shipping_Method.
					 *
					 * @var \WC_Shipping_Method $shipping_method
					 */
					foreach ( $zone['shipping_methods'] as $shipping_method ) {
						if ( $shipping_method->is_enabled() !== true ) {
							continue;
						}

						if ( $this->is_method_woocorreios( $shipping_method )
							&& (
								! isset( $allow_import['woocorreios'] )
								|| 'true' !== $allow_import['woocorreios']
							)
						) {
							continue;
						}

						if ( $this->is_method_menvios( $shipping_method )
							&& (
								! isset( $allow_import['menvios'] )
								|| 'true' !== $allow_import['menvios']
							)
						) {
							continue;
						}

						if ( ! $this->is_method_woocorreios( $shipping_method )
							&& ! $this->is_method_menvios( $shipping_method ) ) {
							continue;
						}

						$created_zone = new \WC_Shipping_Zone( $zone['id'] );
						$instance_id  = $created_zone->add_shipping_method( 'virtuaria-correios-sedex' );

						if ( 0 === $instance_id ) {
							continue;
						}

						try {
							$created_shipping_method = \WC_Shipping_Zones::get_shipping_method( $instance_id );

							$created_shipping_method->init_instance_settings();

							$data = $this->migrate_shipping_settings( $shipping_method, $created_shipping_method );

							foreach ( $created_shipping_method->get_instance_form_fields() as $key => $field ) {
								if ( 'title' !== $created_shipping_method->get_field_type( $field ) ) {
									try {
										$created_shipping_method->instance_settings[ $key ] = $created_shipping_method->get_field_value( $key, $field, $data );
									} catch ( \Exception $e ) {
										$created_shipping_method->add_error( $e->getMessage() );
									}
								}
							}

							update_option(
								$created_shipping_method->get_instance_option_key(),
								$created_shipping_method->instance_settings,
								'yes'
							);

							if ( $disable_imported_methods ) {
								$this->disable_shipping_method( $shipping_method->instance_id );
							}

							$methods_imported = true;
						} catch ( \Exception $e ) {
							$created_zone->delete_shipping_method( $instance_id );
							continue;
						}
					}
				}
			} else {
				$methods_imported = true;
			}

			if ( $methods_imported ) {
				echo esc_url( admin_url( 'admin.php?page=virtuaria-settings' ) );
			}
		}
		wp_die();
	}


	/**
	 * Migrate shipping settings from source shipping method to destination shipping method.
	 *
	 * When a shipping method is enabled on a shipping zone, this method is called to import
	 * the settings from the source shipping method to the destination shipping method.
	 *
	 * @param WC_Shipping_Method $source The source shipping method object.
	 * @param WC_Shipping_Method $destination The destination shipping method object.
	 *
	 * @return array An associative array containing the migrated settings.
	 *
	 * @throws \Exception If the source shipping method is not supported.
	 *
	 * @since 1.8.2
	 */
	private function migrate_shipping_settings( $source, $destination ) {
		$field_enabled            = $destination->get_field_key( 'enabled' );
		$field_title              = $destination->get_field_key( 'title' );
		$field_origin             = $destination->get_field_key( 'origin' );
		$field_service_cod        = $destination->get_field_key( 'service_cod' );
		$field_hide_delivery_time = 'yes' === $destination->get_field_key( 'hide_delivery_time' )
			? null
			: 'yes';
		$field_additional_time    = $destination->get_field_key( 'additional_time' );
		$field_extra_weight       = $destination->get_field_key( 'extra_weight' );
		$field_fee                = $destination->get_field_key( 'fee' );
		$field_receipt_notice     = $destination->get_field_key( 'receipt_notice' );
		$field_own_hands          = $destination->get_field_key( 'own_hands' );
		$field_declare_value      = $destination->get_field_key( 'declare_value' );
		$field_minimum_height     = $destination->get_field_key( 'minimum_height' );
		$field_minimum_width      = $destination->get_field_key( 'minimum_width' );
		$field_minimum_length     = $destination->get_field_key( 'minimum_length' );
		$field_minimum_weight     = $destination->get_field_key( 'minimum_weight' );
		$field_object_type        = $destination->get_field_key( 'object_type' );
		$field_min_value_declared = $destination->get_field_key( 'min_value_declared' );
		$store_postcode           = get_option( 'woocommerce_store_postcode' );

		$shipping_data = array();
		foreach ( $destination->get_instance_form_fields() as $key => $field ) {
			$field_key = $destination->get_field_key( $key );
			if ( 'checkbox' === $field['type'] ) {
				$post_data[ $field_key ] = 'yes' === $field['default']
					? 'yes'
					: null;
				continue;
			}
			$post_data[ $field_key ] = $field['default'] ?? '';
		}

		if ( 'correios-cws' === $source->id ) {
			$postcode = $source->get_option( 'origin_postcode', '' );

			$shipping_data = array_merge(
				$shipping_data,
				array(
					$field_enabled            => $source->is_enabled(),
					$field_title              => $source->get_title(),
					$field_origin             => empty( $postcode ) ? $store_postcode : $postcode,
					$field_service_cod        => $source->get_option( 'product_code' ),
					$field_hide_delivery_time => 'yes' !== $source->get_option( 'show_delivery_time' )
						? 'yes'
						: null,
					$field_additional_time    => $source->get_option( 'additional_time' ),
					$field_extra_weight       => $source->get_option( 'extra_weight' ),
					$field_fee                => $source->get_option( 'fee' ),
					$field_receipt_notice     => 'yes' === $source->get_option( 'receipt_notice' )
						? 'yes'
						: null,
					$field_own_hands          => 'yes' === $source->get_option( 'own_hands' )
						? 'yes'
						: null,
					$field_minimum_height     => $source->get_option( 'minimum_height' ),
					$field_minimum_width      => $source->get_option( 'minimum_width' ),
					$field_minimum_length     => $source->get_option( 'minimum_length' ),
					$field_minimum_weight     => $source->get_option( 'minimum_weight' ),
					$field_object_type        => '2',
				)
			);
			return $shipping_data;
		} elseif (
			in_array(
				$source->id,
				array(
					'melhorenvio_correios_pac',
					'melhorenvio_correios_sedex',
					'melhorenvio_correios_mini',
				),
				true
			)
		) {
			$origin_services = array(
				'melhorenvio_correios_pac'   => '03298',
				'melhorenvio_correios_sedex' => '03220',
				'melhorenvio_correios_mini'  => '04227',
			);

			$shipping_data = array_merge(
				$shipping_data,
				array(
					$field_enabled         => $source->is_enabled(),
					$field_title           => $source->get_title(),
					$field_additional_time => $source->get_option( 'additional_time' ),
					$field_fee             => $source->get_option( 'additional_tax' ),
					$field_service_cod     => $origin_services[ $source->id ],
					$field_object_type     => '2',
				)
			);

			return $shipping_data;
		}

		throw new \Exception( esc_html__( 'Shipping method not supported.', 'virtuaria-correios' ) );
	}

	/**
	 * Disable shipping method by instance ID.
	 *
	 * @param int $instance_id Instance ID of shipping method to be disabled.
	 *
	 * @return int|bool Number of rows affected by the update or false on error.
	 */
	public function disable_shipping_method( $instance_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'woocommerce_shipping_zone_methods',
			array(
				'is_enabled' => 0,
			),
			array(
				'instance_id' => $instance_id,
			)
		);
	}

	/**
	 * Imports settings from WooCommerce Correios Integration plugin.
	 *
	 * Copies the username, password, and post card from the WooCommerce Correios Integration plugin's settings
	 * to the Virtuaria Correios plugin's settings.
	 *
	 * @return void
	 */
	private function import_woocommerce_correios_preferences() {
		$instance = new Virtuaria_WPMU_Correios_Settings();
		$instance->import_woocommerce_correios_preferences();
	}

	/**
	 * Check if the given shipping method is a Melhor Envios method.
	 *
	 * Determines if a shipping method is an instance of any Melhor Envios
	 * shipping classes: WC_Melhor_Envio_Shipping_Correios_Pac,
	 * WC_Melhor_Envio_Shipping_Correios_Sedex, or WC_Melhor_Envio_Shipping_Correios_Mini.
	 *
	 * @param \WC_Shipping_Method $shipping_method The shipping method object to check.
	 * @return bool True if the shipping method is a Melhor Envios method, false otherwise.
	 */
	private function is_method_menvios( $shipping_method ) {
		return $shipping_method instanceof WC_Melhor_Envio_Shipping_Correios_Pac
			|| $shipping_method instanceof WC_Melhor_Envio_Shipping_Correios_Sedex
			|| $shipping_method instanceof WC_Melhor_Envio_Shipping_Correios_Mini;
	}

	/**
	 * Check if the given shipping method is a WooCommerce Correios Integration method.
	 *
	 * Determines if a shipping method is an instance of the WC_Correios_Shipping_Cws class.
	 *
	 * @param \WC_Shipping_Method $shipping_method The shipping method object to check.
	 * @return bool True if the shipping method is a WooCommerce Correios Integration method, false otherwise.
	 */
	private function is_method_woocorreios( $shipping_method ) {
		return $shipping_method instanceof WC_Correios_Shipping_Cws;
	}
}

new Virtuaria_Correios_Install();

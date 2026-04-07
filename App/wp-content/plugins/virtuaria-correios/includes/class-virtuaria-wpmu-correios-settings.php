<?php
/**
 * Correios Settings in Multisite.
 *
 * @package virtuaria.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Virtuaria_WPMU_Correios_Settings' ) ) :
	/**
	 * Define settings used. Warninh this settings can be override by site settings.
	 */
	class Virtuaria_WPMU_Correios_Settings {
		/**
		 * API.
		 *
		 * @var Virtuaria_Correios_API
		 */
		protected $api;

		/**
		 * Plugin settings.
		 *
		 * @var array
		 */
		protected $settings;

		/**
		 * Initialize functions.
		 */
		public function __construct() {
			$this->settings = self::get_settings();
			$this->api      = new Virtuaria_Correios_API(
				isset( $this->settings['debug'] )
					? wc_get_logger()
					: null,
				isset( $this->settings['enviroment'] )
					? $this->settings['enviroment']
					: 'production'
			);

			if ( ! is_multisite()
				|| is_main_site()
				|| ! isset( $this->settings['enabled'] ) ) {
				add_action( 'admin_menu', array( $this, 'correios_settings_menu' ) );
				add_action( 'admin_init', array( $this, 'save_correios_settings' ) );
				add_action(
					'admin_enqueue_scripts',
					array( $this, 'admin_enqueue_scripts' )
				);
			}

			add_action( 'init', array( $this, 'fix_contract_info' ) );

			if ( isset( $this->settings['devolutions'] ) ) {
				require_once 'class-virtuaria-correios-devolutions.php';
				new Virtuaria_Correios_Devolutions( $this->settings );
			}

			add_action( 'admin_notices', array( $this, 'handle_admin_notices' ) );
		}

		/**
		 * Fix contract info.
		 */
		public function fix_contract_info() {
			$fixed = get_option( 'virtuaria_contract_info_fixed' );
			if ( ! $fixed ) {
				delete_transient( 'virtuaria_correios_token' );
				$this->api->get_token( $this->settings );
				update_option( 'virtuaria_contract_info_fixed', true );
			}
		}

		/**
		 * Function to create a menu page for Virtuaria Correios settings.
		 */
		public function correios_settings_menu() {
			add_menu_page(
				__( 'Virtuaria Correios', 'virtuaria-correios' ),
				__( 'Virtuaria Correios', 'virtuaria-correios' ),
				'remove_users',
				'virtuaria-settings',
				array( $this, 'correios_settings_page' ),
				plugin_dir_url( __FILE__ ) . '../admin/images/virtuaria.png'
			);
		}

		/**
		 * Correios settings page function.
		 */
		public function correios_settings_page() {
			require_once VIRTUARIA_CORREIOS_DIR . 'templates/correios-settings.php';
		}

		/**
		 * Function to save Correios settings based on the post data.
		 */
		public function save_correios_settings() {
			if ( isset( $_POST['correios_nonce'] )
				&& wp_verify_nonce(
					sanitize_text_field(
						wp_unslash(
							$_POST['correios_nonce']
						)
					),
					'update-correios-settings'
				)
			) {
				$options = get_option( 'virtuaria_correios_settings' );

				if ( isset( $_POST['woocommerce_virt_correios_enabled'] ) ) {
					$options['enabled'] = 'yes';
				} else {
					unset( $options['enabled'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_username'] ) ) {
					$options['username'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_username'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_password'] ) ) {
					$options['password'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_password'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_post_card'] ) ) {
					$options['post_card'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_post_card'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_full_name'] ) ) {
					$options['full_name'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_full_name'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_ddd'] ) ) {
					$options['ddd'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_ddd'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_fone'] ) ) {
					$options['fone'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_fone'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_email'] ) ) {
					$options['email'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_email'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_cpfcnpj'] ) ) {
					$options['cpfcnpj'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_cpfcnpj'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_origin'] ) ) {
					$options['origin'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_origin'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_logradouro'] ) ) {
					$options['logradouro'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_logradouro'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_numero'] ) ) {
					$options['numero'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_numero'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_complemento'] ) ) {
					$options['complemento'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_complemento'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_bairro'] ) ) {
					$options['bairro'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_bairro'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_cidade'] ) ) {
					$options['cidade'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_cidade'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_estado'] ) ) {
					$options['estado'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_estado'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_enviroment'] ) ) {
					$options['enviroment'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_enviroment'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_debug'] ) ) {
					$options['debug'] = 'yes';
				} else {
					unset( $options['debug'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_automatic_fill'] ) ) {
					$options['automatic_fill'] = 'yes';
				} else {
					unset( $options['automatic_fill'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_calc_in_product'] ) ) {
					$options['calc_in_product'] = 'yes';
				} else {
					unset( $options['calc_in_product'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_parcel_tracking'] ) ) {
					$options['parcel_tracking'] = 'yes';
				} else {
					unset( $options['parcel_tracking'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_serial'] ) ) {
					$options['serial'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_serial'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_category_price'] )
					|| ! $this->is_authenticated() ) {
					$options['category_price'] = 'yes';
				} elseif ( $this->is_authenticated() ) {
					unset( $options['category_price'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_progress_free'] )
					|| ! $this->is_authenticated() ) {
					$options['progress_free'] = 'yes';
				} elseif ( $this->is_authenticated() ) {
					unset( $options['progress_free'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_hide_shipping'] ) ) {
					$options['hide_shipping'] = 'yes';
				} elseif ( $this->is_authenticated() ) {
					unset( $options['hide_shipping'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_display_cart_fields'] ) ) {
					$options['display_cart_fields'] = 'yes';
				} else {
					unset( $options['display_cart_fields'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_error_message'] ) ) {
					$options['error_message'] = 'yes';
				} else {
					unset( $options['error_message'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_devolutions'] ) ) {
					$options['devolutions'] = 'yes';
				} else {
					unset( $options['devolutions'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_easy_mode'] ) ) {
					$options['easy_mode'] = 'yes';
				} else {
					unset( $options['easy_mode'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_activate_checkout'] ) ) {
					$options['activate_checkout'] = 'yes';
				} else {
					unset( $options['activate_checkout'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_optimize_add_cart'] ) ) {
					$options['optimize_add_cart'] = 'yes';
				} else {
					unset( $options['optimize_add_cart'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_hide_free_shipping_notice'] ) ) {
					$options['hide_free_shipping_notice'] = 'yes';
				} else {
					unset( $options['hide_free_shipping_notice'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_disable_feedback'] ) ) {
					$options['disable_feedback'] = 'yes';
				} else {
					unset( $options['disable_feedback'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_automatic_prepost'] ) ) {
					$options['automatic_prepost'] = 'yes';
				} else {
					unset( $options['automatic_prepost'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_disable_email_tracking_code'] ) ) {
					$options['disable_email_tracking_code'] = 'yes';
				} else {
					unset( $options['disable_email_tracking_code'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_compatibility_trakking_code'] ) ) {
					$options['compatibility_trakking_code'] = 'yes';
				} else {
					unset( $options['compatibility_trakking_code'] );
				}

				if ( isset( $_POST['woocommerce_virt_correios_hide_devolution_button'] )
					&& isset( $options['devolutions'] ) ) {
					$options['hide_devolution_button'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_hide_devolution_button'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_print_format'] ) ) {
					$options['print_format'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_print_format'] ) );
				}

				if ( isset( $_POST['woocommerce_virt_correios_jump_nonce_validation'] ) ) {
					$options['jump_nonce_validation'] = 'yes';
				} else {
					unset( $options['jump_nonce_validation'] );
				}

				if ( isset( $_POST['should_import_woocommerce_correios_preferences'] )
					&& 'yes' === $_POST['should_import_woocommerce_correios_preferences'] ) {
					$this->import_woocommerce_correios_preferences();
				} elseif ( isset( $_POST['woocommerce_virt_correios_preferences_import'] )
					&& ! empty( $_POST['woocommerce_virt_correios_preferences_import'] ) ) {
					$this->import_virtuaria_preferences(
						sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_preferences_import'] ) )
					);
				} else {
					$this->setup_virtuaria_settings( $options );
				}

				delete_option( 'virtuaria_correios_display_install' );

				Virtuaria_Correios::get_instance()->is_premium();
			}
		}

		/**
		 * Configures the Virtuaria settings based on provided options.
		 *
		 * Deletes the transient 'virtuaria_correios_token' and sets up the services list.
		 * If 'easy_mode' is enabled in the options, it assigns a predefined list of services.
		 * Otherwise, retrieves the service list from the API and updates the options if available.
		 * Finally, updates the 'virtuaria_correios_settings' option with the modified options.
		 *
		 * @param array $options Array of options to configure the settings.
		 */
		public function setup_virtuaria_settings( $options ) {
			delete_transient( 'virtuaria_correios_token' );
			if ( isset( $options['easy_mode'] ) ) {
				$options['services_list'] = array(
					array(
						'codigo'       => '03220',
						'descricao'    => 'SEDEX CONTRATO AG',
						'coSegmento'   => '3',
						'descSegmento' => 'ENCOMENDA',
					),
					array(
						'codigo'       => '03298',
						'descricao'    => 'PAC CONTRATO AG',
						'coSegmento'   => '3',
						'descSegmento' => 'ENCOMENDA',
					),
				);
			} else {
				$services = $this->api->get_service_list( $options );
				if ( $services ) {
					$options['services_list'] = $services;
				}
			}

			update_option( 'virtuaria_correios_settings', $options );
		}

		/**
		 * Admin enqueue styles and scripts.
		 *
		 * @param string $hook page description.
		 */
		public function admin_enqueue_scripts( $hook ) {
			if ( 'toplevel_page_virtuaria-settings' === $hook ) {
				$dir = VIRTUARIA_CORREIOS_DIR . 'admin/';
				$url = VIRTUARIA_CORREIOS_URL . 'admin/';
				wp_enqueue_style(
					'virtuaria-correios-setup',
					$url . 'css/setup.css',
					array(),
					filemtime( $dir . 'css/setup.css' )
				);

				wp_enqueue_script(
					'virtuaria-correios-setup',
					$url . 'js/setup.js',
					array( 'jquery' ),
					filemtime( $dir . 'js/setup.js' ),
					true
				);
			}
		}

		/**
		 * Get a setting value by key.
		 *
		 * @return array
		 */
		public static function get_settings() {
			$settings = array();
			$default  = array(
				'automatic_fill'  => 'yes',
				'calc_in_product' => 'yes',
				'parcel_tracking' => 'yes',
				'category_price'  => 'yes',
				'progress_free'   => 'yes',
				'enviroment'      => 'production',
			);
			if ( is_multisite() ) {
				switch_to_blog( get_main_site_id() );
				$settings = get_option( 'virtuaria_correios_settings', $default );

				$settings['global']        = true;
				$settings['authenticated'] = get_transient( 'virtuaria_correios_authenticated' );
				$settings['domain']        = str_replace(
					array( 'http://', 'https://' ),
					'',
					get_option( 'siteurl' )
				);
				restore_current_blog();
			}

			if ( ! is_multisite()
				|| ! isset( $settings['enabled'] ) ) {
				$settings = get_option( 'virtuaria_correios_settings', $default );

				$settings['authenticated'] = get_transient( 'virtuaria_correios_authenticated' );
				$settings['domain']        = str_replace(
					array( 'http://', 'https://' ),
					'',
					get_option( 'siteurl' )
				);
			}

			return $settings;
		}

		/**
		 * Checks if the user is authenticated.
		 *
		 * @return bool True if the user is authenticated, false otherwise.
		 */
		private function is_authenticated() {
			return isset( $this->settings['authenticated'] )
				&& $this->settings['authenticated'];
		}

		/**
		 * Handles admin notices for Correios settings.
		 *
		 * Checks if the Correios nonce is set and verified, then displays an error or success message
		 * based on the presence of an error code.
		 *
		 * @return void
		 */
		public function handle_admin_notices() {
			if ( isset( $_POST['correios_nonce'] )
				&& wp_verify_nonce(
					sanitize_text_field(
						wp_unslash(
							$_POST['correios_nonce']
						)
					),
					'update-correios-settings'
				)
			) {
				$error_code = get_option( 'virtuaria_correios_error_token', false );
				if ( $error_code ) {
					if ( intval( $error_code ) > 500 ) {
						include_once VIRTUARIA_CORREIOS_DIR . 'templates/admin-messages/html-error-correios-unstable.php';
					} else {
						include_once VIRTUARIA_CORREIOS_DIR . 'templates/admin-messages/html-error-wrong-data.php';
					}
					delete_option( 'virtuaria_correios_error_token' );
				} else {
					include_once VIRTUARIA_CORREIOS_DIR . 'templates/admin-messages/html-success-updated-settings.php';
				}
			}
		}

		/**
		 * Imports settings from WooCommerce Correios Integration plugin.
		 *
		 * Copies the username, password, and post card from the WooCommerce Correios Integration plugin's settings
		 * to the Virtuaria Correios plugin's settings.
		 *
		 * @return void
		 */
		public function import_woocommerce_correios_preferences() {
			$preferences = get_option( 'woocommerce_correios-integration_settings' );

			if ( isset( $preferences['cws_username'] ) ) {
				$this->settings['username'] = sanitize_text_field( wp_unslash( $preferences['cws_username'] ) );
			}

			if ( isset( $preferences['cws_access_code'] ) ) {
				$this->settings['password'] = sanitize_text_field( wp_unslash( $preferences['cws_access_code'] ) );
			}

			if ( isset( $preferences['cws_posting_card'] ) ) {
				$this->settings['post_card'] = sanitize_text_field( wp_unslash( $preferences['cws_posting_card'] ) );
			}

			$this->settings['debug'] = 'yes';

			$this->setup_virtuaria_settings( $this->settings );
		}

		/**
		 * Imports settings into Virtuaria Correios plugin.
		 *
		 * This function takes serialized preferences, unserializes them, and checks
		 * if the necessary keys ('username', 'password', 'post_card') are present.
		 * If the conditions are met, it sets up the Virtuaria settings using the given preferences.
		 *
		 * @param mixed $preferences Serialized preferences data that includes the required keys.
		 * @return void
		 */
		private function import_virtuaria_preferences( $preferences ) {
			$setup = json_decode( $preferences, true );

			if ( is_array( $setup )
				&& ! empty( $setup )
				&& isset(
					$setup['username'],
					$setup['password'],
					$setup['post_card']
				)
			) {
				$this->setup_virtuaria_settings( $setup );
			}
		}
	}

	new Virtuaria_WPMU_Correios_Settings();

endif;

<?php
/**
 * Plugin Name: Virtuaria Correios - Frete, Etiqueta, Rastreio e Declaração
 * Plugin URI: https://virtuaria.com.br
 * Description: Adiciona o método de entrega Correios em lojas Woocommerce.
 * Version: 1.13.0
 * Author: Virtuaria
 * Author URI: http://virtuaria.com.br
 * License: GPLv2 or later
 *
 * @package Virtuaria/Shipping.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Virtuaria_Correios' ) ) {
	define( 'VIRTUARIA_CORREIOS_DIR', plugin_dir_path( __FILE__ ) );
	define( 'VIRTUARIA_CORREIOS_URL', plugin_dir_url( __FILE__ ) );

	register_activation_hook( __FILE__, array( 'Virtuaria_Correios', 'install_plugin' ) );

	/**
	 * Class definition
	 */
	class Virtuaria_Correios {
		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Singleton Init Correios plugin.
		 */
		private function __construct() {
			if ( class_exists( 'Woocommerce' ) ) {
				$this->load_dependencies();
				add_filter( 'woocommerce_shipping_methods', array( $this, 'add_correios_shipping_method' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'admin_footer', array( $this, 'unistall_form_template' ) );
			add_action( 'wp_ajax_virtuaria_correios_submit_feedback', array( $this, 'submit_feedback' ) );
			add_action( 'wp_ajax_nopriv_virtuaria_correios_submit_feedback', array( $this, 'submit_feedback' ) );
			$plugin_file = plugin_basename( __FILE__ );
			add_filter( "plugin_action_links_{$plugin_file}", array( $this, 'plugin_action_links' ), 10, 4 );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Loads the dependencies for the PHP function.
		 */
		private function load_dependencies() {
			require_once 'includes/class-virtuaria-correios-api.php';
			require_once 'includes/class-virtuaria-wpmu-correios-settings.php';
			require_once 'includes/class-virtuaria-correios-trakking.php';
			require_once 'includes/traits/trait-virtuaria-correios-international.php';
			require_once 'includes/class-virtuaria-shipping-services.php';
			require 'includes/class-virtuaria-correios-shipping.php';
			require_once 'includes/class-virtuaria-correios-sedex.php';
			require_once 'includes/traits/trait-virtuaria-correios-hpo.php';

			$settings = Virtuaria_WPMU_Correios_Settings::get_settings();
			if ( ! isset( $settings['easy_mode'] ) ) {
				require_once 'includes/traits/trait-virtuaria-correios-prepost-c.php';
				require_once 'includes/class-virtuaria-correios-prepost.php';
			}
			require_once 'includes/class-virtuaria-correios-shipping-screen.php';

			require_once 'includes/class-virtuaria-correios-extra-fields.php';
			require_once 'includes/class-virtuaria-correios-content-declaration.php';
			require_once 'includes/class-virtuaria-correios-rest-api.php';
			require_once 'includes/class-virtuaria-correios-install.php';
			require_once 'includes/traits/trait-virtuaria-correios-fast-start-o.php';
			require_once 'includes/class-virtuaria-correios-fast-start.php';
			require_once 'includes/class-virtuaria-correios-diagnostics.php';
		}

		/**
		 * Register correios shipping method.
		 *
		 * @param array $methods the current methods.
		 * @return array
		 */
		public function add_correios_shipping_method( $methods ) {
			$methods['virtuaria-correios-sedex'] = 'Virtuaria_Correios_Sedex';
			return $methods;
		}

		/**
		 * WooCommerce fallback notice.
		 */
		public function woocommerce_missing_notice() {
			?>
			<div class="error">
				<p>
					<strong>A integração com Correios</strong> depende da última versão do woocommerce para funcionar!
				</p>
			</div>
			<?php
		}

		/**
		 * Check if the premium version is active for the user based on certain criteria.
		 *
		 * @return bool Returns true if the user is premium, false otherwise.
		 */
		public function is_premium() {
			$settings = Virtuaria_WPMU_Correios_Settings::get_settings();

			if ( isset( $settings['domain'] ) ) {
				$plugin = get_plugin_data( __FILE__ );

				$response = wp_remote_get(
					'https://premium.virtuaria.com.br/wp-json/v1/auth/premium/plugins?request_id=' . time(),
					array(
						'headers' => array(
							'domain'         => $settings['domain'],
							'serial'         => isset( $settings['serial'] ) ? $settings['serial'] : '',
							'version'        => isset( $plugin['Version'] ) ? $plugin['Version'] : '',
							'mode'           => get_transient( 'virtuaria_correios_authenticated' ) ? 'Premium' : 'Free',
							'module'         => 'virtuaria-correios',
							'Content-Length' => 0,
						),
						'timeout' => 15,
					)
				);

				if ( isset( $settings['global'] ) ) {
					switch_to_blog( get_main_site_id() );
				}

				if ( ! is_wp_error( $response )
					&& 200 === wp_remote_retrieve_response_code( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( isset( $body['authenticated'], $body['auth_date'] )
						&& $body['authenticated']
						&& $body['auth_date'] ) {
						set_transient(
							'virtuaria_correios_authenticated',
							true,
							MONTH_IN_SECONDS * 1
						);
						if ( isset( $settings['global'] ) ) {
							restore_current_blog();
						}
						return true;
					}
				} elseif ( 403 === wp_remote_retrieve_response_code( $response ) ) {
					delete_transient( 'virtuaria_correios_authenticated' );
				}
				if ( isset( $settings['global'] ) ) {
					restore_current_blog();
				}
			}

			return false;
		}

		/**
		 * Add admin unistall scripts.
		 *
		 * @param string $hook The current hook.
		 */
		public static function enqueue_admin_scripts( $hook ) {
			if ( 'plugins.php' === $hook ) {
				wp_enqueue_script(
					'virtuaria-correios-uninstall',
					VIRTUARIA_CORREIOS_URL . 'admin/js/uninstall.js',
					array( 'jquery' ),
					filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/js/uninstall.js' ),
					true
				);

				wp_enqueue_style(
					'virtuaria-correios-uninstall',
					VIRTUARIA_CORREIOS_URL . 'admin/css/uninstall.css',
					array(),
					filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/css/uninstall.css' )
				);

				wp_localize_script(
					'virtuaria-correios-uninstall',
					'virt_correios_uninstall',
					array(
						'nonce'    => wp_create_nonce( 'virtuaria-correios-uninstall' ),
						'ajax_url' => admin_url( 'admin-ajax.php' ),
					)
				);
			}
		}

		/**
		 * Add admin unistall template.
		 */
		public function unistall_form_template() {
			global $pagenow;

			if ( 'plugins.php' === $pagenow ) {
				include_once VIRTUARIA_CORREIOS_DIR . 'templates/uninstall.php';
			}
		}

		/**
		 * Send mail feedback uninstall.
		 */
		public function submit_feedback() {
			if (
				isset(
					$_POST['reason'],
					$_POST['email'],
					$_POST['comment'],
					$_POST['nonce'],
				)
				&& wp_verify_nonce(
					sanitize_text_field(
						wp_unslash( $_POST['nonce'] )
					),
					'virtuaria-correios-uninstall'
				)
			) {
				$mail    = sanitize_text_field( wp_unslash( $_POST['email'] ) )
					? sanitize_text_field( wp_unslash( $_POST['email'] ) )
					: 'Não informado';
				$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ) )
					? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) )
					: 'Não informado';

				wp_mail(
					'tecnologia@virtuaria.com.br',
					'[Plugin Correios] Feedback de Desinstalação',
					sprintf(
						'Novo feedback de desinstalação do plugin Virtuaria Correios recebida.<br><br>Motivo: %1$s.<br><br>E-mail: %2$s.<br><br>Comentário: %3$s.<br><br>Domínio: %4$s',
						sanitize_text_field( wp_unslash( $_POST['reason'] ) ),
						$mail,
						$comment,
						home_url()
					),
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
			}

			wp_die();
		}


		/**
		 * Installs the plugin.
		 *
		 * Checks if the plugin is already installed and sets the installed option to true if not.
		 *
		 * @since 1.8.2
		 */
		public static function install_plugin() {
			if ( ! get_option( 'virtuaria_correios_installed' ) ) {
				update_option( 'virtuaria_correios_installed', true );
			}
		}

		/**
		 * Adds items to the plugin's action links on the Plugins listing screen.
		 *
		 * @param array<string,string> $actions     Array of action links.
		 * @param string               $plugin_file Path to the plugin file relative to the plugins directory.
		 * @param mixed[]              $plugin_data An array of plugin data.
		 * @param string               $context     The plugin context.
		 * @return array<string,string> Array of action links.
		 */
		public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
			$new = array(
				'settings' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=virtuaria-settings' ) ),
					esc_html__( 'Configurações', 'virtuaria-correios' )
				),
				'delivery' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=virtuaria-correios-shipping' ) ),
					esc_html__( 'Entregas', 'virtuaria-correios' )
				),
			);

			return array_merge( $new, $actions );
		}
	}

	add_action( 'plugins_loaded', array( 'Virtuaria_Correios', 'get_instance' ) );
}

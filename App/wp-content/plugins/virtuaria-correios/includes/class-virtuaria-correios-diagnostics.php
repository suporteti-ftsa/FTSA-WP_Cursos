<?php
/**
 * Provide diagnostics for Virtuaria Correios.
 *
 * @package Virtuaria/Correios
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle process from diagnostics.
 */
class Virtuaria_Correios_Diagnostics {
	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Api instance.
	 *
	 * @var Virtuaria_Correios_API
	 */
	private $api;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->settings = Virtuaria_WPMU_Correios_Settings::get_settings();
		$this->api      = new Virtuaria_Correios_API(
			function_exists( 'wc_get_logger' )
				? wc_get_logger()
				: new WC_Logger(),
			'production'
		);
		add_action( 'admin_menu', array( $this, 'add_diagnostic_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_make_diagnostics', array( $this, 'make_diagnostics' ) );
	}

	/**
	 * Adds a diagnostics submenu to the Virtuaria settings menu in the WordPress admin.
	 *
	 * The submenu allows users to access diagnostic tools and information related to the Virtuaria Correios plugin.
	 *
	 * @return void
	 */
	public function add_diagnostic_submenu() {
		add_submenu_page(
			'virtuaria-settings',
			__( 'Diagnóstico', 'virtuaria-correios' ),
			__( 'Diagnóstico', 'virtuaria-correios' ),
			'remove_users',
			'virtuaria-correios-diagnostics',
			array( $this, 'diagnostics_page_content' )
		);
	}

	/**
	 * Generates the content for the diagnostics page.
	 *
	 * This function is called when the user navigates to the diagnostics page in the WordPress admin.
	 * It includes the template file which contains all the content for the diagnostics page.
	 *
	 * @return void
	 */
	public function diagnostics_page_content() {
		require_once VIRTUARIA_CORREIOS_DIR . 'templates/diagnostics.php';
	}

	/**
	 * Enqueues admin scripts and styles for the diagnostics page.
	 *
	 * This function is called when the user navigates to the diagnostics page in the WordPress admin.
	 * It enqueues the diagnostics JavaScript and CSS files.
	 *
	 * @param string $page The current page.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $page ) {
		$dir = VIRTUARIA_CORREIOS_DIR . 'admin/';
		$url = VIRTUARIA_CORREIOS_URL . 'admin/';

		if ( 'virtuaria-correios_page_virtuaria-correios-diagnostics' === $page ) {
			wp_enqueue_script(
				'virtuaria-correios-diagnostics',
				$url . 'js/diagnostics.js',
				array( 'jquery' ),
				filemtime( $dir . 'js/diagnostics.js' ),
				true
			);

			wp_localize_script(
				'virtuaria-correios-diagnostics',
				'diagnostic',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'make-diagnostic' ),
				)
			);

			wp_enqueue_style(
				'virtuaria-correios-diagnostics',
				$url . 'css/diagnostics.css',
				array(),
				filemtime( $dir . 'css/diagnostics.css' )
			);
		}
	}

	/**
	 * Perform diagnostics on the Virtuaria Correios plugin.
	 *
	 * This function is called when the user navigates to the diagnostics page in the WordPress admin.
	 * It checks various aspects of the plugin and returns a JSON object with a success status and an array of diagnostics.
	 *
	 * The diagnostics array contains keys for each check performed with the following format:
	 * [
	 *     'valid'       => boolean, // Whether the check passed or not.
	 *     'description' => string,   // A description of the check and what to do if it fails.
	 * ]
	 *
	 * The function will return a 200 status code on success and a 500 status code on failure.
	 *
	 * @return void
	 */
	public function make_diagnostics() {
		if ( isset( $_POST['nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['nonce'] )
				),
				'make-diagnostic'
			)
		) {
			$diagnostics = array();

			$shipping_area_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping' ) . '">aqui</a>';

			$diagnostics[ __( 'Contrato com os Correios', 'virtuaria-correios' ) ] = array(
				'valid'       => $this->check_correios_contract(),
				'description' => sprintf(
					/* translators: %1$s: easy mode warning %2$s: virtuaria correios settings url */
					__( 'Um contrato ativo com os Correios garante maior precisão na cotação de frete.%1$s Para conferir os dados de seu contrato clique %2$s.', 'virtuaria-correios' ),
					isset( $this->settings['easy_mode'] ) && 'yes' === $this->settings['easy_mode']
					? __( ' Você está utilizando o modo Sem Contrato, por isso, não é possível gerar Etiquetas.', 'virtuaria-correios' )
					: '',
					'<a href="' . admin_url( 'admin.php?page=virtuaria-settings' ) . '">aqui</a>'
				),
			);

			$diagnostics[ __( 'Áreas de Entrega', 'virtuaria-correios' ) ] = array(
				'valid'       => $this->check_shipping_area(),
				'description' => sprintf(
					/* translators: %s: shipping área url */
					__( 'Áreas de entrega definem a(s) região(ões) para as quais você deseja fazer entregas. Cada área de entrega pode conter seus próprios métodos de entrega. Você pode adicionar, de forma independente, quais métodos de entrega deseja disponibilizar em cada área de entrega. Áreas de entrega podem ser adicionadas, editadas ou removidas no menu Woocommerce > Configurações > Entregas ou clique %s.', 'virtuaria-correios' ),
					$shipping_area_link
				),
			);

			$diagnostics[ __( 'Métodos de Entrega', 'virtuaria-correios' ) ] = array(
				'valid'       => $this->exists_virtuaria_correios_methods(),
				'description' => sprintf(
					/* translators: %s: shipping área url */
					__( 'Para ralizar entregas via Correios, é preciso adicionar os métodos de entrega Virtuaria Correios a área de entrega desejada. Os métodos adicionados precisam estar ativos para que possam funcionar. Para conferir os métodos de entrega disponíveis clique %s.', 'virtuaria-correios' ),
					$shipping_area_link
				),
			);

			$tested_methods  = $this->check_virtuaria_method_correct();
			$is_valid_method = true;
			if ( $tested_methods ) {
				foreach ( $tested_methods as $method ) {
					if ( ! $method['correct'] ) {
						$is_valid_method = false;
						break;
					}
				}
			}

			$empty_methods = count( $tested_methods ) === 0;
			if ( $empty_methods ) {
				$is_valid_method = false;
			}

			$diagnostics[ __( 'Configuração do Método de Entrega', 'virtuaria-correios' ) ] = array(
				'valid'       => $is_valid_method,
				'description' => sprintf(
					/* translators: %1$s: shipping área url %2$s: wrong methods list */
					__( 'Para o correto funcionamento do cálculo de frete é necessário que a configuração dos método de entrega Virtuaria Correios esteja correta. São considerados corretos métodos de entrega com título e CEP de origem válidos. Para conferir as configurações de entrega clique %1$s. %2$s', 'virtuaria-correios' ),
					$shipping_area_link,
					'<div class="tested-itens">'
					. '<h4>' . __( 'Métodos analisados:', 'virtuaria-correios' ) . '</h4>' .
					( ! $empty_methods
						? $this->wrong_methods_html( $tested_methods )
						: __( 'Nenhum método Virtuaria Correios Encontrado', 'virtuaria-correios' ) )
					. '</div>'
				),
			);

			wp_send_json_success( $diagnostics, 200 );
		}
		wp_die();
	}

	/**
	 * Checks if the Correios contract is valid.
	 *
	 * @return bool True if the contract is valid, false otherwise.
	 */
	private function check_correios_contract() {
		$cep_test = get_option( 'woocommerce_store_postcode' );
		$cep_test = $this->is_cep_valid( $cep_test )
			? preg_replace( '/\D/', '', $cep_test )
			: '44444444';

		$time_estimate = array(
			'service'     => '03220',
			'origin'      => $cep_test,
			'destination' => $cep_test,
			'username'    => isset( $this->settings['username'] )
				? $this->settings['username']
				: '',
			'password'    => isset( $this->settings['password'] )
				? $this->settings['password']
				: '',
			'post_card'   => isset( $this->settings['post_card'] )
				? $this->settings['post_card']
				: '',
		);

		if ( ( isset( $this->settings['easy_mode'] )
			&& 'yes' === $this->settings['easy_mode'] )
			|| false !== $this->api->get_shipping_national_deadline( $time_estimate )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if there are any shipping zones defined.
	 *
	 * @return bool True if there are shipping zones, false otherwise.
	 */
	private function check_shipping_area() {
		return count( WC_Shipping_Zones::get_zones() ) > 0;
	}

	/**
	 * Checks if there are any enabled Virtuaria Correios shipping methods in any shipping zone.
	 *
	 * @return bool True if there are enabled Virtuaria Correios shipping methods, false otherwise.
	 */
	private function exists_virtuaria_correios_methods() {
		$shipping_zones = WC_Shipping_Zones::get_zones();

		foreach ( $shipping_zones as $zone ) {
			/**
			 * Current Instance of WC_Shipping_Method.
			 *
			 * @var \WC_Shipping_Method $shipping_method
			 */
			foreach ( $zone['shipping_methods'] as $shipping_method ) {
				if ( $shipping_method->is_enabled() === true
					&& $shipping_method instanceof Virtuaria_Correios_Sedex ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if all Virtuaria Correios shipping methods have valid CEP and title.
	 *
	 * @return array An array of arrays, each containing a link to the shipping method and a boolean indicating
	 *               whether the method is correct or not.
	 */
	private function check_virtuaria_method_correct() {
		$shipping_zones = WC_Shipping_Zones::get_zones();

		$methods = array();

		foreach ( $shipping_zones as $zone ) {
			/**
			 * Current Instance of WC_Shipping_Method.
			 *
			 * @var \WC_Shipping_Method $shipping_method
			 */
			foreach ( $zone['shipping_methods'] as $shipping_method ) {
				if ( $shipping_method->is_enabled() === true
					&& $shipping_method instanceof Virtuaria_Correios_Sedex
				) {

					if ( ! $shipping_method->get_title() ) {
						$label = 'Método sem título na área de entrega ' . $zone['zone_name'];
					} else {
						$label = $shipping_method->get_title();
					}

					$methods[] = array(
						'link'    => sprintf(
							'<a href="%s" target="_blank">%s</a>',
							admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=' . $shipping_method->get_instance_id() ),
							$label
						),
						'correct' => (
							$shipping_method->get_title()
							&& $this->is_cep_valid( $shipping_method->get_option( 'origin' ) )
						),
					);
				}
			}
		}

		return $methods;
	}

	/**
	 * Checks if a given CEP is valid.
	 *
	 * @param string $cep The CEP to check.
	 * @return bool True if the CEP is valid, false otherwise.
	 */
	private function is_cep_valid( $cep ) {
		$cep = preg_replace( '/\D/', '', $cep );

		if ( 8 !== strlen( $cep ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generates HTML for displaying a list of invalid shipping methods.
	 *
	 * @param array $methods An array of invalid shipping method descriptions.
	 * @return string HTML markup for the list of invalid shipping methods.
	 */
	private function wrong_methods_html( $methods ) {
		$html = '<ul class="methods">';
		foreach ( $methods as $method ) {
			if ( $method['correct'] ) {
				$status = '<span class="ok">(OK)</span>';
			} else {
				$status = '<span class="error">(ERRO)</span>';
			}
			$html .= "<li class='item'>{$method['link']} $status</li>";
		}
		$html .= '</ul>';
		return $html;
	}
}

new Virtuaria_Correios_Diagnostics();

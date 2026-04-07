<?php
/**
 * Handle extra fields.
 *
 * @package Virtuaria/Integrations/Extra_Fields.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition
 */
class Virtuaria_Correios_Extra_Fields {
	/**
	 * Settings fields.
	 *
	 * @var array.
	 */
	private $fields_settings;

	/**
	 * Init Virtuaria_Extra_Fields
	 */
	public function __construct() {
		$this->fields_settings = $this->get_settings();
		$this->load_dependecys();

		add_action( 'virtuaria_correios_setting_extra_fields', array( $this, 'add_extra_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'save_correios_extra_field_settings' ) );
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependecys() {
		$global_settings = get_option( 'virtuaria_correios_settings', array() );

		if ( isset( $global_settings['activate_checkout'] )
			&& 'yes' === $global_settings['activate_checkout'] ) {

			if ( class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
				add_action( 'admin_notices', array( $this, 'conflict_warning_notice' ) );
			}

			require_once VIRTUARIA_CORREIOS_DIR . 'includes/traits/trait-virtuaria-correios-fields.php';
			require_once VIRTUARIA_CORREIOS_DIR . 'includes/class-virtuaria-correios-front-fields.php';
			require_once VIRTUARIA_CORREIOS_DIR . 'includes/class-virtuaria-correios-extra-fields-profile.php';
			require_once VIRTUARIA_CORREIOS_DIR . 'includes/class-virtuaria-correios-extra-fields-order.php';
			require_once VIRTUARIA_CORREIOS_DIR . 'includes/class-virtuaria-correios-extra-fields-formatters.php';
			require_once VIRTUARIA_CORREIOS_DIR . 'includes/class-virtuaria-correios-extra-fields-api.php';

			new Virtuaria_Correios_Front_Fields( $this->fields_settings );
			new Virtuaria_Correios_Extra_Fields_Profile( $this->fields_settings );
			new Virtuaria_Correios_Extra_Fields_Order( $this->fields_settings );
			new Virtuaria_Correios_Extra_Fields_Formatters();
			new Virtuaria_Correios_Extra_Fields_API( $this->fields_settings );
		}
	}

	/**
	 * Adds extra fields to the checkout
	 */
	public function add_extra_fields() {
		$options = $this->get_settings();
		include_once VIRTUARIA_CORREIOS_DIR . 'templates/extra-fields.php';
	}

	/**
	 * Admin enqueue styles and scripts.
	 *
	 * @param string $hook page description.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_virtuaria-settings' === $hook ) {
			wp_enqueue_script(
				'virtuaria-correios-extra-fields',
				VIRTUARIA_CORREIOS_URL . 'admin/js/setup-extra-fields.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'admin/js/setup-extra-fields.js' ),
				true
			);
		}
	}

	/**
	 * Get a setting value by key.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = array();
		$default  = array(
			'mask'          => 'yes',
			'validate_cpf'  => 'yes',
			'validate_cnpj' => 'yes',
		);

		if ( is_multisite() ) {
			switch_to_blog( get_main_site_id() );
			$main_settings = get_option( 'virtuaria_correios_settings', array() );
			$settings      = get_option( 'virtuaria_correios_extra_fields_settings', $default );

			$settings['global'] = true;
			restore_current_blog();
		}

		if ( ! is_multisite()
			|| ! isset( $main_settings['enabled'] ) ) {
			$settings = get_option( 'virtuaria_correios_extra_fields_settings', $default );
		}

		return $settings;
	}

	/**
	 * Function to save Extra fields settings based on the post data.
	 */
	public function save_correios_extra_field_settings() {
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
			$options = get_option( 'virtuaria_correios_extra_fields_settings' );

			if ( isset( $_POST['woocommerce_virt_correios_person_type'] ) ) {
				$options['person_type'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_person_type'] ) );
			}

			if ( isset( $_POST['woocommerce_virt_correios_only_brazil'] ) ) {
				$options['only_brazil'] = 'yes';
			} else {
				unset( $options['only_brazil'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_rg'] ) ) {
				$options['rg'] = 'yes';
			} else {
				unset( $options['rg'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_ie'] ) ) {
				$options['ie'] = 'yes';
			} else {
				unset( $options['ie'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_birthday_date'] ) ) {
				$options['birthday_date'] = 'yes';
			} else {
				unset( $options['birthday_date'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_gender'] ) ) {
				$options['gender'] = 'yes';
			} else {
				unset( $options['gender'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_cell_phone'] ) ) {
				$options['cell_phone'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_cell_phone'] ) );
			}

			if ( isset( $_POST['woocommerce_virt_correios_district'] ) ) {
				$options['district'] = 'yes';
			} else {
				unset( $options['district'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_style_field'] ) ) {
				$options['style_field'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_virt_correios_style_field'] ) );
			}

			if ( isset( $_POST['woocommerce_virt_correios_validate_cpf'] ) ) {
				$options['validate_cpf'] = 'yes';
			} else {
				unset( $options['validate_cpf'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_validate_cnpj'] ) ) {
				$options['validate_cnpj'] = 'yes';
			} else {
				unset( $options['validate_cnpj'] );
			}

			if ( isset( $_POST['woocommerce_virt_correios_mask'] ) ) {
				$options['mask'] = 'yes';
			} else {
				unset( $options['mask'] );
			}

			update_option( 'virtuaria_correios_extra_fields_settings', $options );
		}
	}

	/**
	 * Displays a warning if Brazilian Market on WooCommerce is active.
	 *
	 * Brazilian Market on WooCommerce has a feature that conflicts with the Extra Fields feature of this plugin.
	 * This warning is displayed to alert the user about the conflict.
	 *
	 * @since 1.5.4
	 */
	public function conflict_warning_notice() {
		?>
		<div class="error">
			<p>
				<b>Virtuaria Correios:</b> A Funcionalidade <b style="color:green">Campos Personalizados</b> precisa que o plugin Brazilian Market on WooCommerce esteja desativado para o correto funcionamento!
			</p>
		</div>
		<?php
	}
}

new Virtuaria_Correios_Extra_Fields();

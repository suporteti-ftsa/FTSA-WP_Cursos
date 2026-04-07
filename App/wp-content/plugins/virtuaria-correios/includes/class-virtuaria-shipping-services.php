<?php
/**
 * Shipping services.
 *
 * @package virtuaria/integrations/correios.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle additionals Shipping Services.
 */
class Virtuaria_Shipping_Services {
	use Virtuaria_Correios_International;

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
		$this->settings = Virtuaria_WPMU_Correios_Settings::get_settings();
		$enviroment     = isset( $this->settings['enviroment'] )
			? $this->settings['enviroment']
			: 'production';

		$this->api = new Virtuaria_Correios_API(
			isset( $this->settings['debug'] )
			? wc_get_logger()
			: null,
			$enviroment
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'frontend_style_scripts' )
		);

		add_action( 'wp_ajax_autofill_address', array( $this, 'search_address' ) );
		add_action( 'wp_ajax_nopriv_autofill_address', array( $this, 'search_address' ) );

		if ( isset( $this->settings['calc_in_product'] ) ) {
			if ( has_action( 'woocommerce_single_product_summary' ) ) {
				add_action( 'woocommerce_single_product_summary', array( $this, 'display_calc_shipping' ), 70 );
			} else {
				add_action( 'woocommerce_product_thumbnails', array( $this, 'display_calc_shipping' ), 20 );
			}
		}
		add_action( 'wp_ajax_product_calc_shipping', array( $this, 'product_calc_shipping' ) );
		add_action( 'wp_ajax_nopriv_product_calc_shipping', array( $this, 'product_calc_shipping' ) );

		add_shortcode(
			'progress_free_shipping',
			array( $this, 'add_shortcode_progress_free_shipping' )
		);

		add_action(
			'woocommerce_cart_totals_after_shipping',
			array( $this, 'display_progress_free_shipping' ),
			15
		);

		add_action(
			'woocommerce_review_order_after_shipping',
			array( $this, 'display_checkout_progress_free_shipping' ),
			15
		);

		add_filter(
			'woocommerce_package_rates',
			array( $this, 'hide_shipping_when_free_is_available' ),
			10,
			2
		);

		add_action(
			'woocommerce_after_shipping_rate',
			array( $this, 'display_delivery_time' ),
			100
		);
		add_filter(
			'woocommerce_order_shipping_method',
			array( $this, 'shipping_method_delivery_time' ),
			110,
			2
		);
		add_filter(
			'woocommerce_order_item_display_meta_key',
			array( $this, 'item_display_delivery_time' ),
			100,
			2
		);

		add_action(
			'woocommerce_before_checkout_billing_form',
			array( $this, 'authenticate_premium' )
		);

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'setup_shipping_scritps' )
		);

		add_filter(
			'woocommerce_shipping_free_shipping_is_available',
			array( $this, 'display_free_shipping_product_page' ),
			10,
			3
		);

		add_action(
			'woocommerce_checkout_update_order_meta',
			array( $this, 'save_correios_shipping_method' )
		);

		add_shortcode(
			'virtuaria_correios_calculadora',
			array( $this, 'display_cart_totals_shortcode' )
		);

		add_action(
			'woocommerce_checkout_update_order_review',
			array( $this, 'checkout_update_refresh_shipping_methods' )
		);

		add_action(
			'admin_notices',
			array( $this, 'warning_token_invalid' )
		);
	}

	/**
	 * Add styles and script to frontend.
	 */
	public function frontend_style_scripts() {
		$is_internationl = $this->is_international_shipping();

		if ( isset( $this->settings['automatic_fill'] )
			&& ( is_checkout() || is_page( 'finalizar-compra' ) )
			&& ! $is_internationl
			&& (
				get_transient( 'virtuaria_correios_token' )
				|| ( isset( $this->settings['easy_mode'] ) && 'yes' === $this->settings['easy_mode'] )
				)
			) {
			wp_enqueue_script(
				'virtuaria-correios-autofill',
				VIRTUARIA_CORREIOS_URL . 'public/js/autofill.min.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/autofill.min.js' ),
				true
			);

			wp_localize_script(
				'virtuaria-correios-autofill',
				'virtCorreios',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'virtuaria_correios_autofill' ),
				)
			);
		}

		if ( is_product() || is_page( 'produto' ) ) {
			global $post;

			$current_product = wc_get_product( $post );
			if ( $current_product && $current_product->is_virtual() ) {
				return;
			}

			wp_enqueue_script(
				'virtuaria-correios-calc',
				VIRTUARIA_CORREIOS_URL . 'public/js/calc.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/calc.js' ),
				true
			);
			wp_enqueue_style(
				'virtuaria-correios-calc',
				VIRTUARIA_CORREIOS_URL . 'public/css/calc.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/css/calc.css' )
			);

			wp_localize_script(
				'virtuaria-correios-calc',
				'virtCorreios',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'do_calc_product_shipping' ),
					'product_id' => $post->ID,
				)
			);
		}

		if ( is_cart() || is_checkout() ) {
			wp_enqueue_style(
				'virtuaria-correios-cart-checkout',
				VIRTUARIA_CORREIOS_URL . 'public/css/cart-checkout.css',
				array(),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/css/cart-checkout.css' )
			);

			if ( is_cart() ) {
				wp_enqueue_script(
					'cep-mask',
					VIRTUARIA_CORREIOS_URL . 'public/js/cep-mask.js',
					array( 'jquery' ),
					filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/cep-mask.js' ),
					true
				);
			}

			$specific_countrys = get_option( 'woocommerce_specific_allowed_countries', array() );
			$ship_to_countries = get_option( 'woocommerce_ship_to_countries' );
			if ( is_cart()
				&& (
					! isset( $this->settings['display_cart_fields'] )
					|| 'yes' !== $this->settings['display_cart_fields']
				)
			) {
				if ( ( 'specific' === $ship_to_countries
					|| ! $ship_to_countries )
					&& is_array( $specific_countrys )
					&& in_array( 'BR', $specific_countrys, true ) ) {
						wp_enqueue_style(
							'virtuaria-correios-hide-country',
							VIRTUARIA_CORREIOS_URL . 'public/css/hide-country.css',
							array(),
							filemtime( VIRTUARIA_CORREIOS_DIR . 'public/css/hide-country.css' )
						);

						wp_localize_script(
							'cep-mask',
							'mask',
							array(
								'stateHidden' => true,
							)
						);
				}
			}

			wp_enqueue_script(
				'cep-placeholder',
				VIRTUARIA_CORREIOS_URL . 'public/js/cep-placeholder.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/cep-placeholder.js' ),
				true
			);
		}

		if ( is_checkout() ) {
			if ( ! $is_internationl ) {
				wp_enqueue_script(
					'virtuaria-correios-trigger-calc',
					VIRTUARIA_CORREIOS_URL . 'public/js/trigger-calc.js',
					array( 'jquery' ),
					filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/trigger-calc.js' ),
					true
				);
			}

			if ( isset( $this->settings['activate_checkout'] )
				&& 'yes' === $this->settings['activate_checkout'] ) {
				wp_enqueue_script(
					'virtuaria-correios-international',
					VIRTUARIA_CORREIOS_URL . 'public/js/international.js',
					array( 'jquery' ),
					filemtime( VIRTUARIA_CORREIOS_DIR . 'public/js/international.js' ),
					true
				);
			}
		}
	}

	/**
	 * Search address by postcode.
	 */
	public function search_address() {
		if ( isset( $_POST['nonce'], $_POST['postcode'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['nonce'] )
				),
				'virtuaria_correios_autofill'
			)
		) {
			$postcode = preg_replace(
				'/\D/',
				'',
				sanitize_text_field( wp_unslash( $_POST['postcode'] ) )
			);

			$data = array(
				'postcode'  => $postcode,
				'username'  => $this->settings['username'] ?? '',
				'password'  => $this->settings['password'] ?? '',
				'post_card' => $this->settings['post_card'] ?? '',
			);

			if ( isset( $this->settings['easy_mode'] ) ) {
				$data['easy_mode'] = 'yes';
			}

			$address = $this->api->get_address_by_postcode(
				$data
			);

			if ( 'NAO_ENCONTRADO' === $address ) {
				$address = false;
			}
			echo wp_json_encode( $address );
		}
		wp_die();
	}

	/**
	 * Display the shipping calculator.
	 */
	public function display_calc_shipping() {
		echo do_shortcode( '[virtuaria_correios_calculadora]' );
	}

	/**
	 * Dislay the shipping calculator.
	 */
	public function display_cart_totals_shortcode() {
		ob_start();
		if ( is_product() ) {
			global $post;

			$current_product = wc_get_product( $post );
			if ( $current_product && $current_product->is_virtual() ) {
				return ob_get_clean();
			}
			include VIRTUARIA_CORREIOS_DIR . 'templates/calc-shipping.php';
		} else {
			echo '<div class="calc-shipping unavailable">A calculadora de frete só está disponível na página do produto.</div>';
		}
		return ob_get_clean();
	}

	/**
	 * Perform shipping calculation for a product.
	 */
	public function product_calc_shipping() {
		if ( isset( $_POST['nonce'], $_POST['postcode'], $_POST['blog_id'] )
			&& (
				(
					isset( $this->settings['jump_nonce_validation'] )
					&& 'yes' === $this->settings['jump_nonce_validation']
				)
				|| wp_verify_nonce(
					sanitize_text_field(
						wp_unslash( $_POST['nonce'] )
					),
					'do_calc_product_shipping'
				)
			)
		) {
			if ( is_multisite() ) {
				switch_to_blog(
					sanitize_text_field(
						wp_unslash( $_POST['blog_id'] )
					)
				);
			}

			$variation_id = isset( $_POST['variation_id'] )
				? sanitize_text_field( wp_unslash( $_POST['variation_id'] ) )
				: false;
			$product_id   = isset( $_POST['product_id'] )
				? sanitize_text_field( wp_unslash( $_POST['product_id'] ) )
				: false;

			if ( empty( $product_id ) && isset( $_POST['virt_post_id'] ) ) {
				$product_id = sanitize_text_field(
					wp_unslash(
						$_POST['virt_post_id']
					)
				);
			}

			$product = wc_get_product(
				$variation_id
					? $variation_id
					: $product_id
			);

			if ( ! $product ) {
				if ( is_multisite() ) {
					restore_current_blog();
				}
				echo 'Falha ao calcular frete para este produto.';
				wp_die();
			}

			$cep = sanitize_text_field(
				wp_unslash( $_POST['postcode'] )
			);

			$package_total = $product->get_price();
			$contents[]    = array(
				'data'     => $product,
				'quantity' => 1,
			);
			WC_Shipping::instance()->calculate_shipping(
				array(
					array(
						'contents'        => $contents,
						'contents_cost'   => $package_total,
						'applied_coupons' => false,
						'user'            => array(
							'ID' => get_current_user_id(),
						),
						'destination'     => array(
							'country'  => 'BR',
							'postcode' => $cep,
							'state'    => $this->get_state_from_cep( $cep ),
						),
						'cart_subtotal'   => $package_total,
						'is_product_page' => true,
					),
				)
			);
			$shipping = WC()->shipping()->get_packages()[0]['rates'];
			if ( is_multisite() ) {
				restore_current_blog();
			}

			if ( is_array( $shipping ) && ! empty( $shipping ) ) {
				echo '<div class="info-calc">';
				echo '<table class="table-calc">';
				echo '<thead><tr><th>TIPO DE ENTREGA</th><th>CUSTO</th></tr></thead>';
				foreach ( $shipping as $rate_id => $rate ) {
					$meta = $rate->get_meta_data();
					$time = '';

					if ( isset( $meta['_delivery_time'] ) ) {
						$time = $meta['_delivery_time'];

						if ( $time > 1 ) {
							$time = 'Até ' . $time . ' dias úteis';
						} else {
							$time = 'Até ' . $time . ' dia útil';
						}
					}
					$cost = floatval( $rate->cost );
					echo '<tr><td>' . esc_html( $rate->label );
					echo '<span class="delivery-time">' . esc_html( $time ) . '</span></td>';
					echo '<td>R$ ' . esc_html( number_format( $cost, 2, ',', '.' ) ) . '</td></tr>';
				}
				echo '</table>';
				echo '</div>';
				setcookie(
					'virtuaria_correios_user_cep',
					$cep,
					time() + ( WEEK_IN_SECONDS * 1 ),
					'/'
				);
			} else {
				$errors = wc_get_notices( 'error' );

				$error_printed = false;
				if ( ! empty( $errors ) ) {
					foreach ( $errors as $error ) {
						if ( isset( $error['data'] )
							&& is_array( $error['data'] )
							&& in_array( 'INVALID_CEP', $error['data'], true ) ) {
							echo esc_html__(
								'CEP inválido. Por favor, tente novamente com um CEP válido.',
								'virtuaria-correios'
							);
							wc_clear_notices();
							$error_printed = true;
							break;
						}
					}
				}

				if ( ! $error_printed ) {
					echo 'Não há métodos de entrega disponíveis para este CEP.';
				}
			}

			if ( isset( $this->settings['error_message'] ) ) {
				woocommerce_output_all_notices();
			}
		} else {
			echo 'Falha ao validar campos para o cálculo de frete.';
		}

		wp_die();
	}

	/**
	 * Generate a progress bar for free shipping based on cart contents total.
	 */
	public function add_shortcode_progress_free_shipping() {
		if ( ! isset( $this->settings['serial'], $this->settings['progress_free'], $this->settings['authenticated'] )
			|| 'yes' !== $this->settings['progress_free']
			|| ! isset( WC()->cart )
			|| ! $this->settings['authenticated'] ) {
			return;
		}

		ob_start();
		$package       = WC()->shipping()->get_packages()[0];
		$shipping_zone = WC_Shipping_Zones::get_zone_matching_package(
			$package
		);

		$allowed_methods = array(
			'free_shipping',
			'virtuaria-correios-pac',
			'virtuaria-correios-sedex',
		);

		foreach ( $shipping_zone->get_shipping_methods( false ) as $method ) {
			if ( $method->is_enabled()
				&& in_array( $method->id, $allowed_methods, true )
				&& ( ( method_exists( $method, 'get_min_to_free_shipping' )
				&& $method->get_min_to_free_shipping() )
				|| ( isset( $method->requires, $method->min_amount )
				&& 'min_amount' === $method->requires ) ) ) {

				$cond_special = json_decode(
					$method->get_option( 'cond_special' ),
					true
				);

				if ( $cond_special ) {
					$applied_cond_special = false;
					foreach ( $cond_special as $key => $cond ) {
						if ( method_exists( $method, 'cond_match' )
							&& $method->cond_match(
								$cond,
								$package
							)
						) {
							$applied_cond_special = true;
							break;
						}
					}

					if ( $applied_cond_special ) {
						continue;
					}
				}

				$min = method_exists( $method, 'get_min_to_free_shipping' )
					? floatval( $method->get_min_to_free_shipping() )
					: 0;

				if ( 0 === $min && isset( $method->requires, $method->min_amount ) ) {
					$min = $method->min_amount;
				}

				if ( floatval( WC()->cart->get_cart_contents_total() ) < $min ) {
					$need_value = $min - WC()->cart->get_cart_contents_total();
					$need_value = wc_price( $need_value );

					$percent = round( ( WC()->cart->get_cart_contents_total() / $min ) * 100, 2 );

					$msg = "<span> Adicione $need_value para ganhar <strong>frete grátis</strong> no {$method->title}</span>";
				} else {
					$percent = 100;
					$msg     = '<span>Parabéns! Mínimo de ' . wc_price( $min ) . ' foi alcançado no ' . $method->title . '.</span>';
				}

				if ( is_checkout() ) {
					echo '<tr><td colspan="2">';
				}
				?>
				<div class="virt-progress-free-shipping">
					<?php echo wp_kses_post( $msg ); ?>
					<div class="progress">
						<div role="progressbar" class="progress-bar progress-bar-striped" style="width: <?php echo esc_attr( $percent ); ?>%;">
							<span> Frete grátis <strong><?php echo esc_attr( $percent ); ?>%</strong></span>
						</div>
					</div>
				</div>
				<style>
					.virt-progress-free-shipping .progress-bar {
						background-color: green;
						background-image: linear-gradient(45deg,#ffffff26 25%,#0000 0,#0000 50%,#ffffff26 0,#ffffff26 75%,#0000 0,#0000);
						background-size: 25px;
						color: #fff;
						font-weight: bold;
						text-align: center;
						padding: 3px 10px;
						text-wrap: nowrap;
					}
					.virt-progress-free-shipping .progress {
						background-color: #d3e1d3;
						height: 26px;
						overflow: hidden;
						font-size: 14px;
						border-radius: 6px;
					}
					.virt-progress-free-shipping {
						margin-top: 10px;
					}
					.virt-progress-free-shipping > span {
						font-weight: normal;
					}
					.woocommerce-cart .virt-progress-free-shipping {
						margin-bottom: 15px;
					}
				</style>
				<?php
				if ( is_checkout() ) {
					echo '</td></tr>';
				}
			}
		}

		return ob_get_clean();
	}

	/**
	 * Display progress for free shipping.
	 */
	public function display_progress_free_shipping() {
		if ( is_cart() ) {
			echo do_shortcode( '[progress_free_shipping]' );
		}
	}

	/**
	 * Display progress for free shipping.
	 */
	public function display_checkout_progress_free_shipping() {
		if ( is_checkout() ) {
			echo do_shortcode( '[progress_free_shipping]' );
		}
	}

	/**
	 * Hide shippings methods when free is available.
	 *
	 * @param array $rates   rates.
	 * @param array $package package.
	 */
	public function hide_shipping_when_free_is_available( $rates, $package ) {
		if ( ! isset( $this->settings['serial'], $this->settings['hide_shipping'], $this->settings['authenticated'] )
			|| ! $this->settings['serial']
			|| ! $this->settings['authenticated']
			|| 'yes' !== $this->settings['hide_shipping'] ) {
			return $rates;
		}

		$new_rates = array();
		foreach ( $rates as $rate_id => $rate ) {
			// Only modify rates if free_shipping is present.
			if ( 'free_shipping' === $rate->method_id ) {
				$new_rates[ $rate_id ] = $rate;
				break;
			}
		}

		if ( ! empty( $new_rates ) ) {
			return $new_rates;
		}

		return $rates;
	}

	/**
	 * Adds delivery time after method name.
	 *
	 * @param WC_Shipping_Rate $shipping_method Shipping method data.
	 */
	public function display_delivery_time( $shipping_method ) {
		$meta_data = $shipping_method->get_meta_data();
		$total     = isset( $meta_data['_delivery_time'] )
			? intval( $meta_data['_delivery_time'] )
			: 0;

		if ( $total <= 0
			|| ( isset( $this->settings['hide_delivery_time'] )
			&& 'yes' === $this->settings['hide_delivery_time'] ) ) {
			return;
		}

		if ( $total > 0 ) {
			if ( $total > 1 ) {
				$time = 'Previsão de entrega em até ' . $total . ' dias úteis';
			} else {
				$time = 'Previsão de entrega em a ' . $total . ' dia útil';
			}
			/* translators: %d: days to delivery */
			echo '<p class="delivery-time"><small>' . esc_html( $time ) . '</small></p>';
		}
	}

	/**
	 * Append delivery forecast in shipping method name.
	 *
	 * @param string   $name  Method name.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function shipping_method_delivery_time( $name, $order ) {
		foreach ( $order->get_shipping_methods() as $shipping_method ) {
			if ( ! in_array(
				$shipping_method->get_method_id(),
				array(
					'virtuaria-correios-sedex',
					'virtuaria-correios-pac',
				),
				true
			) ) {
				continue;
			}
			$total = (int) $shipping_method->get_meta( '_delivery_time' );

			if ( $total ) {
				$name = sprintf(
					/* translators: %1$d: shipping method name, %2$d: days to delivery */
					_n(
						'%1$s (entrega em até %2$d dia útil)',
						'%1$s (entrega em até %2$d dias úteis)',
						$total,
						'virtuaria-correios'
					),
					$shipping_method->get_name(),
					$total
				);
			}
		}

		return $name;
	}

	/**
	 * Properly display _delivery_time name.
	 *
	 * @param  string       $display_key Display key.
	 * @param  WC_Meta_Data $meta        Meta data.
	 * @return string
	 */
	public function item_display_delivery_time( $display_key, $meta ) {
		return '_delivery_time' === $meta->key
			? __( 'Previsão de entrega', 'virtuaria-correios' )
			: $display_key;
	}

	/**
	 * Handles the display of cart fields.
	 *
	 * This function checks if the 'display_cart_fields' setting is set to 'yes'.
	 * If it is not set or is not 'yes', hide some fields.
	 *
	 * @param bool $display The current display status of the cart fields.
	 * @return bool The updated display status of the cart fields.
	 */
	public function handle_cart_fields( $display ) {
		if ( ! isset( $this->settings['display_cart_fields'] )
			|| 'yes' !== $this->settings['display_cart_fields'] ) {
			$display = false;
		}
		return $display;
	}

	/**
	 * Authenticate premium user.
	 */
	public function authenticate_premium() {
		if ( ! get_transient( 'virtuaria_correios_checkout_authenticated' ) ) {
			Virtuaria_Correios::get_instance()->is_premium();
			set_transient(
				'virtuaria_correios_checkout_authenticated',
				true,
				HOUR_IN_SECONDS * 12
			);
		}
	}

	/**
	 * Enqueue style and scripts.
	 *
	 * @param string $hook hook.
	 */
	public function setup_shipping_scritps( $hook ) {
		if ( 'woocommerce_page_wc-settings' === $hook
			&& isset( $_GET['tab'], $_GET['instance_id'] )
			&& 'shipping' === $_GET['tab']
			&& $this->is_virtuariashipping_method_by_instance_id( intval( $_GET['instance_id'] ) ) ) {
			$dir = VIRTUARIA_CORREIOS_DIR . 'admin/';
			$url = VIRTUARIA_CORREIOS_URL . 'admin/';

			wp_enqueue_script(
				'virtuaria-correios-shipping',
				$url . 'js/shipping-method.js',
				array( 'jquery' ),
				filemtime( $dir . 'js/shipping-method.js' ),
				true
			);

			wp_localize_script(
				'virtuaria-correios-shipping',
				'shipping',
				array( 'authorized' => true )
			);

			wp_enqueue_style(
				'virtuaria-correios-shipping',
				$url . 'css/shipping-method.css',
				array(),
				filemtime( $dir . 'css/shipping-method.css' )
			);
		}
	}

	/**
	 * Retrieves the state from a given CEP (Brazilian postal code).
	 *
	 * @param string $cep The CEP to retrieve the state from.
	 * @return string The state corresponding to the given CEP. If the CEP is not found in the range_ceps array,
	 *                it retrieves the shipping state from the WC()->customer object or the default customer location.
	 */
	private function get_state_from_cep( $cep ) {
		$cep = preg_replace( '/\D/', '', $cep );

		$cep = (int) $cep;

		// Faixas de CEP por estado.
		$ranges = array(
			array(
				'start' => 69900000,
				'end'   => 69999999,
				'state' => 'AC',
			),
			array(
				'start' => 57000000,
				'end'   => 57999999,
				'state' => 'AL',
			),
			array(
				'start' => 69000000,
				'end'   => 69299999,
				'state' => 'AM',
			),
			array(
				'start' => 69400000,
				'end'   => 69899999,
				'state' => 'AM',
			),
			array(
				'start' => 68900000,
				'end'   => 68999999,
				'state' => 'AP',
			),
			array(
				'start' => 40000000,
				'end'   => 48999999,
				'state' => 'BA',
			),
			array(
				'start' => 60000000,
				'end'   => 63999999,
				'state' => 'CE',
			),
			array(
				'start' => 70000000,
				'end'   => 72799999,
				'state' => 'DF',
			),
			array(
				'start' => 73000000,
				'end'   => 73699999,
				'state' => 'DF',
			),
			array(
				'start' => 29000000,
				'end'   => 29999999,
				'state' => 'ES',
			),
			array(
				'start' => 72800000,
				'end'   => 72999999,
				'state' => 'GO',
			),
			array(
				'start' => 73700000,
				'end'   => 76799999,
				'state' => 'GO',
			),
			array(
				'start' => 65000000,
				'end'   => 65999999,
				'state' => 'MA',
			),
			array(
				'start' => 30000000,
				'end'   => 39999999,
				'state' => 'MG',
			),
			array(
				'start' => 79000000,
				'end'   => 79999999,
				'state' => 'MS',
			),
			array(
				'start' => 78000000,
				'end'   => 78899999,
				'state' => 'MT',
			),
			array(
				'start' => 66000000,
				'end'   => 68899999,
				'state' => 'PA',
			),
			array(
				'start' => 58000000,
				'end'   => 58999999,
				'state' => 'PB',
			),
			array(
				'start' => 50000000,
				'end'   => 56999999,
				'state' => 'PE',
			),
			array(
				'start' => 64000000,
				'end'   => 64999999,
				'state' => 'PI',
			),
			array(
				'start' => 80000000,
				'end'   => 87999999,
				'state' => 'PR',
			),
			array(
				'start' => 20000000,
				'end'   => 28999999,
				'state' => 'RJ',
			),
			array(
				'start' => 59000000,
				'end'   => 59999999,
				'state' => 'RN',
			),
			array(
				'start' => 76800000,
				'end'   => 76999999,
				'state' => 'RO',
			),
			array(
				'start' => 69300000,
				'end'   => 69399999,
				'state' => 'RR',
			),
			array(
				'start' => 90000000,
				'end'   => 99999999,
				'state' => 'RS',
			),
			array(
				'start' => 88000000,
				'end'   => 89999999,
				'state' => 'SC',
			),
			array(
				'start' => 49000000,
				'end'   => 49999999,
				'state' => 'SE',
			),
			array(
				'start' => 01000000,
				'end'   => 19999999,
				'state' => 'SP',
			),
			array(
				'start' => 77000000,
				'end'   => 77999999,
				'state' => 'TO',
			),
		);

		foreach ( $ranges as $range ) {
			if ( $cep >= $range['start'] && $cep <= $range['end'] ) {
				return $range['state'];
			}
		}

		return false;
	}

	/**
	 * Disables free shipping on product pages.
	 *
	 * @param bool                      $is_available The current availability of the shipping method.
	 * @param array                     $package The package being shipped.
	 * @param WC_Shipping_Free_Shipping $method The free shipping method.
	 * @return bool The updated availability of the shipping method.
	 */
	public function display_free_shipping_product_page( $is_available, $package, $method ) {
		if ( isset( $package['is_product_page'] )
			&& in_array( $method->requires, array( 'min_amount', 'either' ), true )
			&& $package['cart_subtotal'] >= $method->min_amount ) {
			$is_available = true;
		}
		return $is_available;
	}

	/**
	 * Saves the Correios shipping method information for a given order.
	 *
	 * @param int $order_id The ID of the order.
	 * @return void
	 */
	public function save_correios_shipping_method( $order_id ) {
		$order = wc_get_order( $order_id );

		$method_info = WC()->session->get( 'virtuaria_correios_methods', array() );

		if ( $order
			&& $method_info
			&& $order->get_shipping_methods() ) {
			$allowed_methods = array(
				'virtuaria-correios-sedex',
				'virtuaria-correios-pac',
			);
			foreach ( $order->get_shipping_methods() as $method ) {
				$shipping = new Virtuaria_Correios_Sedex( $method->get_instance_id() );
				if ( $shipping
					&& $method->get_method_id()
					&& in_array( $method->get_method_id(), $allowed_methods, true )
					&& isset( $method_info[ $shipping->service_cod ] ) ) {
					$order->update_meta_data(
						'_virtuaria_correios_shipping_method_info',
						array_map(
							'sanitize_text_field',
							wp_unslash( $method_info[ $shipping->service_cod ] )
						)
					);
					$order->save();
					WC()->session->__unset( 'virtuaria_correios_methods' );
					break;
				}
			}
		}
	}

	/**
	 * Resets shipping methods on checkout update.
	 *
	 * This forces a shipping method recalculation on checkout update.
	 *
	 * @param array $post_data Posted data.
	 */
	public function checkout_update_refresh_shipping_methods( $post_data ) {
		if ( apply_filters( 'virtuaria_correios_force_checkout_update_refresh_shipping_methods', true ) ) {
			$packages = WC()->cart->get_shipping_packages();
			foreach ( $packages as $package_key => $package ) {
				WC()->session->set( 'shipping_for_package_' . $package_key, false );
			}
		}
	}

	/**
	 * Shows a warning message when the Correios token is invalid.
	 *
	 * This message is shown in the WordPress admin area and asks the user to check the contract data in the integration tab.
	 */
	public function warning_token_invalid() {
		if ( ( ! isset( $this->settings['password'] )
			|| ! isset( $this->settings['username'] )
			|| ! isset( $this->settings['post_card'] )
			|| empty( $this->settings['password'] )
			|| empty( $this->settings['username'] )
			|| empty( $this->settings['post_card'] ) )
			&& ! isset( $this->settings['easy_mode'] ) ) {
			?>
			<div class="error">
				<p>
					<strong>Virtuaria Correios:</strong> Os dados de seu contrato com os Correios foram preenchidos incorretamente! Por favor, atualize as informações do contrato na aba integração clicando <a href="<?php echo esc_url( admin_url( 'admin.php?page=virtuaria-settings' ) ); ?>">aqui</a>.
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check if a shipping method is Virtuaria Correios Sedex by instance ID.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 *
	 * @return bool True if the shipping method is Virtuaria Correios Sedex, false otherwise.
	 */
	private function is_virtuariashipping_method_by_instance_id( $instance_id ) {
		$zones = WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone ) {
			$shipping_methods = $zone['shipping_methods'];
			foreach ( $shipping_methods as $shipping_method ) {
				if ( $shipping_method->instance_id == $instance_id
					&& 'virtuaria-correios-sedex' === $shipping_method->id ) {
					return true;
				}
			}
		}

		return false;
	}
}

new Virtuaria_Shipping_Services();

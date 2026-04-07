<?php
/**
 * Correios API.
 *
 * @package virtuaria.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Correios_API {
	/**
	 * Log.
	 *
	 * @var WC_Logger
	 */
	private $log;

	/**
	 * Endpoint.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Endpoint to basic mode.
	 *
	 * @var string
	 */
	private $basic_endpoint;

	/**
	 * Endpoint.
	 *
	 * @var string
	 */
	private $log_handler;

	/**
	 * Timeout.
	 *
	 * @var int
	 */
	private const TIMEOUT = 15;

	/**
	 * Initialize functions.
	 *
	 * @param WC_Logger $log the log instance.
	 * @param string    $enviroment the enviroment.
	 */
	public function __construct( WC_Logger $log = null, $enviroment ) {
		$this->log         = $log;
		$this->log_handler = 'virtuaria-correios';
		if ( 'production' === $enviroment ) {
			$this->endpoint = 'https://api.correios.com.br/';
		} else {
			$this->endpoint = 'https://apihom.correios.com.br/';
		}

		$this->basic_endpoint = 'https://correios.virtuaria.com.br/wp-json/v1/correios/';
	}

	/**
	 * Get acess token.
	 *
	 * @param array $data the data to generate token.
	 */
	public function get_token( array $data ) {
		$token = get_transient( 'virtuaria_correios_token' );
		if ( ! $token ) {
			if ( ! isset(
				$data['username'],
				$data['password'],
				$data['post_card']
			) ) {
				if ( $this->log ) {
					$this->log->add(
						$this->log_handler,
						'Falha ao obter token, dados ausentes. ' . wp_json_encode( $data ),
						WC_Log_Levels::ERROR
					);
				}
				update_option( 'virtuaria_correios_error_token', 404 );
				return false;
			}

			if ( $this->log ) {
				$to_log              = $data;
				$to_log['username']  = preg_replace( '/\w/', 'x', $to_log['username'] );
				$to_log['password']  = preg_replace( '/\w/', 'x', $to_log['password'] );
				$to_log['post_card'] = preg_replace( '/\w/', 'x', $to_log['post_card'] );
				$this->log->add(
					$this->log_handler,
					'Request to get correios token: ' . $this->endpoint . ' data: ' . wp_json_encode( $to_log ),
					WC_Log_Levels::INFO
				);
			}

			$request = wp_remote_post(
				$this->endpoint . 'token/v1/autentica/cartaopostagem',
				array(
					'headers' => array(
						'content-Type'  => 'application/json',
						'Authorization' => 'Basic ' . base64_encode( $data['username'] . ':' . $data['password'] ),
					),
					'body'    => '{"numero":"' . $data['post_card'] . '"}',
					'timeout' => self::TIMEOUT,
				)
			);

			$response_code = wp_remote_retrieve_response_code( $request );
			if ( is_wp_error( $request )
				|| ! in_array( $response_code, array( 200, 201 ), true ) ) {

				if ( $this->log ) {
					$this->log->add(
						$this->log_handler,
						'Erro ao obter token: ' . ( is_wp_error( $request )
							? $request->get_error_message()
							: wp_json_encode( $request ) ),
						WC_Log_Levels::ERROR
					);
				}

				update_option( 'virtuaria_correios_error_token', $response_code );

				return false;
			}

			$request = json_decode( wp_remote_retrieve_body( $request ), true );

			if ( isset( $request['token'] ) && isset( $request['expiraEm'] ) ) {
				set_transient(
					'virtuaria_correios_token',
					$request['token'],
					strtotime( $request['expiraEm'] ) - time()
				);
				update_option(
					'virtuaria_correios_contract',
					$request
				);
				$token = $request['token'];
			}
		}

		return $token;
	}

	/**
	 * Get shipping deadline.
	 *
	 * @param array $data data to shipping.
	 * @return mixed|false
	 */
	public function get_shipping_national_deadline( array $data ) {
		if ( ! isset( $data['easy_mode'] ) ) {
			$token = $this->get_token( $data );

			if ( ! $token ) {
				return false;
			}
		}

		$request = array(
			'idLote'          => strval( time() ),
			'parametrosPrazo' => array(
				array(
					'coProduto'    => $data['service'],
					'cepOrigem'    => $data['origin'],
					'cepDestino'   => $data['destination'],
					'nuRequisicao' => $this->get_nsu(),
				),
			),
		);

		if ( isset( $data['easy_mode'] ) ) {
			$this->add_extra_information( $request );
		}

		$endpoint = $this->endpoint . 'prazo/v1/nacional';

		if ( isset( $data['easy_mode'] ) ) {
			$endpoint = $this->basic_endpoint . 'deadline';
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . ( isset( $token )
						? $token
						: '' ),
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( ! is_wp_error( $response )
			&& in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ), true ) ) {
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Consulta Prazo para os dados: ' . wp_remote_retrieve_body( $response ),
					WC_Log_Levels::INFO
				);
			}
			$delivery_time = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $delivery_time[0]['prazoEntrega'] ) ) {
				return $delivery_time[0]['prazoEntrega'];
			} elseif ( isset( $data['destinationCountry'] ) ) {
				return $delivery_time[0]['prazoMaximo'];
			}
		}

		if ( $this->log ) {
			$to_log = $request;
			unset( $to_log['domain'] );
			unset( $to_log['module'] );
			unset( $to_log['mode'] );
			unset( $to_log['version'] );

			$this->log->add(
				$this->log_handler,
				'Consulta Prazo ' . wp_json_encode( $to_log ),
				WC_Log_Levels::ERROR
			);
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					$response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					wp_remote_retrieve_body( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		return false;
	}

	/**
	 * Get shipping deadline.
	 *
	 * @param array $data data to shipping.
	 * @return mixed|false
	 */
	public function get_shipping_international_deadline( array $data ) {
		if ( ! isset( $data['easy_mode'] ) ) {
			$token = $this->get_token( $data );

			if ( ! $token ) {
				return false;
			}
		}

		if ( isset( $data['easy_mode'] ) ) {
			return false;
		}

		$query_url = sprintf(
			'prazo/v2/internacional/exportacao/%s?sgPaisOrigem=BR&sgPaisDestino=%s&dtPostagem=%s',
			$data['service'],
			$data['destinationCountry'],
			wp_date( 'd-m-Y', strtotime( '+1 day' ) )
		);

		$response = wp_remote_get(
			$this->endpoint . $query_url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . (
						isset( $token )
							? $token
							: ''
						),
					'Accept'         => 'application/json',
					'Content-Type'   => 'application/json',
					'Content-Length' => 0,
				),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( ! is_wp_error( $response )
			&& in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ), true ) ) {
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Consulta Prazo para os dados: ' . wp_remote_retrieve_body( $response ),
					WC_Log_Levels::INFO
				);
			}
			$delivery_time = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $delivery_time['prazoMaximo'] ) ) {
				return $delivery_time['prazoMaximo'];
			}
		}

		if ( $this->log ) {
			$this->log->add(
				$this->log_handler,
				'Consulta Prazo ' . $this->endpoint . $query_url,
				WC_Log_Levels::ERROR
			);
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					$response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					wp_remote_retrieve_body( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		return false;
	}

	/**
	 * Get shipping cost.
	 *
	 * @param array $data the data do fetch cost.
	 */
	public function get_shipping_cost( array $data ) {
		if ( ! isset( $data['easy_mode'] ) ) {
			$token = $this->get_token( $data );

			if ( ! $token ) {
				return false;
			}
		}

		$request = array(
			'idLote'            => strval( time() ),
			'parametrosProduto' => array(
				array(
					'coProduto'    => $data['service'],
					'cepOrigem'    => $data['origin'],
					'cepDestino'   => $data['destination'],
					'psObjeto'     => $data['weight'],
					'comprimento'  => $data['length'],
					'largura'      => $data['width'],
					'altura'       => $data['height'],
					'nuRequisicao' => $this->get_nsu(),
					'tpObjeto'     => $data['object_type'], // 1 - Envelope, 2 - Pacote; 3 - Rolo.
					'nuContrato'   => $data['nuContrato'],
					'nuDR'         => $data['nuDR'],
				),
			),
		);

		if ( isset( $data['destinationCountry'] ) ) {
			unset( $request['parametrosProduto'][0]['cepDestino'] );
			$request['parametrosProduto'][0]['sgPaisDestino'] = $data['destinationCountry'];
		}

		if ( isset( $data['vlDeclarado'] ) ) {
			$request['parametrosProduto'][0]['vlDeclarado'] = $data['vlDeclarado'];
		}

		if ( isset( $data['servicosAdicionais'] ) ) {
			$request['parametrosProduto'][0]['servicosAdicionais'] = $data['servicosAdicionais'];
		}

		if ( isset( $data['easy_mode'] ) ) {
			$this->add_extra_information( $request );
		}

		$endpoint = $this->endpoint . 'preco/v1/nacional/';

		if ( isset( $data['easy_mode'] ) ) {
			$endpoint = $this->basic_endpoint . 'cotation';
		} elseif ( isset( $data['destinationCountry'] ) ) {
			$endpoint = $this->endpoint . 'preco/v1/internacional';
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . ( isset( $token )
						? $token
						: '' ),
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( $this->log ) {
			$to_log = $request;
			if ( isset( $to_log['nuContrato'] ) ) {
				$to_log['nuContrato'] = preg_replace( '/\d/', 'x', $to_log['nuContrato'] );
			}
			unset( $to_log['domain'] );
			unset( $to_log['module'] );
			unset( $to_log['mode'] );
			unset( $to_log['version'] );
			$this->log->add(
				$this->log_handler,
				'Enviando dados para obter Preço de frete ' . wp_json_encode( $to_log ),
				WC_Log_Levels::INFO
			);
		}

		if ( ! is_wp_error( $response )
			&& in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ), true ) ) {
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da Consulta de Preço: ' . wp_remote_retrieve_body( $response ),
					WC_Log_Levels::INFO
				);
			}
			$delivery_time = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $delivery_time[0]['pcFinal'] ) ) {
				return $delivery_time[0]['pcFinal'];
			}
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					$response->get_error_message(),
					WC_Log_Levels::ERROR
				);

				if ( isset( $data['product'], $data['disable_feedback'] )
					&& $data['product']
					&& ! $data['disable_feedback']
					&& ( is_cart()
					|| ( is_checkout() && isset( $_REQUEST['wc-ajax'] ) )
					|| isset( $_REQUEST['update_cart'], $_REQUEST['woocommerce-cart-nonce'] ) ) ) {
					wc_add_notice(
						'<span style="color:red">' .
						sprintf(
							__( 'Falha na comunicação com os sistemas dos Correios.', 'virtuaria-correios' ),
						)
						. '</span>',
						'notice'
					);
				}
			} else {
				$this->log->add(
					$this->log_handler,
					wp_remote_retrieve_body( $response ),
					WC_Log_Levels::ERROR
				);

				if ( isset( $data['product'], $data['show_errors'] )
					&& $data['product']
					&& $data['show_errors'] ) {

					$response = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( isset( $response[0]['txErro'] )
						&& ( is_cart()
						|| ( is_checkout() && isset( $_REQUEST['wc-ajax'] ) )
						|| isset( $_REQUEST['update_cart'], $_REQUEST['woocommerce-cart-nonce'] )
						|| ( isset( $_REQUEST['action'] ) && 'product_calc_shipping' === $_REQUEST['action'] ) ) ) {
						wc_add_notice(
							sprintf(
								/* translators: %1$s: shipping method, %2$s: error */
								__( '%1$s Falha ao consultar frete. %2$s ', 'virtuaria-correios' ),
								"<b>{$data['product']}:</b>",
								$response[0]['txErro']
							),
							'error'
						);
					}
				} elseif ( isset( $data['product'], $data['disable_feedback'] )
					&& $data['product']
					&& ! $data['disable_feedback']
					&& ( is_cart()
					|| ( is_checkout() && isset( $_REQUEST['wc-ajax'] ) )
					|| isset( $_REQUEST['update_cart'], $_REQUEST['woocommerce-cart-nonce'] ) ) ) {
					$response = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( isset( $response[0]['txErro'] ) ) {
						wc_add_notice(
							'<span style="color:red">' .
							sprintf(
								/* translators: %s: shipping method */
								__( 'Não foi possível calcular o frete para o método %s. Isso pode ocorrer quando o pedido não se enquadra nas regras permitidas pelos Correios.', 'virtuaria-correios' ),
								'<b>' . $data['product'] . '</b>'
							) . '</span>',
							'notice'
						);
					} else {
						wc_add_notice(
							'<span style="color:red">' .
							sprintf(
								__( 'Falha na comunicação com os sistemas dos Correios.', 'virtuaria-correios' ),
							)
							. '</span>',
							'notice'
						);
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get the next sequential number for the NSU.
	 *
	 * @return int The next sequential number for the NSU.
	 */
	private function get_nsu() {
		$last_number = get_option( 'virtuaria_correios_nsu', 0 ) + 1;

		update_option(
			'virtuaria_correios_nsu',
			$last_number
		);
		return $last_number;
	}

	/**
	 * Get shipping cost.
	 *
	 * @param array $data the data do fetch cost.
	 */
	public function create_prepost( array $data ) {
		$token = $this->get_token( $data );

		if ( ! $token ) {
			return false;
		}

		$response = wp_remote_post(
			$this->endpoint . 'prepostagem/v1/prepostagens',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( $this->log ) {
			$this->log->add(
				$this->log_handler,
				'Enviando dados para criação da Pré-postagem ' . wp_json_encode( $data ),
				WC_Log_Levels::INFO
			);
		}

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da Criação de Pré-postagem: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response;
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da Criação de Pré-postagem: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Resposta da Criação de Pré-postagem: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		if ( ! is_wp_error( $response ) ) {
			$this->registry_prepost_error( $response );
		}

		return false;
	}

	/**
	 * Registry the error message from create prepost.
	 *
	 * @param array $response the response from create prepost.
	 */
	private function registry_prepost_error( $response ) {
		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $response['msgs'] ) || isset( $response['msg'] ) ) {
			$msg = isset( $response['msgs'] )
				? $response['msgs']
				: array( $response['msg'] );

			foreach ( $msg as $index => $error ) {
				if ( false !== strpos( $error, 'PPN-288' ) ) {
					$msg[ $index ] = __( 'Etiqueta ainda não liberada para impressão. Tente novamente mais tarde.', 'virtuaria-correios' );
				} elseif ( false !== strpos( $error, 'RTL-036: ' ) ) {
					$msg[ $index ] = __( 'O número do destinatário não foi informado no pedido.', 'virtuaria-correios' );
				} else {
					$msg[ $index ] = preg_replace( '/RTL-\d+: ?/', '', $error );
				}
			}
			set_transient(
				'virtuaria_correios_prepost_error',
				$msg,
				30
			);
		}
	}

	/**
	 * Request criation of label.
	 *
	 * @param array $data the data to label.
	 */
	public function generate_label( $data ) {
		$token = $this->get_token( $data );

		if ( ! $token ) {
			return false;
		}

		$response = wp_remote_post(
			$this->endpoint . 'prepostagem/v1/prepostagens/rotulo/assincrono/pdf',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( $this->log ) {
			$this->log->add(
				$this->log_handler,
				'Enviando dados para criação do rótulo de Pré-postagem ' . wp_json_encode( $data ),
				WC_Log_Levels::INFO
			);
		}

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da Criação do Rótulo: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response['idRecibo'];
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da Criação do Rótulo: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Resposta da Criação do Rótulo: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		if ( ! is_wp_error( $response ) ) {
			$this->registry_prepost_error( $response );
		}

		return false;
	}

	/**
	 * Get label from idRecibo.
	 *
	 * @param array $data the data.
	 */
	public function get_label( $data ) {
		$token = $this->get_token( $data );

		if ( ! $token ) {
			return false;
		}

		$response = wp_remote_get(
			$this->endpoint . 'prepostagem/v1/prepostagens/rotulo/download/assincrono/' . $data['idRecibo'],
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta ao obter Rótulo: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response['dados'];
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Resposta ao obter Rótulo: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Resposta ao obter Rótulo: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		if ( ! is_wp_error( $response ) ) {
			$this->registry_prepost_error( $response );
		}

		return false;
	}

	/**
	 * Print DACE from DC-e generated by Correios.
	 *
	 * @param array $data the data.
	 */
	public function print_dace( $data ) {
		$token = $this->get_token( $data );

		if ( ! $token ) {
			return false;
		}

		$body = $data;
		unset( $body['username'], $body['password'], $body['post_card'] );

		$response = wp_remote_post(
			$this->endpoint . 'prepostagem/v1/prepostagens/dce/dace/impressao',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( $this->log ) {
			$this->log->add(
				$this->log_handler,
				'Enviando dados para impressão da DACE ' . wp_json_encode( $data ),
				WC_Log_Levels::INFO
			);
		}

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da impressão da DACE: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response;
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Resposta da impressão da DACE: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Resposta da impressão da DACE: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		if ( ! is_wp_error( $response ) ) {
			$this->registry_prepost_error( $response );
		}

		return false;
	}

	/**
	 * Retrieves the address by postcode.
	 *
	 * @param array $data the data from search.
	 * @return mixed The address data array if successful, false or string otherwise.
	 */
	public function get_address_by_postcode( $data ) {
		if ( ! isset( $data['easy_mode'] ) ) {
			$token = $this->get_token( $data );

			if ( ! $token ) {
				return false;
			}
		}

		$request = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . ( isset( $token )
					? $token
					: '' ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'timeout' => self::TIMEOUT,
		);

		$query_params = array();
		if ( isset( $data['easy_mode'] ) ) {
			$query_params = array(
				'postcode' => $data['postcode'],
			);
			$this->add_extra_information( $query_params );
		}

		$response = wp_remote_get(
			! isset( $data['easy_mode'] )
				? $this->endpoint . 'cep/v2/enderecos/' . $data['postcode']
				: $this->basic_endpoint . 'address?' . http_build_query( $query_params ),
			$request
		);

		if ( ! is_wp_error( $response )
			&& 404 === wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta Consulta endereço: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response;
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Resposta Consulta endereço: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Resposta Consulta endereço: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		return 'NAO_ENCONTRADO';
	}

	/**
	 * Retrieves full trakking.
	 *
	 * @param array $data the data from search.
	 * @return array|bool The trakking data if successful, false otherwise.
	 */
	public function get_trakking_by_code( $data ) {
		if ( ! isset( $data['easy_mode'] ) ) {
			$token = $this->get_token( $data );

			if ( ! $token ) {
				return false;
			}
		}

		$request = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . ( isset( $token )
					? $token
					: '' ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'timeout' => self::TIMEOUT,
		);

		$query_params = array();
		if ( isset( $data['easy_mode'] ) ) {
			$query_params = array(
				'trakking' => $data['trakking'],
			);
			$this->add_extra_information( $query_params );
		}

		$response = wp_remote_get(
			! isset( $data['easy_mode'] )
				? $this->endpoint . 'srorastro/v1/objetos/' . $data['trakking']
				: $this->basic_endpoint . 'trakking?' . http_build_query( $query_params ),
			$request
		);

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Resposta do rastreamento: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response;
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Resposta do rastreamento: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Resposta do rastreamento: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		return false;
	}

	/**
	 * Retrieves service list.
	 *
	 * @param array $data the data from search.
	 */
	public function get_service_list( $data ) {
		$token    = $this->get_token( $data );
		$contract = get_transient( 'virtuaria_correios_contract' );
		$contract = $contract ? $contract : get_option( 'virtuaria_correios_contract' );

		if ( ! $token
			|| ! isset( $contract['cnpj'] )
			|| ! isset( $contract['cartaoPostagem']['contrato'] ) ) {
			if ( isset( $this->log ) ) {
				$this->log->add(
					'virtuaria-correios',
					'Informações do cartão de postagem ausentes ao consultar serviços',
					WC_Log_Levels::ERROR
				);
			}
			return false;
		}

		$response = wp_remote_get(
			$this->endpoint . 'meucontrato/v1/empresas/' . $contract['cnpj'] . '/contratos/' . $contract['cartaoPostagem']['contrato'] . '/servicos?size=500',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Lista de serviços: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return $response['itens'];
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Lista de serviços: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Lista de serviços: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		return false;
	}

	/**
	 * Add extra information to the array.
	 *
	 * @param array $array the array to add extra information.
	 */
	private function add_extra_information( &$array ) {
		$plugin = get_plugin_data(
			VIRTUARIA_CORREIOS_DIR . 'class-virtuaria-correios.php'
		);

		$array['domain']  = str_replace( array( 'http://', 'https://' ), '', get_home_url() );
		$array['version'] = isset( $plugin['Version'] )
			? $plugin['Version']
			: '1.1.9';
		$array['module']  = isset( $plugin['Name'] )
			? $plugin['Name']
			: 'Virtuaria Correios';
		$array['mode']    = get_transient( 'virtuaria_correios_authenticated' )
			? 'Premium'
			: 'Free';
	}

	/**
	 * Get the destination city.
	 *
	 * @param array $data the data from search.
	 * @return string|bool The city code if successful, false otherwise.
	 */
	private function get_destination_city( $data ) {
		if ( ! isset( $data['easy_mode'] ) ) {
			$token = $this->get_token( $data );

			if ( ! $token ) {
				return false;
			}
		}

		$response = wp_remote_get(
			$this->endpoint . 'pais/v1/paises/' . $data['destinationCountry']
				. '/cidades?siglaPais=' . $data['destinationCountry']
				. '&nome=' . str_replace( ' ', '', $data['city'] ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'timeout' => self::TIMEOUT,
			)
		);

		$this->log->debug(
			$this->endpoint . 'pais/v1/paises/' . $data['destinationCountry']
				. '/cidades?siglaPais=' . $data['destinationCountry']
				. '&nome=' . $data['city']
		);

		if ( ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $this->log ) {
				$this->log->add(
					$this->log_handler,
					'Consulta cidade internacional: ' . wp_json_encode( $response ),
					WC_Log_Levels::INFO
				);
			}
			return isset( $response[0]['codigo'] )
				? $response[0]['codigo']
				: false;
		}

		if ( $this->log ) {
			if ( is_wp_error( $response ) ) {
				$this->log->add(
					$this->log_handler,
					'Consulta cidade internacional: ' . $response->get_error_message(),
					WC_Log_Levels::ERROR
				);
			} else {
				$this->log->add(
					$this->log_handler,
					'Consulta cidade internacional: ' . wp_json_encode( $response ),
					WC_Log_Levels::ERROR
				);
			}
		}

		return false;
	}
}

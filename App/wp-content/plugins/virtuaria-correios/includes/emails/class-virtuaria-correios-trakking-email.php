<?php
/**
 * Class Virtuaria_Correios_Trakking file.
 *
 * @package virtuaria/integrations/correios.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Virtuaria_Correios_Trakking_Email' ) ) :

	/**
	 * Tracking Order Email.
	 *
	 * An email sent to the customer when an order receives a tracking.
	 *
	 * @class       Virtuaria_Correios_Trakking
	 * @extends     WC_Email
	 */
	class Virtuaria_Correios_Trakking_Email extends WC_Email {
		/**
		 * Message from trakking mail.
		 *
		 * @var string
		 */
		public $tracking_message;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id            = 'virtuaria_correios_trakking';
			$this->title         = __( 'Virtuaria Rastreio de pedidos', 'virtuaria-correios' );
			$this->description   = __( 'Enviado quando o pedido é entregue a transportadora Correios com código de rastreio.', 'virtuaria-correios' );
			$this->template_html = 'mails/virtuaria-trakking.php';
			$this->placeholders  = array(
				'{order_date}'              => '',
				'{order_number}'            => '',
				'{order_billing_full_name}' => '',
			);
			$this->email_type    = 'html';

			$this->tracking_message = $this->get_option( 'tracking_message', $this->get_default_message() );

			$this->customer_email = true;
			$this->template_base  = VIRTUARIA_CORREIOS_DIR . 'templates/';

			// Call parent constructor.
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}]: Seu pedido #{order_number} foi enviado pelos Correios', 'virtuaria-correios' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Seu pedido foi enviado', 'virtuaria-correios' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int            $order_id The order ID.
		 * @param WC_Order|false $order Order object.
		 * @param string         $tracking_code Tracking code.
		 */
		public function trigger( $order_id, $order = false, $tracking_code = '' ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                                    = $order;
				$this->recipient                                 = $this->object->get_billing_email();
				$this->placeholders['{order_date}']              = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}']            = $this->object->get_order_number();
				$this->placeholders['{order_billing_full_name}'] = $this->object->get_formatted_billing_full_name();
				$this->placeholders['{site_title}']              = get_bloginfo( 'name' );
				$this->placeholders['{tracking_code}']           = sprintf(
					'<ul><li><a target="_blank" href="https://rastreamento.correios.com.br/app/index.php?objetos=%s">%s</a></li></ul>',
					$tracking_code,
					$tracking_code
				);
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					$this->get_attachments()
				);
			}

			$this->restore_locale();
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'tracking_message'   => $this->format_string( $this->tracking_message ),
					'sent_to_admin'      => true,
					'plain_text'         => false,
					'email'              => $this,
				),
				$this->template_base,
				$this->template_base
			);
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @since 3.7.0
		 * @return string
		 */
		public function get_default_additional_content() {
			return __( 'Obrigado pela compra.', 'virtuaria-correios' );
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			/* translators: %s: list of placeholders */
			$placeholder_text  = sprintf( __( 'Placeholders disponíveis: %s', 'virtuaria-correios' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Habilitar', 'virtuaria-correios' ),
					'type'    => 'checkbox',
					'label'   => __( 'Habilita está notificação de e-mail', 'virtuaria-correios' ),
					'default' => 'yes',
				),
				'subject'            => array(
					'title'       => __( 'Assunto', 'virtuaria-correios' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'default'     => $this->get_default_subject(),
				),
				'heading'            => array(
					'title'       => __( 'Cabeçalho do e-mail', 'virtuaria-correios' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'default'     => $this->get_default_heading(),
				),
				'tracking_message'   => array(
					'title'       => __( 'Conteúdo do e-mail', 'woocommerce-correios' ),
					'type'        => 'textarea',
					/* translators: %s: email message */
					'description' => __( 'Insira o conteúdo do e-mail que será enviado para o destinatário.', 'virtuaria-correios' ),
					'default'     => $this->get_default_message(),
					'desc_tip'    => true,
				),
				'additional_content' => array(
					'title'       => __( 'Conteúdo adicional', 'virtuaria-correios' ),
					'description' => __( 'Texto exibido abaixo do conteúdo principal do e-mail.', 'virtuaria-correios' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'virtuaria-correios' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Return email type.
		 *
		 * @return string
		 */
		public function get_email_type() {
			return 'html';
		}

		/**
		 * Returns a default message for the tracking email.
		 *
		 * The message includes a greeting, a notification that the order has been sent by Correios,
		 * and instructions on how to track the delivery.
		 *
		 * @return string The default message for the tracking email.
		 */
		private function get_default_message() {
			return __( 'Olá. Seu pedido #{order_number} foi enviado pelos Correios.', 'virtuaria-correios' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'Para rastrear sua entrega, use o seguinte código de rastreamento: {tracking_code}', 'virtuaria-correios' )
			. PHP_EOL . ' ' . PHP_EOL;
		}
	}

endif;

return new Virtuaria_Correios_Trakking_Email();

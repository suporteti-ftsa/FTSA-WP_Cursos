<?php
/**
 * Handle Correios content declaration PDF.
 *
 * @package virtuaria/correios.
 */

use setasign\Fpdi\Tcpdf\Fpdi;

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Correios_Content_Declaration_PDF extends Fpdi {
	/**
	 * Sender information.
	 *
	 * @var array
	 */
	private $sender;

	/**
	 * Recipient information.
	 *
	 * @var array
	 */
	private $recipient;

	/**
	 * Itens information.
	 *
	 * @var array
	 */
	private $itens;

	/**
	 * Constructor for the Virtuaria_Correios_Content_Declaration_PDF class.
	 *
	 * @param array $sender    The sender information.
	 * @param array $recipient The recipient information.
	 * @param array $itens        The items information.
	 */
	public function __construct( $sender, $recipient, $itens ) {
		parent::__construct();

		$this->sender    = $sender;
		$this->recipient = $recipient;
		$this->itens     = $itens;
	}

	/**
	 * Builds a PDF with the content declaration form.
	 *
	 * @param string $template_path The path to the PDF template file.
	 * @param string $output_path   The path to the output PDF file.
	 */
	public function build_pdf( $template_path, $output_path ) {
		$line_height = 5;
		// Carrega o modelo do formulário oficial.
		$page_count = $this->setSourceFile( $template_path );

		// Usa a primeira página como base.
		$template_id = $this->importPage( 1 );
		$this->AddPage();
		$this->useTemplate( $template_id, array( 'adjustPageSize' => true ) );

		// Configurações de fonte.
		$this->SetFont( 'Helvetica', '', 7 );

		// Preenche os campos do sender.
		$this->SetXY( 20, 20 ); // Nome.
		$this->Write( $line_height, $this->sender['nome'] );
		$this->SetXY( 25, 25 ); // Endereço.
		$this->Write( $line_height, $this->sender['endereco'] );
		$this->SetXY( 5, 30 ); // Bairro e Complemento.
		$neighborhood = 'Bairro ' . $this->sender['bairro'];
		if ( $this->sender['complemento'] ) {
			$neighborhood .= ', ' . $this->sender['complemento'];
		}
		$this->Write( $line_height, $neighborhood );
		$this->SetXY( 20, 35 ); // Cidade.
		$this->Write( $line_height, $this->sender['cidade'] );
		$this->SetXY( 88, 35 ); // UF.
		$this->Write( $line_height, $this->sender['uf'] );
		$this->SetXY( 12, 40 ); // CEP.
		$this->Write( $line_height, $this->sender['cep'] );
		$this->SetXY( 75, 40 ); // CPF/CNPJ.
		$this->Write( $line_height, $this->sender['cpf'] );

		// Preenche os campos do Destinatário.
		$this->SetXY( 120, 20 ); // Nome.
		$this->Write( $line_height, $this->recipient['nome'] );
		$this->SetXY( 123, 25 ); // Endereço.
		$this->Write( $line_height, $this->recipient['endereco'] );
		$this->SetXY( 106, 30 ); // Bairro e Complemento.
		$neighborhood = 'Bairro ' . $this->recipient['bairro'];
		if ( $this->recipient['complemento'] ) {
			$neighborhood .= ', ' . $this->recipient['complemento'];
		}
		$this->Write( $line_height, $neighborhood );
		$this->SetXY( 120, 35 ); // Cidade.
		$this->Write( $line_height, $this->recipient['cidade'] );
		$this->SetXY( 190, 35 ); // UF.
		$this->Write( $line_height, $this->recipient['uf'] );
		$this->SetXY( 111, 40 ); // CEP.
		$this->Write( $line_height, $this->recipient['cep'] );
		$this->SetXY( 175, 40 ); // CPF/CNPJ.
		$this->Write( $line_height, $this->recipient['cpf'] );

		// Preenche os itens dinamicamente.
		$start_y     = 54;
		$item_height = 5;
		$per_page    = 20;

		$current_item = 0;

		foreach ( $this->itens['itens'] as $index => $item ) {
			// Adiciona uma nova página se necessário.
			if ( $current_item > $per_page ) {
				$this->AddPage();
				$this->useTemplate(
					$template_id,
					array( 'adjustPageSize' => true )
				);
				$start_y      = 40; // Reinicia a posição inicial na nova página.
				$current_item = 0; // Reinicia a contagem de itens.
			}

			$this->SetXY( 10, $start_y + ( $current_item * $item_height ) );
			$this->Write( $line_height, $index + 1 );
			$this->SetXY( 25, $start_y + ( $current_item * $item_height ) );
			$this->Write( $line_height, $item['descricao'] );
			$this->SetXY( 150, $start_y + ( $current_item * $item_height ) );
			$this->Write( $line_height, $item['quantidade'] );
			$this->SetXY( 180, $start_y + ( $current_item * $item_height ) );
			$this->Write( $line_height, $item['valor'] );

			++$current_item;
		}

		// Adiciona o peso total e assinatura.
		$this->SetXY( 140, 155 ); // Peso total.
		$this->Write( $line_height, $this->itens['total'] );
		$this->SetXY( 140, 160 ); // Peso total.
		$this->Write( $line_height, $this->itens['weight'] . 'kg' );
		$this->SetXY( 5, 200 ); // Data e assinatura.
		$this->Write( $line_height, $this->sender['cidade'] );
		$this->SetXY( 50, 200 ); // Dia.
		$this->Write( $line_height, gmdate( 'd' ) );
		$this->SetXY( 75, 200 ); // Mês.
		$this->Write( $line_height, gmdate( 'F' ) );
		$this->SetXY( 110, 200 ); // Ano.
		$this->Write( $line_height, gmdate( 'Y' ) );

		$this->Output( $output_path, 'F' );
	}

	/**
	 * Override the header() method to avoid printing the title on the content declaration PDF.
	 */
	public function header() {
	}
}

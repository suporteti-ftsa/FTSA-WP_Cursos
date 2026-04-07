<?php
/**
 * Template to calculate shipping for product page.
 *
 * @package virtuaria/integrations/correios.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="shipping-calc">

	<p>
		<?php
		echo esc_html(
			__( 'Consulte o frete e prazo estimado de entrega:', 'virtuaria-correios' )
		);
		?>
	</p>

	<p class="cep-area">
		<input
			type="text"
			id="virt-postcode"
			autocomplete="off"
			placeholder="<?php echo esc_attr( __( 'Digite seu CEP', 'wsc-plugin' ) ); ?>"
			name="virt_postcode"
			maxlength="9"
			value="<?php echo isset( $_COOKIE['virtuaria_correios_user_cep'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_COOKIE['virtuaria_correios_user_cep'] ) ) ) : ''; ?>"
			class="input-text text" />
	
		<button	id="virt-button" class="button virt-button">
			Consultar
		</button>
	</p>

	<a
		href="https://buscacepinter.correios.com.br/app/endereco/index.php"
		class="search-cep"
		target="_blank">
		<?php esc_attr_e( 'Não sei meu CEP', 'virtuaria-correios' ); ?>
	</a>
	<?php
	global $post;

	if ( $post instanceof WP_Post ) {
		$product_id = $post->ID;
	} else {
		$product_id = 0;
	}
	?>
	<input type="hidden"
		name="virt_post_id"
		id="virt-post-id"
		value="<?php echo esc_attr( $product_id ); ?>">
		
	<input
		type="hidden"
		name="virt_calc_nonce"
		id="virt-calc-nonce"
		value="<?php echo esc_attr( wp_create_nonce( 'virt-calc-shipping' ) ); ?>">
	
	<input
		type="hidden"
		name="virt_blog_id"
		id="virt-blog-id"
		value="<?php echo esc_attr( get_current_blog_id() ); ?>" />

	<div id="virt-calc-response"></div>
</div>

<?php
/**
 * Template html from Wizard install
 *
 * @package Virtuaria\Correios
 * @since   1.8.2
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="welcome-modal">
	<div class="welcome-modal-content">
		<img src="<?php echo esc_url( VIRTUARIA_CORREIOS_URL ); ?>admin/images/icon-256x256.png" alt="Plugin Logo" id="plugin-logo">
		<span class="close-modal dashicons dashicons-no-alt"></span>
		<h2 class="welcome-title">
			Bem-vindo ao Virtuaria Correios
		</h2>
		<p class="welcome-message">
			Facilite sua configuração no Virtuaria Correios com a importação automática! Listaremos os métodos de entrega dos Correios ativos em outros plugins, economizando seu tempo e garantindo que sua loja mantenha as mesmas opções de frete configuradas anteriormente.
		</p>
		<div class="allowed-setting-import">
			<?php
			foreach ( $available_imports as $method ) :
				?>
				<div class="allowed-plugin <?php echo esc_attr( $method['class'] ); ?>">
					<div class="plugin-info">
						<label class="plugin-title">
							<?php echo esc_html( $method['plugin_title'] ); ?>
						</label>
						<p class="plugin-methods">
							Encontramos <?php echo esc_html( $method['count_itens'] ); ?> métodos de entrega ativos para importação
						</p>
					</div>
					<div class="import-options">
						<button
							class="allow-import <?php echo $method['count_itens'] > 0 ? 'checked' : ''; ?>"
							role="switch"
							type="button"
							aria-checked="true"
							aria-required="false"
							data-state="checked"
							<?php echo $method['count_itens'] > 0 ? '' : 'disabled'; ?>
							value="on">
							<span data-state="checked" class="switch"></span>
						</button>
					</div>
				</div>
				<?php
			endforeach;
			?>
		</div>
		<div class="welcome-actions">
			<div class="checkbox-wrapper checked">
				<div class="checkbox-border bg-white border-transparent shadow-sm relative h-5 w-5 rounded-[3px]">
					<div class="check-icon">
						<input
							type="checkbox"
							name="inactive_imported_methods"
							id="inactive_imported_methods"
							value="yes" class="hidden" checked />
					</div>
				</div>
				<label for="inactive_imported_methods">
					Desativar os métodos que foram importados (recomendado)
				</label>
			</div>
			<button
				class="welcome-button import-button"
			>Iniciar importação</button>

			<a href="#" class="jump-step">
				Pular e configurar manualmente
			</a>
		</div>
	</div>
</div>
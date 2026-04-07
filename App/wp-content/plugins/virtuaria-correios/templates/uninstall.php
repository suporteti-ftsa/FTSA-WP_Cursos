<?php
/**
 * Template to unistall form.
 *
 * @package virtuaria/integrations/correios
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="virt-correios-modal" class="modal">
	<div class="modal-content">
		<span class="close">&times;</span>
		<h2>Nos Ajude a Melhorar!</h2>
		<p class="desc">
			Lamentamos ver você partir. Sua opinião é essencial para melhorarmos nosso plugin e oferecer a melhor experiência possível.
		</p>
		<form id="uninstallForm" action="https://virtuaria.com.br/uninstall-feedback" method="POST">
			<label for="motivo">Por favor, conte-nos o motivo de sua decisão:</label>
			<select name="motivo" id="motivo" required>
				<option value="">Selecione um motivo</option>
				<option value="Não atende minhas necessidades">Não atende minhas necessidades</option>
				<option value="Problemas técnicos">Problemas técnicos</option>
				<option value="Interface difícil de usar">Interface difícil de usar</option>
				<option value="Encontrou uma alternativa melhor">Encontrou uma alternativa melhor</option>
				<option value="Outro">Outro</option>
			</select>

			<label for="email">E-mail para Suporte (opcional):</label>
			<input type="email" name="email" id="email">

			<label for="comentarios">Comentários adicionais (opcional):</label>
			<textarea name="comentarios" id="comentarios" rows="4" placeholder="Em caso de erro, cole aqui o log e descrição do problema."></textarea>

			<div class="actions">
				<a href="https://wordpress.org/support/plugin/virtuaria-correios/" class="wordpress-action" target="_blank">
					<span class="dashicons dashicons-wordpress"></span>Fórum Wordpress
				</a>
				<button type="submit" id="enviar-feedback">Enviar Feedback</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=virtuaria-correios-diagnostics' ) ); ?>" class="diagnostic">
				<span class="dashicons dashicons-desktop"></span>Diagnóstico
				</a>
			</div>
		</form>
	</div>
</div>

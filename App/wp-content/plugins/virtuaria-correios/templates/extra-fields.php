<?php
/**
 * Template to extra fields.
 *
 * @package virtuaria/integrations/correios.
 */

defined( 'ABSPATH' ) || exit;
?>
<tr class="separator"></tr>
<tr valign="top">
	<th scope="row" class="titledesc section">
		Configurações
	</th>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_person_type">Exibir Tipo de Pessoa:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Exibir Tipo de Pessoa:</span></legend>
			<select
				name="woocommerce_virt_correios_person_type"
				id="woocommerce_virt_correios_person_type">
				<option <?php echo isset( $options['person_type'] ) ? selected( 'pf', $options['person_type'], false ) : ''; ?> value="pf">Pessoa Física apenas</option>
				<option <?php echo isset( $options['person_type'] ) ? selected( 'both', $options['person_type'], false ) : ''; ?> value="both">Pessoa Física e Pessoa Jurídica</option>
				<option <?php echo isset( $options['person_type'] ) ? selected( 'pj', $options['person_type'], false ) : ''; ?> value="pj">Pessoa Jurídica apenas</option>
				<option <?php echo isset( $options['person_type'] ) ? selected( 'none', $options['person_type'], false ) : ''; ?> value="none">Nenhum</option>
			</select>
			<p class="description">
			Pessoa Física habilita o campo CPF e Pessoa Jurídica habilita o campo CNPJ.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top" class="conditional-field only-both only-pf">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_rg">Exibir RG:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Exibir RG:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_rg"
				id="woocommerce_virt_correios_rg"
				value="yes"
				<?php isset( $options['rg'] ) ? checked( $options['rg'], 'yes' ) : ''; ?> />
			<p class="description">
				Exibir o campo de RG nas informações de cobrança.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top" class="conditional-field only-both only-pj">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_ie">Exibir Inscrição Estadual:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Exibir Inscrição Estadual:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_ie"
				id="woocommerce_virt_correios_ie"
				value="yes"
				<?php isset( $options['ie'] ) ? checked( $options['ie'], 'yes' ) : ''; ?> />
			<p class="description">
				Exibir o campo de Inscrição Estadual nas informações de cobrança.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_birthday_date">Exibir Data de Nascimento:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Exibir Data de Nascimento:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_birthday_date"
				id="woocommerce_virt_correios_birthday_date"
				value="yes"
				<?php isset( $options['birthday_date'] ) ? checked( $options['birthday_date'], 'yes' ) : ''; ?> />
			<p class="description">
				Exibir o campo de data de nascimento nas informações de cobrança.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_gender">Exibir Gênero:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Exibir Gênero:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_gender"
				id="woocommerce_virt_correios_gender"
				value="yes"
				<?php isset( $options['gender'] ) ? checked( $options['gender'], 'yes' ) : ''; ?> />
			<p class="description">
				Exibir o campo de gênero nas informações de cobrança.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_cell_phone">Exibir Celular:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Exibir Celular:</span></legend>
			<select
				name="woocommerce_virt_correios_cell_phone"
				id="woocommerce_virt_correios_cell_phone">
				<option <?php echo isset( $options['cell_phone'] ) ? selected( 'opcional', $options['cell_phone'], false ) : ''; ?> value="opcional">Exibir o campo de celular como opcional</option>
				<option <?php echo isset( $options['cell_phone'] ) ? selected( 'required', $options['cell_phone'], false ) : ''; ?> value="required">Exibir o campo de celular como obrigatório</option>
				<option <?php echo isset( $options['cell_phone'] ) ? selected( 'cel', $options['cell_phone'], false ) : ''; ?> value="cel">Mudar o título do campo de telefone para "Celular"</option>
				<option <?php echo isset( $options['cell_phone'] ) ? selected( 'disabled', $options['cell_phone'], false ) : ''; ?> value="disabled">Desativado</option>
			</select>
		</fieldset>
	</td>
</tr>
<tr class="separator"></tr>
<tr valign="top">
	<th scope="row" class="titledesc section">
		Design
	</th>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_style_field">Estilo dos campos:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Estilo dos campos:</span></legend>
			<select
				name="woocommerce_virt_correios_style_field"
				id="woocommerce_virt_correios_style_field">
				<option <?php echo isset( $options['style_field'] ) ? selected( 'wide', $options['style_field'], false ) : ''; ?> value="wide">Padrão (campos largos)</option>
				<option <?php echo isset( $options['style_field'] ) ? selected( 'side_by_side', $options['style_field'], false ) : ''; ?> value="side_by_side">Lado a lado</option>
			</select>
			<p class="description">
			Escolha o estilo dos campos. Nota: Use o Padrão se estiver tendo problemas com a forma com que os campos são exibidos no seu site.
			</p>
		</fieldset>
	</td>
</tr>
<tr class="separator"></tr>
<tr valign="top">
	<th scope="row" class="titledesc section">
		Opções de Validação
	</th>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_mask">Máscara de Campos:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Validar CPF:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_mask"
				id="woocommerce_virt_correios_mask"
				value="yes"
				<?php isset( $options['mask'] ) ? checked( $options['mask'], 'yes' ) : ''; ?> />
			<p class="description">
				Adicionar máscaras de preenchimento para os campos de CPF, CNPJ, Data de Nascimento, Telefone e Celular.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_validate_cpf">Validar CPF:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Validar CPF:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_validate_cpf"
				id="woocommerce_virt_correios_validate_cpf"
				value="yes"
				<?php isset( $options['validate_cpf'] ) ? checked( $options['validate_cpf'], 'yes' ) : ''; ?> />
			<p class="description">
				Verificar se o CPF é válido.
			</p>
		</fieldset>
	</td>
</tr>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="woocommerce_virt_correios_validate_cnpj">Validar CNPJ:</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span>Validar CNPJ:</span></legend>
			<input
				type="checkbox"
				name="woocommerce_virt_correios_validate_cnpj"
				id="woocommerce_virt_correios_validate_cnpj"
				value="yes"
				<?php isset( $options['validate_cnpj'] ) ? checked( $options['validate_cnpj'], 'yes' ) : ''; ?> />
			<p class="description">
				Verificar se o CNPJ é válido.
			</p>
		</fieldset>
	</td>
</tr>

<div id="my-export-options" class="my-block">
    <div class="wc-oe-header">
		<?php esc_html_e( 'Export date range', 'woocommerce-order-export' ) ?>:
    </div>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( !isset( $settings['export_rule'] ) || ( $settings['export_rule'] == 'none' ) ) ? 'checked' : '' ?>
               value="none">
		<?php esc_html_e( 'None', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'today' ) ) ? 'checked' : '' ?>
               value="today">
		<?php esc_html_e( 'Today', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'last_day' ) ) ? 'checked' : '' ?>
               value="last_day">
		<?php esc_html_e( 'Yesterday', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'this_week' ) ) ? 'checked' : '' ?>
               value="this_week">
		<?php esc_html_e( 'Current week', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'this_month' ) ) ? 'checked' : '' ?>
               value="this_month">
		<?php esc_html_e( 'Current month', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'last_week' ) ) ? 'checked' : '' ?>
               value="last_week">
		<?php esc_html_e( 'Last week', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'last_month' ) ) ? 'checked' : '' ?>
               value="last_month">
		<?php esc_html_e( 'Last month', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'last_quarter' ) ) ? 'checked' : '' ?>
               value="last_quarter">
		<?php esc_html_e( 'Last quarter', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'this_year' ) ) ? 'checked' : '' ?>
               value="this_year">
		<?php esc_html_e( 'This year', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <!-- Modified By Hayato -->
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'last_year' ) ) ? 'checked' : '' ?>
               value="last_year">
		<?php esc_html_e( 'Last year', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <!-- End of Modified -->
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'custom' ) ) ? 'checked' : '' ?>
               value="custom">
		<?php
		$input_days = isset( $settings['export_rule_custom'] ) ? $settings['export_rule_custom'] : 3;
		$input_days = '<input class="width-15" name="settings[export_rule_custom]" value="' . esc_attr($input_days) . '">';
		?>
		<?php
        /* translators: Information about the selected last days */
        echo sprintf( esc_html__( 'Last %s days', 'woocommerce-order-export' ), wp_kses( $input_days, array(
            'input' => array(
                'class' => true,
                'name'  => true,
                'value' => true,
            ),
        ))); ?>
    </label>
</div>
<br>

<div class="my-block">
    <div style="display: inline;">
        <span class="wc-oe-header"><?php esc_html_e( 'Title', 'woocommerce-order-export' ) ?></span>
        <input type=text style="width: 91.9%;" id="settings_title" name="settings[title]"
               value='<?php echo( esc_attr(isset( $settings['title'] ) ? $settings['title'] : '') ) ?>'>
    </div>
</div>
<br>
<div class="my-block line-height__2">
    <div>
        <span class="wc-oe-header"
              title="<?php esc_html_e( 'Empty means "any status"', 'woocommerce-order-export' ) ?>"><?php esc_html_e( 'From status',
		        'woocommerce-order-export' ) ?></span>
        <select id="from_status" name="settings[from_status][]" multiple="multiple"
                style="width: 100%; max-width: 25%;">
			<?php foreach ( apply_filters('woe_settings_order_statuses', wc_get_order_statuses()) as $i => $status ) { ?>
                <option value="<?php echo esc_attr($i) ?>" <?php if ( in_array( $i, $settings['from_status'] ) ) {
					echo 'selected';
				} ?>><?php echo esc_html($status); ?></option>
			<?php } ?>
        </select>
    </div>
    <div>
        <span class="wc-oe-header"
              title="<?php esc_html_e( 'Empty means "any status"', 'woocommerce-order-export' ) ?>"><?php esc_html_e( 'To status',
		        'woocommerce-order-export' ) ?></span>
        <select id="to_status" name="settings[to_status][]" multiple="multiple" style="width: 100%; max-width: 25%;">
			<?php foreach ( apply_filters('woe_settings_order_statuses', wc_get_order_statuses()) as $i => $status ) { ?>
                <option value="<?php echo esc_attr($i); ?>" <?php if ( in_array( $i, $settings['to_status'] ) ) {
					echo 'selected';
				} ?>><?php echo esc_html($status); ?></option>
			<?php } ?>
        </select>
    </div>
	<?php if ( function_exists( "wc_get_logger" ) ) : ?>
        <div>
            <label>
                <input type="hidden" name="settings[log_results]" value=""/>
                <input type="checkbox"
                       name="settings[log_results]" <?php echo ! empty( $settings['log_results'] ) ? 'checked' : '' ?>>
				<?php esc_html_e( 'Log results', 'woocommerce-order-export' ) ?>&nbsp;<a
                        href="admin.php?page=wc-status&tab=logs&source=woocommerce-order-export"
                        target=_blank><?php esc_html_e( 'View logs', 'woocommerce-order-export' ) ?></a>
            </label>
        </div>
	<?php endif; ?>
</div>
<hr>

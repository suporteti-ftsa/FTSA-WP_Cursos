<div class="my-block">
    <div style="display: inline;">
        <span class="wc-oe-header"><?php esc_html_e( 'Title', 'woocommerce-order-export' ) ?></span>
        <input type=text style="width: 91.9%;" id="settings_title" name="settings[title]"
               value='<?php echo( esc_attr(isset( $settings['title'] ) ? $settings['title'] : '') ) ?>'>
    </div>
</div>
<br>
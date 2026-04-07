<span class="my-hide-next "><?php esc_html_e( 'Filter by subscription', 'woocommerce-order-export' ); ?>
<span class="ui-icon ui-icon-triangle-1-s my-icon-triangle"></span></span>
<div id="my-order" class="hide">
    <div><input type="hidden" name="settings[skip_suborders]" value="0"/><label><input type="checkbox"
                                                                                        name="settings[skip_suborders]"
                                                                                        value="1" <?php checked( $settings['skip_suborders'] ) ?> /> <?php esc_html_e( "Don't export child orders",
                'woocommerce-order-export' ) ?></label></div>
    <div><input type="hidden" name="settings[mark_exported_orders]" value="0"/><label><input type="checkbox"
                                                                                                name="settings[mark_exported_orders]"
                                                                                                value="1" <?php checked( $settings['mark_exported_orders'] ) ?> /> <?php esc_html_e( "Mark exported orders",
                'woocommerce-order-export' ) ?></label></div>
    <div><input type="hidden" name="settings[export_unmarked_orders]" value="0"/><label><input
                    type="checkbox" name="settings[export_unmarked_orders]"
                    value="1" <?php checked( $settings['export_unmarked_orders'] ) ?> /> <?php esc_html_e( "Export unmarked orders only",
                'woocommerce-order-export' ) ?></label></div>
    <span class="wc-oe-header"><?php esc_html_e( 'Subscription statuses', 'woocommerce-order-export' ); ?></span>
    <select id="statuses" class="select2-i18n" name="settings[statuses][]" multiple="multiple"
            style="width: 100%; max-width: 25%;">
        <?php foreach (
            apply_filters( 'woe_settings_order_statuses', wc_get_order_statuses() ) as $i => $status
        ) { ?>
            <option value="<?php echo esc_attr($i); ?>" <?php if ( in_array( $i, $settings['statuses'] ) ) {
                echo 'selected';
            } ?>><?php echo esc_html($status); ?></option>
        <?php } ?>
    </select>
    <div>
        <div class="custom-fields__wrapper">
            <div>
                <span class="wc-oe-header"><?php esc_html_e( 'Custom fields', 'woocommerce-order-export' ) ?></span>
            </div>
            <div class="custom-fields__condotion-wrapper custom-fields__condotion-wrapper_position">
                <select id="custom_fields" class="select2-i18n" data-select2-i18n-width="150" style="width: auto;">
                    <?php foreach ( WC_Order_Export_Data_Extractor_UI::get_order_custom_fields() as $cf_name ) { ?>
                        <option><?php echo esc_attr( $cf_name); ?></option>
                    <?php } ?>
                </select>

                <select id="custom_fields_compare" class="select_compare">
                    <option>=</option>
                    <option>&lt;&gt;</option>
                    <option>LIKE</option>
                    <option>&gt;</option>
                    <option>&gt;=</option>
                    <option>&lt;</option>
                    <option>&lt;=</option>
                    <option>NOT SET</option>
                    <option>IS SET</option>
                </select>

                <input type="text" id="text_custom_fields" disabled class="like-input" style="display: none;">
                <button id="add_custom_fields" class="button-secondary"><span
                            class="dashicons dashicons-plus-alt"></span></button>
            </div>
        </div>
        <select id="custom_fields_check" class="select2-i18n" multiple name="settings[order_custom_fields][]"
                style="width: 100%; max-width: 25%;">
            <?php
            if ( $settings['order_custom_fields'] ) {
                foreach ( $settings['order_custom_fields'] as $prod ) {
                    ?>
                    <option selected value="<?php echo esc_attr($prod); ?>"> <?php echo esc_html($prod); ?></option>
                <?php }
            } ?>
        </select>
        <hr />
        <div class="custom-fields__wrapper">
            <span class="wc-oe-header">Start date</span>
            <input type=text class='date' name="settings[sub_start_from_date]" id="sub_start_from_date"
                value='<?php echo esc_attr(! empty($options['show_date_time_picker_for_date_range']) ? $settings['sub_start_from_date']: remove_time_from_date($settings['sub_start_from_date'])) ?>'>
            <?php esc_html_e( 'to', 'woocommerce-order-export' ) ?>
            <input type=text class='date' name="settings[sub_start_to_date]" id="sub_start_to_date"
                value='<?php echo esc_attr(! empty($options['show_date_time_picker_for_date_range']) ? $settings['sub_start_to_date']: remove_time_from_date($settings['sub_start_to_date'])) ?>'>
        </div>
        <br />
        <div class="custom-fields__wrapper">
            <span class="wc-oe-header">End date</span>
            <input type=text class='date' name="settings[sub_end_from_date]" id="sub_end_from_date"
                value='<?php echo esc_attr(! empty($options['show_date_time_picker_for_date_range']) ? $settings['sub_end_from_date']: remove_time_from_date($settings['sub_end_from_date'])) ?>'>
            <?php esc_html_e( 'to', 'woocommerce-order-export' ) ?>
            <input type=text class='date' name="settings[sub_end_to_date]" id="sub_end_to_date"
                value='<?php echo esc_attr(! empty($options['show_date_time_picker_for_date_range']) ? $settings['sub_end_to_date']: remove_time_from_date($settings['sub_end_to_date'])) ?>'>
        
        </div>
        <br />
        <div class="custom-fields__wrapper">
            <span class="wc-oe-header">Next payment date</span>
            <input type=text class='date' name="settings[sub_next_paym_from_date]" id="sub_next_paym_from_date"
                value='<?php echo esc_attr(! empty($options['show_date_time_picker_for_date_range']) ? $settings['sub_next_paym_from_date']: remove_time_from_date($settings['sub_next_paym_from_date'])) ?>'>
            <?php esc_html_e( 'to', 'woocommerce-order-export' ) ?>
            <input type=text class='date' name="settings[sub_next_paym_to_date]" id="sub_next_paym_to_date"
                value='<?php echo esc_attr(! empty($options['show_date_time_picker_for_date_range']) ? $settings['sub_next_paym_to_date']: remove_time_from_date($settings['sub_next_paym_to_date'])) ?>'>
        
        </div>
    </div>
</div>

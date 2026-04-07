<div class="my-block">
    <div style="display: inline;">
        <span class="wc-oe-header"><?php esc_html_e( 'Title', 'woocommerce-order-export' ) ?></span>
        <input type=text style="width: 91.9%;" id="settings_title" name="settings[title]"
               value='<?php echo( esc_attr( isset( $settings['title'] ) ? $settings['title'] : '' ) ) ?>'>
    </div>
    <div>
        <label>
            <input type="hidden" name="settings[skip_empty_file]" value=""/>
            <input type="checkbox"
                   name="settings[skip_empty_file]" <?php echo ! empty( $settings['skip_empty_file'] ) ? 'checked' : '' ?>>
			<?php esc_html_e( "Don't send empty file", 'woocommerce-order-export' ) ?>
        </label>
    </div>
</div>
<br>
<div id="my-shedule-days" class="my-block">
    <div class="wc-oe-header"><?php esc_html_e( 'Schedule', 'woocommerce-order-export' ) ?></div>
    <div id="d-schedule-1">
        <input type="radio" name="settings[schedule][type]" value="schedule-1" id="schedule-1"
               class="wc-oe-schedule-type" <?php echo ( ( isset( $settings['schedule'] ) && isset( $settings['schedule']['type'] ) && $settings['schedule']['type'] == 'schedule-1' ) || ! isset( $settings['schedule'] ) ) ? 'checked' : '' ?>>
        <div class="block d-scheduled-block">

            <div class="weekday">
				<?php foreach ( WC_Order_Export_Pro_Manage::get_days() as $kday => $day ) : ?>
                    <label>
						<?php echo esc_html($day); ?>
                        <input type="checkbox"
                               name="settings[schedule][weekday][<?php echo esc_attr($kday); ?>]" <?php echo isset( $settings['schedule']['weekday'][ $kday ] ) ? 'checked' : '' ?>>
                    </label>
				<?php endforeach; ?>
            </div>
            <div class="">
                <label style="margin-left: 10px;"><?php esc_html_e( 'Run at', 'woocommerce-order-export' ) ?>:
                    <select name="settings[schedule][run_at]" style="width: 80px">
						<?php
						for ( $i = 0; $i <= 23; $i ++ ) :
							$h = ( $i < 10 ) ? '0' . $i : $i;

							for ( $m = 0; $m < 60; $m += 5 ) :
								$time = "$h:" . ( $m < 10 ? "0" . $m : $m );
								?>
                                <option <?php echo ( isset( $settings['schedule']['run_at'] ) and $time == $settings['schedule']['run_at'] ) ? 'selected' : '' ?>>
									<?php
									echo esc_html($time);
									?>
                                </option>
							<?php endfor; ?>
						<?php endfor; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>
    <div class="weo_clearfix"></div>

    <div id="d-schedule-2" class="padding-bottom-10">
        <input type="radio" name="settings[schedule][type]" value="schedule-2" id="schedule-2"
               class="wc-oe-schedule-type" <?php echo ( isset( $settings['schedule'] ) && isset( $settings['schedule']['type'] ) && $settings['schedule']['type'] == 'schedule-2' ) ? 'checked' : '' ?>>
        <div class="block d-scheduled-block">
            <select class="wc_oe-select-interval" name="settings[schedule][interval]">
                <option value="-1"><?php esc_html_e( 'Choose', 'woocommerce-order-export' ) ?></option>
                <option value="custom" <?php echo ( isset( $settings['schedule']['interval'] ) AND $settings['schedule']['interval'] == 'custom' ) ? 'selected' : '' ?>><?php esc_html_e( 'Custom',
						'woocommerce-order-export' ) ?></option>
				<?php
				$schedules = wp_get_schedules();
				foreach ( $schedules as $name => $schedule ) :
					?>
                    <option value="<?php echo esc_attr($name); ?>" <?php echo ( isset( $settings['schedule']['interval'] ) AND $settings['schedule']['interval'] == $name ) ? 'selected' : '' ?>>
						<?php echo esc_html($schedule['display']); ?>
                    </option>
				<?php endforeach; ?>
                <option value="first_day_month" <?php echo ( isset( $settings['schedule']['interval'] ) AND $settings['schedule']['interval'] == 'first_day_month' ) ? 'selected' : '' ?>><?php esc_html_e( 'On the 1st day of the month',
						'woocommerce-order-export' ) ?></option>
                <option value="first_day_quarter" <?php echo ( isset( $settings['schedule']['interval'] ) AND $settings['schedule']['interval'] == 'first_day_quarter' ) ? 'selected' : '' ?>><?php esc_html_e( 'On the 1st day of the quarter',
						'woocommerce-order-export' ) ?></option>
            </select>
            <label id="custom_interval">
				<?php esc_html_e( 'interval (min)', 'woocommerce-order-export' ) ?>:
                <input name="settings[schedule][custom_interval]"
                       value="<?php echo esc_attr(isset( $settings['schedule']['custom_interval'] ) ? $settings['schedule']['custom_interval'] : '') ?>"
                       type="text" class="input-mins-custom-interval">
            </label>
        </div>
    </div>
    <div id="d-schedule-3" class="padding-bottom-10">
        <input type="radio" name="settings[schedule][type]" value="schedule-3" id="schedule-3"
               class="wc-oe-schedule-type" <?php echo ( isset( $settings['schedule'] ) and $settings['schedule']['type'] == 'schedule-3' ) ? 'checked' : '' ?>>
        <input type="hidden" name="settings[schedule][times]"
               value="<?php echo esc_attr(isset( $settings['schedule']['times'] ) ? $settings['schedule']['times'] : '') ?>">
        <div class="block d-scheduled-block">
            <div class="input-times"></div>

            <select style="width:80px" class="wc_oe-select-weekday">
				<?php
				foreach ( WC_Order_Export_Pro_Manage::get_days() as $kday => $day ) :
					?>
                    <option value="<?php echo esc_attr($kday); ?>">
						<?php echo esc_html($day); ?>
                    </option>
				<?php endforeach; ?>
            </select>

            <select style="width: 80px" class="wc_oe-select-time">
				<?php
				for ( $i = 0; $i <= 23; $i ++ ) :
					$h = ( $i < 10 ) ? '0' . $i : $i;

					for ( $m = 0; $m < 60; $m += 5 ) :
						$time = "$h:" . ( $m < 10 ? "0" . $m : $m );
						?>
                        <option <?php echo ( isset( $settings['schedule']['run_at'] ) and $time == $settings['schedule']['run_at'] ) ? 'selected' : '' ?>>
							<?php
							echo esc_html($time);
							?>
                        </option>
					<?php endfor; ?>
				<?php endfor; ?>
            </select>

            <input type="button" class="button-secondary btn-add"
                   value="<?php esc_html_e( 'Add', 'woocommerce-order-export' ) ?>">
        </div>
    </div>
    <div id="d-schedule-4" class="padding-bottom-10">

        <input type="radio" name="settings[schedule][type]" value="schedule-4" id="schedule-4"
               class="wc-oe-schedule-type" <?php echo ( isset( $settings['schedule'] ) && isset( $settings['schedule']['type'] ) && $settings['schedule']['type'] == 'schedule-4' ) ? 'checked' : '' ?>>

        <input type="hidden" name="settings[schedule][date_times]"
               value="<?php echo esc_attr(isset( $settings['schedule']['date_times'] ) ? $settings['schedule']['date_times'] : '') ?>">

        <div class="block d-scheduled-block">

            <div class="input-date-times"></div>

            <span class="date-picker">
                                                <input type="text" value="<?php echo esc_attr(gmdate( 'Y-m-d' )) ?>"
                                                       class="datetimes-date" readonly="">
                                            </span>

            <select style="width: 80px" class="wc_oe-select-time">
				<?php
				for ( $i = 0; $i <= 23; $i ++ ) :
					$h = ( $i < 10 ) ? '0' . $i : $i;

					for ( $m = 0; $m < 60; $m += 5 ) :
						$time = "$h:" . ( $m < 10 ? "0" . $m : $m );
						?>
                        <option <?php echo ( isset( $settings['schedule']['run_at'] ) and $time == $settings['schedule']['run_at'] ) ? 'selected' : '' ?>>
							<?php
							echo esc_html($time);
							?>
                        </option>
					<?php endfor; ?>
				<?php endfor; ?>
            </select>

            <input type="button" class="button-secondary btn-add"
                   value="<?php esc_html_e( 'Add', 'woocommerce-order-export' ) ?>">
        </div>
    </div>

    <div class="weo_clearfix"></div>
    <div id="d-schedule-5" class="padding-bottom-10">
		<?php if ( version_compare( PHP_VERSION, '7.0', '>=' ) ) : ?>
            <input type="radio" name="settings[schedule][type]" value="schedule-5" id="schedule-5"
                   style="margin-top: 4px;"
                   class="wc-oe-schedule-type" <?php echo ( isset( $settings['schedule'] ) && isset( $settings['schedule']['type'] ) && $settings['schedule']['type'] == 'schedule-5' ) ? 'checked' : '' ?>>
            <div class="block d-scheduled-block">
				<?php esc_html_e( 'Cron expression', 'woocommerce-order-export' ) ?>:
                <input type="text" name="settings[schedule][crontab]" len=20
                       value="<?php echo esc_attr(isset( $settings['schedule']['crontab'] ) ? $settings['schedule']['crontab'] : '0 * * * *') ?>">
            </div>
		<?php else: ?>
            <input type="radio" name="settings[schedule][type]" value="schedule-5" id="schedule-5" style="margin-top: 0"
                   class="wc-oe-schedule-type" disabled>
			<?php esc_html_e( 'Cron expression requires PHP 7.0!', 'woocommerce-order-export' ) ?>
		<?php endif; ?>
    </div>

	<?php if ( function_exists( "wc_get_logger" ) ) : ?>
        <div id="d-schedule-6" class="padding-bottom-10">
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
<br>
<div id="my-export-options" class="my-block">
    <div class="wc-oe-header">
		<?php esc_html_e( 'Export date range', 'woocommerce-order-export' ) ?>:
    </div>
    <label>
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( isset( $settings['export_rule'] ) && ( $settings['export_rule'] == 'none' ) ) ? 'checked' : '' ?>
               value="none">
		<?php esc_html_e( 'None', 'woocommerce-order-export' ) ?>
    </label>
    <br>
    <label title="<?php esc_html_e( 'This option doesn\'t applied to Preview or manual Export buttons',
		'woocommerce-order-export' ) ?>">
        <input type="radio" name="settings[export_rule]"
               class="width-100" <?php echo ( ! isset( $settings['export_rule'] ) || ( $settings['export_rule'] == 'last_run' ) ) ? 'checked' : '' ?>
               value="last_run">
		<?php esc_html_e( 'Since last run of this job', 'woocommerce-order-export' ) ?>
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
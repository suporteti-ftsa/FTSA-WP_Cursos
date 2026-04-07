<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$url                = admin_url( 'admin-ajax.php?action=order_exporter_run&method=run_cron_jobs&key=' . $settings['cron_key'] );
$sample_link        = '<b>curl "http://site.com/...&key=xyz"</b>';
$max_products_input = '<input type="text" name="autocomplete_products_max" size="3" value="' . esc_attr($settings['autocomplete_products_max']) . '">';
$step_input         = '<input type="text" name="ajax_orders_per_step" size="3" value="' . esc_attr($settings['ajax_orders_per_step']) . '">';

$sections = array(
	'general'   => __( 'General', 'woocommerce-order-export' ),
	'interface' => __( 'Interface', 'woocommerce-order-export' ),
    'html'      => __( 'HTML format', 'woocommerce-order-export' ),
	'jobs'      => __( 'Jobs', 'woocommerce-order-export' ),
	'failed'    => __( 'Failed exports', 'woocommerce-order-export' ),
    'zapier'    => __( 'Zapier', 'woocommerce-order-export' ),
);
?>
<ul class="subsubsub woe-settings-subsubsub">
	<?php foreach ( $sections as $id => $section_title ): ?>
        <li>
            <a class="section_choice"
               data-section="<?php echo esc_attr($id); ?>" href="#section=<?php echo esc_html($id); ?>">
				<?php echo esc_html($section_title); ?>
            </a>
			<?php echo( end( $sections ) == $section_title ? '' : ' | ' ); ?>
        </li>
	<?php endforeach; ?>
</ul>

<div class="weo_clearfix"></div>
<form id="settings-form">

	<?php wp_nonce_field( 'woe_nonce', 'woe_nonce' ); ?>

    <input type="hidden" name="action" value="order_exporter">
    <input type="hidden" name="method" value="save_settings">
    <input type="hidden" name="tab" value="settings">

    <div class="section" id="general_section">
        <h2><?php esc_html_e( 'General', 'woocommerce-order-export' ) ?></h2>

        <table class="form-table">
            <tbody>
            <tr>
                <td>
                    <label>
						<?php esc_html_e( 'Show tab by default', 'woocommerce-order-export' ) ?>
                        <select style="width: auto;" name="default_tab">
                            <option value="export" <?php selected( $settings['default_tab'],
								'export' ) ?>><?php esc_html_e( 'Export now', 'woocommerce-order-export' ) ?></option>
                            <option value="profiles" <?php selected( $settings['default_tab'],
								'profiles' ) ?>><?php esc_html_e( 'Profiles', 'woocommerce-order-export' ) ?></option>
                            <option value="order_actions" <?php selected( $settings['default_tab'],
								'order_actions' ) ?>><?php esc_html_e( 'Status change jobs',
									'woocommerce-order-export' ) ?></option>
                            <option value="schedules" <?php selected( $settings['default_tab'],
								'schedules' ) ?>><?php esc_html_e( 'Scheduled jobs', 'woocommerce-order-export' ) ?></option>
                        </select>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
						<?php
                        /* translators: The message about showing the first products in the autocomplete */
                        echo sprintf( esc_html__( 'Show first %s products in autocomplete', 'woocommerce-order-export' ),
                            wp_kses( $max_products_input, array(
                            'input' => array(
                                'class' => true,
                                'name'  => true,
                                'value' => true,
                            )))); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="show_all_items_in_filters" value="0">
                        <input type="checkbox" name="show_all_items_in_filters"
                               value="1" <?php checked( $settings['show_all_items_in_filters'] ) ?>>
			            <?php esc_html_e( 'Show all products/categories in filters', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="apply_filters_to_bulk_actions" value="0">
                        <input type="checkbox" name="apply_filters_to_bulk_actions"
                               value="1" <?php checked( $settings['apply_filters_to_bulk_actions'] ) ?>>
			            <?php esc_html_e( 'Apply filters to export via Bulk Actions', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
						<?php
                        /* translators: AJAX progressbar exports */
                        echo sprintf( esc_html__( 'AJAX progressbar exports %s orders per step',
							'woocommerce-order-export' ), wp_kses( $step_input, array(
                            'input' => array(
                                'class' => true,
                                'name'  => true,
                                'value' => true,
                            )))); ?>
                    </label>
                </td>
            </tr>
                <td>
                    <label>
			<?php esc_html_e( 'Date range for "Export Now"', 'woocommerce-order-export' ) ?>
			<select name="default_date_range_for_export_now">
			    <?php foreach (WOE_Helper_DateRangeExportNow::get_select_list() as $value => $label): ?>
				<option value="<?php echo esc_attr($value) ?>" <?php selected( $settings['default_date_range_for_export_now'], $value ) ?>>
				    <?php echo esc_html($label);     ?>
				</option>
			    <?php endforeach; ?>
			</select>
                    </label>
                </td>
            </tr>
            </tbody>
        </table>

    </div>

    <div class="section" id="interface_section">
        <h2><?php esc_html_e( 'Interface', 'woocommerce-order-export' ) ?></h2>

        <table class="form-table">
            <tbody>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="show_export_status_column" value="0">
                        <input type="checkbox" name="show_export_status_column"
                               value="1" <?php checked( $settings['show_export_status_column'] ) ?>>
						<?php esc_html_e( 'Show column "Export Status" in order list', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="show_export_actions_in_bulk" value="0">
                        <input type="checkbox" name="show_export_actions_in_bulk"
                               value="1" <?php checked( $settings['show_export_actions_in_bulk'] ) ?>>
						<?php esc_html_e( 'Add "Mark/unmark exported" to bulk actions in order list',
							'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="show_export_in_status_change_job" value="0">
                        <input type="checkbox" name="show_export_in_status_change_job"
                               value="1" <?php checked( $settings['show_export_in_status_change_job'] ) ?>>
						<?php esc_html_e( 'Allow mass export for "Status Change" jobs', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
	            <td>
		            <label>
			            <input type="hidden" name="show_date_time_picker_for_date_range" value="0">
			            <input type="checkbox" name="show_date_time_picker_for_date_range"
			                   value="1" <?php checked( $settings['show_date_time_picker_for_date_range'] ) ?>>
			            <?php esc_html_e( 'Show time fields for filter "Date Range"', 'woocommerce-order-export' ) ?>
		            </label>
	            </td>
            </tr>
            <tr>
	            <td>
		            <label>
			            <input type="hidden" name="show_destination_in_profile" value="0">
			            <input type="checkbox" name="show_destination_in_profile"
			                   value="1" <?php checked( $settings['show_destination_in_profile'] ) ?>>
			            <?php esc_html_e( 'Support "Destinations" for profiles', 'woocommerce-order-export' ) ?>
		            </label>
	            </td>
            </tr>
            <tr>
	            <td>
		            <label>
			            <input type="hidden" name="display_profiles_export_date_range" value="0">
			            <input type="checkbox" name="display_profiles_export_date_range"
			                   value="1" <?php checked( $settings['display_profiles_export_date_range'] ) ?>>
			            <?php esc_html_e( 'Show "Export date range" for profiles', 'woocommerce-order-export' ) ?>
		            </label>
	            </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="section" id="jobs_section">
        <h2><?php esc_html_e( 'Jobs', 'woocommerce-order-export' ) ?></h2>

        <table class="form-table">
            <tbody>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="cron_tasks_active" value="0">
                        <input type="checkbox" name="cron_tasks_active"
                               value="1" <?php checked( $settings['cron_tasks_active'] ) ?>>
						<?php esc_html_e( 'Activate scheduled jobs', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="dont_log_empty_cron_task" value="0">
                        <input type="checkbox" name="dont_log_empty_cron_task"
                               value="1" <?php checked( $settings['dont_log_empty_cron_task'] ) ?>>
						<?php esc_html_e( "Don't record jobs in log if nothing to export", 'woocommerce-order-export' ) ?>
                    </label>
					<br>
					<i><?php echo esc_html__( 'To avoid unnecessary log files in section >Woocommerce>Status>Logs', 'woocommerce-order-export' ) ?></i>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="status_change_use_queue" value="0">
                        <input type="checkbox" name="status_change_use_queue"
                               value="1" <?php checked( $settings['status_change_use_queue'] ) ?>>
						<?php esc_html_e( '"Status Change" jobs use WP Cron queue', 'woocommerce-order-export' ) ?>
                    </label>
                    <br>
                    <i><?php echo esc_html__( 'Turn on only if you use a lot of "Status Change" jobs and they slow down order processing!', 'woocommerce-order-export' ) ?></i>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
						<?php esc_html_e( 'Button "Test" sends', 'woocommerce-order-export' ) ?>
                        <select style="width: auto;" name="limit_button_test">
                            <option value="1" <?php selected( $settings['limit_button_test'],
								'1' ) ?>><?php esc_html_e( 'First suitable order', 'woocommerce-order-export' ) ?></option>
                            <option value="0" <?php selected( $settings['limit_button_test'],
								'0' ) ?>><?php esc_html_e( 'All suitable orders', 'woocommerce-order-export' ) ?></option>
                        </select>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
						<?php esc_html_e( 'Cron url', 'woocommerce-order-export' ) ?>
                        <a id="cron-url" href="<?php echo esc_url($url); ?>"
                           data-value="<?php echo esc_url(admin_url( 'admin-ajax.php?action=order_exporter_run&method=run_cron_jobs&key=' )); ?>"
                           target=_blank><?php echo esc_url($url); ?></a>
                        <input type="hidden" name="cron_key" readonly size="4" id="cron-key"
                               value="<?php echo esc_attr($settings['cron_key']) ?>">
                        <br>

                        <i><?php
                                /* translators: The message about adding as a link is only in case there are problems with WP cron. */
                                echo sprintf( esc_html__( 'Schedule it as %s only if you have problem with WP cron!',
								'woocommerce-order-export' ), wp_kses_post($sample_link) ) ?></i>
                        <br>
                        <button class="button-secondary" id="generate-new-key"><?php esc_html_e( 'Generate new key',
								'woocommerce-order-export' ) ?></button>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
						<?php esc_html_e( 'String to identify IPN call', 'woocommerce-order-export' ) ?>
                        <input type="text" name="ipn_url" value="<?php echo esc_attr($settings['ipn_url']) ?>">
                    </label>
                    <br>
					<?php esc_html_e( 'use | if you must put many values', 'woocommerce-order-export' ) ?>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="section" id="failed_section">
        <h2><?php esc_html_e( 'Failed exports', 'woocommerce-order-export' ) ?></h2>

        <table class="form-table">
            <tbody>
	    <tr>
                <td>
                    <label>
                        <input type="hidden" name="notify_failed_jobs" value="0">
                        <input type="checkbox" name="notify_failed_jobs"
                               value="1" <?php checked( $settings['notify_failed_jobs'] ) ?>>
						<?php esc_html_e( 'Notify about failed jobs', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
			<?php esc_html_e( 'Email recipients', 'woocommerce-order-export' ) ?>
                        <input type="text" name="notify_failed_jobs_email_recipients" size=80 value="<?php echo esc_attr($settings['notify_failed_jobs_email_recipients']) ?>">
                    </label>
                    <br>
		    <?php esc_html_e( 'comma separated list', 'woocommerce-order-export' ) ?>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
			<?php esc_html_e( 'Email subject', 'woocommerce-order-export' ) ?>
                        <input type="text" placeholder= "<?php esc_html_e( 'Scheduled jobs failed', 'woocommerce-order-export' ) ?>" name="notify_failed_jobs_email_subject" size=60 value="<?php echo esc_attr($settings['notify_failed_jobs_email_subject']) ?>">
                    </label>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="section" id="zapier_section">
        <h2><?php esc_html_e( 'Zapier', 'woocommerce-order-export' ) ?></h2>

        <table class="form-table">
            <tbody>
            <tr>
                <td>
                    <label>
						<?php esc_html_e( 'Zapier API key', 'woocommerce-order-export' ) ?>
                        <input type="text" name="zapier_api_key" id="zapier_api_key"
                               value="<?php echo esc_attr($settings['zapier_api_key']) ?>">
                        <button class="button-secondary" id="generate-new-key-zapier"><?php esc_html_e( 'Generate new key',
								'woocommerce-order-export' ) ?></button>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
						<?php
                        /* translators: A message that the Zapier files will be deleted after a selected number of minutes. */
                        echo sprintf( esc_html__('Zapier files will be deleted after %s minutes', 'woocommerce-order-export' ),  '<input type="text" name="zapier_file_timeout"
						value="'. esc_attr($settings['zapier_file_timeout']) . '">') ?>
                    </label>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div class="section" id="html_section">
        <h2><?php esc_html_e( 'HTML', 'woocommerce-order-export' ) ?></h2>
        <table class="form-table">
            <tbody>
            <tr>
                <td>
                    <label class="default-html-css-label" for="default_html_css">
			            <?php esc_html_e( 'Default HTML css', 'woocommerce-order-export' ) ?>
                    </label>
		            <textarea id="default_html_css" name="default_html_css" rows=5 cols=40><?php echo esc_html($settings['default_html_css']) ?></textarea>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="hidden" name="display_html_report_in_browser" value="0">
                        <input type="checkbox" name="display_html_report_in_browser"
                               value="1" <?php checked( $settings['display_html_report_in_browser'] ) ?>>
						<?php esc_html_e( 'Display HTML report in browser', 'woocommerce-order-export' ) ?>
                    </label>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <p class="submit">
        <button type="submit" id="save-btn" class="button-primary"><?php esc_html_e( 'Save settings',
				'woocommerce-order-export' ) ?></button>
    </p>

    <div id=Settings_updated
         style='display:none;color:green;font-size: 120%;'><?php esc_html_e( "Settings were successfully updated!",
			'woocommerce-order-export' ) ?></div>
    <div id=Settings_error
         style='display:none;color:red;font-size: 120%;'></div>
</form>

<script>
	jQuery( function ( $ ) {
		$( '#generate-new-key' ).click( function ( e ) {
			e.preventDefault();
			var key = Math.random().toString( 36 ).substring( 2, 6 );
			$( '#cron-key' ).val( key );
			$( '#cron-url' ).text( $( '#cron-url' ).data( 'value' ) + key );
			$( '#cron-url' ).attr( 'href', $( '#cron-url' ).data( 'value' ) + key );
		} );

		$( '#generate-new-key-zapier' ).click( function ( e ) {
			e.preventDefault();
			var key = Math.random().toString( 36 ).substring( 2, 10 );
			$( '#zapier_api_key' ).val( key );
		} );

		$( "#settings-form" ).submit( function ( e ) {
			e.preventDefault();
			var data = $( '#settings-form' ).serialize();
			$( '#Settings_updated' ).hide();
			$( '#Settings_error' ).hide();
			$.post( ajaxurl, data, function ( response ) {
                                if (response.error) {
                                    $( '#Settings_error' ).html(response.error);
                                    $( '#Settings_error' ).show().delay( 5000 ).fadeOut();
                                } else {
                                    $( '#Settings_updated' ).show().delay( 5000 ).fadeOut();
                                }
			} );
			return false;
		} );

		$( '.section_choice' ).click( function () {

			$( '.section_choice' ).removeClass( 'active' );
			$( this ).addClass( 'active' );

			$( '.section' ).removeClass( 'active' );
			$( '#' + $( this ).data( 'section' ) + '_section' ).addClass( 'active' );

			$( '#save-btn' ).show();

			window.location.href = $( this ).attr( 'href' );
		} );

		setTimeout( function () {
			if ( window.location.hash.indexOf( 'section' ) !== - 1 ) {
				$( '.section_choice[href="' + window.location.hash + '"]' ).click()
			} else {
				$( '.section_choice' ).first().click()
			}
		}, 0 );
	} );
</script>

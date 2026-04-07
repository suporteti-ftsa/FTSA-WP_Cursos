<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once WOE_PRO_PLUGIN_BASEPATH . '/classes/admin/tabs/tables/class-wc-table-schedules.php';

$t_schedules = new WC_Table_Schedules();
$schedules_bulk_action_run_now_output = get_transient('woe_schedules_bulk_action_run_now_output');
?>
<?php if ($schedules_bulk_action_run_now_output): ?>
    <div class="notice notice-info" style="margin: 15px 0; padding: 12px;">
	<?php echo esc_html(implode('<br><br>', $schedules_bulk_action_run_now_output)); ?>
    </div>
    <?php delete_transient('woe_schedules_bulk_action_run_now_output') ?>
<?php endif; ?>
<?php
if ( false === $this->settings['cron_tasks_active'] ) {
?>
	<div class="notice notice-error" style="margin: 15px 0; padding: 12px;">
		<?php echo esc_html__( 'All scheduled jobs are inactive while option "Activate scheduled jobs" is disabled', 'woocommerce-order-export' ); ?>
	</div>
<?php
}
?>
<div class="tabs-content">
	<?php $t_schedules->output(); ?>
</div>

<script>
	var $tab_name = "<?php echo esc_html($t_schedules->tab_name);?>";
	var woe_active_tab = "<?php echo esc_html($active_tab); ?>";

	jQuery( document ).ready( function ( $ ) {
		$( '#add_schedule' ).click( function () {

		    var post_type = $('.woe-order-post-type').val();

		    document.location = '<?php echo esc_url_raw(admin_url( 'admin.php?page=wc-order-export&tab=schedules&wc_oe=add_schedule' )) ?>' + '&woe_post_type=' + post_type;
		} )

		$( '.btn-trash' ).click( function () {
			var id = $( this ).attr( 'data-id' );
			var f = confirm(
				'<?php esc_attr_e( 'Are you sure you want to DELETE this job?', 'woocommerce-order-export' ) ?>' )
			if ( f ) {
				document.location = '<?php echo esc_url_raw(admin_url( 'admin.php?page=wc-order-export&tab=schedules&wc_oe=delete_schedule&schedule_id=' )) ?>' + id + '&woe_nonce=' + woe_nonce;
			}
		} )
		$( '.btn-export' ).click( function () {
			var id = $( this ).attr( 'data-id' );
			document.location = '<?php echo esc_url_raw(admin_url( 'admin-ajax.php?action=order_exporter&method=run_one_job&schedule=' )) ?>' + id + '&tab=' + woe_active_tab + '&woe_nonce=' + woe_nonce;
		} )
		$( '.btn-edit' ).click( function () {
			var id = $( this ).attr( 'data-id' );
			document.location = '<?php echo esc_url_raw(admin_url( 'admin.php?page=wc-order-export&tab=schedules&wc_oe=edit_schedule&schedule_id=' )) ?>' + id;
		} )
		$( '.btn-clone' ).click( function () {
			var id = $( this ).attr( 'data-id' );
			var f = confirm(
				'<?php esc_attr_e( 'Are you sure you want to CLONE this job?', 'woocommerce-order-export' ) ?>' )
			if ( f ) {
				document.location = '<?php echo esc_url_raw(admin_url( 'admin.php?page=wc-order-export&tab=schedules&wc_oe=copy_schedule&schedule_id=' )) ?>' + id + '&woe_nonce=' + woe_nonce;
			}
		} )
		$( '#doaction' ).click( function () {
			var chosen_schedules = [];

			jQuery.each( $( ' table th.check-column input ' ), function ( index, elem ) {
				if ( $( elem ).prop( "checked" ) ) {
					chosen_schedules.push( $( elem ).val() );
				}
			} );

			document.location = '<?php echo esc_url_raw(admin_url( 'admin.php?page=wc-order-export&tab=schedules&wc_oe=bulk_actions_schedules&chosen_schedules=' )) ?>' + chosen_schedules + '&doaction=' + $(
				'#bulk-action-selector-top' ).val() + '&woe_nonce=' + woe_nonce;
		} );
	} )
</script>
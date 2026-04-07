<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="tabs-content">
<br><br>
<?php esc_html_e( "Export orders by schedule different ways (Email,FTP,etc)",'woocommerce-order-export' )?>.
<a href="https://docs.algolplus.com/algol_order_export/pro-version-algol_order_export/scheduled-jobs/scheduled-jobs/" target=_blank>
<?php esc_html_e( 'More details','woocommerce-order-export' )?></a>
<hr>
<?php
/* translators: purchase Pro link  */
echo sprintf( esc_html__( 'Buy %s to get access to this section', 'woocommerce-order-export' ),
	sprintf( '<a href="https://algolplus.com/plugins/downloads/advanced-order-export-for-woocommerce-pro/" target=_blank>%s</a>', esc_html__( 'Pro version', 'woocommerce-order-export' ) )
	);
?>
</div>

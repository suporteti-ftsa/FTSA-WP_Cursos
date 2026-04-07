<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Custom_Related_Products
 * @subpackage Custom_Related_Products/includes
 * @author     markhf
 */
class Custom_Related_Products_Activator {

	/**
	 * Handles the processes related to the plugin activation.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		
        global $wpdb;

	    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );       
        if ( is_multisite() ) {
            // Get all blogs in the network and activate plugin on each one
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
            foreach( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );

                set_transient( '_crp_screen_activation_redirect', true, 30 );
        		add_option( 'crp_version', Custom_Related_Products::VERSION);
				self::update_cross_promo_banner_version();

                restore_current_blog();
            }
        } else {

        	set_transient( '_crp_screen_activation_redirect', true, 30 );
        	add_option( 'crp_version', Custom_Related_Products::VERSION);

			self::update_cross_promo_banner_version();
        }
	}

	/**
	 *	Check and update the cross promotion banner version.
	 */
	public static function update_cross_promo_banner_version() {
		$current_latest = get_option('wbfte_promotion_banner_version');

		if ( false === $current_latest ||  // User is installing the plugin first time.
			version_compare( $current_latest, WT_CRP_CROSS_PROMO_BANNER_VERSION, '<') // $current_latest is lesser than the installed version in this plugin.
		) {
			update_option('wbfte_promotion_banner_version', WT_CRP_CROSS_PROMO_BANNER_VERSION);
		}
	}
}

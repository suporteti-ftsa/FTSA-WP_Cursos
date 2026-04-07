<?php
/**
 * SSO Module
 * This class is responsible for SSO module.
 * This is used to define internationalization, admin-specific hooks, and public-facing site hooks for SSO module.
 *
 * @link       http://edwiser.org
 * @since      3.0.0
 * @package    Edwiser Bridge Pro
 */

namespace app\wisdmlabs\edwiserBridgePro\includes\sso;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This is not the way to call me!' );
}
/**
 * SSO Redirection class.
 */
class Sso_Redirection {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->version     = $version;
		$this->plugin_name = $plugin_name;
	}

	/**
	 * Get login redirect url.
	 *
	 * @param object $user                    User object.
	 * @param string $default_redirect_url      Default redirect url.
	 * @param int    $ignore_setting_redirect_url Ignore setting redirect url.
	 * @return $redirect_url
	 * @since 1.2
	 */
	public function get_login_redirect_url( $user, $default_redirect_url = '', $ignore_setting_redirect_url = 0 ) {
		$post_content             = null;
		$user_redirect_url_status = $this->get_user_redirect_url( $user );
		$redirect_url             = $user_redirect_url_status;

		// Check if we have a configured redirect URL from settings
		$has_configured_redirect = ! empty( $user_redirect_url_status );

		if ( $ignore_setting_redirect_url && ! empty( $default_redirect_url ) ) {
			$redirect_url = $default_redirect_url;
		} elseif ( empty( $redirect_url ) ) {
			if ( ! empty( $default_redirect_url ) ) {
				$redirect_url = $default_redirect_url;
			} else {
				$redirect_url = get_site_url();
			}
		}

		$get = array();
		if ( isset( $_SERVER['HTTP_REFERER'] ) && filter_var( $_SERVER['HTTP_REFERER'], FILTER_VALIDATE_URL ) ) { // @codingStandardsIgnoreLine
			$postid       = url_to_postid( $_SERVER['HTTP_REFERER'] ); // @codingStandardsIgnoreLine
			$post_content = $postid ? get_post( $postid )->post_content : null;
			$parsed_url   = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_QUERY );
			
			if ( ! empty( $parsed_url ) ) {
				parse_str( $parsed_url, $get ); // @codingStandardsIgnoreLine
			}
		}

		// Only use referer-based redirect if no configured redirect URL exists
		if ( ! $has_configured_redirect && ! $ignore_setting_redirect_url ) {
			$redirect_url = getRedirectUrl( $get, $post_content, $redirect_url );
		}

		// Special cases that override configured redirect
		if ( isset( $post_content ) && has_shortcode( $post_content, 'bridge_woo_single_cart_checkout' ) && ! $ignore_setting_redirect_url ) {
			$redirect_url = $_SERVER['HTTP_REFERER']; // @codingStandardsIgnoreLine
		} elseif ( isset( $post_content ) && has_shortcode( $post_content, 'woocommerce_checkout' ) && ! $ignore_setting_redirect_url ) {
			$redirect_url = $_SERVER['HTTP_REFERER']; // @codingStandardsIgnoreLine
		} elseif ( isset( $get['login_action'] ) && 'moodle' === $get['login_action'] && ! empty( $get['redirect_to'] ) ) {
			// Only use redirect_to if it's a WordPress URL, not Moodle URL (including /lms path)
			$redirect_to = $get['redirect_to'];
			$moodle_url = eb_get_mdl_url();
			if ( ! empty( $moodle_url ) && strpos( $redirect_to, $moodle_url ) === false ) {
				$redirect_url = $redirect_to;
			}
		}

		if ( isset( $get['redirect_to'] ) && filter_var( $get['redirect_to'], FILTER_VALIDATE_URL ) && isset( $get['is_enroll'] ) ) {
			$redirect_to = $get['redirect_to'];
			$moodle_url = eb_get_mdl_url();
			// Only use redirect_to if it's not a Moodle URL
			if ( empty( $moodle_url ) || strpos( $redirect_to, $moodle_url ) === false ) {
				$redirect_url = $redirect_to;
				$redirect_url = add_query_arg( 'auto_enroll', 'true', $redirect_url );
			}
		}
		global $wp;
		$protocol = is_ssl() ? 'https://' : 'http://';
		$current_url      = ($protocol) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		
		// Check if role-based redirect is enabled
		$redirect_urls = get_option( 'eb_sso_settings_redirection' );
		$role_based_enabled = isset( $redirect_urls['ebsso_role_base_redirect'] ) && 'on' === $redirect_urls['ebsso_role_base_redirect'];
		
		// For WooCommerce my-account page, only override if role-based redirect is NOT enabled
		// If role-based redirect is enabled, we should use the role-based URL instead
		if ( function_exists('WC') && isset( $_GET['redirect_to'] ) && get_option('woocommerce_myaccount_page_id') == url_to_postid($current_url) ) {
			$redirect_to = $_GET['redirect_to'];
			$moodle_url = eb_get_mdl_url();
			// Only use redirect_to if it's not a Moodle URL
			if ( empty( $moodle_url ) || strpos( $redirect_to, $moodle_url ) === false ) {
				$redirect_url = $redirect_to;
			}
		}
		return apply_filters( 'eb_sso_login_url', $redirect_url );
	}

	/**
	 * Get user redirect url.
	 *
	 * @param object $user User object.
	 */
	private function get_user_redirect_url( $user ) {
		$redirect_urls = get_option( 'eb_sso_settings_redirection' );
		if ( isset( $redirect_urls['ebsso_role_base_redirect'] ) && 'no' === $redirect_urls['ebsso_role_base_redirect'] ) {
			return $this->get_redirect_url( $redirect_urls, 'ebsso_login_redirect_url' );
		} else {
			return $this->get_redirect_url( $redirect_urls, 'ebsso_login_redirect_url_' . $user->roles[0] );
		}
	}

	/**
	 * Get redirect url.
	 *
	 * @param array  $data  Data.
	 * @param string $role  Role.
	 */
	private function get_redirect_url( $data, $role ) {
		$redirect = false;
		if ( isset( $_GET['mdl_course_id'] ) && ! empty( $_GET['mdl_course_id'] ) ) { // @codingStandardsIgnoreLine
			$redirect = eb_get_mdl_url() . '/course/view.php?id=' . $_GET['mdl_course_id']; // @codingStandardsIgnoreLine
		} elseif ( isset( $data[ $role ] ) && ! empty( $data[ $role ] ) ) {
			$redirect = $data[ $role ];
		} elseif ( isset( $data['ebsso_login_redirect_url'] ) && ! empty( $data['ebsso_login_redirect_url'] ) ) {
			$redirect = $data['ebsso_login_redirect_url'];
		}

		return $redirect;
	}
}

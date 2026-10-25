<?php
/**
 * CareConnect Headless theme.
 *
 * @package careconnect-headless
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'template_redirect',
	static function () {
		// The only page rendered by WordPress: demo of the [careconnect_services] shortcode.
		if ( is_page( 'wp-services-demo' ) ) {
			return;
		}
		$front = getenv( 'CC_FRONTEND_URL' );
		if ( $front ) {
			wp_redirect( esc_url_raw( $front ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- trusted env value.
			exit;
		}
		status_header( 404 );
		echo 'Not found.';
		exit;
	}
);

// Performance: this theme needs none of these.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );

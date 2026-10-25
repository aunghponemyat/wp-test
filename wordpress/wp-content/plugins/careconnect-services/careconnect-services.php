<?php
/**
 * Plugin Name: CareConnect Services
 * Description: Content, publishing workflow and public API for the fictional CareConnect directory.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Text Domain: careconnect-services
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

define( 'CCS_VERSION', '1.0.0' );
define( 'CCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CCS_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'cpt', 'roles', 'uploads-lock', 'meta-boxes', 'admin-columns', 'settings', 'home', 'contact', 'rest', 'cors', 'shortcode', 'seed', 'vercel-hook' ) as $ccs_class ) {
	require_once CCS_PATH . 'includes/class-' . $ccs_class . '.php';
}

add_action( 'init', array( 'CCS_CPT', 'register' ) );
add_action( 'init', array( 'CCS_Meta_Boxes', 'register_meta' ) );
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'careconnect-services', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
foreach ( array( 'CCS_Uploads_Lock', 'CCS_Meta_Boxes', 'CCS_Admin_Columns', 'CCS_Settings', 'CCS_Home', 'CCS_REST', 'CCS_CORS', 'CCS_Shortcode', 'CCS_Vercel_Hook' ) as $ccs_class ) {
	$ccs_class::hooks();
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);
register_activation_hook(
	__FILE__,
	static function () {
		CCS_CPT::register();
		CCS_Roles::activate();
		update_option( 'ccs_db_version', CCS_VERSION );
		update_option( 'blog_public', 0 );
		flush_rewrite_rules();
	}
);
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CCS_PATH . 'includes/class-cli.php';
}

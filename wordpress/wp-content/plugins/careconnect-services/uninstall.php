<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Explicit uninstall removes content; deactivation never does. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
require_once __DIR__ . '/includes/class-roles.php';
foreach ( array( 'cc_service', 'cc_article', 'cc_message' ) as $ccs_type ) {
	$ccs_ids = get_posts(
		array(
			'post_type'   => $ccs_type,
			'post_status' => array_keys( get_post_stati() ),
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $ccs_ids as $ccs_id ) {
		wp_delete_post( $ccs_id, true ); }
}
foreach ( array(
	'cc_service_type'     => 'cc_service',
	'cc_article_category' => 'cc_article',
) as $ccs_tax => $ccs_type ) {
	register_taxonomy( $ccs_tax, $ccs_type );
	$ccs_terms = get_terms(
		array(
			'taxonomy'   => $ccs_tax,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $ccs_terms ) ) {
		foreach ( $ccs_terms as $ccs_term ) {
			wp_delete_term( $ccs_term->term_id, $ccs_tax ); }
	}
}
foreach ( array( 'ccs_settings', 'ccs_home', 'ccs_db_version', 'ccs_seeded' ) as $ccs_option ) {
	delete_option( $ccs_option ); }
global $wpdb;
// Direct SQL is needed to remove this plugin's transient namespace only.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_ccs_' ) . '%', $wpdb->esc_like( '_transient_timeout_ccs_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
remove_role( 'cc_editor' );
remove_role( 'cc_publisher' );
$ccs_admin = get_role( 'administrator' );
if ( $ccs_admin ) {
	foreach ( array_keys( array_merge( CCS_Roles::caps( true ), CCS_Roles::caps( true, true ), array( 'ccs_manage_settings' => true ) ) ) as $ccs_cap ) {
		if ( 'read' !== $ccs_cap ) {
			$ccs_admin->remove_cap( $ccs_cap ); }
	}
}

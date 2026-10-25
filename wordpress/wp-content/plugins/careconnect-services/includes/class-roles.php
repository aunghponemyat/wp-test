<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Explicit capabilities keep draft authors separate from publishers. */
class CCS_Roles {
	/**
	 * Build the explicit capability set for a content role.
	 *
	 * @param bool $publisher Publisher.
	 * @param bool $messages Messages.
	 * @return array
	 */
	public static function caps( $publisher = false, $messages = false ) {
		$caps = array( 'read' => true );
		foreach ( $messages ? array( 'cc_messages' ) : array( 'cc_services', 'cc_articles' ) as $plural ) {
			foreach ( array( 'edit_', 'delete_' ) as $prefix ) {
				$caps[ $prefix . $plural ] = true;
			}
			if ( $publisher ) {
				foreach ( array( 'publish_', 'edit_others_', 'edit_published_', 'delete_others_', 'delete_published_', 'read_private_', 'edit_private_', 'delete_private_' ) as $prefix ) {
					$caps[ $prefix . $plural ] = true;
				}
			}
		}
		if ( $publisher && ! $messages ) {
			$caps['ccs_manage_home'] = true;
		}
		return $caps;
	}
	/**
	 * Create content roles and grant administrator capabilities.
	 *
	 * @return void
	 */
	public static function activate() {
		add_role( 'cc_editor', __( 'CareConnect Editor', 'careconnect-services' ), self::caps() );
		add_role( 'cc_publisher', __( 'CareConnect Publisher', 'careconnect-services' ), self::caps( true ) );
		// Reconcile existing roles on reactivation after a deployment.
		foreach ( array(
			'cc_editor'    => false,
			'cc_publisher' => true,
		) as $name => $publisher ) {
			$role = get_role( $name );
			foreach ( array_keys( self::caps( true ) ) as $cap ) {
				$role->remove_cap( $cap );
			}
			foreach ( self::caps( $publisher ) as $cap => $grant ) {
				$role->add_cap( $cap, $grant );
			}
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_merge( self::caps( true ), self::caps( true, true ), array( 'ccs_manage_settings' => true ) ) as $cap => $grant ) {
				$admin->add_cap( $cap, $grant );
			}
		}
	}
}

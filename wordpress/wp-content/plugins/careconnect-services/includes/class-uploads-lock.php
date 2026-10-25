<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** The demo has no persistent media disk. */
class CCS_Uploads_Lock {
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_filter(
			'user_has_cap',
			static function ( $caps ) {
				$caps['upload_files'] = false;
				return $caps;
			},
			99
		);
		add_filter( 'upload_mimes', '__return_empty_array' );
		add_action(
			'admin_head',
			static function () {
				if ( in_array( get_post_type(), array( 'cc_service', 'cc_article' ), true ) ) {
					remove_action( 'media_buttons', 'media_buttons' );
				}
			}
		);
	}
}

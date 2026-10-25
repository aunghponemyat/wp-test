<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Content changes request one non-blocking rebuild per minute. */
class CCS_Vercel_Hook {
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action(
			'transition_post_status',
			static function ( $new_status, $old, $post ) {
				if ( in_array( $post->post_type, array( 'cc_service', 'cc_article' ), true ) && ( 'publish' === $new_status || 'publish' === $old ) ) {
					self::trigger();
				}
			},
			10,
			3
		);
		add_action( 'update_option_ccs_home', array( __CLASS__, 'trigger' ) );
	}
	/**
	 * Request a debounced non-blocking Vercel rebuild.
	 *
	 * @return void
	 */
	public static function trigger() {
		$hook = CCS_Settings::get()['vercel_deploy_hook'];
		if ( ! $hook || ! str_starts_with( $hook, 'https://api.vercel.com/' ) || get_transient( 'ccs_rebuild' ) ) {
			return;
		}
		set_transient( 'ccs_rebuild', 1, 60 );
		wp_remote_post(
			$hook,
			array(
				'blocking' => false,
				'timeout'  => 2,
			)
		);
	}
}

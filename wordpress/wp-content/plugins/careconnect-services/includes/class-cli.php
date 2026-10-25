<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Seed only through an explicit WP-CLI command. */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'careconnect seed',
		static function ( $args, $assoc_args ) {
			try {
				$changed = CCS_Seed::run( isset( $assoc_args['force'] ) );
				WP_CLI::success( $changed ? __( 'Seeded 9 fictional services and 4 articles.', 'careconnect-services' ) : __( 'Seed already exists; use --force to reset sample content.', 'careconnect-services' ) );
			} catch ( Throwable $error ) {
				WP_CLI::error( $error->getMessage() );
			}
		}
	);
}

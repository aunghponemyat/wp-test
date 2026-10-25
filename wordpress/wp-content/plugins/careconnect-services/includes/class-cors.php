<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Exact-origin, credential-free CORS for this namespace only. */
class CCS_CORS {
	/**
	 * Check an origin against the exact configured allow-list.
	 *
	 * @param string $origin Origin.
	 * @return bool
	 */
	public static function allowed( $origin ) {
		$origins = array_filter( array_map( 'trim', explode( ',', getenv( 'CC_ALLOWED_ORIGINS' ) ? getenv( 'CC_ALLOWED_ORIGINS' ) : '' ) ) );
		return in_array( $origin, $origins, true );
	}
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action(
			'rest_api_init',
			static function () {
				remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
			},
			20
		);
		add_filter(
			'rest_pre_dispatch',
			static function ( $result, $server, $request ) {
				$origin = $request->get_header( 'origin' );
				if ( str_starts_with( $request->get_route(), '/careconnect/v1/' ) && $origin && ! self::allowed( $origin ) ) {
					return new WP_Error( 'ccs_origin', __( 'This origin is not allowed.', 'careconnect-services' ), array( 'status' => 403 ) );
				}
				return $result;
			},
			10,
			3
		);
		add_filter(
			'rest_pre_serve_request',
			static function ( $served, $result, $request ) {
				if ( ! str_starts_with( $request->get_route(), '/careconnect/v1/' ) ) {
					return $served;
				}
				header( 'Vary: Origin', false );
				$origin = $request->get_header( 'origin' );
				if ( $origin && self::allowed( $origin ) ) {
					header( 'Access-Control-Allow-Origin: ' . $origin );
					header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
					header( 'Access-Control-Allow-Headers: Content-Type' );
					header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages' );
				}
				return $served;
			},
			10,
			3
		);
	}
}

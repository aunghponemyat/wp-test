<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Public contact endpoint with bounded input and private storage. */
class CCS_Contact {
	/**
	 * Validate, rate-limit and privately save a contact message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit( $request ) {
		$generic = __( 'Unable to send this message. Please try again later.', 'careconnect-services' );
		if ( strlen( $request->get_body() ) > 16384 ) {
			return new WP_Error( 'ccs_too_large', $generic, array( 'status' => 413 ) );
		}
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'ccs_invalid', $generic, array( 'status' => 400 ) );
		}
		foreach ( array( 'name', 'email', 'message', 'website', 'ts' ) as $field ) {
			if ( ! isset( $input[ $field ] ) || ! is_scalar( $input[ $field ] ) ) {
				return new WP_Error( 'ccs_invalid', $generic, array( 'status' => 400 ) );
			}
		}
		$age = microtime( true ) * 1000 - (float) $input['ts'];
		if ( '' !== (string) $input['website'] || ! is_numeric( $input['ts'] ) || ! is_finite( $age ) || $age < 3000 || $age > 7200000 ) {
			return new WP_Error( 'ccs_rejected', $generic, array( 'status' => 400 ) );
		}
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'ccs_rate_' . wp_hash( $ip );
		$rate = get_transient( $key );
		if ( ! is_array( $rate ) ) {
			$rate = array(
				'count' => 0,
				'reset' => time() + HOUR_IN_SECONDS,
			);
		}
		if ( $rate['count'] >= 5 ) {
			return new WP_Error( 'ccs_rate_limited', $generic, array( 'status' => 429 ) );
		}
		++$rate['count'];
		set_transient( $key, $rate, max( 1, $rate['reset'] - time() ) );
		$name    = sanitize_text_field( $input['name'] );
		$email   = sanitize_email( $input['email'] );
		$message = sanitize_textarea_field( $input['message'] );
		$errors  = array();
		if ( mb_strlen( $name ) < 1 || mb_strlen( $name ) > 100 ) {
			$errors['name'] = __( 'Enter a name of 1–100 characters.', 'careconnect-services' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'careconnect-services' );
		}
		if ( mb_strlen( $message ) < 10 || mb_strlen( $message ) > 2000 ) {
			$errors['message'] = __( 'Enter a message of 10–2000 characters.', 'careconnect-services' );
		}
		if ( $errors ) {
			return new WP_REST_Response(
				array(
					'code'    => 'ccs_validation',
					'message' => __( 'Please check the highlighted fields.', 'careconnect-services' ),
					'errors'  => $errors,
				),
				422
			);
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'cc_message',
				'post_status'  => 'private',
				'post_title'   => $name,
				'post_content' => $message,
				'meta_input'   => array( '_cc_email' => $email ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'ccs_storage', $generic, array( 'status' => 500 ) );
		}
		$sent = wp_mail( CCS_Settings::get()['contact_email'], __( 'CareConnect contact message', 'careconnect-services' ), $name . "\n" . $email . "\n\n" . $message );
		if ( ! $sent ) {
			error_log( 'CareConnect: contact email delivery failed; private message retained.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- required operational log without personal data.
		}
		$response = new WP_REST_Response( array( 'ok' => true ), 201 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}

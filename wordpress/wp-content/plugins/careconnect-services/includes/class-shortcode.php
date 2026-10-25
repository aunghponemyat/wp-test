<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Dependency-free shortcode demo, with script loaded on demand. */
class CCS_Shortcode {
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_shortcode( 'careconnect_services', array( __CLASS__, 'render' ) );
	}
	/**
	 * Render the authorized administration or shortcode interface.
	 *
	 * @return mixed
	 */
	public static function render() {
		wp_enqueue_script( 'ccs-services', CCS_URL . 'assets/services-list.js', array(), CCS_VERSION, true );
		wp_localize_script(
			'ccs-services',
			'ccsServices',
			array(
				'api'      => rest_url( 'careconnect/v1/' ),
				'frontend' => getenv( 'CC_FRONTEND_URL' ) ? getenv( 'CC_FRONTEND_URL' ) : '',
				'empty'    => __( 'No services match your search.', 'careconnect-services' ),
				'error'    => __( 'Unable to load services. Please try again.', 'careconnect-services' ),
				'loading'  => __( 'Loading services…', 'careconnect-services' ),
				'waking'   => __( 'Waking up the demo server…', 'careconnect-services' ),
				'previous' => __( 'Previous', 'careconnect-services' ),
				'next'     => __( 'Next', 'careconnect-services' ),
				'all'      => __( 'All service types', 'careconnect-services' ),
				'page'     => __( 'Page', 'careconnect-services' ),
			)
		);
		$id = wp_unique_id( 'ccs-search-' );
		return '<section class="ccs-directory"><form role="search"><p><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Search services', 'careconnect-services' ) . '</label><br><input id="' . esc_attr( $id ) . '" name="search" type="search" maxlength="100"></p><p><label for="' . esc_attr( $id . '-type' ) . '">' . esc_html__( 'Service type', 'careconnect-services' ) . '</label><br><select name="type" id="' . esc_attr( $id . '-type' ) . '"><option value="">' . esc_html__( 'All service types', 'careconnect-services' ) . '</option></select></p><button type="submit">' . esc_html__( 'Search', 'careconnect-services' ) . '</button></form><div class="ccs-results" aria-live="polite"></div><div class="ccs-pagination"></div></section>';
	}
}

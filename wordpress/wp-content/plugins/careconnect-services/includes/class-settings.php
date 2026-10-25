<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Settings API owns CSRF and option permissions. */
class CCS_Settings {
	/**
	 * Return the initial settings values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'contact_email'      => get_option( 'admin_email' ),
			'featured_count'     => 3,
			'vercel_deploy_hook' => '',
		);
	}
	/**
	 * Read settings with defaults for missing keys.
	 *
	 * @return array|WP_REST_Response
	 */
	public static function get() {
		return wp_parse_args( get_option( 'ccs_settings', array() ), self::defaults() );
	}
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action(
			'admin_menu',
			static function () {
				add_menu_page( __( 'CareConnect', 'careconnect-services' ), __( 'CareConnect', 'careconnect-services' ), 'ccs_manage_home', 'careconnect', array( 'CCS_Home', 'render' ), 'dashicons-heart' );
				add_submenu_page( 'careconnect', __( 'Settings', 'careconnect-services' ), __( 'Settings', 'careconnect-services' ), 'ccs_manage_settings', 'careconnect-settings', array( __CLASS__, 'render' ) );
			}
		);
		add_action(
			'admin_init',
			static function () {
				register_setting(
					'ccs_settings_group',
					'ccs_settings',
					array(
						'sanitize_callback' => array( __CLASS__, 'sanitize' ),
						'default'           => self::defaults(),
					)
				);
			}
		);
		add_filter(
			'option_page_capability_ccs_settings_group',
			static function () {
				return 'ccs_manage_settings';
			}
		);
	}
	/**
	 * Sanitize the submitted Settings API option.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old   = self::get();
		$email = sanitize_email( $input['contact_email'] ?? '' );
		$hook  = esc_url_raw( $input['vercel_deploy_hook'] ?? '' );
		if ( ! is_email( $email ) ) {
			add_settings_error( 'ccs_settings', 'email', __( 'Enter a valid contact email.', 'careconnect-services' ) );
			$email = $old['contact_email'];
		}
		if ( $hook && ! str_starts_with( $hook, 'https://api.vercel.com/' ) ) {
			add_settings_error( 'ccs_settings', 'hook', __( 'Deploy hook must start with https://api.vercel.com/.', 'careconnect-services' ) );
			$hook = $old['vercel_deploy_hook'];
		}
		return array(
			'contact_email'      => $email,
			'featured_count'     => max( 1, min( 6, absint( $input['featured_count'] ?? 3 ) ) ),
			'vercel_deploy_hook' => $hook,
		);
	}
	/**
	 * Render the authorized administration or shortcode interface.
	 *
	 * @return mixed
	 */
	public static function render() {
		if ( ! current_user_can( 'ccs_manage_settings' ) ) {
			return;
		}
		$settings = self::get();
		echo '<div class="wrap"><h1>' . esc_html__( 'CareConnect settings', 'careconnect-services' ) . '</h1>';
		settings_errors();
		echo '<form action="options.php" method="post">';
		settings_fields( 'ccs_settings_group' );
		foreach ( array(
			'contact_email'      => __( 'Contact email', 'careconnect-services' ),
			'featured_count'     => __( 'Featured count (1–6)', 'careconnect-services' ),
			'vercel_deploy_hook' => __( 'Vercel deploy hook', 'careconnect-services' ),
		) as $field => $label ) {
			echo '<p><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label><br><input class="regular-text" type="' . esc_attr( 'featured_count' === $field ? 'number' : ( 'contact_email' === $field ? 'email' : 'url' ) ) . '" id="' . esc_attr( $field ) . '" name="ccs_settings[' . esc_attr( $field ) . ']" value="' . esc_attr( $settings[ $field ] ) . '" ' . ( 'featured_count' === $field ? 'min="1" max="6"' : '' ) . '></p>';
		}
		submit_button();
		echo '</form></div>';
	}
}

<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Home content is editable by publishers through its own option group. */
class CCS_Home {
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action(
			'admin_menu',
			static function () {
				add_submenu_page( 'careconnect', __( 'Home Content', 'careconnect-services' ), __( 'Home Content', 'careconnect-services' ), 'ccs_manage_home', 'careconnect', array( __CLASS__, 'render' ) );
			}
		);
		add_action(
			'admin_init',
			static function () {
				register_setting( 'ccs_home_group', 'ccs_home', array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
			}
		);
		add_filter(
			'option_page_capability_ccs_home_group',
			static function () {
				return 'ccs_manage_home';
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
		$ids = array_values( array_unique( array_map( 'absint', (array) ( $input['featured_ids'] ?? array() ) ) ) );
		$ids = array_filter(
			$ids,
			static function ( $id ) {
				return 'cc_service' === get_post_type( $id ) && 'publish' === get_post_status( $id );
			}
		);
		return array(
			'hero_title'    => mb_substr( sanitize_text_field( $input['hero_title'] ?? '' ), 0, 80 ),
			'hero_subtitle' => mb_substr( sanitize_text_field( $input['hero_subtitle'] ?? '' ), 0, 160 ),
			'featured_ids'  => array_slice( array_values( $ids ), 0, CCS_Settings::get()['featured_count'] ),
		);
	}
	/**
	 * Render the authorized administration or shortcode interface.
	 *
	 * @return mixed
	 */
	public static function render() {
		if ( ! current_user_can( 'ccs_manage_home' ) ) {
			return;
		}
		$home = wp_parse_args(
			get_option( 'ccs_home', array() ),
			array(
				'hero_title'    => '',
				'hero_subtitle' => '',
				'featured_ids'  => array(),
			)
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'Home Content', 'careconnect-services' ) . '</h1>';
		settings_errors();
		echo '<form action="options.php" method="post">';
		settings_fields( 'ccs_home_group' );
		foreach ( array(
			'hero_title'    => 80,
			'hero_subtitle' => 160,
		) as $field => $max ) {
			echo '<p><label for="' . esc_attr( $field ) . '">' . esc_html( 'hero_title' === $field ? __( 'Hero title', 'careconnect-services' ) : __( 'Hero subtitle (one sentence)', 'careconnect-services' ) ) . '</label><br><input class="large-text" id="' . esc_attr( $field ) . '" name="ccs_home[' . esc_attr( $field ) . ']" maxlength="' . esc_attr( $max ) . '" value="' . esc_attr( $home[ $field ] ) . '"></p>';
		}
		echo '<p><label for="ccs_featured">' . esc_html__( 'Featured services (displayed in list order)', 'careconnect-services' ) . '</label><br><select id="ccs_featured" name="ccs_home[featured_ids][]" multiple size="9">';
		$posts = get_posts(
			array(
				'post_type'   => 'cc_service',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		usort(
			$posts,
			static function ( $a, $b ) use ( $home ) {
				$a_rank = array_search( $a->ID, $home['featured_ids'], true );
				$b_rank = array_search( $b->ID, $home['featured_ids'], true );
				return ( false === $a_rank ? PHP_INT_MAX : $a_rank ) <=> ( false === $b_rank ? PHP_INT_MAX : $b_rank );
			}
		);
		foreach ( $posts as $post ) {
			echo '<option value="' . esc_attr( $post->ID ) . '" ' . selected( in_array( $post->ID, $home['featured_ids'], true ), true, false ) . '>' . esc_html( $post->post_title ) . '</option>';
		}
		echo '</select></p><p>' . esc_html__( 'Use the arrow buttons to reorder selected services. Only the configured featured count is saved.', 'careconnect-services' ) . '</p><button type="button" class="button" id="ccs-up">' . esc_html__( 'Move selected up', 'careconnect-services' ) . '</button> <button type="button" class="button" id="ccs-down">' . esc_html__( 'Move selected down', 'careconnect-services' ) . '</button>';
		wp_enqueue_script( 'ccs-home', CCS_URL . 'assets/home.js', array(), CCS_VERSION, true );
		submit_button();
		echo '</form></div>';
	}
}

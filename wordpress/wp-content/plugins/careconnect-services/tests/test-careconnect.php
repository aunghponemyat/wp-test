<?php
/**
 * Publication, permission and request boundary integration tests.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Exercise publication and public request boundaries against disposable MySQL. */
class CCS_Integration_Test extends WP_UnitTestCase {
	/**
	 * Initialize content types, roles and the REST server for a test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		CCS_CPT::register();
		CCS_Roles::activate();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
		wp_set_current_user( 0 );
	}
	/**
	 * Create fictional content for the integration scenario.
	 *
	 * @return void
	 */
	private function seed() {
		CCS_Seed::run( true );
	}
	/**
	 * Read settings with defaults for missing keys.
	 *
	 * @param string $path Path.
	 * @param array  $params Params.
	 * @return array|WP_REST_Response
	 */
	private function get( $path, $params = array() ) {
		$request = new WP_REST_Request( 'GET', '/careconnect/v1/' . $path );
		$request->set_query_params( $params );
		return rest_get_server()->dispatch( $request );
	}
	/**
	 * Verify that phone formats and normalization.
	 *
	 * @return void
	 */
	public function test_phone_formats_and_normalization() {
		$this->assertSame( '+65 6555 0101', CCS_Meta_Boxes::phone( '+65 6555 0101' ) );
		$this->assertSame( '+65 6555 0101', CCS_Meta_Boxes::phone( '65550101' ) );
		$this->assertFalse( CCS_Meta_Boxes::phone( '12345' ) );
	}
	/**
	 * Verify that long short description is rejected.
	 *
	 * @return void
	 */
	public function test_long_short_description_is_rejected() {
		$errors = CCS_Meta_Boxes::validate( array( 'short_description' => str_repeat( '界', 201 ) ), 'cc_service' );
		$this->assertArrayHasKey( 'short_description', $errors );
	}
	/**
	 * Verify that listing and details never expose unpublished.
	 *
	 * @return void
	 */
	public function test_listing_and_details_never_expose_unpublished() {
		$this->seed();
		$posts = get_posts(
			array(
				'post_type'   => 'cc_service',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);
		foreach ( array( 'draft', 'pending', 'private' ) as $index => $status ) {
			wp_update_post(
				array(
					'ID'          => $posts[ $index ]->ID,
					'post_status' => $status,
				)
			);
			$this->assertSame( 404, $this->get( 'services/' . $posts[ $index ]->post_name )->get_status() );
		}
		$data = $this->get(
			'services',
			array(
				'status'   => 'any',
				'per_page' => 50,
			)
		)->get_data();
		$this->assertSame( 6, $data['total'] );
		foreach ( $data['items'] as $item ) {
			$this->assertSame( 'publish', get_post_status( $item['id'] ) );
		}
	}
	/**
	 * Verify that search and type filter are combined.
	 *
	 * @return void
	 */
	public function test_search_and_type_filter_are_combined() {
		$this->seed();
		$data = $this->get(
			'services',
			array(
				'search' => 'Sunrise',
				'type'   => 'home-care',
			)
		)->get_data();
		$this->assertSame( 1, $data['total'] );
		$this->assertSame( 'Sunrise Home Visit Programme', $data['items'][0]['name'] );
		$this->assertSame(
			0,
			$this->get(
				'services',
				array(
					'search' => 'Sunrise',
					'type'   => 'community-clinic',
				)
			)->get_data()['total']
		);
		$this->assertSame( 0, $this->get( 'services', array( 'type' => 'missing-type' ) )->get_data()['total'] );
	}
	/**
	 * Verify that role boundaries and upload lock.
	 *
	 * @return void
	 */
	public function test_role_boundaries_and_upload_lock() {
		$editor    = self::factory()->user->create( array( 'role' => 'cc_editor' ) );
		$publisher = self::factory()->user->create( array( 'role' => 'cc_publisher' ) );
		wp_set_current_user( $editor );
		$this->assertTrue( current_user_can( 'edit_cc_services' ) );
		$this->assertFalse( current_user_can( 'publish_cc_services' ) );
		$this->assertFalse( current_user_can( 'upload_files' ) );
		$this->assertFalse( current_user_can( 'edit_cc_messages' ) );
		wp_set_current_user( $publisher );
		$this->assertTrue( current_user_can( 'publish_cc_services' ) );
		$this->assertTrue( current_user_can( 'ccs_manage_home' ) );
		$this->assertFalse( current_user_can( 'ccs_manage_settings' ) );
	}
	/**
	 * Verify that honeypot is rejected.
	 *
	 * @return void
	 */
	public function test_honeypot_is_rejected() {
		$request = new WP_REST_Request( 'POST', '/careconnect/v1/contact' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'name'    => 'Example Visitor',
					'email'   => 'visitor@example.test',
					'message' => 'A fictional message for the directory.',
					'website' => 'spam',
					'ts'      => ( time() - 5 ) * 1000,
				)
			)
		);
		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
		$this->assertEmpty(
			get_posts(
				array(
					'post_type'   => 'cc_message',
					'post_status' => 'private',
				)
			)
		);
	}
	/**
	 * Verify that invalid service is downgraded to draft.
	 *
	 * @return void
	 */
	public function test_invalid_service_is_downgraded_to_draft() {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'cc_service',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 0, $this->get( 'services' )->get_data()['total'] );
	}
	/**
	 * Verify that related and home exclude unpublished.
	 *
	 * @return void
	 */
	public function test_related_and_home_exclude_unpublished() {
		$this->seed();
		$home = $this->get( 'home' )->get_data();
		$id   = $home['featured_services'][0]['id'];
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		foreach ( $this->get( 'home' )->get_data()['featured_services'] as $card ) {
			$this->assertNotSame( $id, $card['id'] );
		}
		$all = $this->get( 'services', array( 'per_page' => 50 ) )->get_data();
		foreach ( $all['items'] as $item ) {
			foreach ( $this->get( 'services/' . $item['slug'] )->get_data()['related'] as $related ) {
				$this->assertNotSame( $id, $related['id'] );
			}
		}
	}
	/**
	 * Verify that seed is idempotent and has two overdue services.
	 *
	 * @return void
	 */
	public function test_seed_is_idempotent_and_has_two_overdue_services() {
		$this->seed();
		$this->assertFalse( CCS_Seed::run() );
		$this->assertSame( 9, $this->get( 'services', array( 'per_page' => 50 ) )->get_data()['total'] );
		$this->assertSame( 4, $this->get( 'articles' )->get_data()['total'] );
		$query = new WP_Query(
			array(
				'post_type'   => 'cc_service',
				'post_status' => 'publish',
				'meta_query'  => array(
					array(
						'key'     => '_cc_last_reviewed',
						'value'   => CCS_Admin_Columns::cutoff(),
						'compare' => '<',
						'type'    => 'DATE',
					),
				),
			)
		);
		$this->assertSame( 2, $query->found_posts );
	}
	/**
	 * Verify that paging and invalid arguments.
	 *
	 * @return void
	 */
	public function test_paging_and_invalid_arguments() {
		$this->seed();
		$response = $this->get(
			'services',
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);
		$this->assertCount( 2, $response->get_data()['items'] );
		$this->assertSame( 5, $response->get_data()['total_pages'] );
		$this->assertSame( 400, $this->get( 'services', array( 'per_page' => 51 ) )->get_status() );
		$this->assertSame( 400, $this->get( 'services', array( 'page' => -1 ) )->get_status() );
	}
	/**
	 * Verify that disallowed origin and anonymous users endpoint.
	 *
	 * @return void
	 */
	public function test_disallowed_origin_and_anonymous_users_endpoint() {
		$request = new WP_REST_Request( 'POST', '/careconnect/v1/contact' );
		$request->set_header( 'Origin', 'https://untrusted.example.test' );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
		$this->assertArrayNotHasKey( '/wp/v2/users', rest_get_server()->get_routes() );
	}
	/**
	 * Verify that accepted messages stay private and the sixth attempt is limited.
	 *
	 * @return void
	 */
	public function test_contact_private_storage_and_rate_limit() {
		add_filter( 'pre_wp_mail', '__return_true' );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.44';
		$key                    = 'ccs_rate_' . wp_hash( '192.0.2.44' );
		delete_transient( $key );
		try {
			for ( $index = 0; $index < 6; ++$index ) {
				$request = new WP_REST_Request( 'POST', '/careconnect/v1/contact' );
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body(
					wp_json_encode(
						array(
							'name'    => 'Example Visitor',
							'email'   => 'visitor@example.test',
							'message' => 'A fictional message for the directory.',
							'website' => '',
							'ts'      => ( time() - 5 ) * 1000,
						)
					)
				);
				$this->assertSame( 5 === $index ? 429 : 201, rest_get_server()->dispatch( $request )->get_status() );
			}
			$messages = get_posts(
				array(
					'post_type'   => 'cc_message',
					'post_status' => 'private',
					'numberposts' => -1,
				)
			);
			$this->assertCount( 5, $messages );
			$this->assertSame( 'visitor@example.test', get_post_meta( $messages[0]->ID, '_cc_email', true ) );
			$this->assertArrayNotHasKey( 'ip', get_transient( $key ) );
		} finally {
			remove_filter( 'pre_wp_mail', '__return_true' );
			delete_transient( $key );
			unset( $_SERVER['REMOTE_ADDR'] );
		}
	}
	/**
	 * Verify that an invalid Pending Review submission is saved as a draft.
	 *
	 * @return void
	 */
	public function test_invalid_pending_submission_keeps_previous_valid_meta() {
		$this->seed();
		$post = get_posts(
			array(
				'post_type'   => 'cc_service',
				'post_status' => 'publish',
				'numberposts' => 1,
			)
		)[0];
		$user = self::factory()->user->create( array( 'role' => 'cc_publisher' ) );
		wp_set_current_user( $user );
		$old_phone                  = get_post_meta( $post->ID, '_cc_phone', true );
		$_POST['ccs_service_nonce'] = wp_create_nonce( 'ccs_save_service' );
		$_POST['ccs_phone']         = 'invalid';
		try {
			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'pending',
				)
			);
			$this->assertSame( 'draft', get_post_status( $post->ID ) );
			$this->assertSame( $old_phone, get_post_meta( $post->ID, '_cc_phone', true ) );
		} finally {
			unset( $_POST['ccs_service_nonce'], $_POST['ccs_phone'] );
		}
	}
	/**
	 * Verify that contact timestamps must fit the permitted time window.
	 *
	 * @return void
	 */
	public function test_contact_timing_and_size_boundaries() {
		foreach ( array( microtime( true ) * 1000, ( time() - 8000 ) * 1000 ) as $timestamp ) {
			$request = new WP_REST_Request( 'POST', '/careconnect/v1/contact' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body(
				wp_json_encode(
					array(
						'name'    => 'Example Visitor',
						'email'   => 'visitor@example.test',
						'message' => 'A fictional message for the directory.',
						'website' => '',
						'ts'      => $timestamp,
					)
				)
			);
			$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
		}
		$request->set_body( wp_json_encode( array( 'message' => str_repeat( 'x', 16385 ) ) ) );
		$this->assertSame( 413, rest_get_server()->dispatch( $request )->get_status() );
	}
	/**
	 * Verify that explicit empty filters remain strings rather than REST objects.
	 *
	 * @return void
	 */
	public function test_explicit_empty_filters_return_all_published_content() {
		$this->seed();
		$response = $this->get(
			'services',
			array(
				'search'   => '',
				'type'     => '',
				'page'     => '1',
				'per_page' => '9',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 9, $response->get_data()['total'] );
		$this->assertSame( 4, $this->get( 'articles', array( 'category' => '' ) )->get_data()['total'] );
	}
	/**
	 * Verify that explicit uninstall removes auto drafts, trash, content and roles.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_every_content_status_and_custom_roles() {
		$this->seed();
		foreach ( array( 'auto-draft', 'trash' ) as $status ) {
			self::factory()->post->create(
				array(
					'post_type'   => 'cc_service',
					'post_status' => $status,
				)
			);
		}
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true ); }
		require dirname( __DIR__ ) . '/uninstall.php';
		foreach ( array( 'cc_service', 'cc_article', 'cc_message' ) as $type ) {
			$this->assertEmpty(
				get_posts(
					array(
						'post_type'   => $type,
						'post_status' => array_keys( get_post_stati() ),
						'numberposts' => -1,
					)
				)
			);
		}
		$this->assertFalse( get_option( 'ccs_seeded' ) );
		$this->assertNull( get_role( 'cc_editor' ) );
		$this->assertNull( get_role( 'cc_publisher' ) );
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'ccs_manage_settings' ) );
	}
	/**
	 * Verify that WordPress does not add a default editor to private messages.
	 *
	 * @return void
	 */
	public function test_messages_have_no_editable_content_support() {
		$this->assertFalse( post_type_supports( 'cc_message', 'title' ) );
		$this->assertFalse( post_type_supports( 'cc_message', 'editor' ) );
	}
}

<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** The only public content API; status is never read from request input. */
class CCS_REST {
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}
	/**
	 * Define bounded pagination request arguments.
	 *
	 * @return array
	 */
	public static function pagination_args() {
		$args = array();
		foreach ( array(
			'page'     => array( 1, PHP_INT_MAX ),
			'per_page' => array( 9, 50 ),
		) as $name => $range ) {
			$args[ $name ] = array(
				'default'           => $range[0],
				'sanitize_callback' => 'absint',
				'validate_callback' => static function ( $value ) use ( $range ) {
								return false !== filter_var(
									$value,
									FILTER_VALIDATE_INT,
									array(
										'options' => array(
											'min_range' => 1,
											'max_range' => $range[1],
										),
									)
								);
				},
			);
		}
		return $args;
	}
	/**
	 * Register the public API routes and response caching policy.
	 *
	 * @return void
	 */
	public static function routes() {
		$pagination = self::pagination_args();
		$slug       = array(
			'sanitize_callback' => static function ( $value ) {
				return sanitize_title( $value ); },
			'validate_callback' => static function ( $value ) {
						return is_string( $value ) && strlen( $value ) <= 200;
			},
		);
		$search     = array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function ( $value ) {
						return is_string( $value ) && mb_strlen( $value ) <= 100;
			},
		);
		foreach ( array(
			'services' => array_merge(
				$pagination,
				array(
					'search' => $search,
					'type'   => $slug,
				)
			),
			'articles' => array_merge( $pagination, array( 'category' => $slug ) ),
		) as $route => $args ) {
			register_rest_route(
				'careconnect/v1',
				'/' . $route,
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'args'                => $args,
					'callback'            => static function ( $request ) use ( $route ) {
						return self::listing( $request, 'services' === $route ? 'cc_service' : 'cc_article' );
					},
				)
			);
			register_rest_route(
				'careconnect/v1',
				'/' . $route . '/(?P<slug>[a-z0-9-]+)',
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'args'                => array( 'slug' => $slug ),
					'callback'            => static function ( $request ) use ( $route ) {
						return self::detail( $request['slug'], 'services' === $route ? 'cc_service' : 'cc_article' );
					},
				)
			);
		}
		register_rest_route(
			'careconnect/v1',
			'/service-types',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'types' ),
			)
		);
		register_rest_route(
			'careconnect/v1',
			'/home',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'home' ),
			)
		);
		register_rest_route(
			'careconnect/v1',
			'/contact',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( 'CCS_Contact', 'submit' ),
			)
		);
		add_filter(
			'rest_post_dispatch',
			static function ( $response, $server, $request ) {
				if ( str_starts_with( $request->get_route(), '/careconnect/v1/' ) ) {
					$response->header( 'Cache-Control', 'GET' === $request->get_method() && $response->get_status() < 400 ? 'public, max-age=60' : 'no-store' );
				}
				return $response;
			},
			10,
			3
		);
	}
	/**
	 * Serialize the selected content classification.
	 *
	 * @param int    $id Id.
	 * @param string $taxonomy Taxonomy.
	 * @return array|null
	 */
	public static function term( $id, $taxonomy ) {
		$terms = wp_get_object_terms( $id, $taxonomy );
		return ! is_wp_error( $terms ) && $terms ? array(
			'slug' => $terms[0]->slug,
			'name' => $terms[0]->name,
		) : null;
	}
	/**
	 * Serialize a safe image URL and its alternative text.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function image( $id ) {
		$url = get_post_meta( $id, '_cc_image_url', true );
		return $url && CCS_Meta_Boxes::valid_image( $url ) ? array(
			'url' => esc_url_raw( $url ),
			'alt' => get_post_meta( $id, '_cc_image_alt', true ),
		) : null;
	}
	/**
	 * Serialize the public card fields of a published post.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function card( $post ) {
		$base = array(
			'id'    => $post->ID,
			'slug'  => $post->post_name,
			'image' => self::image( $post->ID ),
		);
		if ( 'cc_service' === $post->post_type ) {
			return array_merge(
				$base,
				array(
					'name'              => $post->post_title,
					'type'              => self::term( $post->ID, 'cc_service_type' ),
					'short_description' => get_post_meta( $post->ID, '_cc_short_description', true ),
				)
			);
		}
		return array_merge(
			$base,
			array(
				'title'    => $post->post_title,
				'summary'  => $post->post_excerpt,
				'date'     => substr( $post->post_date, 0, 10 ),
				'category' => self::term( $post->ID, 'cc_article_category' ),
			)
		);
	}
	/**
	 * Query published items with validated filters and pagination.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $type Type.
	 * @return WP_REST_Response
	 */
	public static function listing( $request, $type ) {
		$service = 'cc_service' === $type;
		$args    = array(
			'post_type'           => $type,
			'post_status'         => 'publish',
			'posts_per_page'      => (int) $request['per_page'],
			'paged'               => (int) $request['page'],
			'orderby'             => $service ? 'title' : 'date',
			'order'               => $service ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
		);
		if ( $service ) {
			$args['s'] = $request['search'] ? $request['search'] : '';
		}
		$filter = $request[ $service ? 'type' : 'category' ];
		if ( $filter ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $service ? 'cc_service_type' : 'cc_article_category',
					'field'    => 'slug',
					'terms'    => $filter,
				),
			);
		}
		$query    = new WP_Query( $args );
		$response = new WP_REST_Response(
			array(
				'items'       => array_map( array( __CLASS__, 'card' ), $query->posts ),
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => (int) $request['page'],
				'per_page'    => (int) $request['per_page'],
			)
		);
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );
		return $response;
	}
	/**
	 * Return a published content detail or a generic not-found error.
	 *
	 * @param string $slug Slug.
	 * @param string $type Type.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function detail( $slug, $type ) {
		$query = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
			)
		);
		if ( ! $query->posts ) {
			return new WP_Error( 'ccs_not_found', __( 'This content was not found.', 'careconnect-services' ), array( 'status' => 404 ) );
		}
		$post                 = $query->posts[0];
		$data                 = self::card( $post );
		$data['content_html'] = wp_kses_post( wpautop( $post->post_content ) );
		if ( 'cc_service' === $type ) {
			foreach ( array( 'eligibility', 'address', 'phone', 'opening_hours', 'last_reviewed' ) as $field ) {
				$data[ $field ] = get_post_meta( $post->ID, '_cc_' . $field, true );
			}
			foreach ( array( 'lat', 'lng' ) as $field ) {
				$value          = get_post_meta( $post->ID, '_cc_' . $field, true );
				$data[ $field ] = '' === $value ? null : (float) $value;
			}
			$ids             = array_slice(
				array_filter(
					array_map( 'absint', (array) get_post_meta( $post->ID, '_cc_related_ids', true ) ),
					static function ( $id ) use ( $post ) {
						return $id && $id !== $post->ID;
					}
				),
				0,
				3
			);
			$data['related'] = $ids ? array_map(
				array( __CLASS__, 'card' ),
				get_posts(
					array(
						'post_type'   => 'cc_service',
						'post_status' => 'publish',
						'post__in'    => $ids,
						'orderby'     => 'post__in',
						'numberposts' => 3,
					)
				)
			) : array();
		}
		return new WP_REST_Response( $data );
	}
	/**
	 * Return types with counts of published services only.
	 *
	 * @return WP_REST_Response
	 */
	public static function types() {
		$result = array();
		$terms  = get_terms(
			array(
				'taxonomy'   => 'cc_service_type',
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$query = new WP_Query(
					array(
						'post_type'      => 'cc_service',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'tax_query'      => array(
							array(
								'taxonomy' => 'cc_service_type',
								'terms'    => $term->term_id,
							),
						),
					)
				);
				if ( $query->found_posts ) {
					$result[] = array(
						'slug'  => $term->slug,
						'name'  => $term->name,
						'count' => (int) $query->found_posts,
					);
				}
			}
		}
		return new WP_REST_Response( $result );
	}
	/**
	 * Return editable home content and published featured items.
	 *
	 * @return WP_REST_Response
	 */
	public static function home() {
		$home     = get_option( 'ccs_home', array() );
		$ids      = array_filter( array_map( 'absint', (array) ( $home['featured_ids'] ?? array() ) ) );
		$featured = $ids ? get_posts(
			array(
				'post_type'   => 'cc_service',
				'post_status' => 'publish',
				'post__in'    => $ids,
				'orderby'     => 'post__in',
				'numberposts' => CCS_Settings::get()['featured_count'],
			)
		) : array();
		$articles = get_posts(
			array(
				'post_type'   => 'cc_article',
				'post_status' => 'publish',
				'numberposts' => 3,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		return new WP_REST_Response(
			array(
				'hero'              => array(
					'title'    => $home['hero_title'] ?? '',
					'subtitle' => $home['hero_subtitle'] ?? '',
				),
				'featured_services' => array_map( array( __CLASS__, 'card' ), $featured ),
				'latest_articles'   => array_map( array( __CLASS__, 'card' ), $articles ),
			)
		);
	}
}

<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Content types are deliberately absent from the core REST API. */
class CCS_CPT {
	/**
	 * Register private content types and editorial taxonomies.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( array(
			'cc_service' => __( 'Services', 'careconnect-services' ),
			'cc_article' => __( 'Articles', 'careconnect-services' ),
			'cc_message' => __( 'Contact messages', 'careconnect-services' ),
		) as $type => $label ) {
			register_post_type(
				$type,
				array(
					'labels'             => array(
						'name'          => $label,
						'singular_name' => $label,
					),
					'public'             => false,
					'show_ui'            => true,
					'show_in_rest'       => false,
					'publicly_queryable' => false,
					'capability_type'    => array( $type, $type . 's' ),
					'map_meta_cap'       => true,
					'supports'           => 'cc_message' === $type ? false : ( 'cc_article' === $type ? array( 'title', 'editor', 'excerpt' ) : array( 'title', 'editor' ) ),
					'menu_icon'          => 'dashicons-heart',
					'capabilities'       => 'cc_message' === $type ? array( 'create_posts' => 'do_not_allow' ) : array(),
				)
			);
		}
		foreach ( array(
			'cc_service_type'     => array( 'cc_service', __( 'Service types', 'careconnect-services' ) ),
			'cc_article_category' => array( 'cc_article', __( 'Article categories', 'careconnect-services' ) ),
		) as $tax => $values ) {
			register_taxonomy(
				$tax,
				$values[0],
				array(
					'label'        => $values[1],
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => false,
					'hierarchical' => false,
					'meta_box_cb'  => false,
					'capabilities' => array(
						'manage_terms' => 'ccs_manage_settings',
						'edit_terms'   => 'ccs_manage_settings',
						'delete_terms' => 'ccs_manage_settings',
						'assign_terms' => 'edit_' . $values[0] . 's',
					),
				)
			);
		}
	}
}

<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Editorial review dates are visible as text and colour. */
class CCS_Admin_Columns {
	/**
	 * Return the date twelve months before the current site date.
	 *
	 * @return string
	 */
	public static function cutoff() {
		return current_datetime()->modify( '-12 months' )->format( 'Y-m-d' );
	}
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_filter(
			'manage_cc_service_posts_columns',
			static function ( $cols ) {
				$cols['ccs_type']     = __( 'Type', 'careconnect-services' );
				$cols['ccs_reviewed'] = __( 'Last reviewed', 'careconnect-services' );
				return $cols;
			}
		);
		add_filter(
			'manage_edit-cc_service_sortable_columns',
			static function ( $cols ) {
				$cols['ccs_reviewed'] = 'ccs_reviewed';
				return $cols;
			}
		);
		add_action(
			'manage_cc_service_posts_custom_column',
			static function ( $column, $id ) {
				if ( 'ccs_type' === $column ) {
					$terms = wp_get_object_terms( $id, 'cc_service_type', array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $terms ) ) {
						echo esc_html( implode( ', ', $terms ) );
					}
				}
				if ( 'ccs_reviewed' === $column ) {
					$date = get_post_meta( $id, '_cc_last_reviewed', true );
					echo esc_html( $date );
					if ( $date && $date < self::cutoff() ) {
						echo ' <span class="ccs-overdue">' . esc_html__( 'Overdue', 'careconnect-services' ) . '</span>';
					}
				}
			},
			10,
			2
		);
		add_action(
			'restrict_manage_posts',
			static function ( $type ) {
				if ( 'cc_service' !== $type ) {
					return;
				}
				$selected = isset( $_GET['ccs_review'] ) ? sanitize_key( $_GET['ccs_review'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
				echo '<label class="screen-reader-text" for="ccs_review">' . esc_html__( 'Review date', 'careconnect-services' ) . '</label><select name="ccs_review" id="ccs_review">';
				foreach ( array(
					''        => __( 'All review dates', 'careconnect-services' ),
					'recent'  => __( 'Reviewed in last 12 months', 'careconnect-services' ),
					'overdue' => __( 'Older than 12 months', 'careconnect-services' ),
				) as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			}
		);
		add_action(
			'pre_get_posts',
			static function ( $query ) {
				if ( ! is_admin() || ! $query->is_main_query() || 'cc_service' !== $query->get( 'post_type' ) ) {
					return;
				}
				if ( 'ccs_reviewed' === $query->get( 'orderby' ) ) {
					$query->set( 'meta_key', '_cc_last_reviewed' );
					$query->set( 'orderby', 'meta_value' );
					$query->set( 'meta_type', 'DATE' );
				}
				$review = isset( $_GET['ccs_review'] ) ? sanitize_key( $_GET['ccs_review'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
				if ( in_array( $review, array( 'recent', 'overdue' ), true ) ) {
					$query->set(
						'meta_query',
						array(
							array(
								'key'     => '_cc_last_reviewed',
								'value'   => self::cutoff(),
								'compare' => 'recent' === $review ? '>=' : '<',
								'type'    => 'DATE',
							),
						)
					);
				}
			}
		);
		add_filter(
			'post_class',
			static function ( $classes, $css, $id ) {
				$date = get_post_meta( $id, '_cc_last_reviewed', true );
				if ( is_admin() && 'cc_service' === get_post_type( $id ) && $date && $date < self::cutoff() ) {
					$classes[] = 'ccs-review-overdue';
				}
				return $classes;
			},
			10,
			3
		);
		add_action(
			'admin_enqueue_scripts',
			static function () {
				$screen = get_current_screen();
				if ( $screen && in_array( $screen->post_type, array( 'cc_service', 'cc_article' ), true ) ) {
					wp_enqueue_style( 'ccs-admin', CCS_URL . 'assets/admin.css', array(), CCS_VERSION );
					wp_enqueue_script( 'ccs-admin', CCS_URL . 'assets/admin.js', array(), CCS_VERSION, true );
				}
			}
		);
	}
}

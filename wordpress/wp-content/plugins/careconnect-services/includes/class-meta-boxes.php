<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Validation is shared by the admin UI and the publish gate. */
class CCS_Meta_Boxes {
	/**
	 * Register the WordPress hooks for this feature.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_cc_service', array( __CLASS__, 'save' ) );
		add_action( 'save_post_cc_article', array( __CLASS__, 'save' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'publish_gate' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'add_meta_boxes_cc_message', array( __CLASS__, 'message_box' ) );
	}
	/**
	 * Return the permitted meta fields for this content type.
	 *
	 * @param string $type Type.
	 * @return string[]
	 */
	public static function fields( $type ) {
		$fields = array( 'image_url', 'image_alt' );
		return 'cc_service' === $type ? array_merge( array( 'short_description', 'eligibility', 'address', 'phone', 'opening_hours' ), $fields, array( 'related_ids', 'last_reviewed', 'lat', 'lng' ) ) : $fields;
	}
	/**
	 * Sanitize a registered metadata value.
	 *
	 * @param mixed  $value Value.
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function clean( $value, $key = '' ) {
		$field = str_replace( '_cc_', '', $key );
		if ( 'related_ids' === $field ) {
			return array_values( array_unique( array_map( 'absint', is_array( $value ) ? $value : array() ) ) );
		}
		if ( in_array( $field, array( 'lat', 'lng' ), true ) ) {
			return '' === $value ? '' : (float) $value;
		}
		if ( 'image_url' === $field ) {
			return esc_url_raw( $value );
		}
		if ( 'phone' === $field ) {
			$phone = self::phone( $value );
			return false !== $phone ? $phone : sanitize_text_field( $value );
		}
		return in_array( $field, array( 'short_description', 'eligibility', 'opening_hours' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
	/**
	 * Register private metadata and its sanitization callbacks.
	 *
	 * @return void
	 */
	public static function register_meta() {
		foreach ( array( 'cc_service', 'cc_article' ) as $type ) {
			foreach ( self::fields( $type ) as $field ) {
				register_post_meta(
					$type,
					'_cc_' . $field,
					array(
						'type'              => 'related_ids' === $field ? 'array' : ( in_array( $field, array( 'lat', 'lng' ), true ) ? 'number' : 'string' ),
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => array( __CLASS__, 'clean' ),
						'auth_callback'     => static function ( $allowed, $key, $id ) {
							return current_user_can( 'edit_post', $id );
						},
					)
				);
			}
		}
	}
	/**
	 * Validate and normalize a Singapore telephone number.
	 *
	 * @param mixed $value Value.
	 * @return string|false
	 */
	public static function phone( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\+65[\s-]?)?[3689]\d{3}[\s-]?\d{4}$/', $value ) ) {
			return false;
		}
		$digits = preg_replace( '/\D/', '', $value );
		$digits = substr( $digits, -8 );
		return '+65 ' . substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4 );
	}
	/**
	 * Accept HTTPS URLs and safe root-relative image paths.
	 *
	 * @param string $url Url.
	 * @return bool
	 */
	public static function valid_image( $url ) {
		return '' === $url || ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) && ! str_contains( $url, '\\' ) ) || ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && wp_parse_url( $url, PHP_URL_HOST ) );
	}
	/**
	 * Collect field errors without persisting invalid values.
	 *
	 * @param array  $values Values.
	 * @param string $type Type.
	 * @param int    $id Id.
	 * @return array
	 */
	public static function validate( $values, $type, $id = 0 ) {
		$errors = array();
		$url    = $values['image_url'] ?? '';
		if ( ! self::valid_image( $url ) ) {
			$errors['image_url'] = __( 'Image URL must use HTTPS or a root-relative path.', 'careconnect-services' );
		}
		if ( $url && empty( $values['image_alt'] ) ) {
			$errors['image_alt'] = __( 'Add meaningful alt text for the image.', 'careconnect-services' );
		}
		if ( empty( $values['term'] ) || ! term_exists( (int) $values['term'], 'cc_service' === $type ? 'cc_service_type' : 'cc_article_category' ) ) {
			$errors['term'] = __( 'Choose one valid type or category.', 'careconnect-services' );
		}
		if ( 'cc_service' !== $type ) {
			return $errors;
		}
		if ( empty( $values['short_description'] ) || mb_strlen( $values['short_description'] ) > 200 ) {
			$errors['short_description'] = __( 'Short description is required and must be at most 200 characters.', 'careconnect-services' );
		}
		if ( empty( $values['address'] ) ) {
			$errors['address'] = __( 'Address is required.', 'careconnect-services' );
		}
		if ( ! self::phone( $values['phone'] ?? '' ) ) {
			$errors['phone'] = __( 'Enter a valid Singapore phone number.', 'careconnect-services' );
		}
		$date   = $values['last_reviewed'] ?? '';
		$parsed = DateTime::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date || $date > current_datetime()->format( 'Y-m-d' ) ) {
			$errors['last_reviewed'] = __( 'Last reviewed must be a valid date, no later than today.', 'careconnect-services' );
		}
		$related = $values['related_ids'] ?? array();
		if ( count( $related ) > 3 ) {
			$errors['related_ids'] = __( 'Choose at most three other published services.', 'careconnect-services' );
		}
		foreach ( $related as $related_id ) {
			if ( $related_id === $id || 'cc_service' !== get_post_type( $related_id ) || 'publish' !== get_post_status( $related_id ) ) {
				$errors['related_ids'] = __( 'Related services must be other published services.', 'careconnect-services' );
			}
		}
		foreach ( array(
			'lat' => 90,
			'lng' => 180,
		) as $field => $limit ) {
			$value = $values[ $field ] ?? '';
			if ( '' !== $value && ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || abs( (float) $value ) > $limit ) ) {
				$errors[ $field ] = __( 'Coordinates are outside the valid range.', 'careconnect-services' );
			}
		}
		return $errors;
	}
	/**
	 * Verify the content-specific form nonce.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	private static function submitted( $type ) {
		$kind = 'cc_service' === $type ? 'service' : 'article';
		return isset( $_POST[ 'ccs_' . $kind . '_nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'ccs_' . $kind . '_nonce' ] ) ), 'ccs_save_' . $kind );
	}
	/**
	 * Read sanitized form values or the stored content values.
	 *
	 * @param int    $id Id.
	 * @param string $type Type.
	 * @param bool   $submitted Submitted.
	 * @return array
	 */
	private static function values( $id, $type, $submitted ) {
		$values = array();
		foreach ( self::fields( $type ) as $field ) {
			$raw = $submitted ? ( isset( $_POST[ 'ccs_' . $field ] ) ? wp_unslash( $_POST[ 'ccs_' . $field ] ) : ( 'related_ids' === $field ? array() : '' ) ) : get_post_meta( $id, '_cc_' . $field, true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by submitted(); fields sanitized immediately below.
			// Preserve invalid numeric/URL input for validation before any lossy sanitization.
			$values[ $field ] = in_array( $field, array( 'lat', 'lng', 'image_url' ), true ) ? sanitize_text_field( $raw ) : self::clean( $raw, '_cc_' . $field );
			if ( 'related_ids' === $field && ! is_array( $values[ $field ] ) ) {
				$values[ $field ] = array();
			}
		}
		$taxonomy       = 'cc_service' === $type ? 'cc_service_type' : 'cc_article_category';
		$terms          = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
		$values['term'] = $submitted ? absint( $_POST['ccs_term'] ?? 0 ) : ( ! is_wp_error( $terms ) && 1 === count( $terms ) ? $terms[0] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by submitted(); fields sanitized immediately below.
		return $values;
	}
	/**
	 * Prevent invalid content from being published or scheduled.
	 *
	 * @param array $data Data.
	 * @param array $postarr Postarr.
	 * @return array
	 */
	public static function publish_gate( $data, $postarr ) {
		if ( ! in_array( $data['post_type'], array( 'cc_service', 'cc_article' ), true ) || ( ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) && ! self::submitted( $data['post_type'] ) ) ) {
			return $data;
		}
		$id     = absint( $postarr['ID'] ?? 0 );
		$errors = self::validate( self::values( $id, $data['post_type'], self::submitted( $data['post_type'] ) ), $data['post_type'], $id );
		if ( $errors ) {
			$data['post_status'] = 'draft';
			set_transient( 'ccs_errors_' . get_current_user_id(), array_values( $errors ), 60 );
		}
		return $data;
	}
	/**
	 * Save permitted values after nonce and capability checks.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public static function save( $id ) {
		$type = get_post_type( $id );
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || ! self::submitted( $type ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$values = self::values( $id, $type, true );
		$errors = self::validate( $values, $type, $id );
		foreach ( self::fields( $type ) as $field ) {
			if ( ! isset( $errors[ $field ] ) ) {
				update_post_meta( $id, '_cc_' . $field, self::clean( $values[ $field ], '_cc_' . $field ) );
			}
		}
		if ( ! isset( $errors['term'] ) ) {
			wp_set_object_terms( $id, array( (int) $values['term'] ), 'cc_service' === $type ? 'cc_service_type' : 'cc_article_category' );
		}
		if ( $errors ) {
			set_transient( 'ccs_errors_' . get_current_user_id(), array_values( $errors ), 60 );
		}
	}
	/**
	 * Display and clear the current user’s validation errors.
	 *
	 * @return void
	 */
	public static function notices() {
		$errors = get_transient( 'ccs_errors_' . get_current_user_id() );
		if ( ! $errors ) {
			return;
		}
		delete_transient( 'ccs_errors_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Content kept as a draft. Please correct:', 'careconnect-services' ) . '</p><ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}
	/**
	 * Register the service and article detail forms.
	 *
	 * @return void
	 */
	public static function boxes() {
		add_meta_box( 'ccs-service', __( 'Service details', 'careconnect-services' ), array( __CLASS__, 'render' ), 'cc_service' );
		add_meta_box( 'ccs-article', __( 'Article details', 'careconnect-services' ), array( __CLASS__, 'render' ), 'cc_article' );
	}
	/**
	 * Render the authorized administration or shortcode interface.
	 *
	 * @param WP_Post $post Post.
	 * @return mixed
	 */
	public static function render( $post ) {
		$type = $post->post_type;
		$kind = 'cc_service' === $type ? 'service' : 'article';
		wp_nonce_field( 'ccs_save_' . $kind, 'ccs_' . $kind . '_nonce' );
		$values = self::values( $post->ID, $type, false );
		$labels = array(
			'short_description' => __( 'Short description (required, maximum 200 characters)', 'careconnect-services' ),
			'eligibility'       => __( 'Eligibility', 'careconnect-services' ),
			'address'           => __( 'Address (required)', 'careconnect-services' ),
			'phone'             => __( 'Phone (required)', 'careconnect-services' ),
			'opening_hours'     => __( 'Opening hours', 'careconnect-services' ),
			'image_url'         => __( 'Image URL', 'careconnect-services' ),
			'image_alt'         => __( 'Image alt text (required with an image)', 'careconnect-services' ),
			'related_ids'       => __( 'Related services (maximum three)', 'careconnect-services' ),
			'last_reviewed'     => __( 'Last reviewed (required)', 'careconnect-services' ),
			'lat'               => __( 'Latitude', 'careconnect-services' ),
			'lng'               => __( 'Longitude', 'careconnect-services' ),
		);
		echo '<p><label for="ccs_term">' . esc_html( 'service' === $kind ? __( 'Service type (required)', 'careconnect-services' ) : __( 'Category (required)', 'careconnect-services' ) ) . '</label><br><select id="ccs_term" name="ccs_term"><option value="">' . esc_html__( 'Choose one', 'careconnect-services' ) . '</option>';
		$terms = get_terms(
			array(
				'taxonomy'   => 'service' === $kind ? 'cc_service_type' : 'cc_article_category',
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $values['term'], $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select></p>';
		foreach ( self::fields( $type ) as $field ) {
			$name = 'ccs_' . $field;
			echo '<p><label for="' . esc_attr( $name ) . '">' . esc_html( $labels[ $field ] ) . '</label><br>';
			if ( 'related_ids' === $field ) {
				echo '<select multiple id="ccs_related_ids" name="ccs_related_ids[]">';
				foreach ( get_posts(
					array(
						'post_type'    => 'cc_service',
						'post_status'  => 'publish',
						'numberposts'  => -1,
						'post__not_in' => array( $post->ID ),
						'orderby'      => 'title',
						'order'        => 'ASC',
					)
				) as $other ) {
					echo '<option value="' . esc_attr( $other->ID ) . '" ' . selected( in_array( $other->ID, $values[ $field ], true ), true, false ) . '>' . esc_html( $other->post_title ) . '</option>';
				}
				echo '</select>';
			} elseif ( in_array( $field, array( 'short_description', 'eligibility', 'opening_hours' ), true ) ) {
				echo '<textarea class="widefat" rows="3" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ' . ( 'short_description' === $field ? 'maxlength="200" aria-describedby="ccs-counter"' : '' ) . '>' . esc_textarea( $values[ $field ] ) . '</textarea>';
				if ( 'short_description' === $field ) {
					echo '<output id="ccs-counter" for="ccs_short_description">' . esc_html( mb_strlen( $values[ $field ] ) . ' / 200' ) . '</output>';
				}
			} else {
				echo '<input class="widefat" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( 'last_reviewed' === $field ? 'date' : ( in_array( $field, array( 'lat', 'lng' ), true ) ? 'number' : 'text' ) ) . '" step="any" value="' . esc_attr( $values[ $field ] ) . '">';
			}
			echo '</p>';
		}
	}
	/**
	 * Display administrator-only contact details as read-only text.
	 *
	 * @return void
	 */
	public static function message_box() {
		add_meta_box(
			'ccs-message',
			__( 'Message', 'careconnect-services' ),
			static function ( $post ) {
				if ( ! current_user_can( 'read_post', $post->ID ) ) {
					return;
				}
				echo '<p>' . esc_html( $post->post_title ) . '</p><p>' . esc_html( get_post_meta( $post->ID, '_cc_email', true ) ) . '</p><p>' . nl2br( esc_html( $post->post_content ) ) . '</p>';
			},
			'cc_message'
		);
	}
}

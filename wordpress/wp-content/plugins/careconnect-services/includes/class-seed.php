<?php
/**
 * CareConnect services.
 *
 * @package careconnect-services
 */

defined( 'ABSPATH' ) || exit;

/** Fictional, repeatable sample content; never runs during activation. */
class CCS_Seed {
	/**
	 * Find or create a seed taxonomy term.
	 *
	 * @param string $name Name.
	 * @param string $slug Slug.
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 * @throws RuntimeException When a seed term cannot be created.
	 */
	private static function term( $name, $slug, $taxonomy ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term ) {
			return $term->term_id;
		}
		$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( esc_html( $result->get_error_message() ) );
		}
		return $result['term_id'];
	}
	/**
	 * Create or reset a fictional seed post as a draft.
	 *
	 * @param string $type Type.
	 * @param string $title Title.
	 * @param string $content Content.
	 * @param string $excerpt Excerpt.
	 * @param string $date Date.
	 * @return int
	 * @throws RuntimeException When a seed post cannot be created.
	 */
	private static function post( $type, $title, $content, $excerpt = '', $date = '' ) {
		$slug = sanitize_title( $title );
		$old  = get_page_by_path( $slug, OBJECT, $type );
		$data = array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		);
		if ( $old ) {
			$data['ID'] = $old->ID;
		}
		if ( $date ) {
			$data['post_date']     = $date;
			$data['post_date_gmt'] = get_gmt_from_date( $date );
		}
		$id = wp_insert_post( $data, true );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( esc_html( $id->get_error_message() ) );
		}
		return $id;
	}
	/**
	 * Populate the fictional content once, or reset it explicitly.
	 *
	 * @param bool $force Force.
	 * @return bool
	 */
	public static function run( $force = false ) {
		if ( get_option( 'ccs_seeded' ) && ! $force ) {
			return false;
		}
		$types   = array(
			'home-care'        => self::term( __( 'Home Care', 'careconnect-services' ), 'home-care', 'cc_service_type' ),
			'community-clinic' => self::term( __( 'Community Clinic', 'careconnect-services' ), 'community-clinic', 'cc_service_type' ),
			'senior-activity'  => self::term( __( 'Senior Activity', 'careconnect-services' ), 'senior-activity', 'cc_service_type' ),
		);
		$records = array(
			array( 'Sunrise Home Visit Programme', 'home-care', 2, 'Friendly support for everyday routines, brought to your home.' ),
			array( 'Evergreen Meal Delivery', 'home-care', 5, 'Balanced meals and a friendly check-in for older neighbours.' ),
			array( 'Maple Respite Support', 'home-care', 14, 'Short breaks and practical support for family caregivers.' ),
			array( 'Harbour Community Clinic', 'community-clinic', 3, 'A welcoming place for everyday health checks and advice.' ),
			array( 'Lantern Health Screening Hub', 'community-clinic', 8, 'Simple health screenings with guidance on your next steps.' ),
			array( 'Willow Rehabilitation Corner', 'community-clinic', 18, 'Guided movement sessions to support confidence and independence.' ),
			array( 'Bamboo Seniors Activity Centre', 'senior-activity', 1, 'Make connections through gentle activities and shared interests.' ),
			array( 'Coral Weekend Wellness Club', 'senior-activity', 10, 'Spend a relaxed weekend moving, learning and meeting neighbours.' ),
			array( 'Pebble Digital Skills Corner', 'senior-activity', 4, 'Learn everyday digital skills at a comfortable, supported pace.' ),
		);
		$ids     = array();
		foreach ( $records as $index => $row ) {
			list( $title, $type, $months, $short ) = $row;
			$body                                  = $short . "\n\n" . __( 'Our fictional community team takes time to understand your needs. Contact the programme to discuss a first visit, available support and any eligibility requirements.', 'careconnect-services' );
			$id                                    = self::post( 'cc_service', $title, $body );
			$number                                = $index + 1;
			$meta                                  = array(
				'short_description' => $short,
				'eligibility'       => __( 'For older residents and caregivers seeking community support; please contact the team to discuss suitability.', 'careconnect-services' ),
				'address'           => $number . ' Example Lane, Singapore 0000' . sprintf( '%02d', $number ),
				'phone'             => '+65 6555 01' . sprintf( '%02d', $number ),
				'opening_hours'     => "Monday–Friday: 9:00 am–5:00 pm\nSaturday: 9:00 am–12:00 pm\nSunday: Closed",
				'image_url'         => '/images/seed/' . $type . '.svg',
				'image_alt'         => array(
					'home-care'        => __( 'Illustration of a welcoming home with a heart', 'careconnect-services' ),
					'community-clinic' => __( 'Illustration of a community clinic with a medical cross', 'careconnect-services' ),
					'senior-activity'  => __( 'Illustration of neighbours enjoying a community garden', 'careconnect-services' ),
				)[ $type ],
				'last_reviewed'     => current_datetime()->modify( '-' . $months . ' months' )->format( 'Y-m-d' ),
				'related_ids'       => array(),
				'lat'               => '',
				'lng'               => '',
			);
			if ( in_array( $index, array( 0, 3, 6 ), true ) ) {
				$meta['lat'] = 1.30 + $index * 0.005;
				$meta['lng'] = 103.80 + $index * 0.005;
			}
			foreach ( $meta as $key => $value ) {
				update_post_meta( $id, '_cc_' . $key, $value );
			}
			wp_set_object_terms( $id, array( (int) $types[ $type ] ), 'cc_service_type' );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
			$ids[] = $id;
		}
		foreach ( $ids as $index => $id ) {
			update_post_meta( $id, '_cc_related_ids', array( $ids[ ( $index + 1 ) % count( $ids ) ], $ids[ ( $index + 2 ) % count( $ids ) ] ) );
		}
		$guides    = self::term( __( 'Guides', 'careconnect-services' ), 'guides', 'cc_article_category' );
		$wellbeing = self::term( __( 'Wellbeing', 'careconnect-services' ), 'wellbeing', 'cc_article_category' );
		$articles  = array(
			array( 'Five tips for choosing a care service', 'Start with your needs, ask questions and take time to find support that feels right.', "Write down the everyday tasks you would like support with. Consider what matters to you, including accessibility, opening hours and the way you prefer to communicate.\n\nAsk the service team about eligibility, fees, waiting times and a first visit. Bring a trusted person if that helps you feel comfortable.\n\nReview your choices together and take your time. This fictional guide offers general information and does not replace professional advice.", $guides ),
			array( 'How to prepare for a first clinic visit', 'A little preparation can help you feel more comfortable at your first appointment.', "Before your visit, make a short list of questions and any changes you have noticed. Check the clinic opening hours and how to get there.\n\nAsk the team which documents or medication information to bring. Let them know about any access or communication support you would find useful.\n\nAt the end of your visit, ask about the next steps and write them down. This is a fictional information guide; speak with a qualified clinician about your needs.", $guides ),
			array( 'Staying active: gentle exercises at home', 'Explore small, comfortable ways to add movement to your day.', "Choose a clear space and a stable chair. Comfortable movement can be part of an everyday routine, and everyone starts from a different place.\n\nAsk a qualified professional which activities are suitable for you before starting a new routine. Move at a pace that feels comfortable and stop if you feel unwell.\n\nA friend or caregiver may help you plan a routine you enjoy. This fictional article provides general information rather than individual exercise advice.", $wellbeing ),
			array( 'Understanding eligibility for community programmes', 'Learn which questions to ask when exploring a community programme.', "Community programmes may have different eligibility criteria. A conversation with the team is a useful place to start.\n\nAsk about age groups, referral requirements, catchment areas and any supporting documents. You can also ask what other options are available if a programme is not suitable.\n\nKeep a note of the information shared and whom to contact next. All programmes and details in this directory are fictional.", $guides ),
		);
		foreach ( $articles as $index => $row ) {
			$id = self::post( 'cc_article', $row[0], $row[2], $row[1], current_datetime()->modify( '-' . ( $index + 1 ) . ' weeks' )->format( 'Y-m-d H:i:s' ) );
			update_post_meta( $id, '_cc_image_url', '/images/seed/article.svg' );
			update_post_meta( $id, '_cc_image_alt', __( 'Illustration of an open community care guide', 'careconnect-services' ) );
			wp_set_object_terms( $id, array( (int) $row[3] ), 'cc_article_category' );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}
		update_option(
			'ccs_home',
			array(
				'hero_title'    => __( 'A little support. A stronger community.', 'careconnect-services' ),
				'hero_subtitle' => __( 'Find community care, practical support and welcoming spaces for you and the people you care for.', 'careconnect-services' ),
				'featured_ids'  => array_slice( $ids, 0, 3 ),
			)
		);
		update_option( 'ccs_seeded', 1 );
		return true;
	}
}

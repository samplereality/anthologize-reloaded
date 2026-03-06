<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Anthologize_Ajax_Handlers' ) ) :

	require_once __DIR__ . '/class-project-organizer.php';

	class Anthologize_Ajax_Handlers {

		/** @var Anthologize_Project_Organizer|null */
		private $project_organizer = null;

		public function __construct() {
			$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;

			$this->project_organizer = new Anthologize_Project_Organizer( $project_id );

			add_action( 'wp_ajax_get_filterby_terms', array( $this, 'get_filterby_terms' ) );
			add_action( 'wp_ajax_anthologize_get_posts_by', array( $this, 'get_posts_by' ) );
			add_action( 'wp_ajax_place_item', array( $this, 'place_item' ) );
			add_action( 'wp_ajax_place_items', array( $this, 'place_items' ) );
			add_action( 'wp_ajax_merge_items', array( $this, 'merge_items' ) );
			add_action( 'wp_ajax_get_project_meta', array( $this, 'fetch_project_meta' ) );
			add_action( 'wp_ajax_get_item_comments', array( $this, 'get_item_comments' ) );
			add_action( 'wp_ajax_include_comments', array( $this, 'include_comments' ) );
			add_action( 'wp_ajax_include_all_comments', array( $this, 'include_all_comments' ) );
		}

		/**
		 * Verify the AJAX nonce and check user capability.
		 *
		 * @param string $capability The capability to check. Default 'edit_pages'.
		 */
		private function verify_request( $capability = 'edit_pages' ) {
			check_ajax_referer( 'anthologize_ajax', 'nonce' );

			if ( ! current_user_can( $capability ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'anthologize' ) ), 403 );
			}
		}

		public function get_filterby_terms() {
			$this->verify_request();

			$filtertype = isset( $_POST['filtertype'] ) ? sanitize_key( $_POST['filtertype'] ) : '';
			$terms      = array();

			switch ( $filtertype ) {
				case 'category':
					$cats = get_categories();
					foreach ( $cats as $cat ) {
						$terms[ $cat->term_id ] = $cat->name;
					}
					break;

				case 'tag':
					$tags = get_tags();
					if ( is_array( $tags ) ) {
						foreach ( $tags as $tag ) {
							$terms[ $tag->slug ] = $tag->name;
						}
					}
					break;

				case 'post_type':
					$terms = $this->project_organizer->available_post_types();
					break;
			}

			$terms = apply_filters( 'anth_get_posts_by', $terms, $filtertype );

			wp_send_json_success( $terms );
		}

		public function get_posts_by() {
			$this->verify_request();

			$filterby = isset( $_POST['filterby'] ) ? sanitize_key( $_POST['filterby'] ) : '';

			$submitted_orderby = isset( $_POST['orderby'] ) ? sanitize_key( $_POST['orderby'] ) : 'title_asc';

			$orderby_settings = Anthologize_Project_Organizer::get_orderby_settings( $submitted_orderby );

			$orderby = $orderby_settings['orderby'];
			$order   = $orderby_settings['order'];

			$args = array(
				'post_type'            => array_keys( $this->project_organizer->available_post_types() ),
				'posts_per_page'       => -1,
				'orderby'              => $orderby,
				'order'                => $order,
				'post_status'          => $this->project_organizer->source_item_post_statuses(),
				'is_anthologize_query' => true,
			);

			switch ( $filterby ) {
				case 'date':
					$date_query = array();

					if ( isset( $_POST['startdate'] ) ) {
						$date_query[] = array(
							'after' => sanitize_text_field( wp_unslash( $_POST['startdate'] ) ),
						);
					}

					if ( isset( $_POST['enddate'] ) ) {
						$date_query[] = array(
							'before' => sanitize_text_field( wp_unslash( $_POST['enddate'] ) ),
						);
					}

					if ( $date_query ) {
						$args['date_query'] = $date_query;
					}

					break;

				case 'tag':
					$args['tag'] = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
					break;

				case 'category':
					$args['cat'] = isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0;
					break;

				case 'post_type':
					$term = isset( $_POST['term'] ) ? sanitize_key( $_POST['term'] ) : '';
					if ( '' !== $term ) {
						$args['post_type'] = $term;
					}
					break;
			}

			$posts = new WP_Query( apply_filters( 'anth_get_posts_by_query', $args, $filterby ) );

			$the_posts = array();
			while ( $posts->have_posts() ) {
				$posts->the_post();

				$post_data   = array(
					'title'    => get_the_title(),
					'metadata' => Anthologize_Project_Organizer::get_item_metadata( get_the_ID() ),
					'ID'       => get_the_ID(),
				);
				$the_posts[] = $post_data;
			}

			wp_reset_postdata();

			$the_posts = apply_filters( 'anth_get_posts_by', $the_posts, $filterby );

			wp_send_json_success( $the_posts );
		}

		public function place_item() {
			$this->verify_request();

			global $wpdb;

			$project_id     = absint( $_POST['project_id'] );
			$post_id        = absint( $_POST['post_id'] );
			$dest_part_id   = absint( $_POST['dest_id'] );
			$dest_seq       = wp_unslash( $_POST['dest_seq'] );
			$dest_seq_array = json_decode( $dest_seq, true );
			if ( null === $dest_seq_array ) {
				wp_send_json_error( array( 'message' => __( 'Invalid sequence data.', 'anthologize' ) ), 400 );
			}

			$dest_seq_array = array_map( 'absint', $dest_seq_array );

			if ( 'true' === sanitize_text_field( $_POST['new_post'] ) ) {
				$new_item      = true;
				$src_part_id   = false;
				$src_seq_array = false;
			} else {
				$new_item      = false;
				$src_part_id   = absint( $_POST['src_id'] );
				$src_seq       = wp_unslash( $_POST['src_seq'] );
				$src_seq_array = json_decode( $src_seq, true );
				if ( null === $src_seq_array ) {
					wp_send_json_error( array( 'message' => __( 'Invalid sequence data.', 'anthologize' ) ), 400 );
				}
				$src_seq_array = array_map( 'absint', $src_seq_array );
			}

			$insert_result_id = $this->project_organizer->insert_item( $project_id, $post_id, $new_item, $dest_part_id, $src_part_id, $dest_seq_array, $src_seq_array );

			if ( false === $insert_result_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to insert item.', 'anthologize' ) ), 500 );
			}

			if ( true === $new_item ) {
				$dest_seq_array[ $insert_result_id ] = isset( $dest_seq_array['new_new_new'] ) ? $dest_seq_array['new_new_new'] : 0;
				unset( $dest_seq_array['new_new_new'] );
			}

			$this->project_organizer->rearrange_items( $dest_seq_array );

			$comment_count = $wpdb->get_var( $wpdb->prepare( "SELECT comment_count FROM $wpdb->posts WHERE ID = %d", $post_id ) );

			$insert_result = array(
				array(
					'post_id'       => $insert_result_id,
					'comment_count' => (int) $comment_count,
				),
			);

			wp_send_json_success( $insert_result );
		}

		public function place_items() {
			$this->verify_request();

			global $wpdb;

			$project_id = absint( $_POST['project_id'] );

			$post_ids       = wp_unslash( $_POST['post_ids'] );
			$post_ids_array = json_decode( $post_ids, true );

			if ( ! is_array( $post_ids_array ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid post IDs.', 'anthologize' ) ), 400 );
			}

			$dest_part_id   = absint( $_POST['dest_id'] );
			$dest_seq       = wp_unslash( $_POST['dest_seq'] );
			$dest_seq_array = json_decode( $dest_seq, true );
			if ( null === $dest_seq_array ) {
				wp_send_json_error( array( 'message' => __( 'Invalid sequence data.', 'anthologize' ) ), 400 );
			}

			$new_item      = true;
			$src_part_id   = false;
			$src_seq_array = false;

			$ret_ids = array();
			foreach ( $post_ids_array as $position => $post_id ) {
				$pidarray = explode( '-', sanitize_text_field( $post_id ) );
				$post_id  = absint( array_pop( $pidarray ) );

				$insert_result = $this->project_organizer->insert_item( $project_id, $post_id, $new_item, $dest_part_id, $src_part_id, $dest_seq_array, $src_seq_array );
				if ( false === $insert_result ) {
					wp_send_json_error( array( 'message' => __( 'Failed to insert item.', 'anthologize' ) ), 500 );
				}

				$dest_seq_array[ $insert_result ] = isset( $dest_seq_array[ $post_id ] ) ? $dest_seq_array[ $post_id ] : 0;
				unset( $dest_seq_array[ $post_id ] );

				$comment_count = $wpdb->get_var( $wpdb->prepare( "SELECT comment_count FROM $wpdb->posts WHERE ID = %d", $post_id ) );

				$ret_ids[] = array(
					'post_id'       => $insert_result,
					'comment_count' => (int) $comment_count,
					'original_id'   => $post_id,
				);
			}
			$this->project_organizer->rearrange_items( $dest_seq_array );

			wp_send_json_success( $ret_ids );
		}

		public function merge_items() {
			$this->verify_request();

			$project_id = absint( $_POST['project_id'] );
			$post_id    = absint( $_POST['post_id'] );

			if ( is_array( $_POST['child_post_ids'] ) ) {
				$child_post_ids = array_map( 'absint', $_POST['child_post_ids'] );
			} else {
				$child_post_ids = array( absint( $_POST['child_post_ids'] ) );
			}

			$new_seq       = wp_unslash( $_POST['new_seq'] );
			$new_seq_array = json_decode( $new_seq, true );
			if ( null === $new_seq_array ) {
				wp_send_json_error( array( 'message' => __( 'Invalid sequence data.', 'anthologize' ) ), 400 );
			}

			$append_result = $this->project_organizer->append_children( $post_id, $child_post_ids );

			if ( false === $append_result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to merge items.', 'anthologize' ) ), 500 );
			}

			$this->project_organizer->rearrange_items( $new_seq_array );

			wp_send_json_success();
		}

		public function fetch_project_meta() {
			$this->verify_request();

			$project_id = absint( $_POST['proj_id'] );

			if ( ! $project_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid project ID.', 'anthologize' ) ), 400 );
			}

			$options = get_post_meta( $project_id, 'anthologize_meta', true );

			if ( $options ) {
				wp_send_json_success( $options );
			} else {
				wp_send_json_success( 'none' );
			}
		}

		public function get_item_comments() {
			$this->verify_request();

			$item_id = ! empty( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : false;

			if ( ! is_numeric( $item_id ) ) {
				$i       = explode( '-', $item_id );
				$item_id = isset( $i[1] ) ? absint( $i[1] ) : 0;
			} else {
				$item_id = absint( $item_id );
			}

			if ( ! $item_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid item ID.', 'anthologize' ) ), 400 );
			}

			$anth_meta        = get_post_meta( $item_id, 'anthologize_meta', true );
			$original_post_id = isset( $anth_meta['original_post_id'] ) ? absint( $anth_meta['original_post_id'] ) : false;

			if ( ! $original_post_id ) {
				wp_send_json_error( array( 'message' => __( 'Original post not found.', 'anthologize' ) ), 404 );
			}

			$comments = get_comments( array( 'post_id' => $original_post_id ) );

			foreach ( $comments as $comment ) {
				if ( ! empty( $anth_meta['included_comments'] ) && in_array( $comment->comment_ID, $anth_meta['included_comments'] ) ) {
					$comment->is_included = 1;
				} else {
					$comment->is_included = 0;
				}
			}

			if ( empty( $comments ) ) {
				wp_send_json_success( array(
					'empty' => '1',
					'text'  => __( 'This post has no comments.', 'anthologize' ),
				) );
			}

			wp_send_json_success( $comments );
		}

		public function include_comments() {
			$this->verify_request();

			$comment_id = ! empty( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
			$post_id    = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

			$action = ! empty( $_POST['check_action'] ) && 'add' === sanitize_key( $_POST['check_action'] ) ? 'add' : 'remove';

			if ( empty( $post_id ) || empty( $comment_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Missing required data.', 'anthologize' ) ), 400 );
			}

			require_once ANTHOLOGIZE_INCLUDES_PATH . 'class-comments.php';
			$comments = new Anthologize_Comments( $post_id );

			switch ( $action ) {
				case 'add':
					$comments->import_comment( $comment_id );
					break;

				case 'remove':
				default:
					$comments->remove_comment( $comment_id );
					break;
			}

			$comments->update_included_comments();

			wp_send_json_success( array_values( $comments->included_comments ) );
		}

		public function include_all_comments() {
			$this->verify_request();

			$post_id = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$action  = ! empty( $_POST['check_action'] ) && 'remove' === sanitize_key( $_POST['check_action'] ) ? 'remove' : 'add';

			if ( empty( $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Missing required data.', 'anthologize' ) ), 400 );
			}

			require_once ANTHOLOGIZE_INCLUDES_PATH . 'class-comments.php';
			$comments = new Anthologize_Comments( $post_id );

			switch ( $action ) {
				case 'add':
					$comments->import_all_comments();
					break;

				case 'remove':
				default:
					$comments->remove_all_comments();
					break;
			}

			$comments->update_included_comments();

			wp_send_json_success( array_values( $comments->included_comments ) );
		}
	}

endif;

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Anthologize_Project_Organizer' ) ) :

	class Anthologize_Project_Organizer {

		/** @var int */
		public $project_id;

		/** @var string */
		public $project_name = '';

		public function __construct( $project_id ) {
			$this->project_id = absint( $project_id );

			$project = get_post( $this->project_id );

			if ( ! empty( $project->post_title ) ) {
				$this->project_name = $project->post_title;
			}

			add_filter( 'posts_clauses', array( $this, 'filter_orderby_for_author_name' ), 10, 2 );
		}

		public function display() {
			wp_enqueue_script( 'anthologize-sortlist-js' );
			wp_enqueue_script( 'anthologize-project-organizer' );

			if ( isset( $_POST['new_item'] ) ) {
				check_admin_referer( 'anthologize_project_organizer' );
				$this->add_item_to_part( absint( $_POST['item_id'] ), absint( $_POST['part_id'] ) );
			}

			if ( isset( $_POST['new_part'] ) ) {
				check_admin_referer( 'anthologize_project_organizer' );
				$this->add_new_part( sanitize_text_field( wp_unslash( $_POST['new_part_name'] ) ) );
			}

			if ( isset( $_GET['move_up'] ) ) {
				check_admin_referer( 'anthologize_move_item' );
				$this->move_up( absint( $_GET['move_up'] ) );
			}

			if ( isset( $_GET['move_down'] ) ) {
				check_admin_referer( 'anthologize_move_item' );
				$this->move_down( absint( $_GET['move_down'] ) );
			}

			if ( isset( $_GET['remove'] ) ) {
				check_admin_referer( 'anthologize_remove_item' );
				$this->remove_item( absint( $_GET['remove'] ) );
			}

			if ( isset( $_POST['append_children'] ) ) {
				check_admin_referer( 'anthologize_project_organizer' );
				$this->append_children( absint( $_POST['append_parent'] ), array_map( 'absint', (array) $_POST['append_children'] ) );
			}

			$project_id = absint( isset( $_GET['project_id'] ) ? $_GET['project_id'] : $this->project_id );

			?>

		<div class="wrap anthologize" id="project-<?php echo esc_attr( $project_id ); ?>">

			<div id="blockUISpinner">
				<img src="<?php echo esc_url( anthologize()->plugin_url . 'images/wait28.gif' ); ?>" alt="<?php esc_html_e( 'Please wait...', 'anthologize' ); ?>" aria-hidden="true" />
				<p id="ajaxErrorMsg"><?php esc_html_e( 'There has been an unexpected error. Please wait while we reload the content.', 'anthologize' ); ?></p>
			</div>

			<div id="anthologize-logo"><img src="<?php echo esc_url( anthologize()->plugin_url . 'images/anthologize-logo.gif' ); ?>" alt="<?php esc_attr_e( 'Anthologize logo', 'anthologize' ); ?>" /></div>

			<h2>
				<?php echo esc_html( $this->project_name ); ?>

				<div id="project-actions">
					<a href="admin.php?page=anthologize_new_project&project_id=<?php echo esc_attr( $this->project_id ); ?>"><?php esc_html_e( 'Project Details', 'anthologize' ); ?></a> |
					<a target="_blank" href="<?php echo esc_url( $this->preview_url( $this->project_id, 'anth_project' ) ); ?>"><?php esc_html_e( 'Preview Project', 'anthologize' ); ?></a> |
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=anthologize&action=delete&project_id=' . $this->project_id ), 'anthologize_delete_project' ) ); ?>" class="confirm-delete"><?php esc_html_e( 'Delete Project', 'anthologize' ); ?></a>
				</div>
			</h2>

			<?php if ( isset( $_GET['append_parent'] ) && ! isset( $_GET['append_children'] ) ) : ?>
				<div id="message" class="updated below-h2">
					<p><?php esc_html_e( 'Select the items you would like to append and click Go.', 'anthologize' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="project-organizer-frame">
				<div id="project-organizer-left-column" class="metabox-holder">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">

						<div id="add-custom-links" class="postbox ">
							<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle', 'anthologize' ); ?>"><br></div>
							<h3 class="hndle"><span><?php esc_html_e( 'Items', 'anthologize' ); ?></span></h3>

							<div class="inside">
								<div class="customlinkdiv" id="customlinkdiv">

									<p id="menu-item-name-wrap">
										<?php $this->sortby_dropdown(); ?>
									</p>

									<p id="termfilter">
										<?php $this->filter_dropdown(); ?>
									</p>

									<p id="datefilter">
										<?php $this->filter_date(); ?>
									</p>

									<p id="menu-item-name-wrap">
										<?php $this->orderby_dropdown(); ?>
									</p>

									<h3 class="part-header"><?php esc_html_e( 'Posts', 'anthologize' ); ?></h3>

									<div id="posts-scrollbox">
										<?php $this->get_sidebar_posts(); ?>
									</div>

								</div>
							</div>

						</div>

					</div>
				</div>

				<div class="metabox-holder" id="project-organizer-right-column">

					<div class="postbox" id="anthologize-parts-box">

						<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle', 'anthologize' ); ?>"><br></div>
						<h3 class="hndle">
							<span><?php esc_html_e( 'Parts', 'anthologize' ); ?></span>
							<div class="part-item-buttons button anth-buttons" id="new-part">
								<a href="post-new.php?post_type=anth_part&project_id=<?php echo esc_attr( $this->project_id ); ?>&new_part=1"><?php esc_html_e( 'New Part', 'anthologize' ); ?></a>
							</div>
						</h3>

						<div id="partlist">

							<ul class="project-parts">
								<?php $this->list_existing_parts(); ?>
							</ul>

							<noscript>
								<h3><?php esc_html_e( 'New Parts', 'anthologize' ); ?></h3>
								<p><?php esc_html_e( 'Wanna create a new part? You know you do.', 'anthologize' ); ?></p>
								<form action="" method="post">
									<input type="text" name="new_part_name" />
									<input type="submit" name="new_part" value="New Part" />
									<?php wp_nonce_field( 'anthologize_project_organizer' ); ?>
								</form>
							</noscript>

						</div>

					</div>

					<div class="button" id="export-project-button"><a href="admin.php?page=anthologize_export_project&project_id=<?php echo esc_attr( $this->project_id ); ?>" id="export-project"><?php esc_html_e( 'Export Project', 'anthologize' ); ?></a></div>

				</div>

			</div>

		</div>

			<?php
		}

		public function orderby_dropdown() {
			$filters = array(
				'author_asc'  => __( 'Author (A-Z)', 'anthologize' ),
				'author_desc' => __( 'Author (Z-A)', 'anthologize' ),
				'date_asc'    => __( 'Date (oldest first)', 'anthologize' ),
				'date_desc'   => __( 'Date (newest first)', 'anthologize' ),
				'title_asc'   => __( 'Title (A-Z)', 'anthologize' ),
				'title_desc'  => __( 'Title (Z-A)', 'anthologize' ),
			);

			$orderby  = 'title_asc';
			$corderby = isset( $_COOKIE['anth-orderby'] ) ? sanitize_key( $_COOKIE['anth-orderby'] ) : '';
			if ( $corderby && isset( $filters[ $corderby ] ) ) {
				$orderby = $corderby;
			}

			?>

		<label for="orderby-dropdown"><?php esc_html_e( 'Order by', 'anthologize' ); ?></label>

		<select name="orderby" id="orderby-dropdown">
			<?php foreach ( $filters as $filter => $name ) : ?>
				<option value="<?php echo esc_attr( $filter ); ?>" <?php selected( $filter, $orderby ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>

			<?php
		}

		public function sortby_dropdown() {
			$filters = array(
				'tag'       => __( 'Tag', 'anthologize' ),
				'category'  => __( 'Category', 'anthologize' ),
				'date'      => __( 'Date Range', 'anthologize' ),
				'post_type' => __( 'Post Type', 'anthologize' ),
			);

			$cfilter = isset( $_COOKIE['anth-filter'] ) ? sanitize_key( $_COOKIE['anth-filter'] ) : '';

			?>

		<label for="sortby-dropdown"><?php esc_html_e( 'Filter by', 'anthologize' ); ?></label>

		<select name="sortby" id="sortby-dropdown">
			<option value="" selected="selected"><?php esc_html_e( 'All posts', 'anthologize' ); ?></option>
			<?php foreach ( $filters as $filter => $name ) : ?>
				<option value="<?php echo esc_attr( $filter ); ?>" <?php selected( $filter, $cfilter ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>

			<?php
		}

		public function filter_dropdown() {
			$cterm   = isset( $_COOKIE['anth-term'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['anth-term'] ) ) : '';
			$cfilter = isset( $_COOKIE['anth-filter'] ) ? sanitize_key( $_COOKIE['anth-filter'] ) : '';

			$terms    = array();
			$nulltext = ' - ';

			switch ( $cfilter ) {
				case 'tag':
					$tag_list = get_tags();
					if ( is_array( $tag_list ) ) {
						$terms = $tag_list;
					}
					$nulltext = __( 'All tags', 'anthologize' );
					break;

				case 'category':
					$terms    = get_categories();
					$nulltext = __( 'All categories', 'anthologize' );
					break;

				case 'post_type':
					$types = $this->available_post_types();
					foreach ( $types as $type_id => $type_label ) {
						$type_object          = new stdClass();
						$type_object->term_id = $type_id;
						$type_object->name    = $type_label;
						$type_object->slug    = $type_id;
						$terms[]              = $type_object;
					}
					$nulltext = __( 'All post types', 'anthologize' );
					break;
			}

			?>

		<label class="screen-reader-text" for="filter"><?php esc_html_e( 'Filter by specific term', 'anthologize' ); ?></label>

		<select name="filter" id="filter">
			<option value=""><?php echo esc_html( $nulltext ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<?php $term_value = ( 'tag' === $cfilter && isset( $term->slug ) ) ? $term->slug : ( isset( $term->term_id ) ? $term->term_id : '' ); ?>
				<option value="<?php echo esc_attr( $term_value ); ?>" <?php selected( $cterm, $term_value ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>

			<?php
		}

		public function filter_date() {
			?>

		<label for="startdate"><?php esc_html_e( 'Start', 'anthologize' ); ?></label> <input name="startdate" id="startdate" type="text"/>
		<br />
		<label for="enddate"><?php esc_html_e( 'End', 'anthologize' ); ?></label> <input name="enddate" id="enddate" type="text" />
		<br />
		<input type="button" id="launch_date_filter" value="<?php esc_attr_e( 'Filter', 'anthologize' ); ?>" />

			<?php
		}

		public function available_post_types() {
			$all_post_types = get_post_types(
				array( 'public' => true ),
				false
			);

			$excluded_post_types = apply_filters(
				'anth_excluded_post_types',
				array(
					'anth_library_item',
					'anth_part',
					'anth_project',
					'attachment',
					'revision',
					'nav_menu_item',
				)
			);

			$types = array();
			foreach ( $all_post_types as $name => $post_type ) {
				if ( ! in_array( $name, $excluded_post_types, true ) ) {
					$types[ $name ] = isset( $post_type->labels->name ) ? $post_type->labels->name : $name;
				}
			}

			return apply_filters( 'anth_available_post_types', $types );
		}

		public function add_item_to_part( $item_id, $part_id ) {
			global $wpdb;

			$last_item = (int) get_post_meta( $part_id, 'last_item', true );
			++$last_item;

			$the_item = get_post( $item_id );
			$part     = get_post( $part_id );

			if ( ! $the_item || ! $part ) {
				return false;
			}

			$args = array(
				'menu_order'     => $last_item,
				'comment_status' => $the_item->comment_status,
				'ping_status'    => $the_item->ping_status,
				'pinged'         => $the_item->pinged,
				'post_author'    => get_current_user_id(),
				'post_content'   => $the_item->post_content,
				'post_date'      => $the_item->post_date,
				'post_date_gmt'  => $the_item->post_date_gmt,
				'post_excerpt'   => $the_item->post_excerpt,
				'post_parent'    => $part_id,
				'post_password'  => $the_item->post_password,
				'post_status'    => $part->post_status,
				'post_title'     => $the_item->post_title,
				'post_type'      => 'anth_library_item',
				'to_ping'        => $the_item->to_ping,
			);

			$args = add_magic_quotes( $args );

			$imported_item_id = wp_insert_post( $args );
			if ( ! $imported_item_id ) {
				return false;
			}

			$this->update_project_modified_date();

			$user = get_userdata( $the_item->post_author );

			$author_name = get_post_meta( $item_id, 'author_name', true );
			if ( ! $author_name && $user ) {
				$author_name = $user->display_name;
			}

			$author_name_array = array( $author_name );

			$anthologize_meta = apply_filters(
				'anth_add_item_postmeta',
				array(
					'author_name'       => $author_name,
					'author_name_array' => $author_name_array,
					'author_id'         => $the_item->post_author,
					'original_post_id'  => $item_id,
				)
			);

			update_post_meta( $imported_item_id, 'anthologize_meta', $anthologize_meta );
			update_post_meta( $imported_item_id, 'author_name', $author_name );
			update_post_meta( $imported_item_id, 'author_name_array', $author_name_array );

			return $imported_item_id;
		}

		public function update_project_modified_date() {
			wp_update_post( array(
				'ID'                => $this->project_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			) );
		}

		public function add_new_part( $part_name ) {
			$last_item = (int) get_post_meta( $this->project_id, 'last_item', true );
			++$last_item;

			$project = get_post( $this->project_id );

			$args = array(
				'post_title'  => $part_name,
				'post_type'   => 'anth_part',
				'post_status' => $project ? $project->post_status : 'draft',
				'post_parent' => $this->project_id,
			);

			$part_id = wp_insert_post( $args );
			if ( ! $part_id ) {
				return false;
			}

			update_post_meta( $this->project_id, 'last_item', $last_item );
			$this->update_project_modified_date();

			return true;
		}

		public function list_existing_parts() {
			$args = array(
				'post_type'      => 'anth_part',
				'order'          => 'ASC',
				'orderby'        => 'menu_order',
				'posts_per_page' => -1,
				'post_parent'    => $this->project_id,
			);

			$parts_query = new WP_Query( $args );

			if ( $parts_query->have_posts() ) {
				while ( $parts_query->have_posts() ) {
					$parts_query->the_post();
					$part_id = get_the_ID();

					$remove_url = wp_nonce_url(
						admin_url( 'admin.php?page=anthologize&action=edit&project_id=' . $this->project_id . '&remove=' . $part_id ),
						'anthologize_remove_item'
					);

					?>

				<li class="part" id="part-<?php echo esc_attr( $part_id ); ?>">
					<div class="part-header">
						<h3 class="part-title-header">
							<noscript>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=anthologize&action=edit&project_id=' . $this->project_id . '&move_up=' . $part_id ), 'anthologize_move_item' ) ); ?>">&uarr;</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=anthologize&action=edit&project_id=' . $this->project_id . '&move_down=' . $part_id ), 'anthologize_move_item' ) ); ?>">&darr;</a>
							</noscript>
							<span class="part-title-header"><?php the_title(); ?></span>
						</h3>

						<div class="part-buttons anth-buttons">
							<a href="post.php?post=<?php the_ID(); ?>&action=edit&return_to_project=<?php echo esc_attr( $this->project_id ); ?>"><?php esc_html_e( 'Edit', 'anthologize' ); ?></a> |
							<a target="_blank" href="<?php echo esc_url( $this->preview_url( get_the_ID(), 'anth_part' ) ); ?>"><?php esc_html_e( 'Preview', 'anthologize' ); ?></a> |
							<a href="<?php echo esc_url( $remove_url ); ?>" class="remove"><?php esc_html_e( 'Remove', 'anthologize' ); ?></a> |
							<a href="#collapse" class="collapsepart"> - </a>
						</div>
					</div>

					<div class="part-items">
						<ul>
							<?php $this->get_part_items( $part_id ); ?>
						</ul>
					</div>

				</li>
					<?php
				}
			} else {
				?>

			<p>
				<?php
				printf(
					/* translators: %s: URL to create a new part */
					wp_kses(
						__( 'You haven\'t created any parts yet! Click <a href="%s">"New Part"</a> to get started.', 'anthologize' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'post-new.php?post_type=anth_part&project_id=' . $this->project_id . '&new_part=1' ) )
				);
				?>
			</p>

				<?php
			}

			wp_reset_postdata();
		}

		public function get_sidebar_posts() {
			global $wpdb;

			$args = array(
				'post_type'            => array_keys( $this->available_post_types() ),
				'posts_per_page'       => -1,
				'post_status'          => $this->source_item_post_statuses(),
				'is_anthologize_query' => true,
			);

			$cfilter = isset( $_COOKIE['anth-filter'] ) ? sanitize_key( $_COOKIE['anth-filter'] ) : '';

			if ( 'date' === $cfilter ) {
				$startdate = isset( $_COOKIE['anth-startdate'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['anth-startdate'] ) ) : '';
				$enddate   = isset( $_COOKIE['anth-enddate'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['anth-enddate'] ) ) : '';

				$date_range_where = '';

				if ( strlen( $startdate ) > 0 ) {
					$date_range_where .= $wpdb->prepare( ' AND post_date >= %s', $startdate );
				}

				if ( strlen( $enddate ) > 0 ) {
					$date_range_where .= $wpdb->prepare( ' AND post_date <= %s', $enddate );
				}

				$filter_where = function ( $where ) use ( $date_range_where ) {
					return $where . $date_range_where;
				};

				add_filter( 'posts_where', $filter_where );
			} else {
				$cterm = isset( $_COOKIE['anth-term'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['anth-term'] ) ) : '';

				if ( $cterm && $cfilter ) {
					switch ( $cfilter ) {
						case 'tag':
							$args['tag'] = $cterm;
							break;

						case 'category':
							$args['cat'] = absint( $cterm );
							break;

						case 'post_type':
							$args['post_type'] = sanitize_key( $cterm );
							break;
					}
				}
			}

			$corderby         = isset( $_COOKIE['anth-orderby'] ) ? sanitize_key( $_COOKIE['anth-orderby'] ) : 'title_asc';
			$orderby_settings = self::get_orderby_settings( $corderby );

			$args['orderby'] = $orderby_settings['orderby'];
			$args['order']   = $orderby_settings['order'];

			$big_posts = new WP_Query( $args );

			if ( $big_posts->have_posts() ) {
				?>
			<ul id="sidebar-posts">
				<?php
				while ( $big_posts->have_posts() ) :
					$big_posts->the_post();
					?>
					<?php $item_metadata = self::get_item_metadata( get_the_ID() ); ?>

					<li class="part-item item has-accordion accordion-closed">
						<span class="fromNewId">new-<?php the_ID(); ?></span>
						<h3 class="part-item-title"><?php the_title(); ?></h3>
						<span class="accordion-toggle hide-if-no-js">
							<span class="accordion-toggle-glyph"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Show details', 'anthologize' ); ?></span>
						</span>

						<div class="item-details">
							<ul>
							<?php foreach ( $item_metadata as $im ) : ?>
								<li><?php echo wp_kses_post( $im ); ?></li>
							<?php endforeach; ?>
							</ul>
						</div>
					</li>
				<?php endwhile; ?>
			</ul>
				<?php
			}

			wp_reset_postdata();

			if ( 'date' === $cfilter && isset( $filter_where ) ) {
				remove_filter( 'posts_where', $filter_where );
			}
		}

		/**
		 * Get source item metadata for a post.
		 *
		 * @since 0.8.0
		 *
		 * @param int $item_id ID of the item.
		 * @return array
		 */
		public static function get_item_metadata( $item_id ) {
			$item_post = get_post( $item_id );

			$item_metadata = array(
				'link' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_permalink( $item_post ) ),
					esc_html__( 'View post', 'anthologize' )
				),
			);

			$item_author = get_userdata( $item_post->post_author );
			$item_tags   = get_the_term_list( $item_id, 'post_tag', '', ', ' );
			$item_cats   = get_the_term_list( $item_id, 'category', '', ', ' );

			if ( $item_author ) {
				$item_metadata['author'] = sprintf(
					/* translators: %s: author name */
					__( 'Author: %s', 'anthologize' ),
					esc_html( sprintf( '%s (%s)', $item_author->display_name, $item_author->user_login ) )
				);
			}

			if ( $item_tags ) {
				$item_metadata['tags'] = sprintf( __( 'Tags: %s', 'anthologize' ), $item_tags );
			}

			if ( $item_cats ) {
				$item_metadata['cats'] = sprintf( __( 'Categories: %s', 'anthologize' ), $item_cats );
			}

			return $item_metadata;
		}

		/**
		 * Get order values from stored setting.
		 *
		 * @param string $orderby The orderby key.
		 * @return array
		 */
		public static function get_orderby_settings( $orderby ) {
			$orderby_values = array(
				'date_asc'    => array( 'orderby' => 'date', 'order' => 'ASC' ),
				'date_desc'   => array( 'orderby' => 'date', 'order' => 'DESC' ),
				'title_asc'   => array( 'orderby' => 'title', 'order' => 'ASC' ),
				'title_desc'  => array( 'orderby' => 'title', 'order' => 'DESC' ),
				'author_desc' => array( 'orderby' => 'author_name', 'order' => 'DESC' ),
				'author_asc'  => array( 'orderby' => 'author_name', 'order' => 'ASC' ),
			);

			if ( ! isset( $orderby_values[ $orderby ] ) ) {
				$orderby = 'title_asc';
			}

			return $orderby_values[ $orderby ];
		}

		public function get_part_items( $part_id ) {
			$append_parent = ! empty( $_GET['append_parent'] ) ? absint( $_GET['append_parent'] ) : false;

			$args = array(
				'post_parent'    => absint( $part_id ),
				'post_type'      => 'anth_library_item',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);

			$items_query = new WP_Query( $args );

			if ( $items_query->have_posts() ) {
				while ( $items_query->have_posts() ) :
					$items_query->the_post();
					$this->display_item( $append_parent );
				endwhile;
			}

			wp_reset_postdata();
		}

		public function move_up( $id ) {
			global $wpdb;

			$post          = get_post( $id );
			if ( ! $post ) {
				return false;
			}

			$my_menu_order = (int) $post->menu_order;

			$big_brother = 0;
			$minus       = 0;

			while ( ! $big_brother && $minus < 100 ) {
				++$minus;
				$bb = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND menu_order = %d LIMIT 1",
						$post->post_parent,
						$my_menu_order - $minus
					)
				);
				if ( $bb ) {
					$big_brother = (int) $bb;
				}
			}

			if ( ! $big_brother ) {
				return false;
			}

			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order, $big_brother ) );
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order - $minus, $id ) );

			return true;
		}

		public function move_down( $id ) {
			global $wpdb;

			$post = get_post( $id );
			if ( ! $post ) {
				return false;
			}

			$my_menu_order  = (int) $post->menu_order;
			$little_brother = 0;
			$plus           = 0;

			while ( ! $little_brother && $plus < 100 ) {
				++$plus;
				$lb = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND menu_order = %d LIMIT 1",
						$post->post_parent,
						$my_menu_order + $plus
					)
				);
				if ( $lb ) {
					$little_brother = (int) $lb;
				}
			}

			if ( ! $little_brother ) {
				return false;
			}

			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order, $little_brother ) );
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d", $my_menu_order + $plus, $id ) );

			return true;
		}

		public function insert_item( $project_id, $post_id, $new_post, $dest_id, $source_id, $dest_seq, $source_seq ) {
			if ( ! $project_id || ! $post_id || ! $dest_id || ! is_array( $dest_seq ) ) {
				return false;
			}

			if ( ! $new_post ) {
				if ( ! $source_id || ! is_array( $source_seq ) ) {
					return false;
				}
			}

			if ( true === $new_post ) {
				$add_item_result = $this->add_item_to_part( $post_id, $dest_id );

				if ( false === $add_item_result ) {
					return false;
				}
				$post_id = $add_item_result;
			} else {
				$post_params        = array(
					'ID'          => absint( $post_id ),
					'post_parent' => absint( $dest_id ),
				);
				$update_item_result = wp_update_post( $post_params );
				if ( 0 === $update_item_result ) {
					return false;
				}
				$post_id = $update_item_result;
				$this->rearrange_items( $source_seq );
			}

			return $post_id;
		}

		public function rearrange_items( $seq ) {
			global $wpdb;
			foreach ( $seq as $item_id => $pos ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d",
					absint( $pos ),
					absint( $item_id )
				) );
			}

			$this->update_project_modified_date();

			return true;
		}

		public function remove_item( $id ) {
			if ( ! current_user_can( 'delete_post', $id ) ) {
				return false;
			}

			if ( ! wp_delete_post( $id ) ) {
				return false;
			}

			$this->update_project_modified_date();

			return true;
		}

		public function append_children( $append_parent, $append_children ) {
			$parent_post = get_post( $append_parent );
			if ( ! $parent_post ) {
				return false;
			}

			$pp_content = $parent_post->post_content;

			$author_name = get_post_meta( $append_parent, 'author_name', true );
			if ( ! $author_name ) {
				$author_name = '';
			}

			$author_name_array = get_post_meta( $append_parent, 'author_name_array', true );
			if ( ! is_array( $author_name_array ) ) {
				$author_name_array = array();
			}

			foreach ( $append_children as $append_child ) {
				$child_post = get_post( $append_child );
				if ( ! $child_post ) {
					continue;
				}

				$cp_title = '<h2 class="anthologize-item-header">' . esc_html( $child_post->post_title ) . "</h2>\n";
				$cp_content = $child_post->post_content;

				$pp_content .= $cp_title . $cp_content . "\n";

				if ( '' !== $author_name ) {
					$author_name .= ', ';
				}

				$cp_author_name      = get_post_meta( $append_child, 'author_name', true );
				$author_name        .= $cp_author_name;
				$author_name_array[] = $cp_author_name;

				wp_delete_post( $append_child );
			}

			$result = wp_update_post( array(
				'ID'           => $append_parent,
				'post_content' => $pp_content,
			) );

			if ( ! $result ) {
				return false;
			}

			update_post_meta( $append_parent, 'author_name', $author_name );
			update_post_meta( $append_parent, 'author_name_array', $author_name_array );

			$this->update_project_modified_date();

			return true;
		}

		public function display_item( $append_parent ) {
			global $post;

			$anth_meta = get_post_meta( get_the_ID(), 'anthologize_meta', true );

			$original_comment_count = 0;
			if ( ! empty( $anth_meta['original_post_id'] ) ) {
				$original_post = get_post( $anth_meta['original_post_id'] );
				if ( $original_post ) {
					$original_comment_count = (int) $original_post->comment_count;
				}
			}

			$included_comment_count = 0;
			if ( ! empty( $anth_meta['included_comments'] ) ) {
				$included_comment_count = count( $anth_meta['included_comments'] );
			}

			$remove_url = wp_nonce_url(
				admin_url( 'admin.php?page=anthologize&action=edit&project_id=' . $this->project_id . '&remove=' . get_the_ID() ),
				'anthologize_remove_item'
			);

			?>

		<li id="item-<?php the_ID(); ?>" class="part-item item">

			<?php if ( $append_parent ) : ?>
				<input type="checkbox" name="append_children[]" value="<?php the_ID(); ?>"
					<?php
					if ( $append_parent == $post->ID ) {
						echo 'checked="checked" disabled="disabled"';
					}
					?>
				/>
			<?php endif; ?>

			<noscript>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=anthologize&action=edit&project_id=' . $this->project_id . '&move_up=' . get_the_ID() ), 'anthologize_move_item' ) ); ?>">&uarr;</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=anthologize&action=edit&project_id=' . $this->project_id . '&move_down=' . get_the_ID() ), 'anthologize_move_item' ) ); ?>">&darr;</a>
			</noscript>

			<h3 class="part-item-title">
				<span class="part-title"><?php the_title(); ?></span>

				<div class="part-item-buttons anth-buttons">
					<a href="post.php?post=<?php the_ID(); ?>&action=edit&return_to_project=<?php echo esc_attr( $this->project_id ); ?>"><?php esc_html_e( 'Edit', 'anthologize' ); ?></a> |

					<a href="#append" class="append toggle"><?php esc_html_e( 'Append', 'anthologize' ); ?></a><span class="append-sep toggle-sep"> |</span>

					<a target="_blank" href="<?php echo esc_url( $this->preview_url( get_the_ID(), 'anth_library_item' ) ); ?>"><?php esc_html_e( 'Preview', 'anthologize' ); ?></a><span class="toggle-sep"> |</span>

					<a href="<?php echo esc_url( $remove_url ); ?>" class="confirm"><?php esc_html_e( 'Remove', 'anthologize' ); ?></a>
				</div>
			</h3>

		</li>

			<?php
		}

		public function preview_url( $post_id = false, $post_type = 'anth_library_item' ) {
			$query_args = array(
				'page'         => 'anthologize',
				'anth_preview' => '1',
				'post_id'      => absint( $post_id ),
				'post_type'    => sanitize_key( $post_type ),
			);

			return add_query_arg( $query_args, admin_url( 'admin.php' ) );
		}

		public function source_item_post_statuses() {
			return apply_filters(
				'anthologize_source_item_post_statuses',
				array( 'publish', 'pending', 'future', 'private' )
			);
		}

		public function filter_orderby_for_author_name( $clauses, $q ) {
			global $wpdb;

			if ( ! $q->get( 'is_anthologize_query' ) ) {
				return $clauses;
			}

			$orderby_param = $q->get( 'orderby' );
			if ( 'author_name' !== $orderby_param ) {
				return $clauses;
			}

			if ( false === strpos( $clauses['join'], 'anthologize_author' ) ) {
				$clauses['join']   .= " LEFT JOIN {$wpdb->users} AS anthologize_author ON ({$wpdb->posts}.post_author = anthologize_author.ID) ";
				$clauses['orderby'] = 'anthologize_author.user_nicename ' . $q->get( 'order' );
			}

			return $clauses;
		}
	}

endif;

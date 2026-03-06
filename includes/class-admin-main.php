<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Anthologize_Admin_Main' ) ) :

	class Anthologize_Admin_Main {
		/** @var string */
		private $minimum_cap;

		/** @var array */
		public $panels = array();

		public function __construct() {
			$this->minimum_cap = $this->minimum_cap();

			add_action( 'admin_init', array( $this, 'init' ) );

			add_action( 'admin_menu', array( $this, 'dashboard_hooks' ), 990 );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			require __DIR__ . '/class-ajax-handlers.php';
			new Anthologize_Ajax_Handlers();

			add_action( 'admin_menu', array( $this, 'load_template' ), 999 );

			if ( is_multisite() ) {
				add_action( 'wpmu_options', array( $this, 'ms_settings' ) );
				add_action( 'update_wpmu_options', array( $this, 'save_ms_settings' ) );
			}
		}

		public function init() {
			foreach ( array( 'anth_project', 'anth_part', 'anth_library_item', 'anth_imported_item' ) as $type ) {
				add_meta_box( 'anthologize', __( 'Anthologize', 'anthologize' ), array( $this, 'item_meta_box' ), $type, 'side', 'high' );
				add_meta_box( 'anthologize-save', __( 'Save', 'anthologize' ), array( $this, 'meta_save_box' ), $type, 'side', 'high' );
				remove_meta_box( 'submitdiv', $type, 'normal' );
			}

			add_action( 'save_post', array( $this, 'item_meta_save' ) );

			do_action( 'anthologize_admin_init' );
		}

		/**
		 * Loads the minimum user capability for displaying the Anthologize menus.
		 *
		 * @return string
		 */
		public function minimum_cap() {
			if ( is_multisite() ) {
				$site_settings = get_site_option( 'anth_site_settings' );
				$default_cap   = ! empty( $site_settings['minimum_cap'] ) ? $site_settings['minimum_cap'] : 'manage_options';
			} else {
				$default_cap = 'manage_options';
			}

			if ( ! is_multisite() || empty( $site_settings['forbid_per_blog_caps'] ) ) {
				$blog_settings = get_option( 'anth_settings' );
				$cap           = ! empty( $blog_settings['minimum_cap'] ) ? $blog_settings['minimum_cap'] : $default_cap;
			} else {
				$cap = $default_cap;
			}

			return apply_filters( 'anth_minimum_cap', $cap );
		}

		/**
		 * Adds Anthologize's plugin pages to the Dashboard.
		 */
		public function dashboard_hooks() {
			global $menu;

			$default_index = apply_filters( 'anth_default_menu_position', 55 );

			while ( ! empty( $menu[ $default_index - 1 ] ) || ! empty( $menu[ $default_index ] ) || ! empty( $menu[ $default_index + 1 ] ) ) {
				++$default_index;
			}

			$separator                  = array(
				0 => '',
				1 => 'read',
				2 => 'separator-anthologize',
				3 => '',
				4 => 'wp-menu-separator',
			);
			$menu[ $default_index - 1 ] = $separator;
			$menu[ $default_index + 1 ] = $separator;

			$plugin_pages = array();

			$this->add_admin_menu_page(
				array(
					'menu_title'   => __( 'Anthologize', 'anthologize' ),
					'page_title'   => __( 'Anthologize', 'anthologize' ),
					'access_level' => $this->minimum_cap,
					'file'         => 'anthologize',
					'function'     => array( $this, 'display' ),
					'position'     => $default_index,
				)
			);

			$plugin_pages[] = add_submenu_page(
				'anthologize',
				__( 'My Projects', 'anthologize' ),
				__( 'My Projects', 'anthologize' ),
				$this->minimum_cap,
				'anthologize',
				array( $this, 'display' )
			);

			$plugin_pages[] = add_submenu_page(
				'anthologize',
				__( 'New Project', 'anthologize' ),
				__( 'New Project', 'anthologize' ),
				$this->minimum_cap,
				'anthologize_new_project',
				array( $this, 'load_admin_panel_new_project' )
			);

			$plugin_pages[] = add_submenu_page(
				'anthologize',
				__( 'Export Project', 'anthologize' ),
				__( 'Export Project', 'anthologize' ),
				$this->minimum_cap,
				'anthologize_export_project',
				array( $this, 'load_admin_panel_export_project' )
			);

			$plugin_pages[] = add_submenu_page(
				'anthologize',
				__( 'Import Content', 'anthologize' ),
				__( 'Import Content', 'anthologize' ),
				$this->minimum_cap,
				'anthologize_import_content',
				array( $this, 'load_admin_panel_import_content' )
			);

			$plugin_pages[] = add_submenu_page(
				'anthologize',
				__( 'Settings', 'anthologize' ),
				__( 'Settings', 'anthologize' ),
				'manage_options',
				'anthologize_settings',
				array( $this, 'load_admin_panel_settings' )
			);

			$plugin_pages[] = add_submenu_page(
				'anthologize',
				__( 'About Anthologize', 'anthologize' ),
				__( 'About', 'anthologize' ),
				$this->minimum_cap,
				'anthologize_about',
				array( $this, 'load_admin_panel_about' )
			);

			foreach ( $plugin_pages as $plugin_page ) {
				add_action( "admin_print_styles-$plugin_page", array( $this, 'load_styles' ) );
				add_action( "admin_print_scripts-$plugin_page", array( $this, 'load_scripts' ) );
			}
		}

		/**
		 * Add admin menu page with custom positioning.
		 *
		 * @param array $args Menu page arguments.
		 * @return string Hook name.
		 */
		public function add_admin_menu_page( $args = '' ) {
			global $menu, $admin_page_hooks, $_registered_pages;

			$defaults = array(
				'page_title'   => '',
				'menu_title'   => '',
				'access_level' => 2,
				'file'         => false,
				'function'     => false,
				'icon_url'     => false,
				'position'     => 100,
			);

			$r = wp_parse_args( $args, $defaults );

			$file       = plugin_basename( $r['file'] );
			$menu_title = $r['menu_title'];
			$page_title = $r['page_title'];
			$access_level = $r['access_level'];
			$function   = $r['function'];
			$icon_url   = $r['icon_url'];
			$position   = $r['position'];

			$admin_page_hooks[ $file ] = sanitize_title( $menu_title );

			$hookname = get_plugin_page_hookname( $file, '' );
			if ( ! empty( $function ) && ! empty( $hookname ) ) {
				add_action( $hookname, $function );
			}

			if ( empty( $icon_url ) ) {
				$icon_url = 'images/generic.png';
			} elseif ( is_ssl() && 0 === strpos( $icon_url, 'http://' ) ) {
				$icon_url = 'https://' . substr( $icon_url, 7 );
			}

			do {
				++$position;
			} while ( ! empty( $menu[ $position ] ) );

			$menu[ $position ] = array( $menu_title, $access_level, $file, $page_title, 'menu-top ' . $hookname, $hookname, $icon_url );
			unset( $menu[ $position ][5] );

			$_registered_pages[ $hookname ] = true;

			return $hookname;
		}

		public function load_admin_panel_new_project() {
			require anthologize()->includes_dir . 'class-new-project.php';
			$this->panels['new_project'] = Anthologize_New_Project::init();
			$this->panels['new_project']->display();
		}

		public function load_admin_panel_export_project() {
			require anthologize()->includes_dir . 'class-export-panel.php';
			$this->panels['export_project'] = Anthologize_Export_Panel::init();
		}

		public function load_admin_panel_import_content() {
			require anthologize()->includes_dir . 'class-import-feeds.php';
			$this->panels['import_content'] = Anthologize_Import_Feeds_Panel::init();
		}

		public function load_admin_panel_settings() {
			require anthologize()->includes_dir . 'class-settings.php';
			$this->panels['settings'] = Anthologize_Settings::init();
		}

		public function load_admin_panel_about() {
			require anthologize()->includes_dir . 'class-about.php';
			$this->panels['about'] = Anthologize_About::init();
		}

		public function load_scripts() {
			wp_enqueue_script( 'anthologize_admin-js', anthologize()->plugin_url . 'js/anthologize_admin.js', array( 'jquery', 'blockUI-js' ), ANTHOLOGIZE_VERSION, true );
		}

		public function load_styles() {
			wp_enqueue_style( 'anthologize-css', anthologize()->plugin_url . 'css/project-organizer.css', array(), ANTHOLOGIZE_VERSION );
			wp_enqueue_style( 'jquery-ui-datepicker-css', anthologize()->plugin_url . 'css/jquery-ui-1.7.3.custom.css', array(), ANTHOLOGIZE_VERSION );
		}

		public function enqueue_assets() {
			wp_enqueue_style( 'anthologize-admin-general' );
		}

		/**
		 * Loads the project organizer.
		 *
		 * @param int $project_id The project ID.
		 */
		public function load_project_organizer( $project_id ) {
			require_once __DIR__ . '/class-project-organizer.php';
			$project_organizer = new Anthologize_Project_Organizer( $project_id );
			$project_organizer->display();
		}

		public function display_no_project_id_message() {
			?>
			<div id="notice" class="error below-h2">
				<p><?php esc_html_e( 'Project not found', 'anthologize' ); ?></p>
			</div>
			<?php
		}

		public function load_template() {
			global $anthologize_formats;

			$return = true;

			if ( isset( $_GET['anth_preview'] ) ) {
				if ( ! current_user_can( 'edit_pages' ) ) {
					wp_die( esc_html__( 'You do not have permission to preview this content.', 'anthologize' ), 403 );
				}
				load_template( plugin_dir_path( __FILE__ ) . '../templates/html_preview/preview.php' );
				die();
			}

			if ( isset( $_POST['export-step'] ) ) {
				if ( absint( $_POST['export-step'] ) === 3 ) {
					check_admin_referer( 'anthologize_export' );
					$return = false;
				}
			}

			if ( $return ) {
				return;
			}

			anthologize_save_project_meta();

			require_once anthologize()->includes_dir . 'class-export-panel.php';
			Anthologize_Export_Panel::save_session();

			$session = anthologize_get_session();
			$format  = isset( $session['filetype'] ) ? sanitize_key( $session['filetype'] ) : '';

			if ( empty( $format ) || ! isset( $anthologize_formats[ $format ] ) || ! is_array( $anthologize_formats[ $format ] ) ) {
				return;
			}

			$project_id = isset( $session['project_id'] ) ? absint( $session['project_id'] ) : 0;

			load_template( $anthologize_formats[ $format ]['loader-path'] );
			die;
		}

		public function get_project_parts( $project_id = null ) {
			global $post;

			if ( ! $project_id ) {
				$project_id = $post->ID;
			}

			$args = array(
				'post_parent'    => absint( $project_id ),
				'post_type'      => 'anth_part',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);

			$parts_query = new WP_Query( $args );

			if ( $parts = $parts_query->posts ) {
				return $parts;
			}

			return false;
		}

		public function get_project_items( $project_id = null ) {
			global $post;

			if ( ! $project_id ) {
				$project_id = $post->ID;
			}

			$parts = $this->get_project_parts( $project_id );

			$items = array();
			if ( $parts ) {
				foreach ( $parts as $part ) {
					$args = array(
						'post_parent'    => $part->ID,
						'post_type'      => 'anth_library_item',
						'posts_per_page' => -1,
						'orderby'        => 'menu_order',
						'order'          => 'ASC',
					);

					$items_query = new WP_Query( $args );

					if ( $child_posts = $items_query->posts ) {
						foreach ( $child_posts as $child_post ) {
							$items[] = $child_post;
						}
					}
				}
			}

			return $items;
		}

		/**
		 * Displays the markup for the main admin panel.
		 */
		public function display() {
			$project = null;
			if ( isset( $_GET['project_id'] ) ) {
				$project = get_post( absint( $_GET['project_id'] ) );
			}

			if ( isset( $_GET['action'] ) ) {
				$action = sanitize_key( $_GET['action'] );

				if ( 'delete' === $action && $project ) {
					check_admin_referer( 'anthologize_delete_project' );

					if ( current_user_can( 'delete_post', $project->ID ) ) {
						wp_delete_post( $project->ID );
					}
				}

				if ( 'edit' === $action && $project ) {
					$this->load_project_organizer( absint( $_GET['project_id'] ) );
				}
			}

			$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

			if (
				'' === $action ||
				'list-projects' === $action ||
				( 'edit' === $action && ! $project ) ||
				'delete' === $action
			) {

				?>

		<div class="wrap anthologize">

		<div id="anthologize-logo"><img src="<?php echo esc_url( anthologize()->plugin_url . 'images/anthologize-logo.gif' ); ?>" alt="<?php esc_attr_e( 'Anthologize logo', 'anthologize' ); ?>" /></div>
		<h2><?php esc_html_e( 'My Projects', 'anthologize' ); ?> <a href="admin.php?page=anthologize_new_project" class="button add-new-h2"><?php esc_html_e( 'Add New', 'anthologize' ); ?></a></h2>

				<?php if ( isset( $_GET['project_saved'] ) ) : ?>
			<div id="message" class="updated fade">
				<p><?php esc_html_e( 'Project Saved', 'anthologize' ); ?></p>
			</div>
		<?php endif; ?>

				<?php

				if ( ! empty( $action ) && 'edit' === $action && ! isset( $_GET['project_id'] ) || isset( $_GET['project_id'] ) && ! $project ) {
					$this->display_no_project_id_message();
				}

				$this->do_project_query();

				if ( have_posts() ) {
					?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<span class="displaying-num" id="group-dir-count">
					</span>

					<span class="page-numbers" id="group-dir-pag">
					</span>

				</div>
			</div>

			<table cellpadding="0" cellspacing="0" class="widefat">

			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Project Title', 'anthologize' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created By', 'anthologize' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Number of Parts', 'anthologize' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Number of Items', 'anthologize' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date Created', 'anthologize' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date Modified', 'anthologize' ); ?></th>
				</tr>
			</thead>

			<tbody>
					<?php
					while ( have_posts() ) :
						the_post();
						?>

					<tr>

						<th scope="row" class="post-title">
							<a href="admin.php?page=anthologize&amp;action=edit&amp;project_id=<?php the_ID(); ?>" class="row-title"><?php echo esc_html( get_the_title() ); ?></a>

							<br />

							<?php
							$controlActions   = array();
							$the_id           = get_the_ID();

							$delete_url = wp_nonce_url( admin_url( 'admin.php?page=anthologize&action=delete&project_id=' . $the_id ), 'anthologize_delete_project' );

							$controlActions[] = '<a href="admin.php?page=anthologize_new_project&project_id=' . esc_attr( $the_id ) . '">' . esc_html__( 'Project Details', 'anthologize' ) . '</a>';
							$controlActions[] = '<a href="admin.php?page=anthologize&action=edit&project_id=' . esc_attr( $the_id ) . '">' . esc_html__( 'Manage Parts', 'anthologize' ) . '</a>';
							$controlActions[] = '<a href="' . esc_url( $delete_url ) . '" class="confirm-delete">' . esc_html__( 'Delete Project', 'anthologize' ) . '</a>';
							?>

							<?php if ( count( $controlActions ) ) : ?>
								<div class="row-actions">
									<?php echo implode( ' | ', $controlActions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>
								</div>
							<?php endif; ?>


						</th>


						<td>
							<?php the_author(); ?>
						</td>

						<td>
							<?php
							$parts = $this->get_project_parts();
							echo ( is_array( $parts ) ? count( $parts ) : '0' );
							?>
						</td>

						<td>
							<?php
							$items = $this->get_project_items();
							echo count( $items );
							?>
						</td>

						<td>
							<?php
							global $post;
							echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) ) );
							?>
						</td>

						<td>
							<?php the_modified_date(); ?>
						</td>

						<?php do_action( 'anthologize_project_column_data' ); ?>

					</tr>

					<?php endwhile; ?>

			</tbody>

			</table>

					<?php
				} else {
					?>
			<p><?php esc_html_e( 'You haven\'t created any projects yet.', 'anthologize' ); ?></p>

			<p><a href="admin.php?page=anthologize_new_project"><?php esc_html_e( 'Start a new project.', 'anthologize' ); ?></a></p>

					<?php
				}

				?>
		</div><!-- .wrap -->
				<?php

			}
		}

		/**
		 * Pulls up the projects for the logged-in user.
		 */
		public function do_project_query() {
			$args = array(
				'post_type' => 'anth_project',
			);

			if ( ! current_user_can( 'edit_others_posts' ) ) {
				$args['author'] = get_current_user_id();
			}

			query_posts( $args ); // phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts -- Legacy, reset below
		}

		public function meta_save_box( $post_id ) {
			?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div>
					<input type="submit" name="save" value="<?php esc_attr_e( 'Save Changes', 'anthologize' ); ?>" class="button button-primary">
				</div>
			</div>
		</div>
			<?php
		}

		/**
		 * Processes post save from the item_meta_box function.
		 *
		 * @param int $post_id The post ID being saved.
		 * @return int
		 */
		public function item_meta_save( $post_id ) {
			if ( empty( $_POST['anthologize_noncename'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anthologize_noncename'] ) ), __FILE__ ) ) {
				return $post_id;
			}

			if ( ! $this->user_can_edit( $post_id ) ) {
				return $post_id;
			}

			if ( empty( $_POST['anthologize_meta'] ) || ! is_array( $_POST['anthologize_meta'] ) ) {
				$new_data = array();
			} else {
				$new_data = array_map( 'sanitize_text_field', wp_unslash( $_POST['anthologize_meta'] ) );
			}

			$anthologize_meta = get_post_meta( $post_id, 'anthologize_meta', true );
			if ( ! is_array( $anthologize_meta ) ) {
				$anthologize_meta = array();
			}

			foreach ( $new_data as $key => $value ) {
				$anthologize_meta[ sanitize_key( $key ) ] = $value;
			}

			update_post_meta( $post_id, 'anthologize_meta', $anthologize_meta );

			$author_name = isset( $new_data['author_name'] ) ? $new_data['author_name'] : '';
			update_post_meta( $post_id, 'author_name', $author_name );

			add_filter( 'redirect_post_location', array( $this, 'item_meta_redirect' ) );

			return $post_id;
		}

		/**
		 * Provides a redirect location after a post is saved.
		 *
		 * @param string $location The redirect URL.
		 * @return string
		 */
		public function item_meta_redirect( $location ) {
			if ( isset( $_POST['post_parent'] ) ) {
				$post_parent_id = absint( $_POST['post_parent'] );
			} else {
				$post_id        = isset( $_POST['ID'] ) ? absint( $_POST['ID'] ) : 0;
				$post           = get_post( $post_id );
				$post_parent_id = $post ? $post->post_parent : 0;
			}

			$post_parent = get_post( $post_parent_id );

			if ( isset( $_POST['new_part'] ) ) {
				$arg = absint( $_POST['parent_id'] );
			} else {
				$arg = $post_parent ? $post_parent->post_parent : 0;
			}

			$location = add_query_arg(
				array(
					'page'       => 'anthologize',
					'action'     => 'edit',
					'project_id' => absint( $arg ),
				),
				admin_url( 'admin.php' )
			);

			if ( isset( $_POST['return_to_project'] ) ) {
				$location = add_query_arg(
					array(
						'page'       => 'anthologize',
						'action'     => 'edit',
						'project_id' => absint( $_POST['return_to_project'] ),
					),
					admin_url( 'admin.php' )
				);
			}

			return $location;
		}

		/**
		 * Displays form for editing item metadata.
		 */
		public function item_meta_box() {
			global $post;

			$meta               = get_post_meta( $post->ID, 'anthologize_meta', true );
			$imported_item_meta = get_post_meta( $post->ID, 'imported_item_meta', true );
			$author_name        = get_post_meta( $post->ID, 'author_name', true );

			?>
		<div class="my_meta_control">

			<label for="author-name"><?php esc_html_e( 'Author Name', 'anthologize' ); ?> <span><?php esc_html_e( '(optional)', 'anthologize' ); ?></span></label>

			<p>
				<textarea class="tags-input" id="author-name" name="anthologize_meta[author_name]" rows="3"><?php echo esc_textarea( $author_name ); ?></textarea>
			</p>

			<?php if ( $imported_item_meta ) : ?>
				<dl>
				<?php foreach ( $imported_item_meta as $key => $value ) : ?>
					<?php
						$the_array = array( 'feed_title', 'link', 'created_date' );
					if ( ! in_array( $key, $the_array, true ) ) {
						continue;
					}

					$dt = '';
					$dd = '';

					switch ( $key ) {
						case 'feed_title':
							$dt = __( 'Source feed:', 'anthologize' );
							$dd = '<a href="' . esc_url( $imported_item_meta['feed_permalink'] ) . '">' . esc_html( $value ) . '</a>';
							break;
						case 'link':
							$dt = __( 'Source URL:', 'anthologize' );
							$dd = '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
							break;
						case 'created_date':
							$dt = __( 'Date created:', 'anthologize' );
							$dd = esc_html( $value );
							break;
						default:
							break;
					}
					?>

					<?php if ( $dt ) : ?>
					<dt><?php echo esc_html( $dt ); ?></dt>
					<dd><?php echo $dd; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></dd>
					<?php endif; ?>
				<?php endforeach; ?>
				</dl>

			<?php endif; ?>

			<?php if ( isset( $_GET['return_to_project'] ) ) : ?>
				<input type="hidden" name="return_to_project" value="<?php echo esc_attr( absint( $_GET['return_to_project'] ) ); ?>" />
			<?php endif; ?>

			<?php if ( isset( $_GET['new_part'] ) ) : ?>
				<input type="hidden" id="new_part" name="new_part" value="1" />
				<input type="hidden" id="anth_parent_id" name="parent_id" value="<?php echo esc_attr( absint( $_GET['project_id'] ) ); ?>" />
			<?php endif; ?>

			<input type="hidden" id="menu_order" name="menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>">
			<input class="tags-input" type="hidden" id="anthologize_noncename" name="anthologize_noncename" value="<?php echo esc_attr( wp_create_nonce( __FILE__ ) ); ?>" />
		</div>
			<?php
		}

		/**
		 * Checks whether a user has permission to edit the item.
		 *
		 * @param int $post_id Optional. The post ID. Defaults to current post.
		 * @param int $user_id Optional. The user ID. Defaults to current user.
		 * @return bool
		 */
		public function user_can_edit( $post_id = false, $user_id = false ) {
			$user_can_edit = false;

			if ( is_super_admin() ) {
				$user_can_edit = true;
			} else {
				if ( ! $user_id ) {
					$user_id = get_current_user_id();
				}

				$post = null;
				if ( $post_id ) {
					$post = get_post( $post_id );
				}

				if ( $post && (int) $user_id === (int) $post->post_author ) {
					$user_can_edit = true;
				}
			}

			return apply_filters( 'anth_user_can_edit', $user_can_edit, $post_id, $user_id );
		}

		/**
		 * Adds Anthologize settings to the MS dashboard.
		 */
		public function ms_settings() {

			$site_settings = get_site_option( 'anth_site_settings' );
			$minimum_cap   = ! empty( $site_settings['minimum_cap'] ) ? $site_settings['minimum_cap'] : 'manage_options';

			?>

		<h3><?php esc_html_e( 'Anthologize', 'anthologize' ); ?></h3>

		<table id="menu" class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Allow individual site admins to determine which kinds of users can use Anthologize?', 'anthologize' ); ?></th>
				<td>

				<label><input type="checkbox" class="tags-input" name="anth_site_settings[forbid_per_blog_caps]" value="1"
				<?php
				if ( empty( $site_settings['forbid_per_blog_caps'] ) ) :
					?>
					checked="checked"<?php endif; ?>> <?php esc_html_e( 'When unchecked, access to Anthologize will be limited to the default role you select below.', 'anthologize' ); ?></label>

				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="minimum-cap"><?php esc_html_e( 'Default minimum role for Anthologizers', 'anthologize' ); ?></label></th>

				<td>
					<select class="tags-input" name="anth_site_settings[minimum_cap]">
						<option<?php selected( $minimum_cap, 'manage_network' ); ?> value="manage_network"><?php esc_html_e( 'Network Admin', 'anthologize' ); ?></option>

						<option<?php selected( $minimum_cap, 'manage_options' ); ?> value="manage_options"><?php esc_html_e( 'Administrator', 'anthologize' ); ?></option>

						<option<?php selected( $minimum_cap, 'delete_others_posts' ); ?> value="delete_others_posts"><?php esc_html_e( 'Editor', 'anthologize' ); ?></option>

						<option<?php selected( $minimum_cap, 'publish_posts' ); ?> value="publish_posts"><?php esc_html_e( 'Author', 'anthologize' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

			<?php

			wp_nonce_field( 'anth_site_settings', 'anth_site_settings_nonce' );
		}

		/**
		 * Saves the settings created in ms_settings().
		 */
		public function save_ms_settings() {
			if ( ! isset( $_POST['anth_site_settings_nonce'] ) ) {
				return;
			}

			check_admin_referer( 'anth_site_settings', 'anth_site_settings_nonce' );

			$forbid_per_blog_caps = empty( $_POST['anth_site_settings']['forbid_per_blog_caps'] ) ? 1 : 0;

			$allowed_caps = array( 'manage_network', 'manage_options', 'delete_others_posts', 'publish_posts' );
			$minimum_cap  = isset( $_POST['anth_site_settings']['minimum_cap'] ) ? sanitize_text_field( wp_unslash( $_POST['anth_site_settings']['minimum_cap'] ) ) : 'manage_options';
			if ( ! in_array( $minimum_cap, $allowed_caps, true ) ) {
				$minimum_cap = 'manage_options';
			}

			$anth_site_settings = array(
				'forbid_per_blog_caps' => $forbid_per_blog_caps,
				'minimum_cap'          => $minimum_cap,
			);

			update_site_option( 'anth_site_settings', $anth_site_settings );
		}
	}

endif;

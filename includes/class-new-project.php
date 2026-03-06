<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Anthologize_New_Project' ) ) :

	class Anthologize_New_Project {

		public static function init() {
			static $instance;
			if ( empty( $instance ) ) {
				$instance = new Anthologize_New_Project();
			}
			return $instance;
		}

		public function __construct() {
			// Display is called explicitly by the admin panel loader.
		}

		public function save_project() {
			if ( ! current_user_can( 'edit_pages' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'anthologize' ), 403 );
			}

			$post_data = array(
				'post_title'    => __( 'Default Title', 'anthologize' ),
				'post_type'     => 'anth_project',
				'post_status'   => '',
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', true ),
			);

			if ( ! empty( $_POST['post_title'] ) ) {
				$post_data['post_title'] = sanitize_text_field( wp_unslash( $_POST['post_title'] ) );
			}

			if ( ! empty( $_POST['post_status'] ) ) {
				$post_data['post_status'] = sanitize_key( $_POST['post_status'] );
			}

			if ( ! empty( $_POST['project_id'] ) ) {
				$project_id = absint( $_POST['project_id'] );

				$new_anthologize_meta = get_post_meta( $project_id, 'anthologize_meta', true );
				if ( ! is_array( $new_anthologize_meta ) ) {
					$new_anthologize_meta = array();
				}

				if ( ! empty( $_POST['anthologize_meta'] ) && is_array( $_POST['anthologize_meta'] ) ) {
					foreach ( $_POST['anthologize_meta'] as $key => $value ) {
						$new_anthologize_meta[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
					}
				}

				$the_project = get_post( $project_id );
				if ( $the_project && ! empty( $_POST['post_status'] ) && ( $the_project->post_status !== sanitize_key( $_POST['post_status'] ) ) ) {
					$this->change_project_status( $project_id, sanitize_key( $_POST['post_status'] ) );
				}

				$post_data['ID'] = $project_id;
				wp_update_post( $post_data );

				if ( empty( $new_anthologize_meta ) ) {
					delete_post_meta( $post_data['ID'], 'anthologize_meta' );
				} else {
					update_post_meta( $post_data['ID'], 'anthologize_meta', $new_anthologize_meta );
				}
			} else {
				wp_insert_post( $post_data );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=anthologize&project_saved=1' ) );
			exit;
		}

		public function change_project_status( $project_id, $status ) {
			if ( 'publish' !== $status && 'draft' !== $status ) {
				return;
			}

			$args = array(
				'post_status' => array( 'draft', 'publish' ),
				'post_parent' => absint( $project_id ),
				'nopaging'    => true,
				'post_type'   => 'anth_part',
			);

			$parts = get_posts( $args );

			foreach ( $parts as $part ) {
				if ( $part->post_status !== $status ) {
					wp_update_post( array(
						'ID'          => $part->ID,
						'post_status' => $status,
					) );
				}

				$item_args = array(
					'post_status' => array( 'draft', 'publish' ),
					'post_parent' => $part->ID,
					'nopaging'    => true,
					'post_type'   => 'anth_library_item',
				);

				$library_items = get_posts( $item_args );

				foreach ( $library_items as $item ) {
					if ( $item->post_status !== $status ) {
						wp_update_post( array(
							'ID'          => $item->ID,
							'post_status' => $status,
						) );
					}
				}
			}
		}

		public function display() {

			if ( isset( $_POST['save_project'] ) ) {
				check_admin_referer( 'anthologize_new_project' );
				$this->save_project();
				return;
			}

			$project = null;
			$meta    = array();

			if ( ! empty( $_GET['project_id'] ) ) {
				$project_id = absint( $_GET['project_id'] );
				$project    = get_post( $project_id );
				if ( empty( $project ) ) {
					esc_html_e( 'Unknown project ID', 'anthologize' );
					return;
				}
				$meta = get_post_meta( $project->ID, 'anthologize_meta', true );
				if ( ! is_array( $meta ) ) {
					$meta = array();
				}
			}

			?>
		<div class="wrap anthologize">

			<div id="anthologize-logo"><img src="<?php echo esc_url( anthologize()->plugin_url . 'images/anthologize-logo.gif' ); ?>" alt="<?php esc_attr_e( 'Anthologize logo', 'anthologize' ); ?>" /></div>

			<?php if ( $project ) : ?>
			<h2><?php esc_html_e( 'Edit Project', 'anthologize' ); ?></h2>
			<?php else : ?>
			<h2><?php esc_html_e( 'Add New Project', 'anthologize' ); ?></h2>
			<?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'admin.php?page=anthologize_new_project&noheader=true' ) ); ?>" method="post">
				<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="project-title"><?php esc_html_e( 'Project Title', 'anthologize' ); ?></label></th>
					<?php
					$existing_project_title = $project ? $project->post_title : '';
					?>
					<td><input type="text" name="post_title" id="project-title" value="<?php echo esc_attr( $existing_project_title ); ?>"></td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="project-subtitle"><?php esc_html_e( 'Subtitle', 'anthologize' ); ?></label>
					<?php
					$existing_subtitle = isset( $meta['subtitle'] ) ? $meta['subtitle'] : '';
					?>
					<td><input type="text" name="anthologize_meta[subtitle]" id="project-subtitle" value="<?php echo esc_attr( $existing_subtitle ); ?>" /></td>
				</tr>

			</table>


				<div class="anthologize-button"><input type="submit" name="save_project" value="<?php esc_attr_e( 'Save Project', 'anthologize' ); ?>"></div>
			<?php $existing_project_id = $project ? $project->ID : ''; ?>
			<input type="hidden" name="project_id" value="<?php echo esc_attr( $existing_project_id ); ?>">

			<?php wp_nonce_field( 'anthologize_new_project' ); ?>

			</form>

		</div>
			<?php
		}
	}

endif;

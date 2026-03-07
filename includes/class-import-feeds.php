<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Anthologize_Import_Feeds_Panel' ) ) :

	class Anthologize_Import_Feeds_Panel {

		public static function init() {
			static $instance;
			if ( empty( $instance ) ) {
				$instance = new Anthologize_Import_Feeds_Panel();
			}
			return $instance;
		}

		public function __construct() {
			$this->display();
		}

		public function display() {
			?>
		<div class="wrap anthologize">

		<div id="anthologize-logo"><img src="<?php echo esc_url( anthologize()->plugin_url . 'images/anthologize-logo.gif' ); ?>" alt="<?php esc_attr_e( 'Anthologize logo', 'anthologize' ); ?>" /></div>
			<h2><?php esc_html_e( 'Import Content', 'anthologize' ); ?></h2>

			<?php if ( ! isset( $_POST['feedurl'] ) && ! isset( $_POST['copyitems'] ) ) : ?>

				<div id="export-form">

				<p><?php esc_html_e( 'Want to populate your Anthologize project with content from another web site? Enter the RSS feed address of the site from which you\'d like to import and click Go.', 'anthologize' ); ?></p>

				<p><?php esc_html_e( 'Please respect the rights of copyright holders when using this import tool.', 'anthologize' ); ?></p>

				<form action="" method="post">

				<label for="feedurl"><?php esc_html_e( 'Feed URL:', 'anthologize' ); ?></label>
				<input type="text" name="feedurl" id="feedurl" size="100" />

				<?php wp_nonce_field( 'anthologize_import_feed' ); ?>
				<div class="anthologize-button"><input type="submit" name="submit" id="submit" value="<?php esc_attr_e( 'Go', 'anthologize' ); ?>" /></div>

				</form>

			<?php elseif ( isset( $_POST['feedurl'] ) && ! isset( $_POST['copyitems'] ) ) : ?>
				<?php check_admin_referer( 'anthologize_import_feed' ); ?>
				<?php $feedurl = esc_url_raw( wp_unslash( $_POST['feedurl'] ) ); ?>
				<?php $items = $this->grab_feed( $feedurl ); ?>
				<?php if ( isset( $items['error'] ) ) : ?>

					<p><?php esc_html_e( 'Sorry, no items were found. Please try another feed address.', 'anthologize' ); ?></p>

				<?php else : ?>

				<div id="export-form">

				<p><?php esc_html_e( 'Select the items you\'d like to import to your Imported Items library and click Import.', 'anthologize' ); ?></p>

				<form action="" method="post">

					<h3><?php esc_html_e( 'Feed items:', 'anthologize' ); ?></h3>

					<ul class="potential-feed-items">
					<?php foreach ( $items as $key => $item ) : ?>
						<li>
							<label><input name="copyitems[]" type="checkbox" checked="checked" value="<?php echo esc_attr( $key ); ?>"> <strong><?php echo esc_html( $item['title'] ); ?></strong></label> <?php echo esc_html( wp_strip_all_tags( $item['description'] ) ); ?>
						</li>
					<?php endforeach; ?>
					</ul>

					<input type="hidden" name="feedurl" value="<?php echo esc_attr( $feedurl ); ?>" />
					<?php wp_nonce_field( 'anthologize_import_items' ); ?>
					<div class="anthologize-button"><input type="submit" name="submit_items" id="submit-import" value="<?php esc_attr_e( 'Import', 'anthologize' ); ?>" /></div>

				</form>


				<p><?php esc_html_e( 'Or enter a new feed URL and click Go to import different feed content.', 'anthologize' ); ?></p>

				<form action="" method="post">

					<label for="feedurl"><?php esc_html_e( 'Feed URL:', 'anthologize' ); ?></label>
					<input type="text" name="feedurl" id="feedurl" size="100" value="<?php echo esc_attr( $feedurl ); ?>" />

					<?php wp_nonce_field( 'anthologize_import_feed' ); ?>
					<div class="anthologize-button"><input type="submit" name="submit" id="submit-search" value="<?php esc_attr_e( 'Go', 'anthologize' ); ?>" /></div>

				</form>


				</div>


				<?php endif; ?>

			<?php elseif ( isset( $_POST['copyitems'] ) ) : ?>
				<?php check_admin_referer( 'anthologize_import_items' ); ?>
				<?php

				$feedurl = isset( $_POST['feedurl'] ) ? esc_url_raw( wp_unslash( $_POST['feedurl'] ) ) : '';
				$items   = $this->grab_feed( $feedurl );

				if ( ! isset( $items['error'] ) ) {
					$selected = array_map( 'absint', $_POST['copyitems'] );

					foreach ( $items as $key => $item ) {
						if ( ! in_array( $key, $selected, true ) ) {
							unset( $items[ $key ] );
						}
					}
					$items = array_values( $items );

					$imported_items = array();
					foreach ( $items as $item ) {
						$imported_items[] = $this->import_item( $item );
					}

					?>

				<h3><?php esc_html_e( 'Successfully imported!', 'anthologize' ); ?></h3>

				<?php } else { ?>

				<h3><?php esc_html_e( 'No items found. Please try another feed address.', 'anthologize' ); ?></h3>

				<?php } ?>


				<p><a href="admin.php?page=anthologize"><?php esc_html_e( 'Back to Anthologize', 'anthologize' ); ?></a></p>

			<?php endif; ?>
		</div>
			<?php
		}

		public function grab_feed( $feedurl ) {
			if ( empty( $feedurl ) ) {
				return array( 'error' => 'empty-url' );
			}

			$rss = fetch_feed( trim( $feedurl ) );

			if ( is_wp_error( $rss ) ) {
				return array( 'error' => 'fetch-error' );
			}

			$maxitems = $rss->get_item_quantity();
			if ( ! $maxitems ) {
				return array( 'error' => 'no-items' );
			}

			$feed_title     = $rss->get_title();
			$feed_permalink = $rss->get_permalink();

			$items_data = array(
				'feed_title'     => $feed_title,
				'feed_permalink' => $feed_permalink,
			);

			$items = array();
			foreach ( $rss->get_items( 0, $maxitems ) as $rss_item ) {
				$item_data = $items_data;

				$item_data['link']         = $rss_item->get_link();
				$item_data['title']        = $rss_item->get_title();
				$item_data['authors']      = $rss_item->get_authors();
				$item_data['created_date'] = $rss_item->get_date();
				$item_data['categories']   = $rss_item->get_categories();
				$item_data['contributors'] = $rss_item->get_contributors();
				$item_data['copyright']    = $rss_item->get_copyright();
				$item_data['description']  = $rss_item->get_description();
				$item_data['content']      = $rss_item->get_content();
				$item_data['permalink']    = $rss_item->get_permalink();

				$items[] = $item_data;
			}

			return $items;
		}

		public function import_item( $item ) {
			$tags = array();

			if ( ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
				foreach ( $item['categories'] as $cat ) {
					if ( isset( $cat->term ) ) {
						$tags[] = sanitize_text_field( $cat->term );
					}
				}
			}

			$args = array(
				'post_status'    => 'draft',
				'post_type'      => 'anth_imported_item',
				'post_author'    => get_current_user_id(),
				'guid'           => esc_url_raw( $item['permalink'] ),
				'post_content'   => wp_kses_post( $item['content'] ),
				'post_excerpt'   => wp_kses_post( $item['description'] ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_title'     => sanitize_text_field( $item['title'] ),
				'tags_input'     => $tags,
			);

			if ( isset( $item['created_date'] ) ) {
				$original_post_date    = gmdate( 'Y-m-d H:i:s', strtotime( $item['created_date'] ) );
				$args['post_date']     = $original_post_date;
				$args['post_date_gmt'] = $original_post_date;
			}

			$post_id = wp_insert_post( $args );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				$author_name = ! empty( $item['authors'][0]->name ) ? sanitize_text_field( $item['authors'][0]->name ) : '';
				update_post_meta( $post_id, 'author_name', $author_name );
				update_post_meta( $post_id, 'imported_item_meta', $item );
			}

			return $post_id;
		}
	}

endif;

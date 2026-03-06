<?php
/**
 * Preview project/part template
 *
 * @package Anthologize
 * @since 0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'edit_pages' ) ) {
	wp_die( esc_html__( 'You do not have permission to preview this content.', 'anthologize' ), 403 );
}

$post_id   = ! empty( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
$post_type = ! empty( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';

$allowed_types = array( 'anth_project', 'anth_part', 'anth_library_item' );
if ( ! in_array( $post_type, $allowed_types, true ) ) {
	wp_die( esc_html__( 'Invalid post type.', 'anthologize' ), 400 );
}

if ( ! $post_id ) {
	wp_die( esc_html__( 'Invalid post ID.', 'anthologize' ), 400 );
}

$preview_query = new WP_Query( array(
	'p'         => $post_id,
	'post_type' => $post_type,
) );

$preview_title = '';
if ( $preview_query->have_posts() ) {
	while ( $preview_query->have_posts() ) {
		$preview_query->the_post();
		$preview_title = get_the_title();
	}
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
	<title><?php echo esc_html( $preview_title ); ?> <?php esc_html_e( '(Anthologize Preview Mode)', 'anthologize' ); ?></title>

	<link rel='stylesheet' id='anthologize-preview-css' href='<?php echo esc_url( plugins_url( 'anthologize/css/preview.css' ) ); ?>' type='text/css' media='all' />

</head>

<body>

<p id="preview-notice">
	<?php
	printf(
		/* translators: %1$s: project title, %2$s: export URL */
		wp_kses(
			__( 'You are viewing a preview of <strong>%1$s</strong>. This preview is for proofreading purposes only. To get a more accurate sense of what your Anthologize project will look like, you may want to <a href="%2$s">export the project</a>.', 'anthologize' ),
			array( 'strong' => array(), 'a' => array( 'href' => array() ) )
		),
		esc_html( $preview_title ),
		esc_url( admin_url( 'admin.php?page=anthologize_export_project' ) )
	);
	?>
</p>

<?php
$preview_query->rewind_posts();
if ( $preview_query->have_posts() ) :
?>
	<ul>
	<?php while ( $preview_query->have_posts() ) : ?>
		<?php $preview_query->the_post(); ?>

		<li>
			<h2><?php the_title(); ?></h2>
			<?php the_content(); ?>

			<?php if ( 'anth_library_item' !== $post_type ) : ?>
				<?php $child_post_type = 'anth_part' === $post_type ? 'anth_library_item' : 'anth_part'; ?>
				<?php $children = new WP_Query( array( 'post_parent' => $post_id, 'post_type' => $child_post_type, 'posts_per_page' => -1 ) ); ?>

				<?php if ( $children->have_posts() ) : ?>
					<ul>
					<?php while ( $children->have_posts() ) : ?>
						<?php $children->the_post(); ?>
						<li>
						<h3><?php the_title(); ?></h3>
						<?php the_content(); ?>

						<?php if ( 'anth_project' === $post_type ) : ?>
							<?php $grandchildren = new WP_Query( array( 'post_parent' => get_the_ID(), 'post_type' => 'anth_library_item', 'posts_per_page' => -1 ) ); ?>

							<?php if ( $grandchildren->have_posts() ) : ?>
								<ul>
								<?php while ( $grandchildren->have_posts() ) : ?>
									<?php $grandchildren->the_post(); ?>

									<li>
									<h4><?php the_title(); ?></h4>
									<?php the_content(); ?>
									</li>

								<?php endwhile; ?>
								</ul>
							<?php endif; ?>
							<?php wp_reset_postdata(); ?>
						<?php endif; ?>
						</li>
					<?php endwhile; ?>
					</ul>
				<?php endif; ?>
				<?php wp_reset_postdata(); ?>
			<?php endif; ?>
		</li>

	<?php endwhile; ?>
	</ul>
<?php endif; ?>
<?php wp_reset_postdata(); ?>
</body>
</html>

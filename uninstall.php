<?php
/**
 * Anthologize uninstall routine.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Cleans up custom post types, post meta, and cached files created by Anthologize.
 *
 * @package Anthologize
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all posts of Anthologize custom post types.
 *
 * @param string $post_type The post type to delete.
 */
function anthologize_delete_posts_by_type( $post_type ) {
	$posts = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => array( 'publish', 'draft', 'trash', 'pending', 'private', 'auto-draft' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// Delete all Anthologize custom post type data.
$anthologize_post_types = array(
	'anth_project',
	'anth_part',
	'anth_library_item',
	'anth_imported_item',
);

foreach ( $anthologize_post_types as $post_type ) {
	anthologize_delete_posts_by_type( $post_type );
}

// Delete plugin options.
delete_option( 'anthologize' );
delete_option( 'anthologize_version' );

// Clean up the cache directory.
$upload_dir = wp_upload_dir( null, false );
$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'anthologize-cache';

if ( is_dir( $cache_dir ) ) {
	$files = glob( $cache_dir . '/*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	rmdir( $cache_dir );
}

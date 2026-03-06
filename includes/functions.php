<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save project metadata from an export session.
 *
 * Uses an allowlist of known keys to prevent arbitrary data from being saved.
 */
function anthologize_save_project_meta() {
	if ( ! empty( $_POST['project_id'] ) ) {
		$project_id = absint( $_POST['project_id'] );
	} elseif ( ! empty( $_GET['project_id'] ) ) {
		$project_id = absint( $_GET['project_id'] );
	} else {
		return;
	}

	if ( ! $project_id ) {
		return;
	}

	$project_meta = get_post_meta( $project_id, 'anthologize_meta', true );
	if ( ! is_array( $project_meta ) ) {
		$project_meta = array();
	}

	$allowed_keys = array(
		'cyear', 'cname', 'ctype', 'cctype', 'edition', 'authors',
		'post-title', 'dedication', 'acknowledgements', 'filetype',
		'page-size', 'font-size', 'font-face', 'break-parts',
		'break-items', 'colophon', 'do-shortcodes', 'subtitle',
	);

	foreach ( $allowed_keys as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			$project_meta[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}
	}

	update_post_meta( $project_id, 'anthologize_meta', $project_meta );
}

function anthologize_get_project_parts( $projectId ) {
	$projectParts = new WP_Query(
		array(
			'post_parent'    => absint( $projectId ),
			'post_type'      => 'anth_part',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);

	return $projectParts->posts;
}

function anthologize_get_part_items( $partId ) {
	$partItems = new WP_Query(
		array(
			'post_parent'    => absint( $partId ),
			'post_type'      => 'anth_library_item',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);

	return $partItems->posts;
}

/**
 * Get a list of author names for an item.
 *
 * @since 0.8.0
 *
 * @param int $item_id Project, part, or item ID.
 * @return array
 */
function anthologize_get_item_author_names( $item_id ) {
	$names = array();

	$post = get_post( $item_id );
	if ( ! $post ) {
		return $names;
	}

	$item_names = array();
	switch ( $post->post_type ) {
		case 'anth_project':
			$part_query = new WP_Query(
				array(
					'post_type'      => 'anth_part',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_parent'    => $item_id,
				)
			);

			foreach ( $part_query->posts as $part_post ) {
				$item_names = array_merge( $item_names, anthologize_get_item_author_names( $part_post ) );
			}
			break;

		case 'anth_part':
			$item_query = new WP_Query(
				array(
					'post_type'      => 'anth_library_item',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_parent'    => $item_id,
				)
			);

			foreach ( $item_query->posts as $item_post ) {
				$item_names = array_merge( $item_names, anthologize_get_item_author_names( $item_post ) );
			}
			break;

		case 'anth_library_item':
			$item_names = get_post_meta( $item_id, 'author_name_array', true );
			if ( ! is_array( $item_names ) ) {
				$item_names = array();
			}
			break;
	}

	$item_names = array_filter( array_unique( $item_names ) );
	natcasesort( $item_names );

	return $item_names;
}

function anthologize_display_project_content( $projectId ) {
	$parts = anthologize_get_project_parts( $projectId );

	foreach ( $parts as $part ) {
		echo '<h2>' . esc_html( $part->post_title ) . '</h2>' . "\n";
		echo '<div class="anthologize-part-content">' . "\n";
		echo wp_kses_post( $part->post_content ) . "\n";
		echo '</div>' . "\n";

		$items = anthologize_get_part_items( $part->ID );
		foreach ( $items as $item ) {
			echo '<h3>' . esc_html( $item->post_title ) . '</h3>' . "\n";
			echo '<div class="anthologize-item-content">';
			echo wp_kses_post( $item->post_content );
			echo '</div>';
		}
	}
}

function anthologize_filter_post_content( $content ) {
	global $post;
	if ( $post && 'anth_project' === $post->post_type ) {
		ob_start();
		anthologize_display_project_content( get_the_ID() );
		$content .= ob_get_clean();
	}
	return $content;
}

/**
 * Get data about an export "session".
 *
 * @since 0.7.8
 * @return array
 */
function anthologize_get_session() {
	$session = get_user_meta( get_current_user_id(), 'anthologize_export_session', true );
	if ( ! is_array( $session ) ) {
		$session = array();
	}

	return $session;
}

/**
 * Delete current user's active export session.
 *
 * @since 0.7.8
 */
function anthologize_delete_session() {
	delete_user_meta( get_current_user_id(), 'anthologize_export_session' );
}

/**
 * Save data to the current export "session".
 *
 * @since 0.7.8
 *
 * @param array $data Data to save.
 */
function anthologize_save_session( $data ) {
	$keys = anthologize_get_session_data_keys();

	$session = anthologize_get_session();

	foreach ( $keys as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$session[ $key ] = $data[ $key ];
		}
	}

	update_user_meta( get_current_user_id(), 'anthologize_export_session', $session );
}

/**
 * Get a list of keys that are allowed for sessions.
 *
 * @return array
 */
function anthologize_get_session_data_keys() {
	$keys = array(
		// Step 1
		'project_id',
		'cyear',
		'cname',
		'ctype',
		'cctype',
		'edition',
		'authors',

		// Step 2
		'post-title',
		'dedication',
		'acknowledgements',
		'filetype',

		// Step 3
		'page-size',
		'font-size',
		'font-face',
		'break-parts',
		'break-items',
		'colophon',
		'do-shortcodes',
		'metadata',

		'creatorOutputSettings',
		'outputParams',
	);

	/** @since 0.7.8 */
	return apply_filters( 'anthologize_get_session_data_keys', $keys );
}

/**
 * Get session "outputParams" needed by export formats.
 *
 * @return array
 */
function anthologize_get_session_output_params() {
	$session = anthologize_get_session();

	$keys = array(
		'page-size',
		'font-size',
		'font-face',
		'break-parts',
		'break-items',
		'colophon',
		'do-shortcodes',
		'creatorOutputSettings',
		'download',
		'gravatar-default',
		'metadata',
	);

	$params = array();
	foreach ( $keys as $key ) {
		$value          = isset( $session[ $key ] ) ? $session[ $key ] : '';
		$params[ $key ] = $value;
	}

	return $params;
}

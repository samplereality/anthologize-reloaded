<?php
/*
Plugin Name: Anthologize
Plugin URI: http://anthologize.org
Description: Use the power of WordPress to transform your content into a book.
Version: 1.0.1
Text Domain: anthologize
Requires at least: 6.0
Requires PHP: 7.4
Author: One Week | One Tool
Author URI: http://oneweekonetool.org
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
Copyright (C) 2010 Center for History and New Media, George Mason University

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

Anthologize includes TCPDF, which is released under the LGPL Use and
modifications of TDPDF must comply with its license.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ANTHOLOGIZE_VERSION' ) ) {
	define( 'ANTHOLOGIZE_VERSION', '1.0.0' );
}

$anthologize_autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( ! file_exists( $anthologize_autoloader ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Anthologize: Required dependencies are missing. Please run "composer install" in the plugin directory, or download a pre-built release.', 'anthologize' )
		);
	} );
	return;
}
require $anthologize_autoloader;

if ( ! class_exists( 'Anthologize' ) ) :

class Anthologize {

	/** @var string */
	public $basename;

	/** @var string */
	public $plugin_dir;

	/** @var string */
	public $plugin_url;

	/** @var string */
	public $includes_dir;

	/** @var string */
	public $cache_dir;

	/** @var string */
	public $cache_url;

	/** @var Anthologize_Admin_Main|null */
	public $admin;

	/**
	 * Bootstrap for the Anthologize singleton.
	 *
	 * @since 0.7
	 * @return Anthologize
	 */
	public static function init() {
		static $instance;
		if ( empty( $instance ) ) {
			$instance = new Anthologize();
		}
		return $instance;
	}

	/**
	 * Constructor for the Anthologize class.
	 *
	 * @since 0.7
	 */
	public function __construct() {
		if ( ! self::check_minimum_php() ) {
			add_action( 'admin_notices', array( 'Anthologize', 'phpversion_nag' ) );
			return;
		}

		if ( ! self::check_minimum_wp() ) {
			add_action( 'admin_notices', array( 'Anthologize', 'wpversion_nag' ) );
			return;
		}

		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

		$bn = explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) );
		$this->basename     = array_pop( $bn );
		$this->plugin_dir   = plugin_dir_path( __FILE__ );
		$this->plugin_url   = plugin_dir_url( __FILE__ );
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );

		$upload_dir         = wp_upload_dir( null, false );
		$this->cache_dir    = trailingslashit( $upload_dir['basedir'] . '/anthologize-cache' );
		$this->cache_url    = trailingslashit( $upload_dir['baseurl'] . '/anthologize-cache' );

		$this->setup_constants();
		$this->includes();
		$this->setup_hooks();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Check minimum PHP version (7.4).
	 *
	 * @return bool
	 */
	public static function check_minimum_php() {
		return version_compare( phpversion(), '7.4', '>=' );
	}

	/**
	 * Check minimum WP version (6.0).
	 *
	 * @return bool
	 */
	public static function check_minimum_wp() {
		return version_compare( get_bloginfo( 'version' ), '6.0', '>=' );
	}

	/**
	 * Admin notice for PHP version requirement.
	 *
	 * @since 0.7
	 */
	public static function phpversion_nag() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %s: Current PHP version */
				esc_html__( 'Anthologize requires PHP 7.4 or greater. You are running PHP %s.', 'anthologize' ),
				esc_html( phpversion() )
			)
		);
	}

	/**
	 * Admin notice for WP version requirement.
	 *
	 * @since 0.7
	 */
	public static function wpversion_nag() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %s: Current WordPress version */
				esc_html__( 'Anthologize requires WordPress 6.0 or greater. You are running WordPress %s.', 'anthologize' ),
				esc_html( get_bloginfo( 'version' ) )
			)
		);
	}

	/**
	 * Set up constants.
	 *
	 * @since 0.7
	 */
	public function setup_constants() {
		if ( ! defined( 'ANTHOLOGIZE_INSTALL_PATH' ) ) {
			define( 'ANTHOLOGIZE_INSTALL_PATH', $this->plugin_dir );
		}

		if ( ! defined( 'ANTHOLOGIZE_INCLUDES_PATH' ) ) {
			define( 'ANTHOLOGIZE_INCLUDES_PATH', $this->includes_dir );
		}

		if ( ! defined( 'ANTHOLOGIZE_TEIDOM_PATH' ) ) {
			define( 'ANTHOLOGIZE_TEIDOM_PATH', $this->includes_dir . 'class-tei-dom.php' );
		}

		if ( ! defined( 'ANTHOLOGIZE_TEIDOMAPI_PATH' ) ) {
			define( 'ANTHOLOGIZE_TEIDOMAPI_PATH', $this->includes_dir . 'class-tei-api.php' );
		}

		if ( ! defined( 'ANTHOLOGIZE_CREATORS_ALL' ) ) {
			define( 'ANTHOLOGIZE_CREATORS_ALL', 1 );
		}

		if ( ! defined( 'ANTHOLOGIZE_CREATORS_ASSERTED' ) ) {
			define( 'ANTHOLOGIZE_CREATORS_ASSERTED', 2 );
		}
	}

	/**
	 * Include required files.
	 *
	 * @since 0.7
	 */
	public function includes() {
		require $this->includes_dir . 'class-format-api.php';
		require $this->includes_dir . 'functions.php';

		if ( is_admin() ) {
			require $this->includes_dir . 'class-admin-main.php';
			$this->admin = new Anthologize_Admin_Main();
		}
	}

	/**
	 * Set up hooks.
	 */
	public function setup_hooks() {
		add_action( 'init',             array( $this, 'anthologize_init' ) );
		add_action( 'anthologize_init', array( $this, 'register_post_types' ) );
		add_action( 'plugins_loaded',   array( $this, 'textdomain' ) );
	}

	/**
	 * Fire the anthologize_init action.
	 */
	public static function anthologize_init() {
		do_action( 'anthologize_init' );
	}

	/**
	 * Activation routine.
	 */
	public function activation() {
		require_once dirname( __FILE__ ) . '/includes/class-activation.php';
		$activation = new Anthologize_Activation();
	}

	/**
	 * Deactivation routine.
	 */
	public function deactivation() {}

	/**
	 * Register custom post types.
	 */
	public function register_post_types() {
		register_post_type( 'anth_project', array(
			'label'               => __( 'Projects', 'anthologize' ),
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'capability_type'     => 'page',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'revisions' ),
		) );

		register_post_type( 'anth_part', array(
			'label'               => __( 'Parts', 'anthologize' ),
			'labels'              => array(
				'add_new_item' => __( 'Add New Part', 'anthologize' ),
			),
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'capability_type'     => 'page',
			'hierarchical'        => true,
			'supports'            => array( 'title' ),
		) );

		register_post_type( 'anth_library_item', array(
			'label'               => __( 'Library Items', 'anthologize' ),
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'capability_type'     => 'page',
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'revisions', 'comments' ),
		) );

		register_post_type( 'anth_imported_item', array(
			'label'               => __( 'Imported Items', 'anthologize' ),
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'capability_type'     => 'page',
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'revisions' ),
		) );
	}

	/**
	 * Load text domain.
	 */
	public function textdomain() {
		$locale = get_locale();

		$mofile_custom = WP_CONTENT_DIR . "/anthologize-files/languages/anthologize-$locale.mo";

		if ( file_exists( $mofile_custom ) ) {
			load_textdomain( 'anthologize', $mofile_custom );
		} else {
			load_plugin_textdomain( 'anthologize', false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}
	}

	/**
	 * Register static assets with WordPress.
	 *
	 * @since 0.8.0
	 */
	public function register_assets() {
		wp_register_style( 'anthologize-admin-general', $this->plugin_url . 'css/admin-general.css', array(), ANTHOLOGIZE_VERSION );

		wp_register_style( 'anthologize-admin', $this->plugin_url . 'css/admin.css', array(), ANTHOLOGIZE_VERSION );

		wp_register_script( 'blockUI-js', $this->plugin_url . 'js/jquery.blockUI.js', array(), ANTHOLOGIZE_VERSION, true );
		wp_register_script( 'jquery-cookie', $this->plugin_url . 'js/jquery-cookie.js', array(), ANTHOLOGIZE_VERSION, true );

		wp_register_script(
			'anthologize-project-organizer',
			$this->plugin_url . 'js/project-organizer.js',
			array(
				'jquery-ui-sortable',
				'jquery-ui-draggable',
				'jquery-ui-datepicker',
				'blockUI-js',
				'jquery-cookie',
			),
			ANTHOLOGIZE_VERSION,
			true
		);

		wp_register_script( 'anthologize-sortlist-js', $this->plugin_url . 'js/anthologize-sortlist.js', array( 'anthologize-project-organizer' ), ANTHOLOGIZE_VERSION, true );

		wp_localize_script( 'anthologize-sortlist-js', 'anth_ajax', array(
			'nonce' => wp_create_nonce( 'anthologize_ajax' ),
		) );

		wp_localize_script( 'anthologize-sortlist-js', 'anth_strings', array(
			'append'           => __( 'Append', 'anthologize' ),
			'cancel'           => __( 'Cancel', 'anthologize' ),
			'commenter'        => __( 'Commenter', 'anthologize' ),
			'comment_content'  => __( 'Comment Content', 'anthologize' ),
			'comments'         => __( 'Comments', 'anthologize' ),
			'comments_explain' => __( 'Check the comments from the original post that you would like to include in your project.', 'anthologize' ),
			'done'             => __( 'Done', 'anthologize' ),
			'edit'             => __( 'Edit', 'anthologize' ),
			'hide_details'     => __( 'Hide details', 'anthologize' ),
			'less'             => __( 'less', 'anthologize' ),
			'more'             => __( 'more', 'anthologize' ),
			'no_comments'      => __( 'This post has no comments associated with it.', 'anthologize' ),
			'preview'          => __( 'Preview', 'anthologize' ),
			'posted'           => __( 'Posted', 'anthologize' ),
			'remove'           => __( 'Remove', 'anthologize' ),
			'save'             => __( 'Save', 'anthologize' ),
			'select_all'       => __( 'Select all', 'anthologize' ),
			'select_none'      => __( 'Select none', 'anthologize' ),
			'show_details'     => __( 'Show details', 'anthologize' ),
		) );
	}
}

endif;

/**
 * Access the Anthologize singleton and bootstrap the plugin.
 *
 * @since 0.7
 * @return Anthologize
 */
function anthologize() {
	return Anthologize::init();
}

$_GLOBALS['anthologize'] = anthologize();

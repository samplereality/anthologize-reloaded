<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anthologize_Settings {
	/** @var array */
	private $settings;

	/** @var array */
	private $site_settings;

	/** @var string */
	private $minimum_cap;

	/** @var bool */
	private $forbid_local_caps;

	public static function init() {
		static $instance;
		if ( empty( $instance ) ) {
			$instance = new Anthologize_Settings();
		}
		return $instance;
	}

	public function __construct() {
		$this->settings          = $this->get_settings();
		$this->site_settings     = $this->get_site_settings();
		$this->forbid_local_caps = $this->forbid_local_caps();
		$this->minimum_cap       = $this->minimum_cap();

		$this->display();
	}

	public function get_settings() {
		$settings = get_option( 'anth_settings' );
		return is_array( $settings ) ? $settings : array();
	}

	public function get_site_settings() {
		$site_settings = array();

		if ( is_multisite() ) {
			$site_settings = get_site_option( 'anth_site_settings' );
			if ( ! is_array( $site_settings ) ) {
				$site_settings = array();
			}
		}

		return apply_filters( 'anth_site_settings', $site_settings );
	}

	public function forbid_local_caps() {
		$forbid_local_caps = false;

		if ( ! empty( $this->site_settings['forbid_per_blog_caps'] ) ) {
			$forbid_local_caps = true;
		}

		return apply_filters( 'anth_forbid_local_caps', $forbid_local_caps );
	}

	public function save() {
		check_admin_referer( 'anth_settings' );

		$allowed_caps = array( 'manage_network', 'manage_options', 'delete_others_posts', 'publish_posts' );

		$anth_settings = array();

		if ( ! empty( $_POST['anth_settings'] ) && is_array( $_POST['anth_settings'] ) ) {
			if ( isset( $_POST['anth_settings']['minimum_cap'] ) ) {
				$cap = sanitize_text_field( wp_unslash( $_POST['anth_settings']['minimum_cap'] ) );
				if ( in_array( $cap, $allowed_caps, true ) ) {
					$anth_settings['minimum_cap'] = $cap;
				} else {
					$anth_settings['minimum_cap'] = 'manage_options';
				}
			}
		}

		$this->settings    = $anth_settings;
		$this->minimum_cap = isset( $anth_settings['minimum_cap'] ) ? $anth_settings['minimum_cap'] : 'manage_options';

		update_option( 'anth_settings', $anth_settings );
	}

	public function display() {
		if ( ! empty( $_POST['anth_settings_submit'] ) ) {
			$this->save();
		}
		?>

		<div class="wrap anthologize">

			<div id="blockUISpinner">
				<img src="<?php echo esc_url( anthologize()->plugin_url . 'images/wait28.gif' ); ?>" alt="<?php esc_html_e( 'Please wait...', 'anthologize' ); ?>" aria-hidden="true" />
				<p id="ajaxErrorMsg"><?php esc_html_e( 'There has been an unexpected error. Please wait while we reload the content.', 'anthologize' ); ?></p>
			</div>

			<div id="anthologize-logo"><img src="<?php echo esc_url( anthologize()->plugin_url . 'images/anthologize-logo.gif' ); ?>" alt="<?php esc_attr_e( 'Anthologize logo', 'anthologize' ); ?>" /></div>
				<h2><?php esc_html_e( 'Settings', 'anthologize' ); ?></h2>

			<form action="" method="post" id="bp-admin-form">

			<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="minimum-cap"><?php esc_html_e( 'Minimum role for creating and editing Anthologize projects', 'anthologize' ); ?>:</label></th>
					<td>
						<select name="anth_settings[minimum_cap]" id="minimum-cap" <?php disabled( $this->forbid_local_caps ); ?>>
						<?php if ( is_multisite() ) : ?>
							<option<?php selected( $this->minimum_cap, 'manage_network' ); ?> value="manage_network"><?php esc_html_e( 'Network Admin', 'anthologize' ); ?></option>
						<?php endif; ?>

							<option<?php selected( $this->minimum_cap, 'manage_options' ); ?> value="manage_options"><?php esc_html_e( 'Administrator', 'anthologize' ); ?></option>

							<option<?php selected( $this->minimum_cap, 'delete_others_posts' ); ?> value="delete_others_posts"><?php esc_html_e( 'Editor', 'anthologize' ); ?></option>

							<option<?php selected( $this->minimum_cap, 'publish_posts' ); ?> value="publish_posts"><?php esc_html_e( 'Author', 'anthologize' ); ?></option>
						</select>
						<?php if ( $this->forbid_local_caps ) : ?>
							<p class="description"><?php esc_html_e( 'Your network administrator has disabled this setting.', 'anthologize' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
			</table>

			<p class="submit">
				<input class="button-primary" type="submit" name="anth_settings_submit" value="<?php esc_attr_e( 'Save Settings', 'anthologize' ); ?>"/>
			</p>

			<?php wp_nonce_field( 'anth_settings' ); ?>


			</form>

		</div>

		<?php
	}

	public function minimum_cap() {
		$default_cap = 'manage_options';

		if ( is_multisite() ) {
			if ( $this->forbid_local_caps ) {
				$minimum_cap = ! empty( $this->site_settings['minimum_cap'] ) ? $this->site_settings['minimum_cap'] : 'manage_options';
			} else {
				$default_cap = ! empty( $this->site_settings['minimum_cap'] ) ? $this->site_settings['minimum_cap'] : 'manage_options';
				$minimum_cap = ! empty( $this->settings['minimum_cap'] ) ? $this->settings['minimum_cap'] : $default_cap;
			}
		} else {
			$minimum_cap = ! empty( $this->settings['minimum_cap'] ) ? $this->settings['minimum_cap'] : $default_cap;
		}

		return apply_filters( 'anth_settings_minimum_cap', $minimum_cap );
	}
}

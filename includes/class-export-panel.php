<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Anthologize_Export_Panel' ) ) :

	class Anthologize_Export_Panel {

		/** @var int */
		private $project_id;

		/** @var array */
		private $projects;

		public static function init() {
			static $instance;
			if ( empty( $instance ) ) {
				$instance = new Anthologize_Export_Panel();
			}
			return $instance;
		}

		public function __construct() {
			$this->projects = $this->get_projects();

			$project_id = 0;
			if ( isset( $_GET['project_id'] ) ) {
				$project_id = absint( $_GET['project_id'] );
			} elseif ( ! empty( $this->projects ) ) {
				$keys       = array_keys( $this->projects );
				$project_id = $keys[0];
			}

			$this->project_id = $project_id;

			$export_step = isset( $_POST['export-step'] ) ? absint( $_POST['export-step'] ) : 1;

			if ( 1 === $export_step && ! isset( $_POST['export-step'] ) ) {
				anthologize_delete_session();
			}

			if ( 3 !== $export_step ) {
				$this->display();
			}
		}

		public function display() {
			wp_enqueue_style( 'anthologize-admin' );

			$project_id = $this->project_id;

			if ( isset( $_POST['export-step'] ) ) {
				check_admin_referer( 'anthologize_export' );
				$this->save_session();
			}

			$options = get_post_meta( $project_id, 'anthologize_meta', true );
			if ( ! is_array( $options ) ) {
				$options = array();
			}

			$cdate = ! empty( $options['cdate'] ) ? $options['cdate'] : gmdate( 'Y' );

			if ( isset( $options['cname'] ) ) {
				$cname = $options['cname'];
			} elseif ( isset( $options['author_name'] ) ) {
				$cname = $options['author_name'];
			} else {
				$cname = '';
			}

			$ctype  = ! empty( $options['ctype'] ) ? $options['ctype'] : 'cc';
			$cctype = ! empty( $options['cctype'] ) ? $options['cctype'] : 'by';

			$edition = isset( $options['edition'] ) ? $options['edition'] : '';

			if ( isset( $options['authors'] ) ) {
				$authors = $options['authors'];
			} else {
				$author_names = anthologize_get_item_author_names( $project_id );
				$authors      = implode( ', ', $author_names );
			}

			$dedication      = ! empty( $options['dedication'] ) ? $options['dedication'] : '';
			$acknowledgements = ! empty( $options['acknowledgements'] ) ? $options['acknowledgements'] : '';

			$export_step = isset( $_POST['export-step'] ) ? absint( $_POST['export-step'] ) : 0;

			?>
		<div class="wrap anthologize">

		<div id="blockUISpinner">
			<img src="<?php echo esc_url( anthologize()->plugin_url . 'images/wait28.gif' ); ?>" alt="<?php esc_html_e( 'Please wait...', 'anthologize' ); ?>" aria-hidden="true" />
			<p id="ajaxErrorMsg"><?php esc_html_e( 'There has been an unexpected error. Please wait while we reload the content.', 'anthologize' ); ?></p>
		</div>

		<div id="anthologize-logo"><img src="<?php echo esc_url( anthologize()->plugin_url . 'images/anthologize-logo.gif' ); ?>" alt="<?php esc_attr_e( 'Anthologize logo', 'anthologize' ); ?>" /></div>
			<h2><?php esc_html_e( 'Export Project', 'anthologize' ); ?></h2>

			<br />

			<div id="export-form" class="export-panel">

			<?php if ( 0 === $export_step ) : ?>

			<form action="" method="post">

			<div class="export-project-selector">
				<label for="project-id-dropdown"><?php esc_html_e( 'Select a project:', 'anthologize' ); ?></label>
				<select name="project_id" id="project-id-dropdown">
				<?php foreach ( $this->projects as $proj_id => $project_name ) : ?>
					<option value="<?php echo esc_attr( $proj_id ); ?>" <?php selected( $proj_id, $project_id ); ?>><?php echo esc_html( $project_name ); ?></option>
				<?php endforeach; ?>
				</select>
			</div>

			<h3 id="copyright-information-header"><?php esc_html_e( 'Copyright Information', 'anthologize' ); ?></h3>

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="cyear"><?php esc_html_e( 'Year', 'anthologize' ); ?></label></th>
					<td><input type="text" id="cyear" name="cyear" value="<?php echo esc_attr( $cdate ); ?>"/></td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="cname"><?php esc_html_e( 'Copyright Holder', 'anthologize' ); ?></label></th>
					<td><input type="text" id="cname" name="cname" value="<?php echo esc_attr( $cname ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row" id="license-type"><?php esc_html_e( 'Type', 'anthologize' ); ?></th>
					<td>
						<input role="group" aria-labelledby="license-type" type="radio" id="ctype-copyright" name="ctype" value="c" <?php checked( $ctype, 'c' ); ?> /> <label for="ctype-copyright"><?php esc_html_e( 'Copyright', 'anthologize' ); ?></label><br />
						<input role="group" aria-labelledby="license-type" type="radio" id="ctype-cc" name="ctype" value="cc" <?php checked( $ctype, 'cc' ); ?> /> <label for="ctype-cc"><?php esc_html_e( 'Creative Commons', 'anthologize' ); ?></label>

						<label for="cctype" class="screen-reader-text"><?php esc_html_e( 'Select Creative Commons license type', 'anthologize' ); ?></label>
						<select id="cctype" name="cctype">
							<option value=""><?php esc_html_e( 'Select One...', 'anthologize' ); ?></option>
							<option value="by" <?php selected( $cctype, 'by' ); ?>><?php esc_html_e( 'Attribution', 'anthologize' ); ?></option>
							<option value="by-sa" <?php selected( $cctype, 'by-sa' ); ?>><?php esc_html_e( 'Attribution Share-Alike', 'anthologize' ); ?></option>
							<option value="by-nd" <?php selected( $cctype, 'by-nd' ); ?>><?php esc_html_e( 'Attribution No Derivatives', 'anthologize' ); ?></option>
							<option value="by-nc" <?php selected( $cctype, 'by-nc' ); ?>><?php esc_html_e( 'Attribution Non-Commercial', 'anthologize' ); ?></option>
							<option value="by-nc-sa" <?php selected( $cctype, 'by-nc-sa' ); ?>><?php esc_html_e( 'Attribution Non-Commercial Share Alike', 'anthologize' ); ?></option>
							<option value="by-nc-nd" <?php selected( $cctype, 'by-nc-nd' ); ?>><?php esc_html_e( 'Attribution Non-Commercial No Derivatives', 'anthologize' ); ?></option>
						</select>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="edition"><?php esc_html_e( 'Edition', 'anthologize' ); ?></label></th>
					<td><input type="text" id="edition" name="edition" value="<?php echo esc_attr( $edition ); ?>" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="authors"><?php esc_html_e( 'Author(s)', 'anthologize' ); ?></label></th>
					<td>
						<textarea id="authors" name="authors"><?php echo esc_textarea( $authors ); ?></textarea>
						<p class="description"><?php esc_html_e( 'The default value is automatically compiled, based on authors of the source content.', 'anthologize' ); ?></p>
					</td>
				</tr>
			</table>

			<input type="hidden" id="export-step" name="export-step" value="1" />
			<?php wp_nonce_field( 'anthologize_export' ); ?>
			<div class="anthologize-button" id="export-next"><input type="submit" name="submit" id="submit" value="<?php esc_attr_e( 'Next', 'anthologize' ); ?>" /></div>

			</form>

			<?php elseif ( 1 === $export_step ) : ?>

				<?php anthologize_save_project_meta(); ?>

				<?php $project_id = absint( $_POST['project_id'] ); ?>
				<?php $project = get_post( $project_id ); ?>

			<form action="" method="post">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="post-title"><?php esc_html_e( 'Title', 'anthologize' ); ?></label>
						</th>

						<td>
							<input type="text" name="post-title" id="post-title" value="<?php echo esc_attr( $project ? $project->post_title : '' ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="dedication"><?php esc_html_e( 'Dedication', 'anthologize' ); ?></label>
						</th>

						<td>
							<textarea id="dedication" name="dedication" rows="5"><?php echo esc_textarea( $dedication ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="acknowledgements"><?php esc_html_e( 'Acknowledgements', 'anthologize' ); ?></label>
						</th>

						<td>
							<textarea id="acknowledgements" name="acknowledgements" rows="5"><?php echo esc_textarea( $acknowledgements ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Export Format', 'anthologize' ); ?>
						</th>

						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Export Format', 'anthologize' ); ?></legend>

								<?php $this->export_format_list(); ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<input type="hidden" name="export-step" value="2" />
				<?php wp_nonce_field( 'anthologize_export' ); ?>

				<div style="clear: both;"> </div>

				<div class="anthologize-button" id="export-next"><input type="submit" name="submit" id="submit" value="<?php esc_attr_e( 'Next', 'anthologize' ); ?>" /></div>

			</form>

			<?php elseif ( 2 === $export_step ) : ?>

				<form action="<?php echo esc_url( admin_url( 'admin.php?page=anthologize_export_project&project_id=' . intval( $project_id ) . '&noheader=true' ) ); ?>" method="post">

				<h3><?php $this->export_format_options_title(); ?></h3>

				<table class="form-table">

					<?php $this->render_format_options(); ?>

					<tr>
						<th scope="row">
							<label for="do-shortcodes"><?php esc_html_e( 'Shortcodes', 'anthologize' ); ?></label>
						</th>

						<td>
							<select name="do-shortcodes" id="do-shortcodes">
								<option value="1" selected="selected"><?php esc_html_e( 'Enable', 'anthologize' ); ?></option>
								<option value="0"><?php esc_html_e( 'Disable', 'anthologize' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'WordPress shortcodes (such as [caption]) can sometimes cause problems with output formats. If shortcode content shows up incorrectly in your output, choose "Disable" to keep Anthologize from processing them.', 'anthologize' ); ?></p>
						</td>
					</tr>

				</table>

				<input type="hidden" name="export-step" value="3" />
				<?php wp_nonce_field( 'anthologize_export' ); ?>

				<div style="clear: both;"> </div>

				<div class="anthologize-button" id="export-next"><input type="submit" name="submit" id="submit" value="<?php esc_attr_e( 'Export', 'anthologize' ); ?>" /></div>

				</form>

			<?php endif; ?>

			</div>
		</div>
			<?php
		}

		public function export_format_options_title() {
			global $anthologize_formats;

			$session = anthologize_get_session();
			$format  = isset( $session['filetype'] ) ? sanitize_key( $session['filetype'] ) : '';

			if ( ! empty( $format ) && isset( $anthologize_formats[ $format ] ) ) {
				$title = sprintf(
					/* translators: %s: format label */
					__( '%s Publishing Options', 'anthologize' ),
					$anthologize_formats[ $format ]['label']
				);
			} else {
				$title = __( 'Publishing Options', 'anthologize' );
			}

			echo esc_html( $title );
		}

		public static function save_session() {
			$keys = anthologize_get_session_data_keys();
			$data = array();

			foreach ( $keys as $key ) {
				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}

				$data[ $key ] = wp_unslash( $_POST[ $key ] );
			}

			anthologize_save_session( $data );
		}

		public function export_format_list() {
			global $anthologize_formats;

			$checked = true;

			foreach ( $anthologize_formats as $name => $fdata ) {
				$option_id = 'option-format-' . $name;

				$disabled = '';
				$message  = '';

				$is_available = call_user_func( $fdata['is_available_callback'] );
				if ( ! $is_available ) {
					if ( ! current_user_can( 'install_plugins' ) ) {
						continue;
					}

					$disabled = disabled( true, true, false );
					$message  = $fdata['unavailable_notice'];
				}

				?>

			<input type="radio" id="<?php echo esc_attr( $option_id ); ?>" name="filetype" value="<?php echo esc_attr( $name ); ?>" <?php checked( $checked ); ?> <?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of disabled() ?> /> <label for="<?php echo esc_attr( $option_id ); ?>"><?php echo esc_html( $fdata['label'] ); ?> <?php
			if ( $message ) :
				?>
				<span class="disabled-format-message"><?php echo esc_html( $message ); ?></span><?php endif; ?></label><br />

				<?php

				$checked = false;
			}

			do_action( 'anthologize_export_format_list' );
		}

		public function render_format_options() {
			global $anthologize_formats;

			$session = anthologize_get_session();
			$format  = isset( $session['filetype'] ) ? sanitize_key( $session['filetype'] ) : '';

			if ( ! empty( $format ) && isset( $anthologize_formats[ $format ] ) ) {
				$fdata  = $anthologize_formats[ $format ];
				$return = '';
				foreach ( $fdata as $oname => $odata ) {
					if ( 'label' === $oname || 'loader-path' === $oname || 'is_available_callback' === $oname || 'unavailable_notice' === $oname ) {
						continue;
					}

					if ( ! $odata || ! is_array( $odata ) ) {
						continue;
					}

					$default = isset( $odata['default'] ) ? $odata['default'] : false;

					$return .= '<tr>';
					$return .= '<th scope="row">';
					$return .= sprintf( '<label for="%s">', esc_attr( $oname ) );
					$return .= esc_html( $odata['label'] );
					$return .= '</label></th>';

					$return .= '<td>';
					switch ( $odata['type'] ) {
						case 'checkbox':
							$return .= $this->build_checkbox( $oname, $odata['label'] );
							break;

						case 'checkboxes':
							$return .= $this->build_checkboxes( $oname, $odata['values'], $default );
							break;

						case 'dropdown':
							$return .= $this->build_dropdown( $oname, $odata['label'], $odata['values'], $default );
							break;

						default:
							$return .= $this->build_textbox( $oname, $odata['label'] );
							break;
					}
					$return .= '</td>';
					$return .= '</tr>';
				}

				echo $return; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in build_ methods
			} else {
				echo esc_html__( 'This appears to be an invalid export format. Please try again.', 'anthologize' );
			}
		}

		public function build_checkbox( $name, $label ) {
			$html = '<input name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" type="checkbox">';
			return apply_filters( 'anthologize_build_checkbox', $html, $name, $label );
		}

		public function build_checkboxes( $name, $values, $defaults ) {
			$html = '<fieldset><ul>';

			foreach ( $values as $value => $label ) {
				$html .= sprintf(
					'<li><label><input type="checkbox" value="%s" name="%s[]" %s> %s</label></li>',
					esc_attr( $value ),
					esc_attr( $name ),
					checked( in_array( $value, (array) $defaults, true ), true, false ),
					esc_html( $label )
				);
			}

			$html .= '<legend>' . esc_html__( 'Select all data you\'d like to appear with exported posts.', 'anthologize' ) . '</legend>';
			$html .= '</ul></fieldset>';

			return apply_filters( 'anthologize_build_checkboxes', $html, $name, $values, $defaults );
		}

		public function build_dropdown( $name, $label, $options, $default ) {
			$html = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';

			foreach ( $options as $ovalue => $olabel ) {
				$html .= '<option value="' . esc_attr( $ovalue ) . '"';

				if ( $default == $ovalue ) {
					$html .= ' selected="selected"';
				}

				$html .= '>' . esc_html( $olabel ) . '</option>';
			}

			$html .= '</select>';

			return apply_filters( 'anthologize_build_dropdown', $html, $name, $label, $options );
		}

		public function build_textbox( $name, $label ) {
			$html = '<input name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" type="text">';
			return apply_filters( 'anthologize_build_textbox', $html, $name, $label );
		}

		public function get_projects() {
			$projects = array();

			$query = new WP_Query( array(
				'post_type'      => 'anth_project',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			) );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$projects[ get_the_ID() ] = get_the_title();
				}
				wp_reset_postdata();
			}

			return $projects;
		}
	}

endif;

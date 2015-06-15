<?php
/*
Plugin Name: WP.org Site Icon
Plugin URL: http://wordpress.org/
Description: Add a site icon for your website.
Version: 1
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Author: wordpressdotorg

The following code is a derivative work of the code from the Jetpack project, which is licensed GPLv2.
*/

class WP_Site_Icon {

	/**
	 * The minimum size of the site icon.
	 * 
	 * @var int
	 */
	public $min_size  = 512;

	/**
	 * The size to which to crop the image so that we can display it in the UI nicely
	 * 
	 * @var int
	 */
	public $page_crop = 512;

	/**
	 * @var array
	 */
	public $accepted_file_types = array(
		'image/jpg',
		'image/jpeg',
		'image/gif',
		'image/png',
	);

	/**
	 * @var array
	 */
	public $site_icon_sizes = array(
		/**
		 * Square, medium sized tiles for IE11+.
		 *
		 * @link https://msdn.microsoft.com/library/dn455106(v=vs.85).aspx
		 */
		270,

		/**
		 * App icons up to iPhone 6 Plus.
		 *
		 * @link https://developer.apple.com/library/prerelease/ios/documentation/UserExperience/Conceptual/MobileHIG/IconMatrix.html
		 */
		180,

		// Our regular Favicon.
		32,
	);
	


	/**
	 * Register our actions and filters
	 */
	public function __construct() {
		include_once dirname( __FILE__ ) . '/site-icon-functions.php';

		add_action( 'admin_menu',             array( $this, 'admin_menu_upload_site_icon' ) );
		add_filter( 'display_media_states',   array( $this, 'add_media_state' ) );
		add_action( 'admin_init',             array( $this, 'admin_init' ) );
		add_action( 'admin_init',             array( $this, 'delete_site_icon_hook' ) );
		add_action( 'atom_head',              array( $this, 'atom_icon' ) );
		add_action( 'rss2_head',              array( $this, 'rss2_icon' ) );

		add_action( 'admin_print_styles-options-general.php', array( $this, 'add_general_options_styles' ) );

		// Add the favicon to the front end and backend.
		add_action( 'wp_head',    array( $this, 'add_meta' ) );
		add_action( 'admin_head', array( $this, 'add_meta' ) );

		add_action( 'delete_option',     array( $this, 'delete_temp_data' ), 10, 1 ); // used to clean up after itself.
		add_action( 'delete_attachment', array( $this, 'delete_attachment_data' ), 10, 1 ); // in case user deletes the attachment via
		add_filter( 'get_post_metadata', array( $this, 'delete_attachment_images' ), 10, 4 );
	}

	/**
	 * Add meta elements to document header.
	 *
	 * @link http://www.whatwg.org/specs/web-apps/current-work/multipage/links.html#rel-icon HTML5 specification link icon
	 *
	 */
	public function add_meta() {
		$meta_tags = array(
			sprintf( '<link rel="icon" href="%s" sizes="32x32" />', esc_url( site_icon_url( null, 32 ) ) ),
			sprintf( '<link rel="apple-touch-icon-precomposed" href="%s">', esc_url( site_icon_url( null, 180 ) ) ),
			sprintf( '<meta name="msapplication-TileImage" content="%s">', esc_url( site_icon_url( null, 150 ) ) ),
		);

		/**
		 * Filters the site icon meta tags, so Plugins can add their own.
		 *
		 * @since 4.3.0
		 *
		 * @param array $meta_tags Site Icon Meta Elements.
		 */
		$meta_tags = apply_filters( 'site_icon_meta_tags', $meta_tags );
		$meta_tags = array_filter( $meta_tags );

		foreach ( $meta_tags as $meta_tag ) {
			echo "$meta_tag\n";
		}
	}

	/**
	 * Display icons in RSS2.
	 */
	public function rss2_icon() {
		/** This filter is documented in modules/site-icon/wp-site-icon.php */
		if ( apply_filters( 'site_icon_has_favicon', false ) ) {
			return;
		}

		$rss_title = get_wp_title_rss();
		if ( empty( $rss_title ) ) {
			$rss_title = get_bloginfo_rss( 'name' );
		}

		$icon = site_icon_url( null, 32 );
		if ( $icon ) {
			echo '
	<image>
		<url>' . convert_chars( $icon ) . '</url>
		<title>' . $rss_title . '</title>
		<link>' . get_bloginfo_rss( 'url' ) . '</link>
		<width>32</width>
		<height>32</height>
	</image> ' . "\n";
		}
	}

	/**
	 * Display icons in atom feeds.
	 *
	 */
	public function atom_icon() {
		/** This filter is documented in modules/site-icon/wp-site-icon.php */
		if ( apply_filters( 'site_icon_has_favicon', false ) ) {
			return;
		}

		$url = site_icon_url( null, 32 );
		if ( $url ) {
			echo '
	<icon>' . $url . '</icon> ' . "\n";
		}
	}

	/**
	 * Add a hidden upload page from people
	 */
	public function admin_menu_upload_site_icon() {
		$page_hook = add_submenu_page(
			null,
			__( 'Site Icon Upload' ),
			'',
			'manage_options',
			'wp-site-icon-upload',
			array( $this, 'upload_site_icon_page' )
		);

		add_action( "admin_head-$page_hook", array( $this, 'upload_site_icon_head' ) );
	}


	/**
	 * Add styles to the General Settings Screen
	 */
	public function add_general_options_styles() {
		wp_enqueue_style( 'site-icon-admin' );
	}

	/**
	 * Add Styles to the Upload UI Page
	 *
	 */
	public function upload_site_icon_head() {
		wp_register_script( 'site-icon-crop', plugin_dir_url( __FILE__ ) . 'js/site-icon-crop.js', array(
			'jquery',
			'jcrop'
		) );

		if ( isset( $_REQUEST['step'] ) && $_REQUEST['step'] == 2 ) {
			wp_enqueue_script( 'site-icon-crop' );
			wp_enqueue_style( 'jcrop' );
		}
		wp_enqueue_style( 'site-icon-admin' );
	}

	public function add_media_state( $media_states ) {
		if ( has_site_icon() && get_post()->ID == get_option( 'site_icon_id' ) ) {
			$media_states[] = __( 'Site Icon' );
		}

		return $media_states;
	}

	/**
	 * Load on when the admin is initialized
	 */
	public function admin_init() {
		// register the styles and scripts.
		wp_register_style( 'site-icon-admin', plugin_dir_url( __FILE__ ) . 'css/site-icon-admin.css' );

		// register the settings
		add_settings_section( 'wp-site-icon', '<span id="wporg-site-title">' . __( 'Site Icon' ) . '</span>', array( $this, 'settings_section' ), 'general' );

		$field_title = has_site_icon() ? __( 'Manage Site Icon' ) : __( 'Add Site Icon' );
		add_settings_field( 'wp-site-icon', $field_title, array( $this, 'settings_field' ), 'general', 'wp-site-icon' );

		if ( isset( $_REQUEST['step'] ) && 3 == $_REQUEST['step'] ) {
			$this->all_done_page();
		}
	}

	/**
	 * Checks for permission to delete the site_icon
	 */
	public function delete_site_icon_hook() {
		// Delete the site_icon
		if ( isset( $GLOBALS['plugin_page'] ) && 'wp-site-icon-upload' == $GLOBALS['plugin_page'] ) {
			if ( isset( $_GET['action'] )
			     && 'remove' == $_GET['action']
			     && isset( $_GET['_wpnonce'] )
			     && wp_verify_nonce( $_GET['_wpnonce'], 'remove_site_icon' )
			) {

				$site_icon_id = get_option( 'site_icon_id' );
				// Delete the previous site icon
				$this->delete_site_icon( $site_icon_id, true );
				wp_safe_redirect( admin_url( 'options-general.php#wporg-site-icon' ) );
			}
		}
	}

	/**
	 * Add HTML to the General Settings
	 */
	public function settings_section() {
		esc_html_e( 'Site Icon creates a favicon for your site and more.' );
	}

	public function settings_field() {
		$upload_url = admin_url( 'options-general.php?page=wp-site-icon-upload' );

		$update_url = esc_url( add_query_arg( array(
			'page' => 'wp-site-icon-upload',
			'step' => 2,
			'_wpnonce' => wp_create_nonce( 'update-site-icon-2' ),
		), admin_url( 'options-general.php' ) ) );

		// Lets delete the temp data that we might he holding on to.
		$this->delete_temporay_data();

		if ( has_site_icon() ) :
			echo get_site_icon( null, 128 );

			$remove_url = add_query_arg( array(
				'page'   => 'wp-site-icon-upload',
				'action' => 'remove',
				'_wpnonce' => wp_create_nonce( 'remove_site_icon' ),
			), admin_url( 'options-general.php' ) );

			?>
			<p class="hide-if-no-js">
				<label class="screen-reader-text" for="choose-from-library-link"><?php _e( 'Choose an image from your media library:' ); ?></label>
				<button id="choose-from-library-link" class="button"
				        data-update-link="<?php echo esc_attr( $update_url ); ?>"
				        data-choose="<?php esc_attr_e( 'Choose a Site Icon' ); ?>"
				        data-update="<?php esc_attr_e( 'Set as Site Icon' ); ?>"><?php _e( 'Update Site Icon' ); ?></button>
				<a href="<?php echo esc_url( $remove_url ); ?>" id="site-icon-remove"><?php esc_html_e( 'Remove Site Icon' ); ?></a>
			</p>
			<p class="button hide-if-js">
				<a href="<?php echo esc_url( $upload_url ); ?>"><?php _e( 'Add a Site Icon' ); ?></a>
				<a href="<?php echo esc_url( $remove_url ); ?>" id="site-icon-remove"><?php esc_html_e( 'Remove Site Icon' ); ?></a>
			</p>

			<?php

		else :
			wp_enqueue_media();
			wp_enqueue_script( 'custom-header' );

			// Display the site_icon form to upload the image
			?>
			<p class="hide-if-no-js">
				<label class="screen-reader-text" for="choose-from-library-link"><?php _e( 'Choose an image from your media library:' ); ?></label>
				<button id="choose-from-library-link" class="button"
				        data-update-link="<?php echo esc_attr( $update_url ); ?>"
				        data-choose="<?php esc_attr_e( 'Choose a Site Icon' ); ?>"
				        data-update="<?php esc_attr_e( 'Set as Site Icon' ); ?>"><?php _e( 'Choose Image' ); ?></button>
			</p>
			<a class="button hide-if-js" href="<?php echo esc_url( $upload_url ); ?>"><?php _e( 'Add a Site Icon' ); ?></a>
		<?php
		endif;
	}

	/**
	 * Hidden Upload page for people that don't like modals
	 */
	public function upload_site_icon_page() { ?>
		<div class="wrap">
			<?php
			/**
			 * Uploading a site_icon is a 3 step process
			 *
			 * 1. Select the file to upload
			 * 2. Crop the file
			 * 3. Confirmation page
			 */
			$step = isset( $_REQUEST['step'] ) ? $_REQUEST['step'] : 1;
			if ( $step > 1 ) {
				check_admin_referer( 'update-site-icon-' . $step );
			}

			switch ( $step ) {
				case '1':
					$this->select_page();
					break;

				case '2':
					$this->crop_page();
					break;

				case '3':
					$this->all_done_page();
					break;

				default:
					wp_safe_redirect( admin_url( 'options-general.php#wporg-site-icon' ) );
					exit;
			}
			?>
		</div>
	<?php
	}

	/**
	 * Select a file admin view
	 */
	public function select_page() {
		// Display the site_icon form to upload the image
		?>
		<div class="wrap">
			<h2><?php _e( 'Add Site Icon' ); ?></h2>
			<table class="form-table">
				<tr>
					<th>
						<label for="wp-site-icon">
							<?php _e( 'Upload image' ); ?>
						</label>
					</th>
					<td>
						<form action="<?php echo esc_url( admin_url( 'options-general.php?page=wp-site-icon-upload' ) ); ?>" method="post" enctype="multipart/form-data">
							<input name="step" value="2" type="hidden" />
							<input name="wp-site-icon" type="file" />
							<input name="submit" value="<?php esc_attr_e( 'Upload Image' ); ?>" type="submit" class="button button-primary" />
							<p class="description">
								<?php printf( __( 'The image needs to be exactly %spx in both width and height.' ), "<strong>$this->min_size</strong>" ); ?>
							</p>
							<?php wp_nonce_field( 'update-site-icon-2' ); ?>
						</form>
					</td>
				</tr>
			</table>
		</div>
	<?php
	}

	/**
	 * Crop a the image admin view
	 */
	public function crop_page() {
		if ( isset( $_GET['file'] ) ) {
			$attachment_id = absint( $_GET['file'] );
			$file = get_attached_file( $attachment_id, true );
			$url  = wp_get_attachment_image_src( $attachment_id, 'full' );
			$url  = $url[0];
		} elseif ( isset( $_FILES ) ) {
			$upload = $this->handle_upload();
			$attachment_id = $upload['attachment_id'];
			$file = $upload['file'];
			$url  = $upload['url'];
		}


		// Lets try to crop the image into smaller files.
		// We will be doing this later so it is better if it fails now.
		$image_edit = wp_get_image_editor( $file );
		if ( is_wp_error( $image_edit ) ) {
			// this should contain the error message from WP_Image_Editor
			unlink( $file ); // lets delete the file since we are not going to be using it
			return $image_edit;
		}

		$image_size = getimagesize( $file );

		if ( $image_size[0] < $this->min_size || $image_size[1] < $this->min_size ) {
			if ( $image_size[0] < $this->min_size ) {
				?><div id="message" class="updated error below-h2"><p><?php printf( __( 'The selected image is smaller than %upx in width.' ), $this->min_size ); ?></p></div><?php
				// back to step one
				$_POST = array();
				$this->delete_temporay_data();
				$this->select_page();
				return;
			}

			if ( $image_size[1] < $this->min_size ) {
				?><div id="message" class="updated error below-h2"><p><?php printf( __( 'The selected image is smaller than %upx in height.' ), $this->min_size ); ?></p></div><?php
				// back to step one
				$_POST = array();
				$this->delete_temporay_data();
				$this->select_page();
				return;
			}
		}

		// Let's resize the image so that the user can easier crop a image that in the admin view
		$image_edit->resize( $this->page_crop, $this->page_crop, false );

		$resized_filename = $image_edit->generate_filename( 'temp', null, null );
		$image_edit->save( $resized_filename );
		$resized_file_type = wp_check_filetype( $resized_filename );

		$resized_attach_id = $this->save_attachment(
			__( 'Temporary Resized Image for Blog Image' ),
			$resized_filename,
			$resized_file_type['type'],
			false
		);

		$resized_image_size = getimagesize( $resized_filename );
		// Save all of this into the the database for that we can work with it later.
		update_option( 'site_icon_temp_data', array(
			'large_image_attachment_id'  => $attachment_id,
			'large_image_data'           => $image_size,
			'resized_image_attacment_id' => $resized_attach_id,
			'resized_image_data'         => $resized_image_size,
		) );

		// lets make sure that the Javascript ia also loaded
		wp_localize_script( 'site-icon-crop', 'wpSiteIconCropData', $this->initial_crop_data() );
		?>

		<div class="wrap">
			<h2 class="site-icon-title"><?php esc_html_e( 'Site Icon' ); ?></h2>
			<div class="site-icon-crop-shell">
				<form action="" method="post" enctype="multipart/form-data">
					<p class="site-icon-submit-form">
						<input name="submit" value="<?php esc_attr_e( 'Crop Image' ); ?>" type="submit" class="button button-primary button-large"/><?php printf( __( ' or <a href="%s">Cancel</a> and go back to the settings.' ), esc_url( admin_url( 'options-general.php' ) ) ); ?>
					</p>

					<div class="site-icon-crop-preview-shell">
						<h3><?php esc_html_e( 'Preview' ); ?></h3>

						<strong><?php esc_html_e( 'As your favicon' ); ?></strong>

						<div class="site-icon-crop-favicon-preview-shell">
							<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'browser.png' ); ?>" class="site-icon-browser-preview" width="172" height="79" alt="<?php esc_attr_e( 'Browser Chrome' ); ?>"/>

							<div class="site-icon-crop-preview-favicon">
								<img src="<?php echo esc_url( $url ); ?>" id="preview-favicon" alt="<?php esc_attr_e( 'Preview Favicon' ); ?>"/>
							</div>
							<span class="site-icon-browser-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
						</div>

						<strong><?php esc_html_e( 'As a mobile icon' ); ?></strong>

						<div class="site-icon-crop-preview-homeicon">
							<img src="<?php echo esc_url( $url ); ?>" id="preview-homeicon" alt="<?php esc_attr_e( 'Preview Home Icon' ); ?>"/>
						</div>
					</div>
					<img src="<?php echo esc_url( $url ); ?>" id="crop-image" class="site-icon-crop-image" width="<?php echo esc_attr( $resized_image_size[0] ); ?>" height="<?php echo esc_attr( $resized_image_size[1] ); ?>" alt="<?php esc_attr_e( 'Image to be cropped' ); ?>"/>

					<input name="step" value="3" type="hidden"/>
					<input type="hidden" id="crop-x" name="crop-x"/>
					<input type="hidden" id="crop-y" name="crop-y"/>
					<input type="hidden" id="crop-width" name="crop-w"/>
					<input type="hidden" id="crop-height" name="crop-h"/>

					<?php wp_nonce_field( 'update-site-icon-3' ); ?>

				</form>
			</div>
		</div>
	<?php
	}

	/**
	 * All done page admin view
	 *
	 */
	public function all_done_page() {

		$temp_image_data = get_option( 'site_icon_temp_data' );
		if ( ! $temp_image_data ) {
			// start again
			$this->select_page();

			return;
		}
		$crop_ration = $temp_image_data['large_image_data'][0] / $temp_image_data['resized_image_data'][0]; // always bigger then 1

		$crop_data = $this->convert_coordinates_from_resized_to_full( $_POST['crop-x'], $_POST['crop-y'], $_POST['crop-w'], $_POST['crop-h'], $crop_ration );

		$image_edit = wp_get_image_editor( _load_image_to_edit_path( $temp_image_data['large_image_attachment_id'] ) );

		if ( is_wp_error( $image_edit ) ) {
			return $image_edit;
		}

		// Delete the previous site_icon
		$previous_site_icon_id = get_option( 'site_icon_id' );
		$this->delete_site_icon( $previous_site_icon_id );

		// crop the image
		$image_edit->crop( $crop_data['crop_x'], $crop_data['crop_y'], $crop_data['crop_width'], $crop_data['crop_height'], $this->min_size, $this->min_size );

		$dir = wp_upload_dir();

		$site_icon_filename = $image_edit->generate_filename( dechex( time() ) . 'wp_site_icon', null, 'png' );

		// If the attachment is a URL, then change it to a local file name to allow us to save and then upload the cropped image
		$check_url = parse_url( $site_icon_filename );
		if ( isset( $check_url['host'] ) ) {
			$upload_dir         = wp_upload_dir();
			$site_icon_filename = $upload_dir['path'] . '/' . basename( $site_icon_filename );
		}

		$image_edit->save( $site_icon_filename );

		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'additional_sizes' ) );

		$site_icon_id = $this->save_attachment(
			__( 'Large Blog Image' ),
			$site_icon_filename,
			'image/png'
		);

		remove_filter( 'intermediate_image_sizes_advanced', array( $this, 'additional_sizes' ) );

		// Save the site_icon data into option
		update_option( 'site_icon_id', $site_icon_id );

		wp_safe_redirect( admin_url( 'options-general.php#wporg-site-icon' ) );
		exit;
	}

	/**
	 * This function is used to pass data to the localize script
	 * so that we can center the cropper and also set the minimum
	 * cropper if we still want to show the
	 *
	 * @return array
	 */
	public function initial_crop_data() {
		$init_x = $init_y = $init_size = 0;

		$crop_data = get_option( 'site_icon_temp_data' );
		$large_width    = $crop_data['large_image_data'][0];
		$resized_width  = $crop_data['resized_image_data'][0];
		$resized_height = $crop_data['resized_image_data'][1];

		$ration        = $large_width / $resized_width;
		$min_crop_size = ( $this->min_size / $ration );

		// Landscape format ( width > height )
		if ( $resized_width > $resized_height ) {
			$init_x    = ( $this->page_crop - $resized_height ) / 2;
			$init_size = $resized_height;
		}

		// Portrait format ( height > width )
		if ( $resized_width < $resized_height ) {
			$init_y    = ( $this->page_crop - $resized_width ) / 2;
			$init_size = $resized_height;
		}

		// Square height == width
		if ( $resized_width = $resized_height ) {
			$init_size = $resized_height;
		}

		return array(
			'init_x'    => $init_x,
			'init_y'    => $init_y,
			'init_size' => $init_size,
			'min_size'  => $min_crop_size
		);
	}

	/**
	 * Delete the temporary created data and attachments
	 *
	 * @return bool True, if option is successfully deleted. False on failure.
	 */
	public function delete_temporay_data() {
		// This should automatically delete the temporary files as well
		return delete_option( 'site_icon_temp_data' );
	}

	/**
	 * Function gets fired when delete_option( 'site_icon_temp_data' ) is run.
	 *
	 * @param string $option
	 */
	public function delete_temp_data( $option ) {
		if ( 'site_icon_temp_data' !== $option ) {
			return;
		}

		remove_action( 'delete_attachment', array( $this, 'delete_attachment_data' ), 10, 1 );

		$temp_image_data = get_option( 'site_icon_temp_data' );
		wp_delete_attachment( $temp_image_data['large_image_attachment_id'], true );
		wp_delete_attachment( $temp_image_data['resized_image_attacment_id'], true );
	}

	/**
	 * @param $post_id
	 */
	public function delete_attachment_data( $post_id ) {
		// The user could be deleting the site_icon image
		$site_icon_id = get_option( 'site_icon_id' );

		if ( $site_icon_id && $post_id == $site_icon_id ) {
			delete_option( 'site_icon_id' );
		}
	}

	/**
	 * @param $check
	 * @param $post_id
	 * @param $meta_key
	 * @param $single
	 *
	 * @return mixed
	 */
	public function delete_attachment_images( $check, $post_id, $meta_key, $single ) {
		$site_icon_id = get_option( 'site_icon_id' );
		if ( $post_id == $site_icon_id && '_wp_attachment_backup_sizes' == $meta_key && $single ) {
			add_filter( 'intermediate_image_sizes', array( $this, 'intermediate_image_sizes' ) );
		}

		return $check;
	}

	/**
	 * Delete the site icon and all the attached data.
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	public function delete_site_icon( $id ) {
		// We add the filter to make sure that we also delete all the added images
		add_filter( 'intermediate_image_sizes', array( $this, 'intermediate_image_sizes' ) );
		wp_delete_attachment( $id, true );
		remove_filter( 'intermediate_image_sizes', array( $this, 'intermediate_image_sizes' ) );
		// for good measure also
		$this->delete_temporay_data();

		return delete_option( 'site_icon_id' );
	}

	/**
	 * @param $crop_x
	 * @param $crop_y
	 * @param $crop_width
	 * @param $crop_height
	 * @param $ratio
	 *
	 * @return array
	 */
	public function convert_coordinates_from_resized_to_full( $crop_x, $crop_y, $crop_width, $crop_height, $ratio ) {
		return array(
			'crop_x'      => floor( $crop_x * $ratio ),
			'crop_y'      => floor( $crop_y * $ratio ),
			'crop_width'  => floor( $crop_width * $ratio ),
			'crop_height' => floor( $crop_height * $ratio ),
		);
	}

	/**
	 * Upload the file to be cropped in the second step.
	 *
	 * @since 3.4.0
	 */
	public function handle_upload() {
		$uploaded_file = $_FILES['wp-site-icon'];
		$file_type   = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'] );
		if ( ! wp_match_mime_types( 'image', $file_type['type'] ) ) {
			wp_die( __( 'The uploaded file is not a valid image. Please try again.' ) );
		}

		$file = wp_handle_upload( $uploaded_file, array( 'test_form' => false ) );

		if ( isset( $file['error'] ) ) {
			wp_die( $file['error'], __( 'Image Upload Error' ) );
		}

		$url      = $file['url'];
		$type     = $file['type'];
		$file     = $file['file'];
		$filename = basename( $file );

		// Construct the object array
		$object = array(
			'post_title'     => $filename,
			'post_content'   => $url,
			'post_mime_type' => $type,
			'guid'           => $url,
			'context'        => 'site-icon',
		);

		// Save the data
		$attachment_id = wp_insert_attachment( $object, $file );

		return compact( 'attachment_id', 'file', 'filename', 'url', 'type' );
	}

	/**
	 * Save site icon files to Media Library
	 *
	 * @param  string $title
	 * @param  string $file
	 * @param  string $file_type
	 * @param  boolean $generate_meta
	 *
	 * @return int        $attactment_id
	 */
	public function save_attachment( $title, $file, $file_type, $generate_meta = true ) {
		$filename = _wp_relative_upload_path( $file );

		$wp_upload_dir = wp_upload_dir();
		$attachment    = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => $file_type,
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit'
		);
		$attachment_id = wp_insert_attachment( $attachment, $filename );

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}
		if ( ! $generate_meta ) {
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'only_thumbnail_size' ) );
		}

		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		if ( ! $generate_meta ) {
			remove_filter( 'intermediate_image_sizes_advanced', array( $this, 'only_thumbnail_size' ) );
		}

		return $attachment_id;
	}

	/**
	 * Add additional sizes to be made when creating the site_icon images.
	 *
	 * @param array $sizes
	 *
	 * @return array
	 */
	public function additional_sizes( $sizes = array() ) {
		$only_crop_sizes = array();

		/**
		 * Filters the different dimensions that a site icon is saved in.
		 *
		 * @since 4.3.0
		 *
		 * @param array $site_icon_sizes Sizes available for the Site Icon.
		 */
		$this->site_icon_sizes = apply_filters( 'site_icon_image_sizes', $this->site_icon_sizes );
		// use a natural sort of numbers
		natsort( $this->site_icon_sizes );
		$this->site_icon_sizes = array_reverse( $this->site_icon_sizes );

		// ensure that we only resize the image into
		foreach ( $sizes as $name => $size_array ) {
			if ( $size_array['crop'] ) {
				$only_crop_sizes[ $name ] = $size_array;
			}
		}

		foreach ( $this->site_icon_sizes as $size ) {
			if ( $size < $this->min_size ) {
				$only_crop_sizes[ 'site_icon-' . $size ] = array(
					'width ' => $size,
					'height' => $size,
					'crop'   => true,
				);
			}
		}

		return $only_crop_sizes;
	}

	/**
	 * Helps us delete site_icon images that
	 *
	 * @param array $sizes
	 *
	 * @return array
	 */
	public function intermediate_image_sizes( $sizes = array() ) {
		/** This filter is documented in modules/site-icon/wp-site-icon.php */
		$this->site_icon_sizes = apply_filters( 'site_icon_image_sizes', $this->site_icon_sizes );
		foreach ( $this->site_icon_sizes as $size ) {
			$sizes[] = 'site_icon-' . $size;
		}

		return $sizes;
	}

	/**
	 * Only resize the image to thumbnail so we can use
	 * Use when resizing temporary images. This way we can see the temp image in Media Gallery.
	 *
	 * @param array $sizes
	 *
	 * @return array
	 */
	public function only_thumbnail_size( $sizes ) {
		return array( 'thumbnail' => $sizes['thumbnail'] );
	}
}

$GLOBALS['wp_site_icon'] = new WP_Site_Icon;
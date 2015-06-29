<?php

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

		add_action( 'admin_menu',           array( $this, 'admin_menu_upload_site_icon' ) );
		add_filter( 'display_media_states', array( $this, 'add_media_state' ) );

		add_action( 'admin_action_set_site_icon', array( $this, 'set_site_icon' ) );
		add_action( 'admin_action_remove_site_icon', array( $this, 'remove_site_icon' ) );

		// Add the favicon to the front end and backend.
		add_action( 'wp_head',    array( $this, 'add_meta' ) );
		add_action( 'admin_head', array( $this, 'add_meta' ) );
		add_action( 'atom_head',  array( $this, 'atom_icon' ) );
		add_action( 'rss2_head',  array( $this, 'rss2_icon' ) );

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
			sprintf( '<meta name="msapplication-TileImage" content="%s">', esc_url( site_icon_url( null, 270 ) ) ),
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
		/** This filter is documented in modules/site-icon/wporg-site-icon.php */
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
		/** This filter is documented in modules/site-icon/wporg-site-icon.php */
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
	 * Add a hidden upload page.
	 *
	 * There is no need to access it directly.
	 */
	public function admin_menu_upload_site_icon() {
		$hook = add_submenu_page( null, __( 'Site Icon' ), __( 'Site Icon' ), 'manage_options', 'wporg-site-icon', array( $this, 'upload_site_icon_page' ) );

		add_action( "load-$hook", array( $this, 'add_upload_settings' ) );
		add_action( "admin_print_scripts-$hook", array( $this, 'enqueue_scripts' ) );

		add_action( 'load-options-general.php', array( $this, 'add_general_settings' ) );
	}

	/**
	 * Add scripts to admin settings pages.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'jcrop' );
		wp_enqueue_style( 'site-icon-admin', plugin_dir_url( __FILE__ ) . 'css/site-icon-admin.css' );

		wp_enqueue_script( 'site-icon-crop', plugin_dir_url( __FILE__ ) . 'js/site-icon-crop.js', array( 'jquery', 'jcrop' ), false, true );
	}

	/**
	 * Adds Site Icon state to media states.
	 *
	 * @param array $media_states
	 *
	 * @return array
	 */
	public function add_media_state( $media_states ) {
		if ( has_site_icon() && get_post()->ID == get_option( 'site_icon_id' ) ) {
			$media_states[] = __( 'Site Icon' );
		}

		return $media_states;
	}

	/**
	 * Load on when the admin is initialized.
	 */
	public function add_general_settings() {
		add_settings_section( 'wporg-site-icon', '<span id="wporg-site-icon">' . __( 'Site Icon' ) . '</span>', array( $this, 'settings_section' ), 'general' );

		$field_title = has_site_icon() ? __( 'Manage Site Icon' ) : __( 'Add Site Icon' );
		add_settings_field( 'wporg-site-icon', $field_title, array( $this, 'settings_field' ), 'general', 'wporg-site-icon' );
	}

	/**
	 * Load on when the admin is initialized.
	 */
	public function add_upload_settings() {
		add_settings_section( 'wporg-site-icon-upload', false, false, 'wporg-site-icon-upload' );
		add_settings_field( 'wporg-site-icon-upload', __( 'Upload Image' ), array( $this, 'upload_field' ), 'wporg-site-icon-upload', 'wporg-site-icon-upload', array(
			'label_for' => 'wporg-site-icon-upload',
		) );
	}

	/**
	 * Removes site icon.
	 */
	public function remove_site_icon() {
		check_admin_referer( 'remove_site_icon' );

		// We add the filter to make sure that we also delete all the added images
		add_filter( 'intermediate_image_sizes', array( $this, 'intermediate_image_sizes' ) );
		wp_delete_attachment( get_option( 'site_icon_id' ), true );
		remove_filter( 'intermediate_image_sizes', array( $this, 'intermediate_image_sizes' ) );

		delete_option( 'site_icon_id' );

		add_settings_error( 'wporg-site-icon', 'icon-removed', __( 'Site Icon removed.' ), 'updated' );
	}

	/**
	 * Add HTML to the General Settings
	 */
	public function settings_section() {
		esc_html_e( 'Site Icon creates a favicon for your site and more.' );
	}

	public function settings_field() {
		$upload_url = admin_url( 'options-general.php?page=wporg-site-icon' );

		$update_url = esc_url( add_query_arg( array(
			'page' => 'wporg-site-icon',
			'step' => 2,
		), wp_nonce_url( admin_url( 'options-general.php' ), 'update-site-icon-2' ) ) );

		wp_enqueue_media();
		wp_enqueue_script( 'custom-header' );

		if ( has_site_icon() ) :
			echo get_site_icon( null, 180 );

			$remove_url = add_query_arg( array(
				'action' => 'remove_site_icon',
				'_wpnonce' => wp_create_nonce( 'remove_site_icon' ),
			), admin_url( 'options-general.php' ) );

			?>
			<p class="hide-if-no-js">
				<label class="screen-reader-text" for="choose-from-library-link"><?php _e( 'Choose an image from your media library:' ); ?></label>
				<button type="button" id="choose-from-library-link" class="button"
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

			// Display the site_icon form to upload the image
			?>
			<p class="hide-if-no-js">
				<label class="screen-reader-text" for="choose-from-library-link"><?php _e( 'Choose an image from your media library:' ); ?></label>
				<button type="button" id="choose-from-library-link" class="button"
				        data-update-link="<?php echo esc_attr( $update_url ); ?>"
				        data-choose="<?php esc_attr_e( 'Choose a Site Icon' ); ?>"
				        data-update="<?php esc_attr_e( 'Set as Site Icon' ); ?>"><?php _e( 'Choose Image' ); ?></button>
			</p>
			<a class="button hide-if-js" href="<?php echo esc_url( $upload_url ); ?>"><?php _e( 'Add a Site Icon' ); ?></a>
		<?php
		endif;
	}

	/**
	 * Uploading a site_icon is a 3 step process
	 *
	 * 1. Select the file to upload
	 * 2. Crop the file
	 * 3. Confirmation page
	 */
	public function upload_site_icon_page() {
		$step = isset( $_REQUEST['step'] ) ? $_REQUEST['step'] : 1;

		switch ( $step ) {
			case '1':
				$this->select_page();
				break;

			case '2':
				check_admin_referer( 'update-site-icon-2' );
				$this->crop_page();
				break;

			default:
				wp_safe_redirect( admin_url( 'options-general.php#wporg-site-icon' ) );
				exit;
		}
	}

	/**
	 * Displays the site_icon form to upload the image.
	 */
	public function select_page() {
		?>
		<div class="wrap">
			<h2><?php _e( 'Add Site Icon' ); ?></h2>
			<?php settings_errors( 'wporg-site-icon' ); ?>
			<?php do_settings_sections( 'wporg-site-icon-upload' ); ?>
		</div>
	<?php
	}

	/**
	 * Settings field for file upload.
	 */
	public function upload_field() {
		wp_enqueue_media();
		wp_enqueue_script( 'custom-header' );
		wp_dequeue_script( 'site-icon-crop' );

		$update_url = esc_url( add_query_arg( array(
			'page' => 'wporg-site-icon',
			'step' => 2,
		), wp_nonce_url( admin_url( 'options-general.php' ), 'update-site-icon-2' ) ) );
		?>
		<p class="hide-if-no-js">
			<label class="screen-reader-text" for="choose-from-library-link"><?php _e( 'Choose an image from your media library:' ); ?></label>
			<button type="button" id="choose-from-library-link" class="button"
			        data-update-link="<?php echo esc_attr( $update_url ); ?>"
			        data-choose="<?php esc_attr_e( 'Choose a Site Icon' ); ?>"
			        data-update="<?php esc_attr_e( 'Set as Site Icon' ); ?>"><?php _e( 'Choose Image' ); ?></button>
		</p>
		<form class="hide-if-js" action="<?php echo esc_url( admin_url( 'options-general.php?page=wporg-site-icon' ) ); ?>" method="post" enctype="multipart/form-data">
			<input name="step" value="2" type="hidden" />
			<input name="wporg-site-icon" type="file" />
			<input name="submit" value="<?php esc_attr_e( 'Upload Image' ); ?>" type="submit" class="button button-primary" />
			<p class="description">
				<?php printf( __( 'The image needs to be exactly %spx in both width and height.' ), "<strong>$this->min_size</strong>" ); ?>
			</p>
			<?php wp_nonce_field( 'update-site-icon-2' ); ?>
		</form>
	<?php
	}

	/**
	 * Crop a the image admin view.
	 */
	public function crop_page() {
		if ( isset( $_GET['file'] ) ) {
			$attachment_id = absint( $_GET['file'] );
			$file = get_attached_file( $attachment_id, true );
			$url  = wp_get_attachment_image_src( $attachment_id, 'full' );
			$url  = $url[0];
		} else {
			$upload = $this->handle_upload();
			$attachment_id = $upload['attachment_id'];
			$file = $upload['file'];
			$url  = $upload['url'];
		}

		$image_size = getimagesize( $file );

		if ( $image_size[0] < $this->min_size ) {
			add_settings_error( 'wporg-site-icon', 'too-small', sprintf( __( 'The selected image is smaller than %upx in width.' ), $this->min_size ) );

			// back to step one
			$_POST = array();
			$this->select_page();

			return;
		}

		if ( $image_size[1] < $this->min_size ) {
			add_settings_error( 'wporg-site-icon', 'too-small', sprintf( __( 'The selected image is smaller than %upx in height.' ), $this->min_size ) );

			// back to step one
			$_POST = array();
			$this->select_page();

			return;
		}

		// Let's resize the image so that the user can easier crop a image that in the admin view.
		$cropped = wp_crop_image( $attachment_id, 0, 0, 0, 0, $this->page_crop, 0 );
		if ( ! $cropped || is_wp_error( $cropped ) ) {
			wp_die( __( 'Image could not be processed. Please go back and try again.' ), __( 'Image Processing Error' ) );
		}
		$cropped_size = getimagesize( $cropped );
		$crop_ratio   = $image_size[0] / $cropped_size[0];
		wp_delete_file( $cropped );

		wp_localize_script( 'site-icon-crop', 'wpSiteIconCropData', $this->initial_crop_data( $crop_ratio, $cropped_size ) );
		?>

		<div class="wrap">
			<h2 class="site-icon-title"><?php esc_html_e( 'Site Icon' ); ?></h2>
			<?php settings_errors( 'wporg-site-icon' ); ?>

			<div class="site-icon-crop-shell">
				<form action="options-general.php" method="post" enctype="multipart/form-data">
					<p>
						<span class="hide-if-no-js description"><?php _e('Choose the part of the image you want to use as your site icon.'); ?></span>
					<p class="hide-if-js description"><strong><?php _e( 'You need Javascript to choose a part of the image.'); ?></strong></p>
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
					<img src="<?php echo esc_url( $url ); ?>" id="crop-image" class="site-icon-crop-image" width="<?php echo esc_attr( $cropped_size[0] ); ?>" height="<?php echo esc_attr( $cropped_size[1] ); ?>" alt="<?php esc_attr_e( 'Image to be cropped' ); ?>"/>

					<input type="hidden" name="action" value="set_site_icon" />
					<input type="hidden" name="attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
					<input type="hidden" name="crop_ratio" value="<?php echo esc_attr( $crop_ratio ); ?>" />
					<input type="hidden" id="crop-x" name="crop-x" />
					<input type="hidden" id="crop-y" name="crop-y" />
					<input type="hidden" id="crop-width" name="crop-w" />
					<input type="hidden" id="crop-height" name="crop-h" />
					<?php if ( empty( $_POST ) && isset( $_GET['file'] ) ) : ?>
						<input type="hidden" name="create-new-attachment" value="true" />
					<?php endif; ?>
					<?php wp_nonce_field( 'set-site-icon' ); ?>

					<p class="submit">
						<?php submit_button( __( 'Crop and Publish' ), 'primary', 'submit', false ); ?>
						<a class="button secondary" href="options-general.php?action=site_icon_cancel"><?php _e( 'Cancel' ); ?></a>
					</p>
				</form>
			</div>
		</div>
	<?php
	}

	/**
	 * @return void|WP_Error|WP_Image_Editor
	 */
	public function set_site_icon() {
		check_admin_referer( 'set-site-icon' );

		$attachment_id = absint( $_POST['attachment_id'] );

		$crop_ratio = (float) $_POST['crop_ratio'];
		$crop_data = $this->convert_coordinates_from_resized_to_full( $_POST['crop-x'], $_POST['crop-y'], $_POST['crop-w'], $_POST['crop-h'], $crop_ratio );

		// TODO
		if ( empty( $_POST['skip-cropping'] ) ) {
			$cropped = wp_crop_image( $attachment_id, $crop_data['crop_x'], $crop_data['crop_y'], $crop_data['crop_width'], $crop_data['crop_height'], $this->min_size, $this->min_size );
		} elseif ( ! empty( $_POST['create-new-attachment'] ) ) {
			$cropped = _copy_image_file( $attachment_id );
		} else {
			$cropped = get_attached_file( $attachment_id );
		}

		if ( ! $cropped || is_wp_error( $cropped ) ) {
			wp_die( __( 'Image could not be processed. Please go back and try again.' ), __( 'Image Processing Error' ) );
		}

		$object = $this->create_attachment_object( $cropped, $attachment_id );

		if ( ! empty( $_POST['create-new-attachment'] ) ) {
			unset( $object['ID'] );
		}

		// Update the attachment
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'only_thumbnail_size' ) );
		$attachment_id = $this->insert_attachment( $object, $cropped );
		remove_filter( 'intermediate_image_sizes_advanced', array( $this, 'only_thumbnail_size' ) );

		// Save the site_icon data into option
		update_option( 'site_icon_id', $attachment_id );

		add_settings_error( 'wporg-site-icon', 'icon-updated', __( 'Site Icon updated.' ), 'updated' );
	}

	/**
	 * This function is used to pass data to the localize script
	 * so that we can center the cropper and also set the minimum
	 * cropper if we still want to show the
	 *
	 * @return array
	 */
	public function initial_crop_data( $ratio, $cropped_size ) {
		$init_x = $init_y = $init_size = 0;

		$min_crop_size  = ( $this->min_size / $ratio );
		$resized_width  = $cropped_size[0];
		$resized_height = $cropped_size[1];

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
			'min_size'  => $min_crop_size,
		);
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
		$uploaded_file = $_FILES['wporg-site-icon'];
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
	 * Create an attachment 'object'.
	 *
	 * @param string $cropped              Cropped image URL.
	 * @param int    $parent_attachment_id Attachment ID of parent image.
	 *
	 * @return array Attachment object.
	 */
	public function create_attachment_object( $cropped, $parent_attachment_id ) {
		$parent     = get_post( $parent_attachment_id );
		$parent_url = $parent->guid;
		$url        = str_replace( basename( $parent_url ), basename( $cropped ), $parent_url );

		$size       = @getimagesize( $cropped );
		$image_type = ( $size ) ? $size['mime'] : 'image/jpeg';

		$object = array(
			'ID'             => $parent_attachment_id,
			'post_title'     => basename( $cropped ),
			'post_content'   => $url,
			'post_mime_type' => $image_type,
			'guid'           => $url,
			'context'        => 'site-icon'
		);

		return $object;
	}

	/**
	 * Insert an attachment and its metadata.
	 *
	 * @param array $object Attachment object.
	 * @param string $cropped Cropped image URL.
	 *
	 * @return int Attachment ID.
	 */
	public function insert_attachment( $object, $cropped ) {
		$attachment_id = wp_insert_attachment( $object, $cropped );
		$metadata      = wp_generate_attachment_metadata( $attachment_id, $cropped );

		/**
		 * Filter the header image attachment metadata.
		 *
		 * @since 3.9.0
		 *
		 * @see wp_generate_attachment_metadata()
		 *
		 * @param array $metadata Attachment metadata.
		 */
		$metadata = apply_filters( 'wp_site_icon_attachment_metadata', $metadata );
		wp_update_attachment_metadata( $attachment_id, $metadata );

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
		/** This filter is documented in modules/site-icon/wporg-site-icon.php */
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

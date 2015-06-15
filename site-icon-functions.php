<?php

if ( ! function_exists( 'has_site_icon' ) ) :
/**
 * @param int|null $blog_id
 *
 * @return bool
 */
function has_site_icon( $blog_id = null ) {
	return !! site_icon_url( $blog_id, 512 );
}
endif;

if ( ! function_exists( 'get_site_icon' ) ) :
/**
 * @param int|null $blog_id
 * @param int $size
 * @param string $default
 * @param string $alt
 *
 * @return mixed|void
 */
function get_site_icon( $blog_id = null, $size = 512, $default = '', $alt = '' ) {

	if ( ! is_int( $blog_id ) ) {
		$blog_id = get_current_blog_id();
	}

	$size   = absint( $size );
	$class  = "avatar avatar-$size";
	$alt    = $alt ? esc_attr( $alt ) : esc_attr__( 'Site Icon' );
	$src    = esc_url( site_icon_url( $blog_id, $size, $default ) );
	$avatar = "<img alt='{$alt}' src='{$src}' class='$class' height='{$size}' width='{$size}' />";

	/**
	 * Filters the display options for the Site Icon.
	 *
	 * @since 4.3.0
	 *
	 * @param string $avatar The Site Icon in an html image tag.
	 * @param int    $blog_id The local site Blog ID.
	 * @param int    $size The size of the Site Icon, default is 512.
	 * @param string $default The default URL for the Site Icon.
	 * @param string $alt The alt tag for the avatar.
	 */
	return apply_filters( 'get_site_icon', $avatar, $blog_id, $size, $default, $alt );
}
endif;

if ( ! function_exists( 'site_icon_url' ) ) :
/**
 * @param null|int $blog_id Id of the blog to get the site icon for.
 * @param int      $size    Size of the site icon.
 * @param string   $url     Fallback url if no site icon is found.
 *
 * @return bool|string
 */
function site_icon_url( $blog_id = null, $size = 512, $url = '' ) {

	if ( function_exists( 'get_blog_option' ) ) {
		if ( ! is_int( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}
		$site_icon_id = get_blog_option( $blog_id, 'site_icon_id' );
	} else {
		$site_icon_id = get_option( 'site_icon_id' );
	}

	if ( $site_icon_id  ) {
		if ( $size >= 512 ) {
			$size_data = 'full';
		} else {
			$size_data = array( $size, $size );
		}
		$url_data = wp_get_attachment_image_src( $site_icon_id, $size_data );
		$url = $url_data[0];
	}

	return $url;
}
endif;

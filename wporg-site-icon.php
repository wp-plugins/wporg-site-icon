<?php
/*
Plugin Name: WP.org Site Icon
Plugin URL: http://wordpress.org/
Description: Add a site icon for your website.
Version: 5
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Author: wordpressdotorg

The following code is a derivative work of the code from the Jetpack project, which is licensed GPLv2.
*/


// It's been merged into Core, so this is no longer needed.
if ( function_exists( 'get_site_icon_url' ) ) {
	add_action( 'admin_init', function() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	});
} else {
	require_once 'class.wp-site-icon.php';
	$GLOBALS['wp_site_icon'] = new WP_Site_Icon;
}

<?php
/**
 * Plugin Name: WP Default Featured Image
 * Plugin URI: https://sanny.dev/wp-default-featured-image
 * Description: Set a default featured image effortlessly for your posts, pages, or custom post types using our plugin. Streamline the process by establishing a fallback image based on categories. Choose an image from your media library or upload a new one with ease, ensuring a consistent and efficient way to manage featured images across your content.
 * Version: 1.5.1
 * Author: Sanny Srivastava
 * Author URI: https://sanny.dev/
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WP_Default_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// error_log(print_r($_POST, true));

// Define Constants and Version
define('WP_AUTO_FI_URL', WP_PLUGIN_URL . '/wp-auto-featured-image');
define('WP_AUTO_ADMIN_URL', get_admin_url(null, 'admin.php?page=wp_auto_featured_image'));

// Include necessary files
require_once( plugin_dir_path( __FILE__ ) . 'admin/class-wpafi-admin.php' );

// Initialize the admin class
if ( class_exists( 'WP_Auto_Featured_Image_Admin' ) ) {
    $wp_auto_featured_image_admin = new WP_Auto_Featured_Image_Admin();
}
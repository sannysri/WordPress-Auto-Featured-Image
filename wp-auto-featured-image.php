<?php
/**
 * Plugin Name: WordPress Default Featured Image
 * Plugin URI: https://sanny.dev/
 * Description: This plugin provides you an easy way to set a default thumbnail image for your posts, pages or custom post types. You can select an image from your local machine or media library.
 * Author: Sanny Srivastava
 * Author URI: https://sanny.dev/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.5.1
 * 
 * Copyright (c) 2014 by Sanny Srivastava (sannysrivastava@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */


if (! defined('WP_CONTENT_URL') ) {
    define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
}
if (! defined('WP_CONTENT_DIR') ) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}


// Define Constants and Version
define('WP_AUTO_FI_URL', WP_PLUGIN_URL . '/wp-auto-featured-image');
define('WP_AUTO_ADMIN_URL', get_admin_url(null, 'admin.php?page=wp_auto_featured_image'));

// Add the admin options page
add_action('admin_menu', 'wpafi_admin_add_page');
function wpafi_admin_add_page()
{
    add_options_page('WordPress Default Featured Image', 'WP Default Featured Image', 'manage_options', 'wp_auto_featured_image', 'wpafi_start');
}

// Setting option in PLUGIN page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links');
function my_plugin_action_links( $links )
{
    $links[] = '<a href="' . WP_AUTO_ADMIN_URL . '">Settings</a>';
    $links[]  = '<a href="" target="_blank">Documentation</a>';
    return $links;
}

// Menu Callback and backend design
function wpafi_start()
{
    ?>
<div class="wrap wp_afi">
    <?php
    /*
    if( isset($_GET['settings-updated']) ) { ?>
    <div id="message" class="updated">
    <p><strong><?php _e('Settings saved.') ?></strong></p>
    </div>
    <?php } */
    ?>
<h2>WordPress Default Featured Image</h2>
<div class="content_area">
<div class="wp_afi_left_area">
<form action="options.php" method="post">
<div class="metabox-holder">
    <div class="postbox-container postbox" style="width: 99%;">
    <?php settings_fields('wpafi_options'); ?>
    <?php do_settings_sections('wp_auto_featured_image'); ?>
    </div>         
</div>
   <input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
</form>   
</div>

</div><!-- wp_afi LEFT AREA -->
<div class="wp_afi_right_area">
    <div class="metabox-holder">
        <div class="postbox-container postbox" style="width: 99%;">  
                <h3 class="hndle"><span>Support</span></h3>
                <div class="inside">
                <p class="multi-option">Have you encountered any problem with our plugin and need our help? Do you need to ask us any question?</p>
                <p>You can post your question or issues at WordPress <a target="_blank" href="http://wordpress.org/support/plugin/wp-auto-featured-image">Support</a> or can directly  <a href="mailto:sannysrivastava@gmail.com?subject=Support Request For WordPress Default Featured Image From <?php bloginfo('url'); ?>">mail me</a>.</p>
              </div>
        </div>
    </div> 
    
    <div class="metabox-holder">
        <div class="postbox-container postbox" style="width: 99%;">  
                <h3 class="hndle"><span>Help</span></h3>
                <div class="inside">
                <p class="multi-option">
                We need your support to make this and other plugins more smarter and helpful for you, if this plugin saved any minutes of your timeffort, kindly get some time for a favor to us and</p>
                <p>
                 1) <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-auto-featured-image">Rate this plugin.</a><br/>
                 2) Make a small <a href="https://sannysrivastava.wordpress.com" target="_blank"> donation</a> for us. 
                </p>
<form target="_blank" class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post" name="_xclick">
<input type="hidden" name="cmd" value="_xclick" /><input type="hidden" name="business" value="sannysrivastava@gmail.com" />
<input type="hidden" name="item_name" value="Donate for plugin support" /><input type="hidden" name="currency_code" value="USD" />
<select name="amount">
<option value="05.00">05</option>
<option value="10.00">10</option>
<option value="15.00">15</option>
<option value="20.00">20</option>
<option value="25.00">25</option>
<option value="50.00">50</option>
</select>
<input type="image" alt="Make payments with PayPal - it's fast, free and secure!" name="submit" src="http://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" />
</form>
                <p>Any help would be very appreciated. Thanks for using this plugin.<br/>
                Have a good day!!</p>
              </div>
        </div>
    </div>   
    <div class="metabox-holder">
        <div class="postbox-container postbox" style="width: 99%;">  
                <h3 class="hndle"><span>Hire Me</span></h3>
                <div class="inside">
                <p class="multi-option">Do you want more customization on this plugin? or want to develope a new plugin?</p>
                <p><strong><a href="mailto:sannysrivastava@gmail.com?subject=Hire Request From <?php bloginfo('url'); ?>">Hire me</a> at just $15/hour.</strong></p>
              </div>
        </div>
    </div>   
</div><!-- wp_afi RIGHT AREA -->  
</div>
    <?php
}

// Add settings option at the admin form
add_action('admin_init', 'wpafi_admin_init');
function wpafi_admin_init()
{
    register_setting('wpafi_options', 'wpafi_options', 'wpafi_options_validate');
    add_settings_section('wpafi_main', 'General Settings', 'wpafi_desp', 'wp_auto_featured_image');
    add_settings_field('wpafi_post_type', 'Include Post Types:', 'wpafi_post_types', 'wp_auto_featured_image', 'wpafi_main');
    add_settings_field('wpafi_categories', 'Only for these categories:', 'wpafi_categories', 'wp_auto_featured_image', 'wpafi_main');
    add_settings_field('wpafi_categories_all', 'Apply categories filter for all post types:', 'wpafi_categories_all', 'wp_auto_featured_image', 'wpafi_main');
    add_settings_field('wpafi_default_thumb', 'Set Default Thumbnail:', 'wpafi_default_thumb', 'wp_auto_featured_image', 'wpafi_main');
    add_settings_field('wpafi_default_thumb_id', '', 'wpafi_default_thumb_id', 'wp_auto_featured_image', 'wpafi_main');
}


function wpafi_desp()
{
    echo '
<div class="inside">
	<p>Choose an image and configure the settings for specific categories or post types.</p>';
}


function wpafi_post_types()
{
    $options    = get_option('wpafi_options');
    $post_types = get_post_types(array( 'public' => true ), 'names');

    foreach ( $post_types as $post_type ) {
        if ($post_type != 'attachment' ) {
            $selected = '';
            if ($options['wpafi_post_type'] ) {
                if (in_array($post_type, $options['wpafi_post_type']) ) {
                    $selected = " checked='checked'";
                }
            }
            echo '
		<input type="checkbox"' . $selected . ' name="wpafi_options[wpafi_post_type][]" id="wpafi_text_string-' . $post_type . '" value="' . $post_type . '" />
		<label class="post-type" for="wpafi_text_string-' . $post_type . '">' . preg_replace('/[-_]/', ' ', $post_type) . '</label>';
        }
    }
}

function wpafi_categories_all()
{
    $options = get_option('wpafi_options');
    // print_r($options);

    if ($options['wpafi_categories_all'] == 'yes' ) {
        $yes = 'checked="checked';
        $no  = '';
    } else {
        $no  = 'checked="checked';
        $yes = '';
    }
    echo '
		<input type="radio" ' . $yes . ' class="cat_include" name="wpafi_options[wpafi_categories_all]" id="wpafi_text_string-yes" value="yes" />
		<label class="post-type" for="wpafi_text_string-yes">Yes</label>
		<input type="radio" ' . $no . ' class="cat_include" name="wpafi_options[wpafi_categories_all]" id="wpafi_text_string-no" value="no" />
		<label class="post-type" for="wpafi_text_string-no">No</label>';
    echo '<span class="note clear">If you are dealing with other post-types than WordPress default "post" then selecting "YES" will perform the category filter for other post-types as well.</span>';
}


function wpafi_categories()
{
    $options    = get_option('wpafi_options');
    $wpafi_cats = get_categories(
        array(
        'type'       => 'post',
        'hide_empty' => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
        )
    );

    foreach ( $wpafi_cats as $wpafi_cat ) {
        $selected = '';
        if (isset($options['wpafi_categories']) ) {
            if (( is_array($options['wpafi_categories']) ) && ( in_array($wpafi_cat->slug, $options['wpafi_categories']) ) ) {
                $selected = " checked='checked'";
            }
        }
        echo '
		<input type="checkbox"' . $selected . ' name="wpafi_options[wpafi_categories][]" id="wpafi_text_string-' . $wpafi_cat->slug . '" value="' . $wpafi_cat->slug . '" />
		<label class="post-type" for="wpafi_text_string-' . $wpafi_cat->slug . '">' . $wpafi_cat->name . '</label>';
    }
    echo '<span class="note clear">Leave this unchecked if you need the thumbnail for all categories. This setting will not have any effect for pages.</span>';
}

function wpafi_default_thumb()
{
    $options = get_option('wpafi_options');
    if (empty($options['wpafi_default_thumb']) ) {
        echo '<input type="text" id="default_thumb_url" name="wpafi_options[wpafi_default_thumb]" value="" />                          
		  	  <input id="upload_default_thumb" class="button" type="button" value="Upload Thumbnail" />';
    } else {
        echo '
		<input id="upload_default_thumb" class="button" type="button" value="Update Thumbnail" />
		<input type="hidden" id="default_thumb_url" name="wpafi_options[wpafi_default_thumb]" value="' . $options['wpafi_default_thumb'] . '" /> 
		<input id="delete_thumb" name="delete_thumb" type="button" class="button" value="Delete Thumbnail" />
		<div id="uploaded_thumb_preview">
			<img src="' . $options['wpafi_default_thumb'] . '" style="max-width:100%;">
		</div>';
    }
}

function wpafi_default_thumb_id()
{
    $options = get_option('wpafi_options');
    if (empty($options['wpafi_default_thumb_id']) ) {
        echo '<input type="hidden" id="default_thumb_id" class="s1" name="wpafi_options[wpafi_default_thumb_id]" value="" />';
    } else {
        echo '<input type="hidden" id="default_thumb_id" class="s2" name="wpafi_options[wpafi_default_thumb_id]" value="' . $options['wpafi_default_thumb_id'] . '" />';
    }
}


function wpafi_options_validate( $input )
{
    // $newinput['text_string'] = trim($input['text_string']);
    // if(!preg_match('/^[a-z0-9]{32}$/i', $newinput['text_string'])) {
    // $newinput['text_string'] = false;
    // }
    return $input;
}

// Enque scripts to WordPress
add_action('admin_enqueue_scripts', 'wp_afi_enqueue_scripts');
function wp_afi_enqueue_scripts()
{
    wp_enqueue_style('wafi-style', WP_AUTO_FI_URL . '/css/wafi-style.css');
    wp_register_script('wafi-script', WP_AUTO_FI_URL . '/js/wafi-script.js', array( 'jquery', 'media-upload', 'thickbox' ));
    if ('settings_page_wp_auto_featured_image' == get_current_screen()->id ) {
        wp_enqueue_script('jquery');
        // wp_enqueue_script('thickbox'); Removed 21 May 2018
        // wp_enqueue_style('thickbox');     Removed 21 May 2018
        wp_enqueue_script('media-upload');
        wp_enqueue_media(); // Added 21 May 2018
        wp_enqueue_script('wafi-script');
    }
}


// Set Post thumbnail when a post is going to be pubslihed.

add_action('save_post', 'wpfi_set_thumbnail');
function wpfi_set_thumbnail( $post_id )
{
    global $wpdb;
    $_POST ? extract($_POST) : extract($_GET); /**
 * Bug Fix | Nadir Atmaca
*/
    $options = get_option('wpafi_options');

    if (( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) ) {
        return;
    }

    $current_post_type = get_post_type($post_id);
    // Check if this suppose to be set thumbnail.
    if (( ! empty($options['wpafi_post_type']) ) && ( is_array($options['wpafi_post_type']) ) && ( in_array($current_post_type, $options['wpafi_post_type']) != 0 ) && ( has_post_thumbnail($post_id) == false ) ) {
        // If category filter is on.
        if (( is_array($options['wpafi_categories']) ) && ( $current_post_type != 'page' ) ) {

            // if restricted for default POST
            if ($options['wpafi_categories_all'] == 'no' ) {
                if (in_category($options['wpafi_categories'], $post_id) && ( $current_post_type == 'post' ) ) {
                    // Set thumbnail here
                    set_post_thumbnail($post_id, $options['wpafi_default_thumb_id']);
                }
            } elseif ($options['wpafi_categories_all'] == 'yes' ) {
                if (in_category($options['wpafi_categories'], $post_id) ) {
                    // Set thumbnail here
                    set_post_thumbnail($post_id, $options['wpafi_default_thumb_id']);
                }
            }
        } else {
            // Set thumbnail here
            set_post_thumbnail($post_id, $options['wpafi_default_thumb_id']);
        }
    }
}

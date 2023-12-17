<?php
/**
 * Admin class for WP Auto Featured Image plugin.
 *
 * @package WP_Auto_Featured_Image_Admin
 */
class WP_Auto_Featured_Image_Admin {
	/**
     * Constructor for the admin class.
     */
    public function __construct() {

		add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action('save_post', array( $this, 'wpafi_set_thumbnail' ) );

		require_once( plugin_dir_path( __FILE__ ) . '/class-wpafi-settings.php' );
		// Initialize the admin class
		if ( class_exists( 'WP_Auto_Featured_Image_Settings' ) ) {
			$wp_auto_featured_image_settings = new WP_Auto_Featured_Image_Settings();
       		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		}
    }

	 /**
     * Add admin page to the WordPress dashboard menu.
     */
    public function add_admin_page() {
        add_options_page(
            'WP Auto Featured Image Settings',
            'Auto Featured Image',
            'manage_options',
            'wp_auto_featured_image',
            array( $this, 'render_admin_page' )
        );
    }

	/**
     * Render the admin settings page.
     */
    public function render_admin_page() {
        // Output your admin settings HTML here
        include( plugin_dir_path(__FILE__) . '../includes/admin-settings.php' );
    }

	/**
	 * Enqueue scripts and styles for the WP Auto Featured Image plugin.
	 */
	public function enqueue_scripts() {
		// Enqueue the main plugin stylesheet
		wp_enqueue_style('wpafi-style', WP_AUTO_FI_URL . '/css/wpafi-style.css');

		// Register and enqueue the main script with dependencies
		wp_register_script('wpafi-script', WP_AUTO_FI_URL . '/js/wpafi-script.js', array('jquery', 'media-upload', 'thickbox'));

		// Check if the current screen is the WP Auto Featured Image settings page
		if ('settings_page_wp_auto_featured_image' == get_current_screen()->id) {
			// Enqueue necessary scripts and styles for media upload
			wp_enqueue_script('jquery');
			wp_enqueue_script('media-upload');
			wp_enqueue_media();

			// Enqueue the main script for the settings page
			wp_enqueue_script('wpafi-script');

			// Localize the script to pass data to JavaScript
			wp_localize_script('wpafi-script', 'wpafi_vars', array(
				'upload_button_text' => esc_html__('Upload Thumbnail', 'your-text-domain'),
				'delete_button_text' => esc_html__('Delete Thumbnail', 'your-text-domain'),
			));

			// Enqueue Select2 CSS from CDN
			wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');

			// Enqueue jQuery and Select2 JS from CDN
			wp_enqueue_script('jquery');
			wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '', true);
		}
	}

	/**
	 * Set post thumbnail when a post is published.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function wpfi_set_thumbnail($post_id)
	{

		// Bail, if the post is an autosave.
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return false;
		}

		$options = get_option('wpafi_options');

		// Check if wpafi_options settings exist.
		if (empty($options) || !is_array($options)) {
			return;
		}

		// Return if the post thumbnail is already available.
		if( has_post_thumbnail($post_id) ) {
			return;
		}

		if ($this->is_post_meeting_criteria($post_id, $options)) {
			$this->set_post_thumbnail($post_id, $options);
		}
	}

	/**
	 * Check if setting the thumbnail is required.
	 *
	 * @param int $post_id  The ID of the post.
	 * @param array $options Plugin options.
	 *
	 * @return bool True if thumbnail should be set, false otherwise.
	 */
	public function is_post_meeting_criteria($post_id, $options)
	{
		if( empty( $options['wpafi_default_thumb_id'] ) ) {
			return;
		}

		// Check post type condition
		if (!empty($options['wpafi_post_type']) && is_array($options['wpafi_post_type'])) {
			$current_post_type = get_post_type($post_id);
			if (!in_array($current_post_type, $options['wpafi_post_type'])) {
				return false;
			}
		}

		// Check categories condition
		if (!empty($options['wpafi_categories']) && is_array($options['wpafi_categories'])) {
			if ($current_post_type !== 'page' && !in_category($options['wpafi_categories'], $post_id)) {
				return false;
			}
		}

		// Check taxonomy terms condition
		if (!empty($options['wpafi_taxonomy_terms']) && is_array($options['wpafi_taxonomy_terms'])) {
			foreach ($options['wpafi_taxonomy_terms'] as $taxonomy_term) {
				$taxonomy = get_term($taxonomy_term)->taxonomy;
				if (!has_term($taxonomy_term, $taxonomy, $post_id)) {
					return false;
				}
			}
		}

		// Check tags condition
		if (!empty($options['wpafi_tags']) && is_array($options['wpafi_tags'])) {
			$post_tags = wp_get_post_tags($post_id, array('fields' => 'slugs'));
			if (empty(array_intersect($post_tags, $options['wpafi_tags']))) {
				return false;
			}
		}

		// All conditions are met
		return true;
	}

	/**
	 * Set the post thumbnail based on plugin options.
	 *
	 * @param int $post_id  The ID of the post.
	 * @param array $options Plugin options.
	 */
	public function set_post_thumbnail($post_id, $options){
		set_post_thumbnail($post_id, $options['wpafi_default_thumb_id']);
	}

	/**
	 * Check if the thumbnail should be set based on category conditions.
	 *
	 * @param array $options          Plugin options.
	 * @param string $current_post_type Current post type.
	 * @param int $post_id             The ID of the post.
	 *
	 * @return bool True if thumbnail should be set, false otherwise.
	 */
	public function is_post_meeting_criteria_based_on_category($options, $current_post_type, $post_id){
		if (is_array($options['wpafi_categories']) && $current_post_type !== 'page') {
			if ($options['wpafi_categories_all'] == 'no' && in_category($options['wpafi_categories'], $post_id) && $current_post_type == 'post') {
				return true;
			} elseif ($options['wpafi_categories_all'] == 'yes' && in_category($options['wpafi_categories'], $post_id)) {
				return true;
			}
		}

		return false;
	}

}

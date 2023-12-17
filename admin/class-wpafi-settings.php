<?php
/**
 * Settings class for WP Auto Featured Image plugin.
 *
 * @package WP_Auto_Featured_Image_Settings
 */
class WP_Auto_Featured_Image_Settings {
	/**
     * Constructor for the admin class.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'init_settings' ) );
    }

    /**
     * Register plugin settings.
     */
    public function init_settings() {
        register_setting( 'wp_auto_featured_image_options', 'wpafi_options',  array($this, 'sanitize_options') );
		add_settings_section( 'wpafi_default_section', 'General Settings', array( $this, 'wpafi_description' ), 'wp_auto_featured_image_options' );
		add_settings_field('wpafi_post_type', 'Post Types:', array( $this, 'wpafi_post_types' ), 'wp_auto_featured_image_options', 'wpafi_default_section');
		add_settings_field('wpafi_categories', 'Categories:', array( $this, 'wpafi_categories' ), 'wp_auto_featured_image_options', 'wpafi_default_section');
		add_settings_field('wpafi_taxonomies_terms', 'Terms:', array( $this, 'wpafi_taxonomies_terms' ), 'wp_auto_featured_image_options', 'wpafi_default_section');
		add_settings_field('wpafi_tags', 'Tags:', array( $this, 'wpafi_tags' ), 'wp_auto_featured_image_options', 'wpafi_default_section');
		add_settings_field('wpafi_default_thumbnail', 'Default Thumbnail:', array( $this, 'wpafi_default_thumb' ), 'wp_auto_featured_image_options', 'wpafi_default_section');
    }

	public function wpafi_description() {
		echo '<p>' . esc_html__('General settings for WP Auto Featured Image.', 'your-text-domain') . '</p>';
	}

	/**
	 * Sanitize and validate options.
	 *
	 * @param array $input The unsanitized input.
	 * @return array The sanitized input.
	 */
	public function sanitize_options($input) {
		$sanitized_input = array();

		// List of multi-select fields to sanitize
		$multi_select_fields = array('wpafi_post_type', 'wpafi_categories', 'wpafi_tags', 'wpafi_taxonomy_terms');

		foreach ($multi_select_fields as $field) {
			if (isset($input[$field]) && is_array($input[$field])) {
				$sanitized_input[$field] = array_map('sanitize_text_field', $input[$field]);
			}
		}

		// Sanitize specific fields
		$specific_fields = array('wpafi_default_thumb');

		foreach ($specific_fields as $field) {
			// die($input[$field]);
			if (isset($input[$field])) {
				$sanitized_input['wpafi_default_thumb'] = intval($input[$field]);
			}
		}

		return $sanitized_input;
	}


	/**
	 * Render a multiselect dropdown for post types in the plugin settings.
	 */
	public function wpafi_post_types() {
		/**
		 * Options saved in the WordPress database.
		 * @var array
		 */
		$options = get_option('wpafi_options');

		/**
		 * Array of public post types.
		 * @var array
		 */
		$post_types = get_post_types(array('public' => true), 'names');
		echo '<select  class="wpafi-select" id="my-multiselect" name="wpafi_options[wpafi_post_type][]" multiple="multiple">';
		foreach ($post_types as $post_type) {
			if ($post_type != 'attachment') {
				$selected = '';
				if ($options['wpafi_post_type']) {
					if (in_array($post_type, $options['wpafi_post_type'])) {
						$selected = " selected='selected'";
					}
				}
				echo '<option value="' . $post_type . '"' . $selected . '>' . preg_replace('/[-_]/', ' ', $post_type) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Render a multiselect dropdown for categories in the plugin settings.
	 */
	public function wpafi_categories() {
		$options    = get_option('wpafi_options');
		$wpafi_cats = get_categories(array('hide_empty' => 0, 'orderby' => 'name', 'order' => 'ASC'));

		echo '<select class="wpafi-select" id="my-category-multiselect" name="wpafi_options[wpafi_categories][]" multiple="multiple">';
		foreach ($wpafi_cats as $wpafi_cat) {
			$selected = in_array($wpafi_cat->slug, $options['wpafi_categories']) ? ' selected="selected"' : '';
			echo '<option value="' . $wpafi_cat->slug . '"' . $selected . '>' . $wpafi_cat->name . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render a multiselect dropdown for taxonomies terms in the plugin settings.
	 */
	public function wpafi_taxonomies_terms() {
		$options    = get_option('wpafi_options');

		// Get all public taxonomies excluding "category" and "post_tag"
		$taxonomies = get_taxonomies(array('public' => true, 'exclude' => array('category', 'post_tag')), 'names');

		echo '<select id="my-taxonomy-multiselect" class="wpafi-select" name="wpafi_options[wpafi_taxonomy_terms][]" multiple="multiple">';

		foreach ($taxonomies as $taxonomy) {
			$terms = get_terms($taxonomy, array('hide_empty' => false));

			foreach ($terms as $term) {
				$selected = in_array($term->slug, $options['wpafi_taxonomy_terms']) ? ' selected="selected"' : '';
				echo '<option value="' . $term->slug . '"' . $selected . '>' . $term->name . '</option>';
			}
		}

		echo '</select>';
	}


	/**
	 * Render a multiselect dropdown for tags in the plugin settings.
	 */
	public function wpafi_tags() {
		$options = get_option('wpafi_options');
		$tags    = get_tags();

		echo '<select id="my-tag-multiselect" class="wpafi-select"  name="wpafi_options[wpafi_tags][]" multiple="multiple">';
		foreach ($tags as $tag) {
			$selected = in_array($tag->slug, $options['wpafi_tags']) ? ' selected="selected"' : '';
			echo '<option value="' . $tag->slug . '"' . $selected . '>' . $tag->name . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Renders the HTML for the default thumbnail settings in the admin panel.
	 */
	public function wpafi_default_thumb() {
		$options = get_option('wpafi_options');
		?>
		<div class="upload-container">
			<input type="hidden" id="default_thumb_id" name="wpafi_options[wpafi_default_thumb]" value="<?php echo esc_attr($options['wpafi_default_thumb']); ?>" />
			<button id="upload_default_thumb" class="button" type="button"><?php esc_html_e('Upload Thumbnail', 'your-text-domain'); ?></button>
			<?php if (!empty($options['wpafi_default_thumb'])) : ?>
				<button id="delete_thumb" name="delete_thumb" class="button" type="button"><?php esc_html_e('Delete Thumbnail', 'your-text-domain'); ?></button>
			<?php endif; ?>
			<div id="uploaded_thumb_preview">
				<?php
				// Use wp_get_attachment_image to display the image by ID
				echo wp_get_attachment_image($options['wpafi_default_thumb'], 'full', false, array('style' => 'max-width:100%;'));
				?>
			</div>
		</div>
		<?php
	}
}
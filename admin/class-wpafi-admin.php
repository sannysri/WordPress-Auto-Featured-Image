<?php
/**
 * Auto Featured Image Admin Functions
 *
 * @package WP_Auto_Featured_Image
 */

/**
 * Class WPAFI_Admin
 *
 * @package WP_Auto_Featured_Image
 */
class WPAFI_Admin {
	/**
	 * Constructor for the admin class.
	 */
	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'save_post', array( $this, 'wpafi_set_thumbnail' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );

		// AJAX handlers for bulk operations.
		add_action( 'wp_ajax_wpafi_bulk_assign', array( $this, 'ajax_bulk_assign' ) );
		add_action( 'wp_ajax_wpafi_bulk_count', array( $this, 'ajax_bulk_count' ) );

		// Image column in posts list.
		add_action( 'admin_init', array( $this, 'setup_image_column' ) );

		// Include file to create the settings page.
		require_once plugin_dir_path( __FILE__ ) . '/class-wpafi-settings.php';

		// Initialize the settings class and store globally for template access.
		if ( class_exists( 'WPAFI_Settings' ) ) {
			$GLOBALS['wpafi_settings'] = new WPAFI_Settings();
		}

		// Include and initialize the review notice class.
		require_once plugin_dir_path( __FILE__ ) . '/class-wpafi-review-notice.php';
		if ( class_exists( 'WPAFI_Review_Notice' ) ) {
			new WPAFI_Review_Notice();
		}
	}

	/**
	 * Setup image column for enabled post types.
	 */
	public function setup_image_column() {
		$options = get_option( 'wpafi_options' );
		if ( empty( $options['wpafi_show_image_column'] ) ) {
			return;
		}

		$column_post_types = ! empty( $options['wpafi_column_post_types'] ) ? $options['wpafi_column_post_types'] : array( 'post' );

		foreach ( $column_post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_image_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_image_column' ), 10, 2 );
		}
	}

	/**
	 * Add featured image column to posts list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_image_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( 'title' === $key ) {
				$new_columns['wpafi_image'] = __( 'Image', 'sny-auto-featured-image' );
			}
			$new_columns[ $key ] = $value;
		}
		return $new_columns;
	}

	/**
	 * Render the featured image column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_image_column( $column, $post_id ) {
		if ( 'wpafi_image' !== $column ) {
			return;
		}

		$options = get_option( 'wpafi_options' );
		$size    = ! empty( $options['wpafi_column_size'] ) ? intval( $options['wpafi_column_size'] ) : 60;

		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, array( $size, $size ), array( 'style' => 'border-radius: 4px;' ) );
		} else {
			echo '<span class="wpafi-no-image dashicons dashicons-format-image" style="font-size: ' . esc_attr( $size ) . 'px; width: ' . esc_attr( $size ) . 'px; height: ' . esc_attr( $size ) . 'px; color: #ccc;"></span>';
		}
	}

	/**
	 * Add admin page to the WordPress dashboard menu.
	 */
	public function add_admin_page() {
		add_options_page(
			'Auto Featured Image Settings',
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
		// Output your admin settings HTML here.
		include plugin_dir_path( __FILE__ ) . '../includes/admin-settings.php';
	}

	/**
	 * Enqueue scripts and styles for the WP Auto Featured Image plugin.
	 */
	public function enqueue_scripts() {
		$has_pro_features = function_exists( 'wpafi_has_pro_features' ) && wpafi_has_pro_features();

		// Register Select2 JS and CSS early so they can be used as dependencies.
		wp_register_script( 'select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array( 'jquery' ), '4.0.13', true );
		wp_register_style( 'select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13' );

		// Enqueue the main plugin stylesheet.
		wp_enqueue_style( 'wpafi-style', WPAFI_PLUGIN_URL . '/css/wpafi-style.css', array(), WPAFI_VERSION );

		// Register the main script with dependencies.
		wp_register_script( 'wpafi-script', WPAFI_PLUGIN_URL . '/js/wpafi-script.js', array( 'jquery', 'media-upload', 'thickbox', 'select2-js' ), WPAFI_VERSION, true );

		// Check if the current screen is the WP Auto Featured Image settings page.
		if ( 'settings_page_wp_auto_featured_image' === get_current_screen()->id ) {
			// Enqueue necessary scripts and styles for media upload.
			wp_enqueue_script( 'jquery' );
			wp_enqueue_style( 'select2-css' );
			wp_enqueue_script( 'select2-js' );
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_media();

			// Enqueue the main script for the settings page.
			wp_enqueue_script( 'wpafi-script' );

			// Prepare categories for JS.
			$categories     = get_categories( array( 'hide_empty' => false ) );
			$categories_arr = array();
			foreach ( $categories as $cat ) {
				$categories_arr[ $cat->slug ] = $cat->name;
			}

			// Prepare post types for JS.
			$post_types     = get_post_types( array( 'public' => true ), 'objects' );
			$post_types_arr = array();
			foreach ( $post_types as $pt ) {
				if ( 'attachment' !== $pt->name ) {
					$post_types_arr[ $pt->name ] = $pt->label;
				}
			}

			// Check if Pro teasers should be shown.
			$show_pro_teasers = ! $has_pro_features && function_exists( 'wpafi_should_show_pro_teasers' ) && wpafi_should_show_pro_teasers();
			$upgrade_url      = function_exists( 'wpafi_get_upgrade_url' ) ? wpafi_get_upgrade_url( 'add-btn' ) : 'https://sanny.dev/plugins/auto-featured-image-pro/';

			// Localize the script to pass data to JavaScript.
			wp_localize_script(
				'wpafi-script',
				'wpafi_vars',
				array(
					'upload_button_text' => esc_html__( 'Upload Thumbnail', 'sny-auto-featured-image' ),
					'delete_button_text' => esc_html__( 'Delete Thumbnail', 'sny-auto-featured-image' ),
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'bulk_nonce'         => wp_create_nonce( 'wpafi_bulk_nonce' ),
					'bulk_processing'    => esc_html__( 'Processing...', 'sny-auto-featured-image' ),
					'bulk_confirm'       => esc_html__( 'This will update featured images for all matching posts. Continue?', 'sny-auto-featured-image' ),
					'max_rules'          => $has_pro_features ? 999 : 2,
					'max_rules_message'  => esc_html__( 'Upgrade to Pro for unlimited conditional rules!', 'sny-auto-featured-image' ),
					'select_image_title' => esc_html__( 'Select Featured Image', 'sny-auto-featured-image' ),
					'categories'         => $categories_arr,
					'post_types'         => $post_types_arr,
					'show_pro_teasers'   => $show_pro_teasers,
					'upgrade_url'        => esc_url( $upgrade_url ),
					'upgrade_text'       => esc_html__( 'Upgrade to add more', 'sny-auto-featured-image' ),
				)
			);
		}
	}

	/**
	 * Set post thumbnail when a post is published.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function wpafi_set_thumbnail( $post_id ) {
		// Let Pro handle this if active and licensed.
		if ( function_exists( 'wpafi_has_pro_features' ) && wpafi_has_pro_features() ) {
			return;
		}

		// Bail, if the post is an autosave.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Get settings for the plugin.
		$options = get_option( 'wpafi_options' );

		// Check if wpafi_options settings exist.
		if ( empty( $options ) || ! is_array( $options ) ) {
			return;
		}

		// Check conditional rules (first matching rule wins).
		if ( ! empty( $options['wpafi_rules'] ) && is_array( $options['wpafi_rules'] ) ) {
			foreach ( $options['wpafi_rules'] as $rule ) {
				// Skip if rule is disabled.
				if ( isset( $rule['enabled'] ) && ! $rule['enabled'] ) {
					continue;
				}

				// Check if post already has thumbnail and rule doesn't allow overwrite.
				$rule_overwrite = ! empty( $rule['overwrite'] );
				if ( has_post_thumbnail( $post_id ) && ! $rule_overwrite ) {
					continue;
				}

				// Check if rule conditions match.
				if ( ! $this->does_rule_match( $post_id, $rule ) ) {
					continue;
				}

				// Rule matches - try to get an image based on source type.
				$image_id     = null;
				$image_source = isset( $rule['image_source'] ) ? $rule['image_source'] : 'media';

				switch ( $image_source ) {
					case 'first_image':
						// Get first image/video from post content.
						$include_video = ! empty( $rule['include_video'] );
						$sideload      = ! empty( $rule['sideload_external'] );
						$image_id      = $this->get_first_image_from_content( $post_id, $sideload, $include_video );
						break;

					case 'external':
						// Sideload from external URL.
						if ( ! empty( $rule['external_url'] ) ) {
							$image_id = $this->sideload_image( $rule['external_url'], $post_id );
						}
						break;

					case 'media':
					default:
						// Use assigned media library image.
						if ( ! empty( $rule['image_id'] ) ) {
							$image_id = intval( $rule['image_id'] );
						}
						break;
				}

				// Set the thumbnail and exit.
				if ( $image_id ) {
					set_post_thumbnail( $post_id, $image_id );
					return;
				}
			}
		}

		// Legacy fallback: check global settings.
		$should_overwrite = ! empty( $options['wpafi_overwrite'] );
		if ( has_post_thumbnail( $post_id ) && ! $should_overwrite ) {
			return;
		}

		// Check if post meets legacy criteria.
		if ( ! $this->is_post_meeting_criteria( $post_id, $options ) ) {
			return;
		}

		// Try global auto-detect if enabled.
		if ( ! empty( $options['wpafi_auto_detect'] ) ) {
			$image_id = $this->get_first_image_from_content( $post_id, true, true );
			if ( $image_id ) {
				set_post_thumbnail( $post_id, $image_id );
				return;
			}
		}

		// Fall back to default thumbnail.
		if ( ! empty( $options['wpafi_default_thumb_id'] ) ) {
			set_post_thumbnail( $post_id, $options['wpafi_default_thumb_id'] );
		}
	}

	/**
	 * Get image ID from conditional rules.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $rules   Array of rules.
	 * @return int|false Image ID or false if no rule matched.
	 */
	public function get_image_from_rules( $post_id, $rules ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		foreach ( $rules as $rule ) {
			// Check per-rule auto-detect first.
			$rule_auto_detect = ! empty( $rule['auto_detect'] );
			if ( $rule_auto_detect ) {
				// Check if rule conditions match before auto-detecting.
				if ( $this->does_rule_match( $post_id, $rule ) ) {
					$image_id = $this->get_first_image_from_content( $post_id, true, true );
					if ( $image_id ) {
						return $image_id;
					}
				}
			}

			// Skip rules without an image (unless auto-detect is on and handled above).
			if ( empty( $rule['image_id'] ) ) {
				continue;
			}

			// Check if rule conditions match.
			if ( $this->does_rule_match( $post_id, $rule ) ) {
				return intval( $rule['image_id'] );
			}
		}

		return false;
	}

	/**
	 * Check if a rule's conditions match a post.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $rule    The rule to check.
	 * @return bool True if rule matches.
	 */
	private function does_rule_match( $post_id, $rule ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$post_types    = isset( $rule['post_types'] ) ? array_filter( (array) $rule['post_types'] ) : array();
		$categories    = isset( $rule['categories'] ) ? array_filter( (array) $rule['categories'] ) : array();
		$tags          = isset( $rule['tags'] ) ? array_filter( (array) $rule['tags'] ) : array();
		$post_statuses = isset( $rule['post_statuses'] ) ? array_filter( (array) $rule['post_statuses'] ) : array();

		// If no conditions set, this is a default rule - matches all.
		if ( empty( $post_types ) && empty( $categories ) && empty( $tags ) && empty( $post_statuses ) ) {
			return true;
		}

		$post_type_match = true;
		$category_match  = true;
		$tag_match       = true;
		$status_match    = true;

		// Check post type condition.
		if ( ! empty( $post_types ) ) {
			$post_type_match = in_array( $post->post_type, $post_types, true );
		}

		// Check category condition.
		if ( ! empty( $categories ) ) {
			$category_match = false;
			foreach ( $categories as $cat_slug ) {
				if ( has_category( $cat_slug, $post_id ) ) {
					$category_match = true;
					break;
				}
			}
		}

		// Check tag condition.
		if ( ! empty( $tags ) ) {
			$tag_match = false;
			foreach ( $tags as $tag_slug ) {
				if ( has_tag( $tag_slug, $post_id ) ) {
					$tag_match = true;
					break;
				}
			}
		}

		// Check post status condition.
		if ( ! empty( $post_statuses ) ) {
			$status_match = in_array( $post->post_status, $post_statuses, true );
		}

		// All conditions must match (AND logic).
		return $post_type_match && $category_match && $tag_match && $status_match;
	}

	/**
	 * Extract the first image from post content.
	 *
	 * @param int  $post_id       The post ID.
	 * @param bool $include_video Whether to include video thumbnails.
	 * @param bool $sideload      Whether to sideload external images.
	 * @return int|false Attachment ID or false if not found.
	 */
	public function get_first_image_from_content( $post_id, $include_video = true, $sideload = true ) {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		// Try to find video thumbnails first (if enabled).
		if ( $include_video ) {
			$video_thumbnail = $this->get_video_thumbnail_from_content( $post_id, $sideload );
			if ( $video_thumbnail ) {
				return $video_thumbnail;
			}
		}

		// Match <img> tags in content.
		preg_match_all( '/<img[^>]+>/i', $post->post_content, $matches );

		if ( empty( $matches[0] ) ) {
			return false;
		}

		// Get the first image.
		$first_img = $matches[0][0];

		// Try to extract src attribute.
		preg_match( '/src=["\']([^"\']+)["\']/i', $first_img, $src_match );

		if ( empty( $src_match[1] ) ) {
			return false;
		}

		$image_url = $src_match[1];

		// Try to get attachment ID from URL.
		$attachment_id = attachment_url_to_postid( $image_url );

		if ( $attachment_id ) {
			return $attachment_id;
		}

		// Try wp-image-{id} class pattern (Gutenberg).
		preg_match( '/wp-image-(\d+)/i', $first_img, $class_match );

		if ( ! empty( $class_match[1] ) ) {
			return intval( $class_match[1] );
		}

		// If still not found and sideload is enabled, download the external image.
		if ( $sideload && $this->is_external_url( $image_url ) ) {
			return $this->sideload_image( $image_url, $post_id );
		}

		return false;
	}

	/**
	 * Extract video thumbnail from post content (YouTube/Vimeo).
	 *
	 * @param int  $post_id  The post ID.
	 * @param bool $sideload Whether to sideload the thumbnail.
	 * @return int|false Attachment ID or false if not found.
	 */
	public function get_video_thumbnail_from_content( $post_id, $sideload = true ) {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		$content = $post->post_content;

		// YouTube patterns.
		$youtube_patterns = array(
			'/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i',
			'/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/i',
			'/youtu\.be\/([a-zA-Z0-9_-]+)/i',
			'/youtube\.com\/v\/([a-zA-Z0-9_-]+)/i',
		);

		foreach ( $youtube_patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $matches ) ) {
				$video_id      = $matches[1];
				$thumbnail_url = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";

				// Check if maxresdefault exists, fallback to hqdefault.
				$response = wp_remote_head( $thumbnail_url );
				if ( is_wp_error( $response ) || 404 === wp_remote_retrieve_response_code( $response ) ) {
					$thumbnail_url = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
				}

				if ( $sideload ) {
					return $this->sideload_image( $thumbnail_url, $post_id, "youtube-{$video_id}" );
				}
				return false;
			}
		}

		// Vimeo patterns.
		$vimeo_patterns = array(
			'/vimeo\.com\/(\d+)/i',
			'/player\.vimeo\.com\/video\/(\d+)/i',
		);

		foreach ( $vimeo_patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $matches ) ) {
				$video_id      = $matches[1];
				$thumbnail_url = $this->get_vimeo_thumbnail( $video_id );

				if ( $thumbnail_url && $sideload ) {
					return $this->sideload_image( $thumbnail_url, $post_id, "vimeo-{$video_id}" );
				}
				return false;
			}
		}

		return false;
	}

	/**
	 * Get Vimeo thumbnail URL using oEmbed API.
	 *
	 * @param string $video_id Vimeo video ID.
	 * @return string|false Thumbnail URL or false.
	 */
	private function get_vimeo_thumbnail( $video_id ) {
		$api_url  = "https://vimeo.com/api/oembed.json?url=https://vimeo.com/{$video_id}";
		$response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! empty( $data['thumbnail_url'] ) ) {
			// Get higher resolution version.
			return preg_replace( '/_\d+x\d+/', '_1280x720', $data['thumbnail_url'] );
		}

		return false;
	}

	/**
	 * Check if a URL is external (not from this site).
	 *
	 * @param string $url The URL to check.
	 * @return bool True if external.
	 */
	private function is_external_url( $url ) {
		$site_url   = wp_parse_url( home_url(), PHP_URL_HOST );
		$image_host = wp_parse_url( $url, PHP_URL_HOST );

		return $site_url !== $image_host;
	}

	/**
	 * Sideload an external image into the media library.
	 *
	 * @param string $url       The image URL.
	 * @param int    $post_id   The post ID to attach to.
	 * @param string $file_name Optional custom filename.
	 * @return int|false Attachment ID or false on failure.
	 */
	public function sideload_image( $url, $post_id, $file_name = '' ) {
		// Require the file for media_sideload_image function.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check if this image was already sideloaded (prevent duplicates).
		$existing = $this->find_sideloaded_image( $url );
		if ( $existing ) {
			return $existing;
		}

		// Download the file.
		$tmp = download_url( $url, 30 );

		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		// Get file info.
		$file_array = array(
			'name'     => $file_name ? sanitize_file_name( $file_name . '.jpg' ) : basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		// Ensure we have a valid extension.
		$filetype = wp_check_filetype( $file_array['name'] );
		if ( empty( $filetype['ext'] ) ) {
			$file_array['name'] .= '.jpg';
		}

		// Sideload the image.
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// Clean up temp file.
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		// Store original URL as meta to prevent re-downloading.
		update_post_meta( $attachment_id, '_wpafi_source_url', $url );

		return $attachment_id;
	}

	/**
	 * Find an existing sideloaded image by source URL.
	 *
	 * @param string $url The original source URL.
	 * @return int|false Attachment ID or false.
	 */
	private function find_sideloaded_image( $url ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_wpafi_source_url',
					'value' => $url,
				),
			),
		);

		$attachments = get_posts( $args );

		if ( ! empty( $attachments ) ) {
			return $attachments[0]->ID;
		}

		return false;
	}

	/**
	 * Check if setting the thumbnail is required.
	 *
	 * @param int   $post_id  The ID of the post.
	 * @param array $options Plugin options.
	 *
	 * @return bool True if thumbnail should be set, false otherwise.
	 */
	public function is_post_meeting_criteria( $post_id, $options ) {
		if ( empty( $options['wpafi_default_thumb_id'] ) ) {
			return false;
		}

		// Get current post type.
		$current_post_type = get_post_type( $post_id );

		// Check post type condition.
		if ( ! empty( $options['wpafi_post_type'] ) && is_array( $options['wpafi_post_type'] ) ) {
			if ( ! in_array( $current_post_type, $options['wpafi_post_type'], true ) ) {
				return false;
			}
		}

		// Check categories condition.
		if ( ! empty( $options['wpafi_categories'] ) && is_array( $options['wpafi_categories'] ) ) {
			if ( 'page' !== $current_post_type && ! in_category( $options['wpafi_categories'], $post_id ) ) {
				return false;
			}
		}

		// Check tags condition.
		if ( 'page' !== $current_post_type && ! empty( $options['wpafi_tags'] ) && is_array( $options['wpafi_tags'] ) ) {
			$post_tags = wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) );
			if ( empty( array_intersect( $post_tags, $options['wpafi_tags'] ) ) ) {
				return false;
			}
		}

		// All conditions are met.
		return true;
	}

	/**
	 * Get target post types and rules for bulk operations.
	 *
	 * @param string $rule_idx Rule index or 'all'.
	 * @param array  $options Plugin options.
	 * @return array Array with 'target_rules' and 'target_post_types'.
	 */
	private function get_bulk_targets( $rule_idx, $options ) {
		$target_rules = array();
		if ( 'all' === $rule_idx ) {
			$target_rules = ! empty( $options['wpafi_rules'] ) ? $options['wpafi_rules'] : array();
		} elseif ( is_numeric( $rule_idx ) && isset( $options['wpafi_rules'][ $rule_idx ] ) ) {
			$target_rules = array( $options['wpafi_rules'][ $rule_idx ] );
		}

		$target_post_types = array();
		if ( ! empty( $target_rules ) ) {
			foreach ( $target_rules as $rule ) {
				$rule_pts = ! empty( $rule['post_types'] ) ? (array) $rule['post_types'] : array();
				if ( empty( $rule_pts ) ) {
					$target_post_types = get_post_types( array( 'public' => true ), 'names' );
					break;
				}
				$target_post_types = array_merge( $target_post_types, $rule_pts );
			}
		} else {
			$target_post_types = ! empty( $options['wpafi_post_type'] ) ? $options['wpafi_post_type'] : array( 'post' );
		}

		return array(
			'target_rules'      => $target_rules,
			'target_post_types' => array_unique( $target_post_types ),
		);
	}

	/**
	 * AJAX handler for counting posts to process in bulk operations.
	 */
	public function ajax_bulk_count() {
		check_ajax_referer( 'wpafi_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$options = get_option( 'wpafi_options' );
		if ( empty( $options ) || ! is_array( $options ) ) {
			wp_send_json_error( array( 'message' => 'No settings configured' ) );
		}

		$rule_idx = isset( $_POST['rule_idx'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_idx'] ) ) : 'all';
		$targets  = $this->get_bulk_targets( $rule_idx, $options );

		$args = array(
			'post_type'      => $targets['target_post_types'],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$post_ids = get_posts( $args );

		// Store post IDs in transient for consistent batching.
		$batch_key = 'wpafi_bulk_' . get_current_user_id();
		set_transient( $batch_key, $post_ids, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'total'     => count( $post_ids ),
				'batch_key' => $batch_key,
			)
		);
	}

	/**
	 * AJAX handler for bulk assigning featured images.
	 */
	public function ajax_bulk_assign() {
		// Verify nonce.
		check_ajax_referer( 'wpafi_bulk_nonce', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$options = get_option( 'wpafi_options' );

		if ( empty( $options ) || ! is_array( $options ) ) {
			wp_send_json_error( array( 'message' => 'No settings configured' ) );
		}

		$rule_idx = isset( $_POST['rule_idx'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_idx'] ) ) : 'all';
		$offset   = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50;
		$updated  = 0;
		$failed   = 0;

		// Get post IDs from transient or query fresh.
		$batch_key = 'wpafi_bulk_' . get_current_user_id();
		$post_ids  = get_transient( $batch_key );

		if ( false === $post_ids ) {
			// Transient expired - query fresh.
			$targets  = $this->get_bulk_targets( $rule_idx, $options );
			$args     = array(
				'post_type'      => $targets['target_post_types'],
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);
			$post_ids = get_posts( $args );
		}

		$total        = count( $post_ids );
		$batch_ids    = array_slice( $post_ids, $offset, $limit );
		$targets      = $this->get_bulk_targets( $rule_idx, $options );
		$target_rules = $targets['target_rules'];

		foreach ( $batch_ids as $post_id ) {
			$image_id = null;
			$matched  = false;

			// Try to find a matching rule.
			if ( ! empty( $target_rules ) ) {
				foreach ( $target_rules as $rule ) {
					// Skip if rule is disabled.
					if ( isset( $rule['enabled'] ) && ! $rule['enabled'] ) {
						continue;
					}

					// Check if post meets rule criteria.
					if ( ! $this->does_rule_match( $post_id, $rule ) ) {
						continue;
					}

					// If post already has thumb, only proceed if rule allows overwrite.
					if ( has_post_thumbnail( $post_id ) && empty( $rule['overwrite'] ) ) {
						continue;
					}

					// Get image based on source.
					$source = isset( $rule['image_source'] ) ? $rule['image_source'] : 'media';
					switch ( $source ) {
						case 'first_image':
							$image_id = $this->get_first_image_from_content( $post_id );
							break;
						case 'external':
							if ( ! empty( $rule['external_url'] ) ) {
								$image_id = $this->sideload_image( $rule['external_url'], $post_id );
							}
							break;
						case 'media':
						default:
							$image_id = ! empty( $rule['image_id'] ) ? intval( $rule['image_id'] ) : null;
							break;
					}

					if ( $image_id ) {
						$matched = true;
						break;
					}
				}
			}

			// Global fallback (if no rules match or no rules defined).
			if ( ! $matched && 'all' === $rule_idx ) {
				if ( $this->is_post_meeting_criteria( $post_id, $options ) ) {
					$global_overwrite = ! empty( $options['wpafi_overwrite'] );
					if ( ! has_post_thumbnail( $post_id ) || $global_overwrite ) {
						if ( ! empty( $options['wpafi_auto_detect'] ) ) {
							$image_id = $this->get_first_image_from_content( $post_id );
						}
						if ( ! $image_id && ! empty( $options['wpafi_default_thumb_id'] ) ) {
							$image_id = $options['wpafi_default_thumb_id'];
						}
					}
				}
			}

			if ( $image_id ) {
				if ( set_post_thumbnail( $post_id, $image_id ) ) {
					++$updated;
				} else {
					++$failed;
				}
			}
		}

		$processed  = $offset + count( $batch_ids );
		$has_more   = $processed < $total;
		$next_offset = $has_more ? $processed : null;

		// Clean up transient if done.
		if ( ! $has_more ) {
			delete_transient( $batch_key );
		}

		wp_send_json_success(
			array(
				'message'     => sprintf(
					/* translators: %1$d: number of posts updated, %2$d: number failed */
					__( 'Processed %1$d posts: %2$d updated, %3$d failed.', 'sny-auto-featured-image' ),
					count( $batch_ids ),
					$updated,
					$failed
				),
				'updated'     => $updated,
				'failed'      => $failed,
				'processed'   => $processed,
				'total'       => $total,
				'has_more'    => $has_more,
				'next_offset' => $next_offset,
			)
		);
	}
}

<?php
/**
 * Unit tests for WPAFI Settings class.
 *
 * @package WPAFI\Tests\Unit
 */

namespace WPAFI\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Test the WPAFI_Settings class functionality.
 */
class SettingsTest extends TestCase {

	/**
	 * @var \WPAFI_Settings|null
	 */
	private $settings;

	/**
	 * @var bool Track if Pro features are enabled for test.
	 */
	private $pro_enabled = false;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress functions.
		Functions\when( 'register_setting' )->justReturn( true );
		Functions\when( 'add_settings_section' )->justReturn( true );
		Functions\when( 'add_settings_field' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( function( $str ) {
			return trim( strip_tags( $str ) );
		});

		// Include the class file only once.
		if ( ! class_exists( '\WPAFI_Settings' ) ) {
			// Define wpafi_has_pro_features if not defined.
			if ( ! function_exists( 'wpafi_has_pro_features' ) ) {
				// This will be mocked in individual tests.
				Functions\when( 'wpafi_has_pro_features' )->justReturn( false );
			}
			require_once WPAFI_PLUGIN_DIR . 'admin/class-wpafi-settings.php';
		}

		$this->settings = new \WPAFI_Settings();
	}

	/**
	 * Test sanitize_options sanitizes checkbox fields correctly.
	 */
	public function test_sanitize_options_handles_checkboxes(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_auto_detect' => '1',
			'wpafi_overwrite'   => '1',
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( 1, $result['wpafi_auto_detect'] );
		$this->assertEquals( 1, $result['wpafi_overwrite'] );
	}

	/**
	 * Test sanitize_options sets checkboxes to 0 when not set.
	 */
	public function test_sanitize_options_defaults_unchecked_to_zero(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [];

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( 0, $result['wpafi_auto_detect'] );
		$this->assertEquals( 0, $result['wpafi_overwrite'] );
	}

	/**
	 * Test sanitize_options limits rules to 2 for free version.
	 */
	public function test_sanitize_options_limits_rules_for_free(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_rules' => [
				[ 'condition_type' => 'category', 'condition_value' => 'news', 'image_id' => 1 ],
				[ 'condition_type' => 'category', 'condition_value' => 'sports', 'image_id' => 2 ],
				[ 'condition_type' => 'category', 'condition_value' => 'tech', 'image_id' => 3 ], // Should be ignored.
			],
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 2, $result['wpafi_rules'] );
	}

	/**
	 * Test sanitize_options allows unlimited rules for pro version.
	 */
	public function test_sanitize_options_allows_unlimited_rules_for_pro(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( true );

		$input = [
			'wpafi_rules' => [
				[ 'condition_type' => 'category', 'condition_value' => 'news', 'image_id' => 1 ],
				[ 'condition_type' => 'category', 'condition_value' => 'sports', 'image_id' => 2 ],
				[ 'condition_type' => 'category', 'condition_value' => 'tech', 'image_id' => 3 ],
				[ 'condition_type' => 'category', 'condition_value' => 'music', 'image_id' => 4 ],
			],
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 4, $result['wpafi_rules'] );
	}

	/**
	 * Test sanitize_options sanitizes post type multiselect.
	 */
	public function test_sanitize_options_sanitizes_multiselect(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_post_type' => [ 'post', 'page', 'product' ],
			'wpafi_categories' => [ 'news', 'tech' ],
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( [ 'post', 'page', 'product' ], $result['wpafi_post_type'] );
		$this->assertEquals( [ 'news', 'tech' ], $result['wpafi_categories'] );
	}

	/**
	 * Test sanitize_options sanitizes default thumbnail ID.
	 */
	public function test_sanitize_options_sanitizes_thumbnail_id(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_default_thumb_id' => '456',
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertSame( 456, $result['wpafi_default_thumb_id'] );
	}

	/**
	 * Test sanitize_options handles malicious input in thumbnail ID.
	 */
	public function test_sanitize_options_sanitizes_malicious_thumbnail_id(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_default_thumb_id' => '456<script>alert("XSS")</script>',
		];

		$result = $this->settings->sanitize_options( $input );

		// intval should strip out any non-numeric content.
		$this->assertSame( 456, $result['wpafi_default_thumb_id'] );
	}

	/**
	 * Test sanitize_options sanitizes tags multiselect.
	 */
	public function test_sanitize_options_sanitizes_tags(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_tags' => [ 'featured', 'trending', 'breaking' ],
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( [ 'featured', 'trending', 'breaking' ], $result['wpafi_tags'] );
	}

	/**
	 * Test sanitize_options handles display options.
	 */
	public function test_sanitize_options_handles_display_options(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_show_image_column' => '1',
			'wpafi_column_size'       => '80',
			'wpafi_column_post_types' => [ 'post', 'page' ],
		];

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( 1, $result['wpafi_show_image_column'] );
		$this->assertEquals( 80, $result['wpafi_column_size'] );
		$this->assertEquals( [ 'post', 'page' ], $result['wpafi_column_post_types'] );
	}

	/**
	 * Test sanitize_options clamps column size within valid range.
	 */
	public function test_sanitize_options_clamps_column_size(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		// Test minimum.
		$input  = [ 'wpafi_column_size' => '10' ];
		$result = $this->settings->sanitize_options( $input );
		$this->assertEquals( 30, $result['wpafi_column_size'] );

		// Test maximum.
		$input  = [ 'wpafi_column_size' => '200' ];
		$result = $this->settings->sanitize_options( $input );
		$this->assertEquals( 150, $result['wpafi_column_size'] );
	}

	/**
	 * Test sanitize_options handles rule with all fields.
	 */
	public function test_sanitize_options_sanitizes_full_rule(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );
		Functions\when( 'esc_url_raw' )->alias( function( $url ) {
			return filter_var( $url, FILTER_SANITIZE_URL );
		});

		$input = [
			'wpafi_rules' => [
				[
					'name'              => 'Test Rule',
					'image_source'      => 'external',
					'image_id'          => '123',
					'external_url'      => 'https://example.com/image.jpg',
					'include_video'     => '1',
					'sideload_external' => '1',
					'post_types'        => [ 'post', 'page' ],
					'categories'        => [ 'news' ],
					'tags'              => [ 'featured' ],
					'post_statuses'     => [ 'publish', 'draft' ],
					'overwrite'         => '1',
				],
			],
		];

		$result = $this->settings->sanitize_options( $input );

		$rule = $result['wpafi_rules'][0];
		$this->assertEquals( 'Test Rule', $rule['name'] );
		$this->assertEquals( 'external', $rule['image_source'] );
		$this->assertEquals( 123, $rule['image_id'] );
		$this->assertEquals( 'https://example.com/image.jpg', $rule['external_url'] );
		$this->assertEquals( 1, $rule['include_video'] );
		$this->assertEquals( 1, $rule['sideload_external'] );
		$this->assertEquals( [ 'post', 'page' ], $rule['post_types'] );
		$this->assertEquals( [ 'news' ], $rule['categories'] );
		$this->assertEquals( [ 'featured' ], $rule['tags'] );
		$this->assertEquals( [ 'publish', 'draft' ], $rule['post_statuses'] );
		$this->assertEquals( 1, $rule['overwrite'] );
	}

	/**
	 * Test sanitize_options validates image_source.
	 */
	public function test_sanitize_options_validates_image_source(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

		$input = [
			'wpafi_rules' => [
				[
					'image_source' => 'invalid_source',
					'image_id'     => 123,
				],
			],
		];

		$result = $this->settings->sanitize_options( $input );

		// Invalid source should default to 'media'.
		$this->assertEquals( 'media', $result['wpafi_rules'][0]['image_source'] );
	}

	/**
	 * Test sanitize_options accepts valid image sources.
	 */
	public function test_sanitize_options_accepts_valid_image_sources(): void {
		Functions\when( 'wpafi_has_pro_features' )->justReturn( true );
		Functions\when( 'esc_url_raw' )->alias( function( $url ) {
			return filter_var( $url, FILTER_SANITIZE_URL );
		});

		$valid_sources = [ 'media', 'first_image', 'external' ];

		foreach ( $valid_sources as $source ) {
			$input = [
				'wpafi_rules' => [
					[
						'image_source' => $source,
						'image_id'     => 123,
					],
				],
			];

			$result = $this->settings->sanitize_options( $input );

			$this->assertEquals( $source, $result['wpafi_rules'][0]['image_source'], "Failed for source: $source" );
		}
	}
}

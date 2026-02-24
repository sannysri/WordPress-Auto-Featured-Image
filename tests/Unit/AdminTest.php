<?php
/**
 * Unit tests for WPAFI Admin class.
 *
 * Tests individual methods without instantiating the full class.
 *
 * @package WPAFI\Tests\Unit
 */

namespace WPAFI\Tests\Unit;

use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Test the WPAFI_Admin class functionality.
 */
class AdminTest extends TestCase {

	/**
	 * @var \WPAFI_Admin|null
	 */
	private $admin;

	/**
	 * Set up test environment - create instance without constructor.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Include the class file without loading dependent files.
		if ( ! class_exists( '\WPAFI_Admin', false ) ) {
			// Mock all WordPress functions that might be called.
			Functions\when( 'add_action' )->justReturn( true );
			Functions\when( 'add_options_page' )->justReturn( true );
			Functions\when( 'plugin_dir_path' )->alias( function( $file ) {
				return dirname( $file ) . '/';
			});
			Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

			// Include necessary files first.
			require_once WPAFI_PLUGIN_DIR . 'admin/class-wpafi-settings.php';
			require_once WPAFI_PLUGIN_DIR . 'admin/class-wpafi-admin.php';
		}

		// Create instance without calling constructor (to avoid side effects).
		$reflection = new ReflectionClass( '\WPAFI_Admin' );
		$this->admin = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * Test that get_first_image_from_content returns false for empty content.
	 */
	public function test_get_first_image_from_content_returns_false_for_empty_content(): void {
		$mock_post                = new \stdClass();
		$mock_post->post_content  = '';
		$mock_post->ID            = 123;

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->admin->get_first_image_from_content( 123 );

		$this->assertFalse( $result );
	}

	/**
	 * Test that get_first_image_from_content extracts image from content.
	 */
	public function test_get_first_image_from_content_extracts_wp_image_class(): void {
		$mock_post                = new \stdClass();
		$mock_post->post_content  = '<p>Some text</p><img class="wp-image-456" src="test.jpg" />';
		$mock_post->ID            = 123;

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 0 );

		$result = $this->admin->get_first_image_from_content( 123 );

		$this->assertEquals( 456, $result );
	}

	/**
	 * Test is_post_meeting_criteria with matching post type.
	 */
	public function test_is_post_meeting_criteria_matches_post_type(): void {
		$options = [
			'wpafi_default_thumb_id' => 123,
			'wpafi_post_type'        => [ 'post', 'page' ],
		];

		Functions\when( 'get_post_type' )->justReturn( 'post' );

		$result = $this->admin->is_post_meeting_criteria( 123, $options );

		$this->assertTrue( $result );
	}

	/**
	 * Test is_post_meeting_criteria returns false for non-matching post type.
	 */
	public function test_is_post_meeting_criteria_fails_for_wrong_post_type(): void {
		$options = [
			'wpafi_default_thumb_id' => 123,
			'wpafi_post_type'        => [ 'post' ],
		];

		Functions\when( 'get_post_type' )->justReturn( 'product' );

		$result = $this->admin->is_post_meeting_criteria( 123, $options );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_image_from_rules returns correct image for matching category.
	 */
	public function test_get_image_from_rules_matches_category(): void {
		$rules = [
			[
				'categories' => [ 'news' ],
				'image_id'   => 789,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( true );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 789, $result );
	}

	/**
	 * Test get_image_from_rules returns false when no rules match.
	 */
	public function test_get_image_from_rules_returns_false_for_no_match(): void {
		$rules = [
			[
				'categories' => [ 'news' ],
				'image_id'   => 789,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( false );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_image_from_rules matches post type condition.
	 */
	public function test_get_image_from_rules_matches_post_type(): void {
		$rules = [
			[
				'post_types' => [ 'page' ],
				'image_id'   => 999,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'page';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 999, $result );
	}

	/**
	 * Test get_image_from_rules matches tags condition.
	 */
	public function test_get_image_from_rules_matches_tags(): void {
		$rules = [
			[
				'tags'     => [ 'featured' ],
				'image_id' => 555,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_tag' )->justReturn( true );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 555, $result );
	}

	/**
	 * Test get_image_from_rules matches post status condition.
	 */
	public function test_get_image_from_rules_matches_post_status(): void {
		$rules = [
			[
				'post_statuses' => [ 'draft' ],
				'image_id'      => 333,
			],
		];

		$mock_post              = new \stdClass();
		$mock_post->ID          = 123;
		$mock_post->post_type   = 'post';
		$mock_post->post_status = 'draft';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 333, $result );
	}

	/**
	 * Test get_image_from_rules with empty rules returns false.
	 */
	public function test_get_image_from_rules_empty_rules(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->admin->get_image_from_rules( 123, [] );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_image_from_rules with default rule (no conditions) matches all.
	 */
	public function test_get_image_from_rules_default_rule_matches_all(): void {
		$rules = [
			[
				'image_id' => 111,
				// No conditions = default rule.
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 111, $result );
	}

	/**
	 * Test get_first_image_from_content extracts image by URL.
	 */
	public function test_get_first_image_from_content_extracts_by_url(): void {
		$mock_post               = new \stdClass();
		$mock_post->post_content = '<img src="https://example.com/image.jpg" />';
		$mock_post->ID           = 123;

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 789 );

		$result = $this->admin->get_first_image_from_content( 123 );

		$this->assertEquals( 789, $result );
	}

	/**
	 * Test add_image_column adds wpafi_image column.
	 */
	public function test_add_image_column(): void {
		$columns = [
			'cb'    => '<input type="checkbox" />',
			'title' => 'Title',
			'date'  => 'Date',
		];

		$result = $this->admin->add_image_column( $columns );

		$this->assertArrayHasKey( 'wpafi_image', $result );
		// Verify the column was inserted.
		$this->assertCount( 4, $result );
	}

	/**
	 * Test is_post_meeting_criteria returns true for empty post type filter.
	 */
	public function test_is_post_meeting_criteria_empty_filter(): void {
		$options = [
			'wpafi_default_thumb_id' => 123,
			'wpafi_post_type'        => [],
		];

		Functions\when( 'get_post_type' )->justReturn( 'post' );

		$result = $this->admin->is_post_meeting_criteria( 123, $options );

		$this->assertTrue( $result );
	}

	/**
	 * Test is_post_meeting_criteria with category filter.
	 */
	public function test_is_post_meeting_criteria_with_category_filter(): void {
		$options = [
			'wpafi_default_thumb_id' => 123,
			'wpafi_post_type'        => [ 'post' ],
			'wpafi_categories'       => [ 'tech' ],
		];

		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'has_category' )->justReturn( true );
		Functions\when( 'in_category' )->justReturn( true );

		$result = $this->admin->is_post_meeting_criteria( 123, $options );

		$this->assertTrue( $result );
	}

	/**
	 * Test is_post_meeting_criteria fails with non-matching category.
	 */
	public function test_is_post_meeting_criteria_fails_category(): void {
		$options = [
			'wpafi_default_thumb_id' => 123,
			'wpafi_post_type'        => [ 'post' ],
			'wpafi_categories'       => [ 'tech' ],
		];

		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'has_category' )->justReturn( false );
		Functions\when( 'in_category' )->justReturn( false );

		$result = $this->admin->is_post_meeting_criteria( 123, $options );

		$this->assertFalse( $result );
	}
}

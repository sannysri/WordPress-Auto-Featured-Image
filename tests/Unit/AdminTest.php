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

	/**
	 * Test wpafi_set_thumbnail calls get_first_image_from_content with correct argument order:
	 * ( post_id, include_video, sideload ).
	 */
	public function test_wpafi_set_thumbnail_calls_get_first_image_with_correct_argument_order(): void {
		$post_id = 456;
		$rule = [
			'name'              => 'First Image Rule',
			'enabled'           => 1,
			'overwrite'         => 0,
			'image_source'      => 'first_image',
			'include_video'     => 1, // include_video is TRUE
			'sideload_external' => 0, // sideload is FALSE
			'post_types'        => [ 'post' ],
			'categories'        => [],
			'tags'              => [],
			'post_statuses'     => [],
		];

		$options = [
			'wpafi_rules' => [ $rule ],
		];

		$mock_post              = new \stdClass();
		$mock_post->ID          = $post_id;
		$mock_post->post_type   = 'post';
		$mock_post->post_status = 'publish';
		$mock_post->post_content = '<p>Hello world</p>';

		$GLOBALS['wp_test_options']['wpafi_options'] = $options;

		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_post_thumbnail' )->justReturn( false );

		$captured_args = [];
		// Subclass or mock admin method to spy on arguments.
		$admin_spy = $this->getMockBuilder( '\WPAFI_Admin' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_first_image_from_content' ] )
			->getMock();

		$admin_spy->expects( $this->once() )
			->method( 'get_first_image_from_content' )
			->with(
				$this->equalTo( $post_id ),
				$this->equalTo( true ),  // 2nd arg: include_video
				$this->equalTo( false )  // 3rd arg: sideload
			)
			->willReturn( 789 );

		Functions\expect( 'set_post_thumbnail' )
			->once()
			->with( $post_id, 789 )
			->andReturn( true );

		$admin_spy->wpafi_set_thumbnail( $post_id );
	}

	/**
	 * Test that legacy v2.0.3 options (without wpafi_rules) still assign default thumbnail.
	 */
	public function test_legacy_v203_options_fallback_assigns_default_thumbnail(): void {
		$post_id = 999;
		$legacy_options = [
			'wpafi_default_thumb_id' => 777,
			'wpafi_post_type'        => [ 'post' ],
			'wpafi_categories'       => [],
			'wpafi_tags'             => [],
			'wpafi_overwrite'        => 0,
		];

		$GLOBALS['wp_test_options']['wpafi_options'] = $legacy_options;

		$mock_post              = new \stdClass();
		$mock_post->ID          = $post_id;
		$mock_post->post_type   = 'post';
		$mock_post->post_status = 'publish';

		Functions\when( 'wpafi_has_pro_features' )->justReturn( false );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'has_post_thumbnail' )->justReturn( false );

		Functions\expect( 'set_post_thumbnail' )
			->once()
			->with( $post_id, 777 )
			->andReturn( true );

		$this->admin->wpafi_set_thumbnail( $post_id );
	}

	/**
	 * Test that get_bulk_targets includes taxonomy query when a rule specifies categories.
	 */
	public function test_get_bulk_targets_includes_tax_query_for_category_rule(): void {
		$options = [
			'wpafi_rules' => [
				[
					'name'          => 'News Rule',
					'enabled'       => 1,
					'post_types'    => [ 'post' ],
					'categories'    => [ 'news' ],
					'tags'          => [],
					'post_statuses' => [ 'publish' ],
					'image_source'  => 'media',
					'image_id'      => 123,
				],
			],
		];

		$reflection = new ReflectionClass( $this->admin );
		$method     = $reflection->getMethod( 'get_bulk_targets' );

		$result = $method->invoke( $this->admin, 0, $options );

		$this->assertArrayHasKey( 'query_args', $result );
		$this->assertArrayHasKey( 'tax_query', $result['query_args'] );
		$this->assertEquals( 'post', $result['query_args']['post_type'][0] );
		$this->assertEquals( 'category', $result['query_args']['tax_query'][0]['taxonomy'] );
		$this->assertEquals( 'slug', $result['query_args']['tax_query'][0]['field'] );
		$this->assertEquals( [ 'news' ], $result['query_args']['tax_query'][0]['terms'] );
	}

	/**
	 * Smoke test ajax_bulk_preview and ajax_bulk_assign on a category rule.
	 */
	public function test_ajax_bulk_preview_and_apply_on_category_rule(): void {
		$options = [
			'wpafi_rules' => [
				[
					'name'          => 'News Category Rule',
					'enabled'       => 1,
					'post_types'    => [ 'post' ],
					'categories'    => [ 'news' ],
					'tags'          => [],
					'post_statuses' => [ 'publish' ],
					'image_source'  => 'media',
					'image_id'      => 456,
					'overwrite'     => 0,
				],
			],
		];

		$GLOBALS['wp_test_options']['wpafi_options'] = $options;
		$_POST['ruleIdx']                            = '0';
		$_POST['nonce']                              = 'valid_nonce';

		$post_id                = 101;
		$mock_post              = new \stdClass();
		$mock_post->ID          = $post_id;
		$mock_post->post_title  = 'Breaking News Post';
		$mock_post->post_type   = 'post';
		$mock_post->post_status = 'publish';

		$GLOBALS['wp_test_query_posts']       = [ $post_id ];
		$GLOBALS['wp_test_query_found_posts'] = 1;

		$cat_obj       = new \stdClass();
		$cat_obj->slug = 'news';

		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'absint' )->alias( function( $val ) {
			return abs( intval( $val ) );
		} );
		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'has_post_thumbnail' )->justReturn( false );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/test.jpg' );
		Functions\when( 'has_category' )->justReturn( true );
		Functions\when( 'has_tag' )->justReturn( false );
		Functions\when( 'get_the_category' )->justReturn( [ $cat_obj ] );
		Functions\when( 'get_the_tags' )->justReturn( [] );

		$preview_response = null;
		Functions\when( 'wp_send_json_success' )->alias( function( $data ) use ( &$preview_response ) {
			$preview_response = $data;
		} );

		// 1. Smoke ajax_bulk_preview.
		$this->admin->ajax_bulk_preview();

		$this->assertNotNull( $preview_response );
		$this->assertCount( 1, $preview_response['rows'] );
		$this->assertEquals( 101, $preview_response['rows'][0]['id'] );
		$this->assertEquals( 'Breaking News Post', $preview_response['rows'][0]['title'] );
		$this->assertEquals( 'set', $preview_response['rows'][0]['action'] );
		$this->assertEquals( 'https://example.com/test.jpg', $preview_response['rows'][0]['proposed_thumb_url'] );

		// 2. Smoke ajax_bulk_assign.
		Functions\expect( 'set_post_thumbnail' )
			->once()
			->with( $post_id, 456 )
			->andReturn( true );

		$assign_response = null;
		Functions\when( 'wp_send_json_success' )->alias( function( $data ) use ( &$assign_response ) {
			$assign_response = $data;
		} );

		$this->admin->ajax_bulk_assign();

		$this->assertNotNull( $assign_response );
		$this->assertEquals( 1, $assign_response['updated'] );
		$this->assertEquals( 0, $assign_response['failed'] );
	}
}

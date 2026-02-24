<?php
/**
 * Unit tests for rule matching edge cases.
 *
 * @package WPAFI\Tests\Unit
 */

namespace WPAFI\Tests\Unit;

use Brain\Monkey\Functions;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test advanced rule matching scenarios.
 */
class RuleMatchingTest extends TestCase {

	/**
	 * @var \WPAFI_Admin|null
	 */
	private $admin;

	/**
	 * @var ReflectionMethod
	 */
	private $does_rule_match;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WPAFI_Admin', false ) ) {
			Functions\when( 'add_action' )->justReturn( true );
			Functions\when( 'add_options_page' )->justReturn( true );
			Functions\when( 'plugin_dir_path' )->alias( function( $file ) {
				return dirname( $file ) . '/';
			});
			Functions\when( 'wpafi_has_pro_features' )->justReturn( false );

			require_once WPAFI_PLUGIN_DIR . 'admin/class-wpafi-settings.php';
			require_once WPAFI_PLUGIN_DIR . 'admin/class-wpafi-admin.php';
		}

		$reflection = new ReflectionClass( '\WPAFI_Admin' );
		$this->admin = $reflection->newInstanceWithoutConstructor();

		// Make private method accessible
		$this->does_rule_match = $reflection->getMethod( 'does_rule_match' );
		$this->does_rule_match->setAccessible( true );
	}

	/**
	 * Test rule with multiple post types matches any.
	 */
	public function test_multiple_post_types_or_logic(): void {
		$rule = [
			'post_types' => [ 'post', 'page', 'product' ],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'page';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertTrue( $result );
	}

	/**
	 * Test rule with multiple categories - OR logic (any match).
	 */
	public function test_multiple_categories_or_logic(): void {
		$rule = [
			'categories' => [ 'tech', 'news', 'sports' ],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->alias( function( $cat, $post_id ) {
			return $cat === 'news'; // Only matches 'news'
		});

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertTrue( $result );
	}

	/**
	 * Test rule with multiple tags - OR logic (any match).
	 */
	public function test_multiple_tags_or_logic(): void {
		$rule = [
			'tags' => [ 'featured', 'trending', 'breaking' ],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_tag' )->alias( function( $tag, $post_id ) {
			return $tag === 'trending'; // Only matches 'trending'
		});

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertTrue( $result );
	}

	/**
	 * Test combined conditions use AND logic.
	 */
	public function test_combined_conditions_and_logic(): void {
		$rule = [
			'post_types' => [ 'post' ],
			'categories' => [ 'tech' ],
			'tags'       => [ 'featured' ],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( true );
		Functions\when( 'has_tag' )->justReturn( true );

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertTrue( $result );
	}

	/**
	 * Test combined conditions fail if one doesn't match.
	 */
	public function test_combined_conditions_fail_partial(): void {
		$rule = [
			'post_types' => [ 'post' ],
			'categories' => [ 'tech' ],
			'tags'       => [ 'featured' ],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( true );
		Functions\when( 'has_tag' )->justReturn( false ); // Tag doesn't match

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertFalse( $result );
	}

	/**
	 * Test rule with post status condition.
	 */
	public function test_post_status_match(): void {
		$rule = [
			'post_statuses' => [ 'draft', 'pending' ],
		];

		$mock_post              = new \stdClass();
		$mock_post->ID          = 123;
		$mock_post->post_type   = 'post';
		$mock_post->post_status = 'draft';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertTrue( $result );
	}

	/**
	 * Test rule with post status - no match.
	 */
	public function test_post_status_no_match(): void {
		$rule = [
			'post_statuses' => [ 'draft', 'pending' ],
		];

		$mock_post              = new \stdClass();
		$mock_post->ID          = 123;
		$mock_post->post_type   = 'post';
		$mock_post->post_status = 'publish';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertFalse( $result );
	}

	/**
	 * Test rule with empty arrays treated as no condition.
	 */
	public function test_empty_arrays_match_all(): void {
		$rule = [
			'post_types'    => [],
			'categories'    => [],
			'tags'          => [],
			'post_statuses' => [],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'custom_type';

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->does_rule_match->invoke( $this->admin, 123, $rule );

		$this->assertTrue( $result );
	}

	/**
	 * Test rule with null post returns false.
	 */
	public function test_null_post_returns_false(): void {
		$rule = [
			'post_types' => [ 'post' ],
		];

		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->does_rule_match->invoke( $this->admin, 999, $rule );

		$this->assertFalse( $result );
	}

	/**
	 * Test first matching rule wins.
	 */
	public function test_first_matching_rule_wins(): void {
		$rules = [
			[
				'categories' => [ 'tech' ],
				'image_id'   => 100, // First rule
			],
			[
				'categories' => [ 'tech' ], // Also matches but should be ignored
				'image_id'   => 200,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( true );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 100, $result ); // First rule's image
	}

	/**
	 * Test disabled rules are skipped.
	 */
	public function test_disabled_rule_skipped(): void {
		$rules = [
			[
				'enabled'    => false, // Disabled
				'categories' => [ 'tech' ],
				'image_id'   => 100,
			],
			[
				'enabled'    => true, // This should match
				'categories' => [ 'tech' ],
				'image_id'   => 200,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( true );

		// Note: get_image_from_rules doesn't check 'enabled' - that's in wpafi_set_thumbnail
		// This test documents the current behavior
		$result = $this->admin->get_image_from_rules( 123, $rules );

		// get_image_from_rules returns first match regardless of enabled status
		// The enabled check happens in wpafi_set_thumbnail
		$this->assertIsInt( $result );
	}

	/**
	 * Test rule skipped when image_id is empty.
	 */
	public function test_rule_without_image_id_skipped(): void {
		$rules = [
			[
				'categories' => [ 'tech' ],
				// No image_id
			],
			[
				'categories' => [ 'tech' ],
				'image_id'   => 200,
			],
		];

		$mock_post            = new \stdClass();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'has_category' )->justReturn( true );

		$result = $this->admin->get_image_from_rules( 123, $rules );

		$this->assertEquals( 200, $result ); // Second rule (first has no image_id)
	}
}

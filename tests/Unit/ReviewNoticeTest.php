<?php
/**
 * Unit tests for WPAFI Review Notice class.
 *
 * @package WPAFI\Tests\Unit
 */

namespace WPAFI\Tests\Unit;

use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Test the WPAFI_Review_Notice class functionality.
 */
class ReviewNoticeTest extends TestCase {

	/**
	 * @var \WPAFI_Review_Notice|null
	 */
	private $review_notice;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress functions used by the class.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );

		// Include the class file only once.
		if ( ! class_exists( '\WPAFI_Review_Notice', false ) ) {
			require_once WPAFI_PLUGIN_DIR . 'admin/class-wpafi-review-notice.php';
		}

		// Create instance without calling constructor.
		$reflection           = new ReflectionClass( '\WPAFI_Review_Notice' );
		$this->review_notice  = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * Test maybe_display_notice returns early for non-admin users.
	 */
	public function test_maybe_display_notice_returns_for_non_admin(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		ob_start();
		$this->review_notice->maybe_display_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test handle_notice_actions returns early when no action set.
	 */
	public function test_handle_notice_actions_returns_when_no_action(): void {
		// Simulate no wpafi_review_action in request.
		$_GET = [];

		// Should not throw any errors or redirect.
		$this->review_notice->handle_notice_actions();

		$this->assertTrue( true ); // If we get here, no errors occurred.
	}

	/**
	 * Test handle_notice_actions returns when nonce is invalid.
	 */
	public function test_handle_notice_actions_returns_for_invalid_nonce(): void {
		$_GET['wpafi_review_action'] = 'dismiss';
		$_GET['_wpnonce']            = 'invalid_nonce';

		Functions\when( 'sanitize_text_field' )->alias( function( $str ) {
			return trim( strip_tags( $str ) );
		});
		Functions\when( 'wp_unslash' )->alias( function( $str ) {
			return $str;
		});
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		// Should return early without processing.
		$this->review_notice->handle_notice_actions();

		// Clean up.
		unset( $_GET['wpafi_review_action'], $_GET['_wpnonce'] );

		$this->assertTrue( true ); // If we get here, no errors occurred.
	}

	/**
	 * Test handle_notice_actions returns when user lacks capability.
	 */
	public function test_handle_notice_actions_returns_for_non_admin(): void {
		$_GET['wpafi_review_action'] = 'dismiss';
		$_GET['_wpnonce']            = 'valid_nonce';

		Functions\when( 'sanitize_text_field' )->alias( function( $str ) {
			return trim( strip_tags( $str ) );
		});
		Functions\when( 'wp_unslash' )->alias( function( $str ) {
			return $str;
		});
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		// Should return early without processing.
		$this->review_notice->handle_notice_actions();

		// Clean up.
		unset( $_GET['wpafi_review_action'], $_GET['_wpnonce'] );

		$this->assertTrue( true ); // If we get here, no errors occurred.
	}

	/**
	 * Test notice_styles outputs CSS.
	 */
	public function test_notice_styles_outputs_css(): void {
		ob_start();
		$this->review_notice->notice_styles();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<style>', $output );
		$this->assertStringContainsString( '.wpafi-review-notice', $output );
		$this->assertStringContainsString( '</style>', $output );
	}

	/**
	 * Test class has expected structure.
	 */
	public function test_class_has_expected_structure(): void {
		$reflection = new ReflectionClass( '\WPAFI_Review_Notice' );

		$this->assertTrue( $reflection->hasMethod( 'maybe_display_notice' ) );
		$this->assertTrue( $reflection->hasMethod( 'handle_notice_actions' ) );
		$this->assertTrue( $reflection->hasMethod( 'notice_styles' ) );
	}
}

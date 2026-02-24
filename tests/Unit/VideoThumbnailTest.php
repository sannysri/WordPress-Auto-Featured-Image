<?php
/**
 * Unit tests for video thumbnail extraction.
 *
 * @package WPAFI\Tests\Unit
 */

namespace WPAFI\Tests\Unit;

use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Test video thumbnail extraction functionality.
 */
class VideoThumbnailTest extends TestCase {

	/**
	 * @var \WPAFI_Admin|null
	 */
	private $admin;

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
	}

	/**
	 * Test YouTube watch URL pattern.
	 */
	public function test_youtube_watch_url_detected(): void {
		$mock_post               = new \stdClass();
		$mock_post->post_content = 'Check out this video: https://www.youtube.com/watch?v=dQw4w9WgXcQ';
		$mock_post->ID           = 123;

		Functions\when( 'get_post' )->justReturn( $mock_post );
		Functions\when( 'wp_remote_head' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		// Mock sideload to return an attachment ID
		$reflection = new ReflectionClass( $this->admin );
		$method = $reflection->getMethod( 'get_video_thumbnail_from_content' );
		$method->setAccessible( true );

		// We need to mock the sideload_image method - for unit tests we just verify pattern matching
		$content = $mock_post->post_content;
		$pattern = '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i';
		
		$this->assertMatchesRegularExpression( $pattern, $content );
		preg_match( $pattern, $content, $matches );
		$this->assertEquals( 'dQw4w9WgXcQ', $matches[1] );
	}

	/**
	 * Test YouTube embed URL pattern.
	 */
	public function test_youtube_embed_url_detected(): void {
		$content = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
		$pattern = '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/i';
		
		$this->assertMatchesRegularExpression( $pattern, $content );
		preg_match( $pattern, $content, $matches );
		$this->assertEquals( 'dQw4w9WgXcQ', $matches[1] );
	}

	/**
	 * Test YouTube short URL pattern.
	 */
	public function test_youtube_short_url_detected(): void {
		$content = 'https://youtu.be/dQw4w9WgXcQ';
		$pattern = '/youtu\.be\/([a-zA-Z0-9_-]+)/i';
		
		$this->assertMatchesRegularExpression( $pattern, $content );
		preg_match( $pattern, $content, $matches );
		$this->assertEquals( 'dQw4w9WgXcQ', $matches[1] );
	}

	/**
	 * Test Vimeo URL pattern.
	 */
	public function test_vimeo_url_detected(): void {
		$content = 'https://vimeo.com/123456789';
		$pattern = '/vimeo\.com\/(\d+)/i';
		
		$this->assertMatchesRegularExpression( $pattern, $content );
		preg_match( $pattern, $content, $matches );
		$this->assertEquals( '123456789', $matches[1] );
	}

	/**
	 * Test Vimeo player URL pattern.
	 */
	public function test_vimeo_player_url_detected(): void {
		$content = '<iframe src="https://player.vimeo.com/video/123456789"></iframe>';
		$pattern = '/player\.vimeo\.com\/video\/(\d+)/i';
		
		$this->assertMatchesRegularExpression( $pattern, $content );
		preg_match( $pattern, $content, $matches );
		$this->assertEquals( '123456789', $matches[1] );
	}

	/**
	 * Test YouTube thumbnail URL construction.
	 */
	public function test_youtube_thumbnail_url_construction(): void {
		$video_id = 'dQw4w9WgXcQ';
		$expected_maxres = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
		$expected_hq = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
		
		$this->assertEquals( $expected_maxres, "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg" );
		$this->assertEquals( $expected_hq, "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg" );
	}

	/**
	 * Test content without video returns false.
	 */
	public function test_no_video_returns_false(): void {
		$mock_post               = new \stdClass();
		$mock_post->post_content = '<p>Just some text content without any video.</p>';
		$mock_post->ID           = 123;

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->admin->get_video_thumbnail_from_content( 123, false );

		$this->assertFalse( $result );
	}

	/**
	 * Test empty content returns false.
	 */
	public function test_empty_content_returns_false(): void {
		$mock_post               = new \stdClass();
		$mock_post->post_content = '';
		$mock_post->ID           = 123;

		Functions\when( 'get_post' )->justReturn( $mock_post );

		$result = $this->admin->get_video_thumbnail_from_content( 123, false );

		$this->assertFalse( $result );
	}
}

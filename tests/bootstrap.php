<?php
/**
 * PHPUnit Bootstrap for Auto Featured Image Plugin.
 *
 * @package WPAFI\Tests
 */

// IMPORTANT: Patchwork must be loaded FIRST, before any functions are defined.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use Brain\Monkey;

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WPAFI_TESTING' ) ) {
	define( 'WPAFI_TESTING', true );
}

if ( ! defined( 'WPAFI_VERSION' ) ) {
	define( 'WPAFI_VERSION', '2.1.0' );
}

if ( ! defined( 'WPAFI_PLUGIN_DIR' ) ) {
	define( 'WPAFI_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WPAFI_PLUGIN_URL' ) ) {
	define( 'WPAFI_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-auto-featured-image' );
}

// Mock common WordPress functions that may be called during coverage generation.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$result = ( $checked === $current ) ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		$result = ( $selected === $current ) ? ' selected="selected"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'get_categories' ) ) {
	function get_categories( $args = array() ) {
		return array();
	}
}

if ( ! function_exists( 'get_tags' ) ) {
	function get_tags( $args = array() ) {
		return array();
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = array(), $output = 'names' ) {
		return array();
	}
}

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail' ) {
		return '<img src="placeholder.jpg" />';
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $option_group ) {
		// No-op for testing.
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		if ( $echo ) {
			echo '<input type="hidden" name="' . $name . '" value="test_nonce" />';
		}
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {
		return;
	}
}

// Note: Plugin classes are loaded in individual test files after Brain Monkey is set up.
// This allows proper mocking of WordPress functions called in constructors.

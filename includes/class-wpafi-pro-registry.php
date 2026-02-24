<?php
/**
 * Pro Registry Client
 *
 * Fetches plugin info from sanny.dev API to control Pro feature teasers.
 * Uses transient caching to minimize API requests.
 *
 * @package WPAFI
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPAFI_Pro_Registry
 *
 * Client for the Sanny Plugin Registry API.
 * Controls whether Pro teasers are shown based on API response.
 */
class WPAFI_Pro_Registry {

	/**
	 * Singleton instance
	 *
	 * @var WPAFI_Pro_Registry|null
	 */
	private static $instance = null;

	/**
	 * API base URL
	 */
	const API_URL = 'https://sanny.dev/wp-json/sanny/v1/plugin-registry/';

	/**
	 * Plugin slug for API requests
	 */
	const PLUGIN_SLUG = 'auto-featured-image-pro';

	/**
	 * Transient key for caching
	 */
	const TRANSIENT_KEY = 'wpafi_pro_registry';

	/**
	 * Cache duration in seconds (24 hours)
	 */
	const CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Cached registry data
	 *
	 * @var array|null
	 */
	private $data = null;

	/**
	 * Get singleton instance
	 *
	 * @return WPAFI_Pro_Registry
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		// Load cached data on initialization.
		$this->load_data();
	}

	/**
	 * Get API URL (supports constant/filter override for local dev)
	 *
	 * @return string
	 */
	private function get_api_url() {
		// Allow constant override for local development.
		if ( defined( 'WPAFI_REGISTRY_API_URL' ) ) {
			return trailingslashit( WPAFI_REGISTRY_API_URL );
		}

		// Allow filter override.
		return apply_filters( 'wpafi_registry_api_url', self::API_URL );
	}

	/**
	 * Check if caching should be used
	 *
	 * Caching is disabled when custom API URL is defined (local dev).
	 *
	 * @return bool
	 */
	private function use_cache() {
		return ! defined( 'WPAFI_REGISTRY_API_URL' );
	}

	/**
	 * Load data from transient or fetch from API
	 */
	private function load_data() {
		// Only fetch in admin context.
		if ( ! is_admin() ) {
			return;
		}

		// Try to get cached data (skip if custom API URL defined).
		if ( $this->use_cache() ) {
			$cached = get_transient( self::TRANSIENT_KEY );

			if ( false !== $cached && is_array( $cached ) ) {
				$this->data = $cached;
				return;
			}
		}

		// Fetch from API.
		$this->fetch_from_api();
	}

	/**
	 * Fetch registry data from API
	 */
	private function fetch_from_api() {
		$args = array(
			'timeout' => 5,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		// Disable SSL verification for local development with custom API URL.
		if ( defined( 'WPAFI_REGISTRY_API_URL' ) ) {
			$args['sslverify'] = false;
		}

		$response = wp_remote_get(
			$this->get_api_url() . self::PLUGIN_SLUG,
			$args
		);

		// Handle errors gracefully.
		if ( is_wp_error( $response ) ) {
			// Use fallback data.
			$this->data = $this->get_fallback_data();
			// Cache the fallback for 1 hour to avoid repeated failed requests.
			if ( $this->use_cache() ) {
				set_transient( self::TRANSIENT_KEY, $this->data, HOUR_IN_SECONDS );
			}
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code || empty( $body ) ) {
			$this->data = $this->get_fallback_data();
			if ( $this->use_cache() ) {
				set_transient( self::TRANSIENT_KEY, $this->data, HOUR_IN_SECONDS );
			}
			return;
		}

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			$this->data = $this->get_fallback_data();
			if ( $this->use_cache() ) {
				set_transient( self::TRANSIENT_KEY, $this->data, HOUR_IN_SECONDS );
			}
			return;
		}

		// Store and cache successful response.
		$this->data = $decoded;
		if ( $this->use_cache() ) {
			set_transient( self::TRANSIENT_KEY, $this->data, self::CACHE_DURATION );
		}
	}

	/**
	 * Get fallback data when API is unavailable
	 *
	 * @return array
	 */
	private function get_fallback_data() {
		return array(
			'slug'            => self::PLUGIN_SLUG,
			'name'            => 'Auto Featured Image Pro',
			'pro_available'   => false, // Default to hidden when API unavailable.
			'version'         => '1.0.0',
			'price_text'      => '',
			'features'        => array(
				array(
					'icon'        => 'dashicons-images-alt2',
					'title'       => 'AI Image Generation',
					'description' => 'Generate images automatically',
				),
				array(
					'icon'        => 'dashicons-cart',
					'title'       => 'WooCommerce Support',
					'description' => 'Products and variations',
				),
				array(
					'icon'        => 'dashicons-undo',
					'title'       => 'Bulk Undo',
					'description' => 'Revert changes easily',
				),
				array(
					'icon'        => 'dashicons-list-view',
					'title'       => 'Unlimited Rules',
					'description' => 'No restrictions',
				),
				array(
					'icon'        => 'dashicons-email',
					'title'       => 'Priority Support',
					'description' => 'Fast email support',
				),
			),
			'upgrade_url'     => 'https://sanny.dev/plugins/auto-featured-image-pro/',
			'is_fallback'     => true,
			// Offer fields - default to inactive.
			'offer_active'    => false,
			'offer_type'      => '',
			'offer_badge'     => '',
			'offer_title'     => '',
			'offer_message'   => '',
			'offer_cta_text'  => '',
			'offer_cta_url'   => '',
			'offer_countdown' => '',
			'offer_remaining' => 0,
		);
	}

	/**
	 * Check if Pro is available (teasers should be shown)
	 *
	 * @return bool
	 */
	public function is_pro_available() {
		if ( null === $this->data ) {
			return false;
		}

		return ! empty( $this->data['pro_available'] );
	}

	/**
	 * Get all registry data
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data ?? $this->get_fallback_data();
	}

	/**
	 * Get Pro features list
	 *
	 * @return array
	 */
	public function get_features() {
		$data = $this->get_data();
		return $data['features'] ?? array();
	}

	/**
	 * Get price text
	 *
	 * @return string
	 */
	public function get_price_text() {
		$data = $this->get_data();
		return $data['price_text'] ?? '';
	}

	/**
	 * Get upgrade URL with UTM parameters
	 *
	 * @param string $source UTM source identifier.
	 * @param string $medium UTM medium (default: plugin).
	 * @return string
	 */
	public function get_upgrade_url( $source = 'settings', $medium = 'plugin' ) {
		$data     = $this->get_data();
		$base_url = $data['upgrade_url'] ?? 'https://sanny.dev/plugins/auto-featured-image-pro/';

		return add_query_arg(
			array(
				'utm_source'   => sanitize_key( $source ),
				'utm_medium'   => sanitize_key( $medium ),
				'utm_campaign' => 'upsell',
			),
			$base_url
		);
	}

	/**
	 * Check if an offer is currently active
	 *
	 * @return bool
	 */
	public function has_active_offer() {
		$data = $this->get_data();

		// Check if offer is marked active.
		if ( empty( $data['offer_active'] ) ) {
			return false;
		}

		// Check if countdown has passed.
		if ( ! empty( $data['offer_countdown'] ) ) {
			$end_time = strtotime( $data['offer_countdown'] );
			if ( $end_time && $end_time < time() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get offer data
	 *
	 * @return array|null Offer data or null if no active offer.
	 */
	public function get_offer() {
		if ( ! $this->has_active_offer() ) {
			return null;
		}

		$data = $this->get_data();

		return array(
			'type'      => $data['offer_type'] ?? 'limited',
			'badge'     => $data['offer_badge'] ?? '',
			'title'     => $data['offer_title'] ?? '',
			'message'   => $data['offer_message'] ?? '',
			'cta_text'  => $data['offer_cta_text'] ?? __( 'Claim Offer', 'sny-auto-featured-image' ),
			'cta_url'   => $data['offer_cta_url'] ?? $this->get_upgrade_url( 'offer' ),
			'countdown' => $data['offer_countdown'] ?? '',
			'remaining' => absint( $data['offer_remaining'] ?? 0 ),
		);
	}

	/**
	 * Get offer CTA URL with UTM tracking
	 *
	 * @return string
	 */
	public function get_offer_url() {
		$data = $this->get_data();

		// Use custom offer URL if set, otherwise fall back to upgrade URL.
		$base_url = ! empty( $data['offer_cta_url'] )
			? $data['offer_cta_url']
			: ( $data['upgrade_url'] ?? 'https://sanny.dev/plugins/auto-featured-image-pro/' );

		return add_query_arg(
			array(
				'utm_source'   => 'offer-banner',
				'utm_medium'   => 'plugin',
				'utm_campaign' => sanitize_key( $data['offer_type'] ?? 'limited' ) . '-offer',
			),
			$base_url
		);
	}

	/**
	 * Clear cached data (for testing or manual refresh)
	 */
	public function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
		$this->data = null;
	}

	/**
	 * Force refresh data from API
	 */
	public function refresh() {
		$this->clear_cache();
		$this->fetch_from_api();
	}
}

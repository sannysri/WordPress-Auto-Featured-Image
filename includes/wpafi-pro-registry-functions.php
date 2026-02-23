<?php
/**
 * Pro Registry Helper Functions
 *
 * Wrapper functions for the WPAFI_Pro_Registry class.
 * These provide a simpler interface for checking Pro availability and features.
 *
 * @package WPAFI
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if Pro teasers should be shown
 *
 * Returns false if:
 * - Pro plugin is already active
 * - API says pro_available is false
 *
 * @return bool
 */
function wpafi_should_show_pro_teasers() {
	// Don't show teasers if Pro is already installed.
	if ( function_exists( 'wpafi_is_pro_active' ) && wpafi_is_pro_active() ) {
		return false;
	}

	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->is_pro_available();
}

/**
 * Get Pro features list
 *
 * @return array
 */
function wpafi_get_pro_features() {
	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->get_features();
}

/**
 * Get upgrade URL with UTM tracking
 *
 * @param string $source UTM source (e.g., 'sidebar', 'rules-footer').
 * @return string
 */
function wpafi_get_upgrade_url( $source = 'settings' ) {
	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->get_upgrade_url( $source );
}

/**
 * Get price text
 *
 * @return string
 */
function wpafi_get_pro_price_text() {
	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->get_price_text();
}

/**
 * Check if there's an active promotional offer
 *
 * @return bool
 */
function wpafi_has_active_offer() {
	// Don't show offers if Pro is already installed.
	if ( function_exists( 'wpafi_is_pro_active' ) && wpafi_is_pro_active() ) {
		return false;
	}

	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->has_active_offer();
}

/**
 * Get current offer data
 *
 * @return array|null Offer data array or null if no active offer.
 */
function wpafi_get_offer() {
	// Don't show offers if Pro is already installed.
	if ( function_exists( 'wpafi_is_pro_active' ) && wpafi_is_pro_active() ) {
		return null;
	}

	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->get_offer();
}

/**
 * Get offer CTA URL with tracking
 *
 * @return string
 */
function wpafi_get_offer_url() {
	$registry = WPAFI_Pro_Registry::get_instance();
	return $registry->get_offer_url();
}

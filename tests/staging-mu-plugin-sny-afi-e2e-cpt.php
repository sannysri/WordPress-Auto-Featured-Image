<?php
/**
 * Staging-only CPT for SNY Auto Featured Image E2E.
 * Safe to delete after testing. Prefix: sny_e2e.
 *
 * @package WP_Auto_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		register_post_type(
			'sny_e2e_item',
			array(
				'labels'       => array(
					'name'          => 'E2E Items',
					'singular_name' => 'E2E Item',
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-format-image',
			)
		);
	}
);

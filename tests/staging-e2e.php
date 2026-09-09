<?php
/**
 * Staging E2E for SNY Auto Featured Image 2.1.0.
 *
 * Run:
 *   wp eval-file tests/staging-e2e.php --user=test_tester --path=/path/to/wordpress
 *
 * Creates [AFI-E2E] fixtures, asserts assignment paths, leaves a handful of
 * unpublished thumbs for browser Preview/Apply.
 *
 * @package WP_Auto_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['e2e_pass']  = 0;
$GLOBALS['e2e_fail']  = 0;
$GLOBALS['e2e_notes'] = array();

function e2e_ok( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['e2e_pass'];
		echo "[PASS] $msg\n";
		return true;
	}
	++$GLOBALS['e2e_fail'];
	echo "[FAIL] $msg\n";
	return false;
}

function e2e_note( $msg ) {
	$GLOBALS['e2e_notes'][] = $msg;
	echo "[NOTE] $msg\n";
}

function e2e_admin() {
	if ( isset( $GLOBALS['wp_filter']['save_post'] ) ) {
		foreach ( $GLOBALS['wp_filter']['save_post']->callbacks as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				if ( isset( $cb['function'][0] ) && $cb['function'][0] instanceof WPAFI_Admin ) {
					return $cb['function'][0];
				}
			}
		}
	}
	echo "[FAIL] WPAFI_Admin instance missing from save_post hooks\n";
	exit( 1 );
}

function e2e_term( $name, $slug, $tax = 'category' ) {
	$existing = get_term_by( 'slug', $slug, $tax );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) $existing->term_id;
	}
	$r = wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
	if ( is_wp_error( $r ) ) {
		echo '[FAIL] term ' . $slug . ': ' . $r->get_error_message() . "\n";
		exit( 1 );
	}
	return (int) $r['term_id'];
}

function e2e_png( $path, $r, $g, $b ) {
	$im  = imagecreatetruecolor( 160, 90 );
	$col = imagecolorallocate( $im, $r, $g, $b );
	imagefill( $im, 0, 0, $col );
	$w = imagecolorallocate( $im, 255, 255, 255 );
	imagestring( $im, 5, 18, 36, 'AFI E2E', $w );
	imagepng( $im, $path );
	imagedestroy( $im );
}

function e2e_import_png( $label, $r, $g, $b ) {
	$path = sys_get_temp_dir() . '/sny-afi-e2e-' . $label . '.png';
	e2e_png( $path, $r, $g, $b );
	$sideload = array(
		'name'     => 'sny-afi-e2e-' . $label . '.png',
		'type'     => 'image/png',
		'tmp_name' => $path,
		'error'    => 0,
		'size'     => filesize( $path ),
	);
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload( $sideload, 0, 'AFI E2E ' . $label );
	if ( is_wp_error( $id ) ) {
		echo '[FAIL] import ' . $label . ': ' . $id->get_error_message() . "\n";
		exit( 1 );
	}
	update_post_meta( $id, '_sny_afi_e2e', '1' );
	return (int) $id;
}

function e2e_insert( $args ) {
	$defaults = array(
		'post_status' => 'publish',
		'post_type'   => 'post',
		'meta_input'  => array( '_sny_afi_e2e' => '1' ),
	);
	$id       = wp_insert_post( array_merge( $defaults, $args ), true );
	if ( is_wp_error( $id ) ) {
		echo '[FAIL] insert: ' . $id->get_error_message() . "\n";
		exit( 1 );
	}
	return (int) $id;
}

function e2e_options( $rules, $extra = array() ) {
	$base = array(
		'wpafi_rules'             => $rules,
		'wpafi_show_image_column' => 1,
		'wpafi_column_post_types' => array( 'post', 'page', 'sny_e2e_item' ),
		'wpafi_column_size'       => 60,
		'wpafi_auto_detect'       => 0,
		'wpafi_overwrite'         => 0,
	);
	update_option( 'wpafi_options', array_merge( $base, $extra ) );
}

function e2e_media_rule( $name, $image_id, $conds = array() ) {
	return array_merge(
		array(
			'name'              => $name,
			'enabled'           => 1,
			'image_source'      => 'media',
			'image_id'          => (int) $image_id,
			'external_url'      => '',
			'include_video'     => 0,
			'sideload_external' => 0,
			'post_types'        => array(),
			'categories'        => array(),
			'tags'              => array(),
			'post_statuses'     => array(),
			'overwrite'         => 0,
			'collapsed'         => 0,
		),
		$conds
	);
}

function e2e_first_rule( $name, $conds = array() ) {
	return array_merge(
		array(
			'name'              => $name,
			'enabled'           => 1,
			'image_source'      => 'first_image',
			'image_id'          => 0,
			'external_url'      => '',
			'include_video'     => 1,
			'sideload_external' => 1,
			'post_types'        => array(),
			'categories'        => array(),
			'tags'              => array(),
			'post_statuses'     => array(),
			'overwrite'         => 0,
			'collapsed'         => 0,
		),
		$conds
	);
}

function e2e_external_rule( $name, $url, $conds = array() ) {
	return array_merge(
		array(
			'name'              => $name,
			'enabled'           => 1,
			'image_source'      => 'external',
			'image_id'          => 0,
			'external_url'      => $url,
			'include_video'     => 0,
			'sideload_external' => 1,
			'post_types'        => array(),
			'categories'        => array(),
			'tags'              => array(),
			'post_statuses'     => array(),
			'overwrite'         => 0,
			'collapsed'         => 0,
		),
		$conds
	);
}

echo "=== SNY AFI staging E2E ===\n";
echo 'Site: ' . home_url() . "\n";
echo 'Plugin: ' . ( defined( 'WPAFI_VERSION' ) ? WPAFI_VERSION : 'missing' ) . "\n";

$old = get_posts(
	array(
		'post_type'      => array( 'post', 'page', 'sny_e2e_item', 'attachment' ),
		'post_status'    => array( 'publish', 'draft', 'inherit', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_sny_afi_e2e',
		'meta_value'     => '1',
	)
);
foreach ( $old as $old_id ) {
	wp_delete_post( (int) $old_id, true );
}
echo 'Cleaned ' . count( $old ) . " previous E2E posts/attachments\n";

e2e_ok( defined( 'WPAFI_VERSION' ) && '2.1.0' === WPAFI_VERSION, 'Plugin version is 2.1.0' );
e2e_ok( class_exists( 'WPAFI_Admin' ), 'WPAFI_Admin loaded' );
e2e_ok( ! defined( 'SNY_AFI_PRO_ACTIVE' ), 'Pro constant not defined (Free path)' );

$admin = e2e_admin();

$cat_news = e2e_term( 'AFI E2E News', 'afi-e2e-news' );
$cat_tech = e2e_term( 'AFI E2E Tech', 'afi-e2e-tech' );
$cat_bulk = e2e_term( 'AFI E2E Bulk', 'afi-e2e-bulk' );
$tag_id   = e2e_term( 'AFI E2E Tag', 'afi-e2e-tag', 'post_tag' );
e2e_ok( $cat_news && $cat_tech && $cat_bulk && $tag_id, 'Terms created' );

$img_news = e2e_import_png( 'news', 92, 44, 168 );
$img_tech = e2e_import_png( 'tech', 14, 116, 187 );
e2e_ok( $img_news && $img_tech, "Imported media $img_news / $img_tech" );
$img_news_url = wp_get_attachment_url( $img_news );
$img_tech_url = wp_get_attachment_url( $img_tech );

// Pause auto-assign while seeding.
e2e_options( array() );

$yt_id  = 'dQw4w9WgXcQ';
$yt_url = 'https://img.youtube.com/vi/' . $yt_id . '/hqdefault.jpg';

$ids = array();

$ids['news'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] News media rule',
		'post_content'  => 'News post with no content image.',
		'post_category' => array( $cat_news ),
	)
);

$ids['news_browser'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] News for browser apply',
		'post_content'  => 'Left empty for admin Preview/Apply.',
		'post_category' => array( $cat_news ),
	)
);

$ids['tech_img'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] First image in content',
		'post_content'  => '<p>Before.</p><img src="' . esc_url( $img_tech_url ) . '" class="wp-image-' . $img_tech . '" alt="" /><p>After.</p>',
		'post_category' => array( $cat_tech ),
	)
);

$ids['tech_browser'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] First image for browser apply',
		'post_content'  => '<p>Browser bulk.</p><img src="' . esc_url( $img_tech_url ) . '" class="wp-image-' . $img_tech . '" alt="" />',
		'post_category' => array( $cat_tech ),
	)
);

$ids['gutenberg'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] Gutenberg wp-image class',
		'post_content'  => '<!-- wp:image {"id":' . $img_tech . '} --><figure class="wp-block-image"><img src="' . esc_url( $img_tech_url ) . '" class="wp-image-' . $img_tech . '"/></figure><!-- /wp:image -->',
		'post_category' => array( $cat_tech ),
	)
);

$ids['youtube'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] YouTube embed',
		'post_content'  => '<p>Watch:</p><iframe src="https://www.youtube.com/embed/' . $yt_id . '" width="560" height="315"></iframe>',
		'post_category' => array( $cat_tech ),
	)
);

$ids['youtube_browser'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] YouTube for browser apply',
		'post_content'  => 'https://youtu.be/' . $yt_id,
		'post_category' => array( $cat_tech ),
	)
);

$ids['tagged'] = e2e_insert(
	array(
		'post_title'   => '[AFI-E2E] Tag match',
		'post_content' => 'Tagged only.',
		'tags_input'   => array( 'afi-e2e-tag' ),
	)
);

$ids['overwrite'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] Already has thumb',
		'post_content'  => 'Should skip unless overwrite.',
		'post_category' => array( $cat_news ),
	)
);
set_post_thumbnail( $ids['overwrite'], $img_tech );

$ids['draft'] = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] Draft status',
		'post_content'  => '<img src="' . esc_url( $img_tech_url ) . '" class="wp-image-' . $img_tech . '" />',
		'post_status'   => 'draft',
		'post_category' => array( $cat_tech ),
	)
);

$ids['page'] = e2e_insert(
	array(
		'post_type'    => 'page',
		'post_title'   => '[AFI-E2E] Page first image',
		'post_content' => '<img src="' . esc_url( $img_news_url ) . '" class="wp-image-' . $img_news . '" />',
	)
);

$ids['cpt'] = e2e_insert(
	array(
		'post_type'    => 'sny_e2e_item',
		'post_title'   => '[AFI-E2E] CPT first image',
		'post_content' => '<img src="' . esc_url( $img_tech_url ) . '" class="wp-image-' . $img_tech . '" />',
	)
);

$ids['cpt_browser'] = e2e_insert(
	array(
		'post_type'    => 'sny_e2e_item',
		'post_title'   => '[AFI-E2E] CPT for browser apply',
		'post_content' => '<img src="' . esc_url( $img_news_url ) . '" class="wp-image-' . $img_news . '" />',
	)
);

$ids['external'] = e2e_insert(
	array(
		'post_title'   => '[AFI-E2E] External URL rule',
		'post_content' => 'No inline image; rule provides URL.',
	)
);

$ids['no_match'] = e2e_insert(
	array(
		'post_title'   => '[AFI-E2E] No image no category',
		'post_content' => 'Should skip first-image if no img/video.',
	)
);

e2e_ok( ! has_post_thumbnail( $ids['news'] ), 'Seeded news post has no thumb' );
e2e_ok( (int) get_post_thumbnail_id( $ids['overwrite'] ) === $img_tech, 'Overwrite fixture has existing thumb' );

// --- save_post: media + first_image ---
e2e_options(
	array(
		e2e_media_rule(
			'News media',
			$img_news,
			array(
				'post_types' => array( 'post' ),
				'categories' => array( 'afi-e2e-news' ),
			)
		),
		e2e_first_rule(
			'First image/video',
			array(
				'post_types' => array( 'post', 'page', 'sny_e2e_item' ),
			)
		),
	)
);

$admin->wpafi_set_thumbnail( $ids['news'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['news'] ) === $img_news, 'Media rule sets news category thumb' );

$admin->wpafi_set_thumbnail( $ids['tech_img'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['tech_img'] ) === $img_tech, 'First image uses wp-image class / URL' );

$admin->wpafi_set_thumbnail( $ids['gutenberg'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['gutenberg'] ) === $img_tech, 'Gutenberg wp-image-{id} resolves' );

$before_yt = (int) get_post_thumbnail_id( $ids['youtube'] );
$admin->wpafi_set_thumbnail( $ids['youtube'] );
$after_yt = (int) get_post_thumbnail_id( $ids['youtube'] );
if ( $after_yt ) {
	e2e_ok( $after_yt !== $before_yt || $after_yt > 0, "YouTube thumbnail sideloaded (attachment $after_yt)" );
} else {
	e2e_ok( false, 'YouTube thumbnail sideload (outbound fetch failed)' );
	e2e_note( 'Hostinger may block img.youtube.com. Browser apply may also fail for video thumbs.' );
}

$admin->wpafi_set_thumbnail( $ids['page'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['page'] ) === $img_news, 'Page post type first-image' );

$admin->wpafi_set_thumbnail( $ids['cpt'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['cpt'] ) === $img_tech, 'Custom post type first-image' );

$admin->wpafi_set_thumbnail( $ids['overwrite'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['overwrite'] ) === $img_tech, 'Overwrite OFF leaves existing thumb' );

$admin->wpafi_set_thumbnail( $ids['draft'] );
e2e_ok( has_post_thumbnail( $ids['draft'] ), 'Empty post_statuses matches drafts (no status filter)' );

$admin->wpafi_set_thumbnail( $ids['no_match'] );
e2e_ok( ! has_post_thumbnail( $ids['no_match'] ), 'No content image → first_image cannot set' );

// Tag-only rule (swap rules).
e2e_options(
	array(
		e2e_media_rule(
			'Tag media',
			$img_news,
			array(
				'tags' => array( 'afi-e2e-tag' ),
			)
		),
		e2e_first_rule( 'Fallback unused', array( 'enabled' => 0 ) ),
	)
);
$admin->wpafi_set_thumbnail( $ids['tagged'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['tagged'] ) === $img_news, 'Tag condition matches' );

// Status-only published rule should skip draft if we clear its thumb and restrict status.
delete_post_thumbnail( $ids['draft'] );
e2e_options(
	array(
		e2e_first_rule(
			'Published only',
			array(
				'post_types'    => array( 'post' ),
				'post_statuses' => array( 'publish' ),
			)
		),
	)
);
$admin->wpafi_set_thumbnail( $ids['draft'] );
e2e_ok( ! has_post_thumbnail( $ids['draft'] ), 'post_statuses=publish skips draft' );

// Overwrite ON.
e2e_options(
	array(
		e2e_media_rule(
			'News overwrite',
			$img_news,
			array(
				'categories' => array( 'afi-e2e-news' ),
				'overwrite'  => 1,
			)
		),
	)
);
$admin->wpafi_set_thumbnail( $ids['overwrite'] );
e2e_ok( (int) get_post_thumbnail_id( $ids['overwrite'] ) === $img_news, 'Overwrite ON replaces existing thumb' );

// External URL.
e2e_options(
	array(
		e2e_external_rule(
			'External YouTube JPG',
			$yt_url,
			array( 'post_types' => array( 'post' ) )
		),
	)
);
$admin->wpafi_set_thumbnail( $ids['external'] );
$ext_thumb = (int) get_post_thumbnail_id( $ids['external'] );
if ( $ext_thumb ) {
	e2e_ok( true, "External URL sideloaded (attachment $ext_thumb)" );
} else {
	e2e_ok( false, 'External URL sideload' );
}

// Disabled rule skipped.
delete_post_thumbnail( $ids['news_browser'] );
e2e_options(
	array(
		e2e_media_rule(
			'Disabled news',
			$img_news,
			array(
				'categories' => array( 'afi-e2e-news' ),
				'enabled'    => 0,
			)
		),
	)
);
$admin->wpafi_set_thumbnail( $ids['news_browser'] );
e2e_ok( ! has_post_thumbnail( $ids['news_browser'] ), 'Disabled rule is skipped' );

// Sanitize: max 2 rules on Free.
$settings = new WPAFI_Settings();
$sanitized = $settings->sanitize_options(
	array(
		'wpafi_rules' => array(
			e2e_media_rule( 'A', $img_news ),
			e2e_media_rule( 'B', $img_tech ),
			e2e_media_rule( 'C', $img_news ),
		),
	)
);
e2e_ok( isset( $sanitized['wpafi_rules'] ) && 2 === count( $sanitized['wpafi_rules'] ), 'Sanitize caps Free at 2 rules' );

// Preview must not write: restore working rules, snapshot thumbs, call get_first_image_url_from_content.
e2e_options(
	array(
		e2e_media_rule(
			'News media',
			$img_news,
			array(
				'post_types' => array( 'post' ),
				'categories' => array( 'afi-e2e-news' ),
			)
		),
		e2e_first_rule(
			'First image/video',
			array(
				'post_types' => array( 'post', 'page', 'sny_e2e_item' ),
			)
		),
	)
);

$preview_url = $admin->get_first_image_url_from_content( $ids['tech_browser'], true );
e2e_ok( is_string( $preview_url ) && false !== strpos( $preview_url, 'sny-afi-e2e-tech' ), 'Preview URL helper finds content image' );
e2e_ok( ! has_post_thumbnail( $ids['tech_browser'] ), 'Preview helper did not write a thumbnail' );

$yt_preview = $admin->get_first_image_url_from_content( $ids['youtube_browser'], true );
e2e_ok( is_string( $yt_preview ) && false !== strpos( $yt_preview, $yt_id ), 'Preview helper returns YouTube hqdefault without sideload' );
e2e_ok( ! has_post_thumbnail( $ids['youtube_browser'] ), 'YouTube preview did not write' );

// Bulk targets tax_query for category rule.
$ref = new ReflectionClass( $admin );
$m   = $ref->getMethod( 'get_bulk_targets' );
$m->setAccessible( true );
$cat_only_opts = array(
	'wpafi_rules' => array(
		e2e_media_rule(
			'Bulk news only',
			$img_news,
			array(
				'post_types' => array( 'post' ),
				'categories' => array( 'afi-e2e-news' ),
			)
		),
	),
);
$targets = $m->invoke( $admin, '0', $cat_only_opts );
e2e_ok( ! empty( $targets['query_args']['tax_query'] ), 'Category rule bulk query includes tax_query' );

// 50-cap: seed 55 posts with rules off so save_post does not assign.
e2e_options( array() );
$bulk_ids = array();
for ( $i = 1; $i <= 55; $i++ ) {
	$bulk_ids[] = e2e_insert(
		array(
			'post_title'    => sprintf( '[AFI-E2E] Bulk cap %02d', $i ),
			'post_content'  => 'Bulk cap fixture.',
			'post_category' => array( $cat_bulk ),
			'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( 55 - $i ) ),
		)
	);
}
e2e_options(
	array(
		e2e_media_rule(
			'Bulk cap',
			$img_news,
			array(
				'post_types' => array( 'post' ),
				'categories' => array( 'afi-e2e-bulk' ),
			)
		),
	)
);

$cap_targets = $m->invoke( $admin, '0', get_option( 'wpafi_options' ) );
$qargs       = $cap_targets['query_args'];
$qargs['posts_per_page'] = 50;
$qargs['no_found_rows']  = false;
$q                 = new WP_Query( $qargs );
$total_matching    = (int) $q->found_posts;
$capped_ids        = $q->posts;
e2e_ok( $total_matching >= 55, "Bulk category matches $total_matching posts (>=55)" );
e2e_ok( 50 === count( $capped_ids ), 'Free query page size returns 50 IDs' );

$assigned = 0;
foreach ( $capped_ids as $pid ) {
	$admin->wpafi_set_thumbnail( $pid );
	if ( has_post_thumbnail( $pid ) ) {
		++$assigned;
	}
}
e2e_ok( 50 === $assigned, "Assigned 50 of 55 ($assigned)" );

$untouched = 0;
foreach ( $bulk_ids as $pid ) {
	if ( ! has_post_thumbnail( $pid ) ) {
		++$untouched;
	}
}
e2e_ok( $untouched >= 5, "At least 5 bulk posts remain unassigned ($untouched)" );

// save_post on a brand-new post with first-image rule restored.
e2e_options(
	array(
		e2e_media_rule(
			'News media',
			$img_news,
			array(
				'post_types' => array( 'post' ),
				'categories' => array( 'afi-e2e-news' ),
			)
		),
		e2e_first_rule(
			'First image/video',
			array(
				'post_types' => array( 'post', 'page', 'sny_e2e_item' ),
			)
		),
	)
);

$live_id = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] Live save_post first image',
		'post_content'  => '<img src="' . esc_url( $img_tech_url ) . '" class="wp-image-' . $img_tech . '" />',
		'post_category' => array( $cat_tech ),
	)
);
e2e_ok( (int) get_post_thumbnail_id( $live_id ) === $img_tech, 'save_post auto-assigns first image on insert' );

$live_news = e2e_insert(
	array(
		'post_title'    => '[AFI-E2E] Live save_post media',
		'post_content'  => 'No content image.',
		'post_category' => array( $cat_news ),
	)
);
e2e_ok( (int) get_post_thumbnail_id( $live_news ) === $img_news, 'save_post auto-assigns media rule on insert' );

flush_rewrite_rules( false );

$browser_ready = array(
	$ids['news_browser'],
	$ids['tech_browser'],
	$ids['youtube_browser'],
	$ids['cpt_browser'],
);
$browser_empty = 0;
foreach ( $browser_ready as $pid ) {
	if ( ! has_post_thumbnail( $pid ) ) {
		++$browser_empty;
	}
}
e2e_ok( $browser_empty === count( $browser_ready ), 'Browser apply targets still have empty thumbs' );

echo "\n=== SUMMARY ===\n";
echo 'PASS: ' . $GLOBALS['e2e_pass'] . "\nFAIL: " . $GLOBALS['e2e_fail'] . "\n";
if ( $GLOBALS['e2e_notes'] ) {
	echo "NOTES:\n- " . implode( "\n- ", $GLOBALS['e2e_notes'] ) . "\n";
}
echo "\nBrowser leftovers (no thumb):\n";
foreach ( $browser_ready as $pid ) {
	echo "  #$pid " . get_the_title( $pid ) . "\n";
}
echo "Edit: " . admin_url( 'edit.php?s=%5BAFI-E2E%5D' ) . "\n";
echo "Settings: " . admin_url( 'options-general.php?page=wp_auto_featured_image' ) . "\n";

if ( $GLOBALS['e2e_fail'] > 0 ) {
	exit( 1 );
}

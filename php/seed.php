<?php
/** php seed.php [--force] : demo content, a media file and menus. Writes state/ids.json. */
require __DIR__ . '/boot.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$force = in_array( '--force', $argv, true );
$ids_file = lab_state_dir() . '/ids.json';
if ( get_option( 'lab_seeded' ) && is_file( $ids_file ) && ! $force ) {
	echo "already seeded (use --force to add again)\n";
	exit( 0 );
}
wp_set_current_user( 1 );
$fx = require lab_root() . '/seed/fixtures.php';

// Fixed dates on WordPress' default post and page.
wp_update_post( array( 'ID' => 1, 'post_date' => $fx['default_post_date'], 'post_date_gmt' => $fx['default_post_date'], 'edit_date' => true ) );
wp_update_post( array( 'ID' => 2, 'post_date' => $fx['default_page_date'], 'post_date_gmt' => $fx['default_page_date'], 'edit_date' => true ) );

$cats = array();
foreach ( $fx['categories'] as $c ) {
	$cats[ $c ] = wp_create_category( $c );
}
$cat_ids = array_values( $cats );
$posts   = array();
foreach ( $fx['posts'] as $i => $t ) {
	$date    = gmdate( 'Y-m-d H:i:s', strtotime( $fx['first_post_date'] ) - $i * DAY_IN_SECONDS );
	$posts[] = wp_insert_post(
		array(
			'post_title'    => $t,
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_date'     => $date,
			'post_date_gmt' => $date,
			'post_excerpt'  => 'Short summary for: ' . $t . '. Details, dates and contact information are in the full notice.',
			'post_content'  => "<p>$t. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla egestas orci nec dolor posuere pharetra.</p><h2>Details</h2><p>Nullam tempus orci pulvinar ornare vehicula. Sed pulvinar lacinia libero a fermentum.</p><ul><li>First point</li><li>Second point</li></ul>",
			'post_category' => array( $cat_ids[ $i % count( $cat_ids ) ] ),
			'tags_input'    => array( $fx['tags'][0], $fx['tags'][1][ $i % 2 ] ),
		)
	);
}
$pages = array();
foreach ( $fx['pages'] as $key => $p ) {
	$pages[ $key ] = wp_insert_post( array( 'post_title' => $p[0], 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $p[1], 'post_date' => $fx['default_page_date'], 'post_date_gmt' => $fx['default_page_date'] ) );
}

// Media: the test logo goes through the real sideload path (thumbnails included when GD is present).
$png  = lab_root() . '/seed/assets/test-logo.png';
$copy = wp_tempnam( 'lab-logo.png' );
copy( $png, $copy );
$att = media_handle_sideload( array( 'name' => 'lab-test-logo.png', 'tmp_name' => $copy ), 0, 'Lab test logo' );
$logo = is_wp_error( $att ) ? 0 : $att;

// Menus: a flat item, a parent with two children (one with a description), more flat items; and a footer menu.
$main   = wp_create_nav_menu( 'Main Menu' );
$footer = wp_create_nav_menu( 'Footer Menu' );
$u = function ( $path ) {
	return home_url( $path );
};
$item = function ( $menu, $args ) {
	return wp_update_nav_menu_item( $menu, 0, $args + array( 'menu-item-status' => 'publish' ) );
};
$item( $main, array( 'menu-item-title' => 'About', 'menu-item-object' => 'page', 'menu-item-object-id' => $pages['about'], 'menu-item-type' => 'post_type' ) );
$parent = $item( $main, array( 'menu-item-title' => 'Themes', 'menu-item-url' => $u( '/?cat=' . $cats['News'] ), 'menu-item-type' => 'custom' ) );
$item( $main, array( 'menu-item-title' => 'Features', 'menu-item-url' => $u( '/?cat=' . $cats['Events'] ), 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent, 'menu-item-description' => 'Sub item description' ) );
$item( $main, array( 'menu-item-title' => 'Documentation', 'menu-item-url' => $u( '/?cat=' . $cats['Admissions'] ), 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent ) );
$item( $main, array( 'menu-item-title' => 'Plugins', 'menu-item-url' => $u( '/?tag=notice' ), 'menu-item-type' => 'custom' ) );
$item( $main, array( 'menu-item-title' => 'Connect', 'menu-item-object' => 'page', 'menu-item-object-id' => $pages['contact'], 'menu-item-type' => 'post_type' ) );
$item( $footer, array( 'menu-item-title' => 'Privacy', 'menu-item-url' => $u( '/' ), 'menu-item-type' => 'custom' ) );

$ids = array(
	'post'       => $posts[0],
	'posts'      => $posts,
	'page'       => $pages['about'],
	'contact'    => $pages['contact'],
	'cat'        => $cats['News'],
	'logo'       => $logo,
	'menu_main'  => $main,
	'menu_footer' => $footer,
);
file_put_contents( $ids_file, json_encode( $ids ) );
update_option( 'lab_seeded', 1 );
echo 'seeded: ' . count( $posts ) . ' posts, ' . count( $pages ) . " pages, logo attachment #$logo\n";

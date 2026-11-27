<?php
/** php use.php <target> <label> : activate the build that `lab use` linked into wp-content. */
require __DIR__ . '/boot.php';
$name = $argv[1];
$t    = lab_target( $name );
$slug = $t['slug'];
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( 'theme' === $t['type'] ) {
	wp_clean_themes_cache( true );
	switch_theme( $slug );
	// switch_theme() defers the menu/widget remap to the next admin request; drop it so state stays exactly as set.
	delete_option( 'theme_switched' );
	delete_option( 'theme_switch_menu_locations' );
	if ( get_option( 'stylesheet' ) !== $slug ) {
		fwrite( STDERR, "could not activate theme $slug\n" );
		exit( 1 );
	}
	$theme = wp_get_theme( $slug );
	echo "theme $slug active, Version header " . $theme->get( 'Version' ) . "\n";
} else {
	$main = $t['main'] ?? ( "$slug/$slug.php" );
	$r    = activate_plugin( $main );
	if ( is_wp_error( $r ) ) {
		fwrite( STDERR, 'plugin activation failed: ' . $r->get_error_message() . "\n" );
		exit( 1 );
	}
	echo "plugin $slug active\n";
}
foreach ( $t['php']['transients'] ?? array() as $tr ) {
	delete_transient( $tr );
}

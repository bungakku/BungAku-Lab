<?php
/**
 * php state.php <target> <state> [mods-json]
 * Puts the site into a named, repeatable state: target setup (menus, widgets, contact details),
 * optional theme mods, front page setting. Mods are given without the target's prefix.
 */
require __DIR__ . '/boot.php';
$name  = $argv[1];
$state = $argv[2] ?? 'ltr';
$mods  = json_decode( $argv[3] ?? '{}', true );
$t     = lab_target( $name );
if ( ! isset( $t['states'][ $state ] ) ) {
	fwrite( STDERR, "state '$state' is not defined for $name (" . implode( ', ', array_keys( $t['states'] ) ) . ")\n" );
	exit( 2 );
}
$def = $t['states'][ $state ];
$ids = lab_ids();
if ( ! $ids ) {
	fwrite( STDERR, "no seed data: run `bin/lab seed` first\n" );
	exit( 2 );
}
// Start from the same site identity every time, even when an earlier run stopped half way.
update_option( 'blogname', get_option( 'lab_site_name', 'BungAku Lab' ) );
update_option( 'blogdescription', get_option( 'lab_site_tagline', '' ) );
if ( 'theme' === $t['type'] ) {
	delete_option( 'theme_mods_' . $t['slug'] );
}
if ( ! empty( $t['setup'] ) ) {
	$f = lab_root() . "/targets/$name/" . $t['setup'];
	require_once $f;
	if ( function_exists( 'lab_target_setup' ) ) {
		lab_target_setup( $ids, $t );
	}
}
$prefix = $t['php']['mod_prefix'] ?? '';
$core   = $t['php']['core_mods'] ?? array();
foreach ( (array) $mods as $k => $v ) {
	set_theme_mod( in_array( $k, $core, true ) ? $k : $prefix . $k, $v );
}
if ( 'page' === ( $def['front'] ?? 'posts' ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['page'] );
	update_option( 'page_for_posts', 0 );
} else {
	update_option( 'show_on_front', 'posts' );
	update_option( 'page_on_front', 0 );
}
foreach ( $t['php']['transients'] ?? array() as $tr ) {
	delete_transient( $tr );
}
file_put_contents( lab_state_dir() . '/state.json', json_encode( array( 'name' => $state, 'rtl' => ! empty( $def['rtl'] ), 'mods' => $mods ) ) );
echo "state $state applied" . ( $mods ? ' with mods ' . json_encode( $mods ) : '' ) . "\n";

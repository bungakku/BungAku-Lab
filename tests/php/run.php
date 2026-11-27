<?php
/**
 * In-WordPress checks. php run.php <target> <label> [--update]
 * Needs the site up, the build active and the seed data present (`lab test` takes care of that).
 */
require dirname( __DIR__, 2 ) . '/php/boot.php';
$name   = $argv[1];
$label  = $argv[2] ?? '';
$update = in_array( '--update', $argv, true );
$t      = lab_target( $name );
$slug   = $t['slug'];
$c      = new Lab_Check( 'php' );

// 1. The right build is loaded
if ( 'theme' === $t['type'] ) {
	$c->ok( "active theme is $slug", get_option( 'stylesheet' ) === $slug, get_option( 'stylesheet' ) );
	$theme = wp_get_theme( $slug );
	$c->ok( 'theme has no load errors', ! $theme->errors(), $theme->errors() ? $theme->errors()->get_error_message() : '' );
	$ver = preg_match( '/^v?(\d[\w.\-]*)$/', $label, $m ) ? $m[1] : null;
	if ( $ver ) {
		$c->ok( "Version header equals the label ($ver)", $theme->get( 'Version' ) === $ver, $theme->get( 'Version' ) );
	}
	$req = $theme->get( 'RequiresPHP' );
	$c->info( 'WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ", theme Version {$theme->get( 'Version' )}" . ( $req ? ", Requires PHP $req" : ', no Requires PHP header' ) );
}

if ( 'plugin' === $t['type'] ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$main = $t['main'];
	$c->ok( "plugin $main is active", is_plugin_active( $main ) );
	$data = is_file( WP_PLUGIN_DIR . '/' . $main ) ? get_plugin_data( WP_PLUGIN_DIR . '/' . $main, false, false ) : array();
	$ver  = preg_match( '/^v?(\d[\w.\-]*)$/', $label, $m ) ? $m[1] : null;
	if ( $ver ) {
		$c->ok( "Version header equals the label ($ver)", ( $data['Version'] ?? '' ) === $ver, $data['Version'] ?? 'no header' );
	}
	$c->info( 'WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ', plugin Version ' . ( $data['Version'] ?? '?' ) );
}

// 2. Customizer: callbacks and the settings inventory
if ( ! empty( $t['php']['customizer'] ) ) {
	require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
	$wp_customize = new WP_Customize_Manager( array( 'theme' => $slug ) );
	$GLOBALS['wp_customize'] = $wp_customize;
	do_action( 'customize_register', $wp_customize );
	$re   = '/' . $t['php']['prefix_regex'] . '/';
	$inv  = array( 'settings' => array(), 'controls' => array(), 'sections' => array(), 'panels' => array() );
	$bad  = array();
	$name_of = function ( $cb ) {
		if ( is_string( $cb ) ) {
			return $cb;
		}
		if ( is_array( $cb ) ) {
			return ( is_object( $cb[0] ) ? get_class( $cb[0] ) : $cb[0] ) . '::' . $cb[1];
		}
		return $cb instanceof Closure ? 'Closure' : '';
	};
	foreach ( $wp_customize->settings() as $s ) {
		if ( ! preg_match( $re, $s->id ) ) {
			continue;
		}
		foreach ( array( 'sanitize_callback', 'sanitize_js_callback', 'validate_callback' ) as $p ) {
			if ( ! empty( $s->$p ) && ! is_callable( $s->$p ) ) {
				$bad[] = "{$s->id}:$p";
			}
		}
		$inv['settings'][ $s->id ] = array( 'type' => $s->type, 'default' => $s->default, 'transport' => $s->transport, 'capability' => $s->capability, 'sanitize' => $name_of( $s->sanitize_callback ) );
	}
	foreach ( $wp_customize->controls() as $ctl ) {
		$mine = (bool) preg_match( $re, $ctl->id );
		foreach ( $ctl->settings as $st ) {
			$mine = $mine || ( $st && preg_match( $re, $st->id ) );
		}
		if ( ! $mine ) {
			continue; // core controls the theme does not own are not part of the inventory
		}
		if ( ! empty( $ctl->active_callback ) && ! is_callable( $ctl->active_callback ) ) {
			$bad[] = "{$ctl->id}:active_callback";
		}
		$inv['controls'][ $ctl->id ] = array( 'type' => $ctl->type, 'section' => $ctl->section );
	}
	foreach ( $wp_customize->sections() as $sec ) {
		if ( preg_match( $re, $sec->id ) ) {
			$inv['sections'][ $sec->id ] = array( 'panel' => $sec->panel );
		}
	}
	foreach ( $wp_customize->panels() as $pan ) {
		if ( preg_match( $re, $pan->id ) ) {
			$inv['panels'][ $pan->id ] = true;
		}
	}
	foreach ( $inv as $k => &$v ) {
		ksort( $v );
	}
	unset( $v );
	// Defaults may contain the site name or the current year; the snapshot must not change on 1 January.
	foreach ( $inv['settings'] as $id => $row ) {
		if ( is_string( $row['default'] ) ) {
			$inv['settings'][ $id ]['default'] = str_replace( array( get_bloginfo( 'name' ), date_i18n( 'Y' ) ), array( '{SITE}', '{YEAR}' ), $row['default'] );
		}
	}
	$tr = array();
	foreach ( $inv['settings'] as $s ) {
		$tr[ $s['transport'] ] = ( $tr[ $s['transport'] ] ?? 0 ) + 1;
	}
	$c->ok( 'Customizer settings registered', count( $inv['settings'] ) > 0, '0 settings' );
	$c->info( count( $inv['settings'] ) . ' settings, ' . count( $inv['controls'] ) . ' controls, ' . count( $inv['sections'] ) . ' sections, transport ' . json_encode( $tr ) );
	$c->ok( 'every sanitize / validate / active callback is callable', ! $bad, implode( ', ', array_slice( $bad, 0, 5 ) ) );

	global $wp_filter;
	$tot  = 0;
	$miss = array();
	foreach ( $wp_filter as $tag => $obj ) {
		foreach ( $obj->callbacks as $cbs ) {
			foreach ( $cbs as $cb ) {
				$f = $cb['function'];
				if ( is_string( $f ) && preg_match( $re, $f ) ) {
					++$tot;
					if ( ! function_exists( $f ) ) {
						$miss[] = "$tag -> $f";
					}
				}
			}
		}
	}
	$c->ok( "all $tot theme hook callbacks (by name) resolve", ! $miss && $tot > 0, implode( ', ', array_slice( $miss, 0, 4 ) ) );

	$file = lab_root() . '/' . $t['php']['inventory'];
	$json = json_encode( $inv, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	if ( $update ) {
		@mkdir( dirname( $file ), 0777, true );
		file_put_contents( $file, $json );
		$c->info( 'inventory snapshot written: ' . $t['php']['inventory'] . ' (review it with git diff)' );
	} elseif ( ! is_file( $file ) ) {
		$c->ok( 'inventory snapshot exists', false, 'none yet: run `bin/lab test php ' . $name . ' --update` once and commit the file' );
	} else {
		$old = json_decode( file_get_contents( $file ), true );
		$diff = array();
		foreach ( array( 'settings', 'controls', 'sections', 'panels' ) as $k ) {
			$a = $old[ $k ] ?? array();
			$b = $inv[ $k ];
			foreach ( array_diff_key( $b, $a ) as $id => $x ) {
				$diff[] = "+ $k $id";
			}
			foreach ( array_diff_key( $a, $b ) as $id => $x ) {
				$diff[] = "- $k $id";
			}
			foreach ( array_intersect_key( $a, $b ) as $id => $x ) {
				if ( json_encode( $x ) !== json_encode( $b[ $id ] ) ) {
					$diff[] = "~ $k $id";
				}
			}
		}
		$c->ok( 'settings inventory equals the committed snapshot', ! $diff, count( $diff ) . ' differences: ' . implode( '; ', array_slice( $diff, 0, 6 ) ) . ' (intended? re-run with --update and commit)' );
	}
}

// 3. Target-specific checks
foreach ( $t['php']['checks'] ?? array() as $rel ) {
	$f = lab_root() . "/targets/$name/$rel";
	$fn = require $f;
	$c->info( "checks: $rel" );
	$fn( $c, $t );
}
$c->finish();

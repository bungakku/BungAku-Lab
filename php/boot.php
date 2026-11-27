<?php
/** Loads WordPress for a lab helper and exposes lab_* utilities. */
$lab_wp = getenv( 'WP_DIR' );
if ( ! $lab_wp || ! is_file( "$lab_wp/wp-load.php" ) ) {
	fwrite( STDERR, "WP_DIR is not set or WordPress is missing\n" );
	exit( 2 );
}
require_once __DIR__ . '/lib/lab.php';
// WordPress ends a failed load (database down, wrong credentials) with a plain die(), exit code 0. Make that a failure.
$lab_booted = false;
register_shutdown_function(
	function () use ( &$lab_booted ) {
		if ( ! $lab_booted ) {
			fwrite( STDERR, "lab: WordPress did not finish loading (database or wp-config problem)\n" );
			exit( 1 );
		}
	}
);
require_once "$lab_wp/wp-load.php";
$lab_booted = true;

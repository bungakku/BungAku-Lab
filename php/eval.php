<?php
/** php eval.php --code '<php>' | --file FILE [args]  : run code inside WordPress. */
require __DIR__ . '/boot.php';
if ( '--file' === ( $argv[1] ?? '' ) ) {
	$argv = array_slice( $argv, 2 );
	require $argv[0];
} else {
	eval( $argv[2] ?? '' ); // phpcs:ignore -- developer tool, local only.
}
echo "\n";

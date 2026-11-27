<?php
/** build-version.php <target.json> <tree> <version> : rewrite version headers like the release workflow does. */
list( , $json, $tree, $version ) = $argv + array( null, null, null, null );
$t = json_decode( file_get_contents( $json ), true );
foreach ( $t['build']['version_files'] ?? array() as $vf ) {
	$f = "$tree/{$vf['file']}";
	if ( ! is_file( $f ) ) {
		continue;
	}
	$src = file_get_contents( $f );
	$new = preg_replace( '/' . str_replace( '/', '\/', $vf['pattern'] ) . '/m', str_replace( '{version}', $version, $vf['replace'] ), $src, 1, $count );
	if ( $count ) {
		file_put_contents( $f, $new );
	}
}
echo "version set to $version\n";

<?php
/**
 * Router for `php -S`: real files are served directly, every other path goes to WordPress
 * (so pretty permalinks work too).
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
if ( '/' !== $path && file_exists( $root . $path ) ) {
	return false;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
require $root . '/index.php';

<?php
/**
 * Database admin for the lab, without loading WordPress.
 *   php db-init.php ping    can the server be reached?
 *   php db-init.php schema  create the database and user if missing
 *   php db-init.php drop    drop and recreate the database (used by `lab reset`)
 * Own mode: root over the private socket. External mode: DB_ROOT_USER/DB_ROOT_PASS if set.
 */
mysqli_report( MYSQLI_REPORT_OFF );
$mode = getenv( 'DB_MODE' ) ?: 'own';
$name = getenv( 'DB_NAME' ) ?: 'wp';
$user = getenv( 'DB_USER' ) ?: 'wp';
$pass = getenv( 'DB_PASS' ) ?: 'wp';
$cmd  = $argv[1] ?? 'ping';

if ( 'own' === $mode ) {
	$link = mysqli_connect( 'localhost', 'root', '', '', 0, getenv( 'DB_SOCKET' ) );
	$host = 'localhost';
} else {
	$ru   = getenv( 'DB_ROOT_USER' ) ?: ( getenv( 'DB_ROOT_PASS' ) ? 'root' : $user );
	$rp   = getenv( 'DB_ROOT_PASS' ) ?: $pass;
	$link = mysqli_connect( getenv( 'DB_HOST' ) ?: '127.0.0.1', $ru, $rp, '', (int) ( getenv( 'DB_PORT' ) ?: 3306 ) );
	$host = '%';
}
if ( ! $link ) {
	fwrite( STDERR, 'database not reachable: ' . mysqli_connect_error() . "\n" );
	exit( 1 );
}
if ( 'ping' === $cmd ) {
	echo "ok\n";
	exit( 0 );
}
if ( 'version' === $cmd ) {
	echo mysqli_get_server_info( $link ) . "\n";
	exit( 0 );
}
$q = function ( $sql ) use ( $link ) {
	if ( ! mysqli_query( $link, $sql ) ) {
		fwrite( STDERR, 'SQL failed: ' . mysqli_error( $link ) . " [$sql]\n" );
		exit( 1 );
	}
};
$n = '`' . str_replace( '`', '``', $name ) . '`';
if ( 'drop' === $cmd ) {
	$q( "DROP DATABASE IF EXISTS $n" );
}
$q( "CREATE DATABASE IF NOT EXISTS $n CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci" );
if ( 'own' === $mode || getenv( 'DB_ROOT_PASS' ) ) {
	$u = "'" . mysqli_real_escape_string( $link, $user ) . "'@'" . $host . "'";
	$q( "CREATE USER IF NOT EXISTS $u IDENTIFIED BY '" . mysqli_real_escape_string( $link, $pass ) . "'" );
	$q( "GRANT ALL PRIVILEGES ON $n.* TO $u" );
}
echo "database $name ready\n";

<?php
/** GitHub updater: request shape, update entry, and the zip-folder fix (a GitHub zip root must install as the theme slug). */
return function ( Lab_Check $c, $t ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wpdb;
	$px  = rtrim( $t['php']['mod_prefix'], '_' ); // bungaku_theme
	$tpl = get_template();
	$urls = array();
	add_filter(
		'pre_http_request',
		function ( $pre, $args, $url ) use ( &$urls ) {
			$urls[] = array( $url, $args['headers']['User-Agent'] ?? '' );
			return array(
				'headers'  => array(),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
				'body'     => json_encode( array( array( 'tag_name' => 'v99.0.0', 'zipball_url' => 'https://api.github.com/repos/bungakku/BungAku-Theme/zipball/v99.0.0', 'html_url' => 'https://github.com/bungakku/BungAku-Theme/releases/tag/v99.0.0', 'draft' => false, 'prerelease' => false ) ) ),
			);
		},
		10,
		3
	);
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%github_release%'" );
	$transient = (object) array( 'checked' => array( $tpl => wp_get_theme( $tpl )->get( 'Version' ) ), 'response' => array() );
	$fn = $px . '_check_for_update';
	if ( ! $c->ok( "$fn exists", function_exists( $fn ) ) ) {
		return;
	}
	$r = $fn( $transient );
	$e = $r->response[ $tpl ] ?? null;
	$c->ok( 'update entry created for the active theme folder', (bool) $e, "template=$tpl" );
	$c->ok( 'new_version is the published tag without "v"', $e && '99.0.0' === $e['new_version'], json_encode( $e ) );
	$c->ok( 'exactly one API request', 1 === count( $urls ), count( $urls ) . ' requests' );
	$c->ok( 'API host is api.github.com/bungakku', ! empty( $urls[0] ) && false !== strpos( $urls[0][0], 'api.github.com/repos/bungakku/BungAku-Theme' ), $urls[0][0] ?? '-' );
	$c->ok( 'User-Agent header is sent (GitHub requires it)', ! empty( $urls[0][1] ), $urls[0][1] ?? '-' );
	$fix = $px . '_fix_github_zip_folder_name';
	if ( $c->ok( "$fix exists", function_exists( $fix ) ) ) {
		foreach ( array( 'bungakku-BungAku-Theme-abc1234', 'bungaku-theme' ) as $shape ) {
			$d = sys_get_temp_dir() . '/lab_upg_' . $px;
			$GLOBALS['wp_filesystem']->delete( $d, true );
			wp_mkdir_p( "$d/$shape" );
			file_put_contents( "$d/$shape/style.css", '/* t */' );
			$res = $fix( "$d/$shape/", $d, null, array( 'theme' => $tpl ) );
			$c->ok( "zip root '$shape' installs as '$tpl'", ! is_wp_error( $res ) && basename( untrailingslashit( $res ) ) === $tpl, is_wp_error( $res ) ? $res->get_error_message() : $res );
			$GLOBALS['wp_filesystem']->delete( $d, true );
		}
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%github_release%'" );
};

<?php
/**
 * Plugin Name: Lab autologin
 * Description: Loopback-only login for tests: /?__lab_login=TOKEN&__lab_to=/wp-admin/ (token lives in .lab/state/salts.json).
 */
add_action(
	'init',
	function () {
		if ( ! defined( 'LAB_LOGIN_TOKEN' ) || empty( $_GET['__lab_login'] ) ) {
			return;
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		if ( ! in_array( $ip, array( '127.0.0.1', '::1' ), true ) || ! hash_equals( LAB_LOGIN_TOKEN, (string) $_GET['__lab_login'] ) ) {
			wp_die( 'forbidden', 403 );
		}
		wp_set_auth_cookie( 1, true );
		wp_set_current_user( 1 );
		$to = isset( $_GET['__lab_to'] ) ? (string) $_GET['__lab_to'] : '/wp-admin/';
		wp_safe_redirect( home_url( $to ) );
		exit;
	}
);

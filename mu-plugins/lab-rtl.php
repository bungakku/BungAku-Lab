<?php
/**
 * Plugin Name: Lab RTL
 * Description: Forces right-to-left rendering when the request carries the cookie lab_rtl=1 (no language pack needed).
 */
add_action(
	'after_setup_theme',
	function () {
		global $wp_locale;
		if ( ! empty( $_COOKIE['lab_rtl'] ) && $wp_locale ) {
			$wp_locale->text_direction = 'rtl';
		}
	},
	0
);

<?php
/**
 * Runs inside WordPress every time `bin/lab state <target> <name>` is applied.
 * Put the settings your target needs to look and behave the same on every run
 * (options, menus, widgets, shortcodes' demo pages). $ids holds the seeded post, page, menu and logo ids.
 */
function lab_target_setup( $ids, $t ) {
	// Example: update_option( 'my_plugin_settings', array( 'mode' => 'demo' ) );
}

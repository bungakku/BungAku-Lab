<?php
/** Installs WordPress into the (empty) database. Idempotent. */
define( 'WP_INSTALLING', true );
require __DIR__ . '/boot.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( is_blog_installed() ) {
	echo "WordPress already installed\n";
	exit( 0 );
}
wp_install( 'BungAku Lab', 'admin', 'admin@example.org', true, '', 'admin', 'en_US' );
update_option( 'permalink_structure', '' );
update_option( 'timezone_string', 'UTC' );
update_option( 'default_comment_status', 'closed' );
// Core's default tagline differs by version ("Just another WordPress site" on 6.0, empty on 6.2). Pin it, so every core renders the same header.
update_option( 'blogdescription', '' );
// Remembered so `lab state` can restore them after a test changed them.
update_option( 'lab_site_name', get_option( 'blogname' ) );
update_option( 'lab_site_tagline', get_option( 'blogdescription' ) );
echo 'WordPress ' . get_bloginfo( 'version' ) . " installed\n";

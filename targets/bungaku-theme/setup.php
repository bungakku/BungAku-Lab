<?php
/** BungAku-Theme: menus in their locations, the four widgets, contact details and the login button. */
function lab_target_setup( $ids, $t ) {
	$p   = $t['php']['mod_prefix'];
	$idb = $p . 'recent_posts';
	set_theme_mod( 'nav_menu_locations', array( 'primary' => $ids['menu_main'], 'footer' => $ids['menu_footer'] ) );
	update_option( 'widget_search', array( 2 => array( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'widget_categories', array( 2 => array( 'title' => 'Categories', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_' . $idb, array( 2 => array( 'title' => 'Latest Updates', 'number' => 5, 'show_thumbnail' => 0, 'show_excerpt' => 1, 'show_readmore' => 1, 'excerpt_length' => 12 ), '_multiwidget' => 1 ) );
	update_option( 'widget_text', array( 2 => array( 'title' => 'Footer', 'text' => 'Footer text', 'filter' => false, 'visual' => false ), '_multiwidget' => 1 ) );
	update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array(), 'sidebar-1' => array( 'search-2', $idb . '-2', 'categories-2' ), 'footer-1' => array( 'text-2' ), 'array_version' => 3 ) );
	$contact = array(
		'phone_1' => '+91 98765 43210', 'phone_2' => '+91 98765 43211', 'email' => 'info@example.org',
		'facebook_url' => 'https://example.org/f', 'twitter_url' => 'https://example.org/t', 'instagram_url' => 'https://example.org/i',
		'linkedin_url' => 'https://example.org/l', 'youtube_url' => 'https://example.org/y',
	);
	foreach ( $contact as $k => $v ) {
		set_theme_mod( $p . $k, $v );
	}
	set_theme_mod( $p . 'show_login_button', true );
}

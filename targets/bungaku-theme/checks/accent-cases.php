<?php
/** Site title accent: the markup for tricky site names. Expected strings were reviewed by hand (v1.8.0). */
return function ( Lab_Check $c, $t ) {
	$orig = get_option( 'blogname' );
	$cases = array(
		// name, accent text, expected markup (null = must equal plain bloginfo)
		array( 'BungAku', 'Aku', 'Bung<span class="site-title-accent">Aku</span>' ),
		array( "St Mary's Cathedral", 'Cathedral', 'St Mary&#8217;s <span class="site-title-accent">Cathedral</span>' ),
		array( "St Mary's Cathedral", "mary's", 'St <span class="site-title-accent">Mary&#8217;s</span> Cathedral' ),
		array( "St Mary's Cathedral", "St Mary's Cathedral", '<span class="site-title-accent">St Mary&#8217;s Cathedral</span>' ),
		array( 'বাংলা স্কুল', 'স্কুল', 'বাংলা <span class="site-title-accent">স্কুল</span>' ),
		array( 'A & B College', '& B', 'A <span class="site-title-accent">&#038; B</span> College' ),
		array( 'Bung (Aku)', '(Aku)', 'Bung <span class="site-title-accent">(Aku)</span>' ),
		array( 'BungAku', '  Aku  ', 'Bung<span class="site-title-accent">Aku</span>' ),
		array( 'Bung <b>Aku</b>', 'Aku', 'Bung &lt;b&gt;<span class="site-title-accent">Aku</span>&lt;/b&gt;' ), // markup in the name stays escaped
		array( 'Aku Aku Bung', 'aku', '<span class="site-title-accent">Aku</span> Aku Bung' ), // first match, case-insensitive
		array( 'Delhi Public School', 'Public', 'Delhi <span class="site-title-accent">Public</span> School' ),
		array( 'BungAku', 'xyz', null ), // not in the title: nothing is coloured
		array( 'BungAku', '', null ),     // empty: feature off
	);
	foreach ( $cases as $i => $case ) {
		list( $name, $accent, $want ) = $case;
		update_option( 'blogname', $name );
		set_theme_mod( 'bungaku_theme_title_accent_text', $accent );
		ob_start();
		bungaku_theme_the_site_title();
		$got = ob_get_clean();
		if ( null === $want ) {
			ob_start();
			bloginfo( 'name' );
			$want = ob_get_clean();
		}
		$c->ok( sprintf( 'accent "%s" in "%s"', $accent, $name ), $got === $want, "got: $got | want: $want" );
	}
	update_option( 'blogname', $orig );
	remove_theme_mod( 'bungaku_theme_title_accent_text' );
};

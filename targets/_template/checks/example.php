<?php
/** In-WordPress checks: list this file in target.json under php.checks. */
return function ( Lab_Check $c, $t ) {
	$c->ok( 'example: WordPress is loaded', function_exists( 'get_bloginfo' ) );
};

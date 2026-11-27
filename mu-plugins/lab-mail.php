<?php
/**
 * Plugin Name: Lab mail
 * Description: No mail leaves the lab. wp_mail() reports success without sending (keeps installs and tests quiet).
 */
add_filter( 'pre_wp_mail', '__return_true' );

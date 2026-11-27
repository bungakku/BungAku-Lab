<?php
/**
 * Demo content for every target. Fixed titles and distinct, fixed dates keep page order stable
 * between runs (Related Posts and archive order flip when dates collide).
 */
return array(
	'categories' => array( 'News', 'Events', 'Admissions' ),
	'posts'      => array(
		'Admissions open for the new session',
		'Annual sports day highlights',
		'Exam timetable published',
		'Workshop on web accessibility',
		'Library hours extended during exams',
		'New computer lab inaugurated',
		'Parent-teacher meeting schedule',
		'Scholarship applications now open',
		'Science fair winners announced',
		'Campus clean-up drive',
		'Guest lecture on digital skills',
		'Holiday notice for the festival season',
	),
	'first_post_date' => '2026-11-20 10:00:00', // each following post is one day older
	'tags'       => array( 'Notice', array( 'Campus', 'Academics' ) ),
	'pages'      => array(
		'about'   => array( 'About', '<p>Page body text. Lorem ipsum dolor sit amet.</p><h2>Heading</h2><p>More text here.</p>' ),
		'contact' => array( 'Contact', '<p>Contact page body.</p>' ),
	),
	// WordPress' own default content gets fixed dates so nothing depends on the install day.
	'default_post_date' => '2026-11-01 09:00:00',
	'default_page_date' => '2026-11-01 09:00:00',
);

<?php
/**
 * Static checks on a BUILT tree (and zip). No WordPress needed.
 * php run.php <target> <tree-dir> <zip-file> <label>
 */
require dirname( __DIR__, 2 ) . '/php/lib/lab.php';
list( , $name, $tree, $zip, $label ) = $argv + array( null, null, null, null, null );
$t     = lab_target( $name );
$s     = $t['static'];
$slug  = $t['slug'];
$c     = new Lab_Check( 'static' );
$tree  = rtrim( $tree, '/' ) . '/' . $slug;
$ver   = preg_match( '/^v?(\d+\.\d+[\w.\-]*)$/', $label, $m ) ? $m[1] : null;

// 1. Zip layout
$za = new ZipArchive();
if ( $c->ok( 'zip opens', true === $za->open( $zip ) ) ) {
	$roots = array();
	$files = 0;
	for ( $i = 0; $i < $za->numFiles; $i++ ) {
		$n = $za->getNameIndex( $i );
		$roots[ explode( '/', $n )[0] ] = true;
		if ( '/' !== substr( $n, -1 ) ) {
			++$files;
		}
	}
	$c->ok( "zip has one root folder named '$slug'", array_keys( $roots ) === array( $slug ), implode( ',', array_keys( $roots ) ) );
	$c->info( "zip files: $files" );
	$za->close();
}

// 2. Required and forbidden files
foreach ( $s['required_files'] as $f ) {
	$c->ok( "required file $f", file_exists( "$tree/$f" ) );
}
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tree, FilesystemIterator::SKIP_DOTS ) );
$all = array();
foreach ( $it as $f ) {
	if ( $f->isFile() ) {
		$all[] = substr( $f->getPathname(), strlen( $tree ) + 1 );
	}
}
sort( $all );
$bad = array();
foreach ( $all as $rel ) {
	foreach ( explode( '/', $rel ) as $part ) {
		foreach ( $s['forbidden_files'] as $fb ) {
			if ( fnmatch( $fb, $part ) ) {
				$bad[] = $rel;
			}
		}
	}
}
$c->ok( 'no repo-only files in the package', ! $bad, implode( ', ', array_slice( $bad, 0, 5 ) ) );

// 3. Version consistency
$read = function ( $rel ) use ( $tree ) {
	return is_file( "$tree/$rel" ) ? file_get_contents( "$tree/$rel" ) : '';
};
$found = array();
if ( preg_match( '/^[\s*\/#]*' . preg_quote( $s['version_header'], '/' ) . '\s*:\s*(\S+)/mi', $read( $s['main_file'] ), $m ) ) {
	$found[ $s['main_file'] . ' ' . $s['version_header'] ] = $m[1];
}
if ( ! empty( $s['version_constant'] ) && preg_match( '/define\(\s*[\'"]' . preg_quote( $s['version_constant']['name'], '/' ) . '[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $read( $s['version_constant']['file'] ), $m ) ) {
	$found[ $s['version_constant']['name'] ] = $m[1];
}
if ( ! empty( $s['readme_stable_tag'] ) && preg_match( '/^Stable tag:\s*(\S+)/mi', $read( $s['readme_stable_tag'] ), $m ) ) {
	$found['readme Stable tag'] = $m[1];
}
if ( ! empty( $s['changelog'] ) && preg_match( '/^##\s*\[([^\]]+)\]/m', $read( $s['changelog'] ), $m ) ) {
	$found['top CHANGELOG entry'] = $m[1];
}
$c->ok( 'version found in every place', count( $found ) >= 3, json_encode( $found ) );
$c->ok( 'all version places agree', 1 === count( array_unique( $found ) ), json_encode( $found ) );
if ( $ver ) {
	$c->ok( "version equals the label ($ver)", array_unique( $found ) === array( $ver ) || array_values( array_unique( $found ) ) === array( $ver ), json_encode( $found ) );
}

// 4. Syntax
$php_bad = array();
$js_bad  = array();
foreach ( $all as $rel ) {
	$p = "$tree/$rel";
	if ( preg_match( '/\.php$/', $rel ) ) {
		exec( 'php -l ' . escapeshellarg( $p ) . ' 2>&1', $o, $rc );
		if ( $rc ) {
			$php_bad[] = $rel;
		}
		$o = array();
	} elseif ( preg_match( '/\.js$/', $rel ) && ! preg_match( '/\.min\.js$/', $rel ) ) {
		exec( 'node --check ' . escapeshellarg( $p ) . ' 2>&1', $o, $rc );
		if ( $rc ) {
			$js_bad[] = $rel;
		}
		$o = array();
	} elseif ( preg_match( '/\.json$/', $rel ) && null === json_decode( file_get_contents( $p ) ) ) {
		$js_bad[] = $rel;
	}
}
$nphp = count( array_filter( $all, function ( $r ) { return preg_match( '/\.php$/', $r ); } ) );
$njs  = count( array_filter( $all, function ( $r ) { return preg_match( '/\.js$/', $r ); } ) );
$c->ok( "php -l on $nphp files", ! $php_bad, implode( ', ', $php_bad ) );
$c->ok( "node --check / JSON on $njs js files", ! $js_bad, implode( ', ', $js_bad ) );

// 5. Forbidden content
foreach ( $s['forbidden_content'] as $rule ) {
	$hits = array();
	foreach ( $all as $rel ) {
		$ok = false;
		foreach ( $rule['files'] as $g ) {
			$ok = $ok || fnmatch( $g, basename( $rel ) );
		}
		if ( $ok && preg_match( '/' . $rule['pattern'] . '/i', $read( $rel ), $mm ) ) {
			$hits[] = "$rel ({$mm[0]})";
		}
	}
	$c->ok( 'forbidden content absent: ' . $rule['pattern'], ! $hits, ( $rule['why'] ?? '' ) . ' :: ' . implode( ', ', array_slice( $hits, 0, 4 ) ) );
}

// 6. Text domain on every translation call (tokenizer, no regex guessing)
$argpos = array( '__' => 1, '_e' => 1, 'esc_html__' => 1, 'esc_attr__' => 1, 'esc_html_e' => 1, 'esc_attr_e' => 1, '_x' => 2, '_ex' => 2, 'esc_html_x' => 2, 'esc_attr_x' => 2, '_n' => 3, '_nx' => 4 );
$td_bad = array();
$calls  = 0;
foreach ( $all as $rel ) {
	if ( ! preg_match( '/\.php$/', $rel ) ) {
		continue;
	}
	$tok = token_get_all( $read( $rel ) );
	$sig = array();
	foreach ( $tok as $tk ) {
		if ( is_array( $tk ) && in_array( $tk[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		$sig[] = $tk;
	}
	$line = 1;
	for ( $i = 0, $n = count( $sig ); $i < $n; $i++ ) {
		$tk = $sig[ $i ];
		if ( ! is_array( $tk ) || T_STRING !== $tk[0] || ! isset( $argpos[ $tk[1] ] ) ) {
			continue;
		}
		$prev = $i > 0 ? $sig[ $i - 1 ] : null;
		if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}
		if ( '(' !== ( $sig[ $i + 1 ] ?? null ) ) {
			continue;
		}
		++$calls;
		$depth = 0;
		$args  = array( array() );
		for ( $j = $i + 1; $j < $n; $j++ ) {
			$x = $sig[ $j ];
			$s_ = is_array( $x ) ? $x[1] : $x;
			if ( '(' === $s_ || '[' === $s_ ) {
				++$depth;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $s_ || ']' === $s_ ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $s_ && 1 === $depth ) {
				$args[] = array();
				continue;
			}
			$args[ count( $args ) - 1 ][] = $x;
		}
		$idx = $argpos[ $tk[1] ];
		$a   = $args[ $idx ] ?? null;
		$ln  = is_array( $tk ) ? $tk[2] : 0;
		if ( null === $a || ! $a ) {
			$td_bad[] = "$rel:$ln {$tk[1]}() has no text domain";
		} elseif ( 1 !== count( $a ) || ! is_array( $a[0] ) || T_CONSTANT_ENCAPSED_STRING !== $a[0][0] ) {
			$td_bad[] = "$rel:$ln {$tk[1]}() text domain is not a string literal";
		} elseif ( trim( $a[0][1], '\'"' ) !== $s['text_domain'] ) {
			$td_bad[] = "$rel:$ln {$tk[1]}() uses domain " . $a[0][1];
		}
	}
}
$c->ok( "text domain '{$s['text_domain']}' on all $calls translation calls", ! $td_bad, implode( ' | ', array_slice( $td_bad, 0, 4 ) ) );

// 7. Headers
$style = $read( $s['main_file'] );
foreach ( array( 'theme' === $t['type'] ? 'Theme Name' : 'Plugin Name', 'Text Domain', 'License' ) as $h ) {
	$c->ok( "header '$h' present", (bool) preg_match( '/^[\s*\/#]*' . preg_quote( $h, '/' ) . '\s*:\s*\S/mi', $style ) );
}
// WordPress reads these two from the main file to refuse installs on old versions; a warning, not a failure.
foreach ( array( 'Requires at least', 'Requires PHP' ) as $h ) {
	$c->warn( "header '$h' present in {$s['main_file']}", (bool) preg_match( '/^[\s*\/#]*' . preg_quote( $h, '/' ) . '\s*:\s*\S/mi', $style ) );
}
if ( preg_match( '/^[\s*\/#]*Text Domain\s*:\s*(\S+)/mi', $style, $m ) ) {
	$c->ok( 'Text Domain header equals the slug', $m[1] === $slug, $m[1] );
}
$c->finish();

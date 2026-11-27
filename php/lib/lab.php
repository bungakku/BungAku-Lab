<?php
/** Small helpers shared by every lab PHP script (they work without WordPress loaded). */

function lab_root() {
	return dirname( __DIR__, 2 );
}

function lab_target( $name ) {
	$f = lab_root() . "/targets/$name/target.json";
	if ( ! is_file( $f ) ) {
		fwrite( STDERR, "unknown target '$name'\n" );
		exit( 2 );
	}
	$t = json_decode( file_get_contents( $f ), true );
	if ( ! is_array( $t ) ) {
		fwrite( STDERR, "invalid JSON in $f\n" );
		exit( 2 );
	}
	return $t;
}

function lab_state_dir() {
	return getenv( 'STATE_DIR' ) ?: ( getenv( 'LAB_HOME' ) . '/state' );
}

function lab_ids() {
	$f = lab_state_dir() . '/ids.json';
	return is_file( $f ) ? json_decode( file_get_contents( $f ), true ) : array();
}

/** Replace {post}, {page} ... in a path with seeded ids. */
function lab_expand( $path ) {
	$ids = lab_ids();
	return preg_replace_callback(
		'/\{(\w+)\}/',
		function ( $m ) use ( $ids ) {
			return isset( $ids[ $m[1] ] ) ? $ids[ $m[1] ] : $m[0];
		},
		$path
	);
}

/** Minimal assertion runner. Prints PASS/FAIL lines and a final RESULT line the wrapper parses. */
class Lab_Check {
	public $suite;
	public $pass = 0;
	public $fail = 0;
	public $warn = 0;
	public function __construct( $suite ) {
		$this->suite = $suite;
	}
	public function ok( $name, $cond, $detail = '' ) {
		if ( $cond ) {
			++$this->pass;
			echo "  PASS $name\n";
		} else {
			++$this->fail;
			echo "  FAIL $name" . ( '' !== $detail ? " :: $detail" : '' ) . "\n";
		}
		return (bool) $cond;
	}
	/** A finding worth knowing about that does not fail the suite. */
	public function warn( $name, $cond, $detail = '' ) {
		if ( $cond ) {
			++$this->pass;
			echo "  PASS $name\n";
		} else {
			++$this->warn;
			echo "  WARN $name" . ( '' !== $detail ? " :: $detail" : '' ) . "\n";
		}
		return (bool) $cond;
	}
	public function info( $line ) {
		echo "  .... $line\n";
	}
	public function finish() {
		echo "RESULT {$this->suite} pass={$this->pass} fail={$this->fail} warn={$this->warn}\n";
		exit( $this->fail ? 1 : 0 );
	}
}

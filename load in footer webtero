add_filter( 'sikmo/block/maybe_located', function( $maybe_located, $args ) {
	global $fce_front;

	if( self::$located ) {
		self::$located = false;

		// load anything at beginning of footer	
	}

	if( str_contains( $maybe_located, 'footer.php' ) ) {
		self::$located = true;
	}

	return $maybe_located;
}, 20, 2);

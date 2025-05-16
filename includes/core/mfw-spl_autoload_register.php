spl_autoload_register( function( $class ) {
    $prefix   = 'MFW_';
    $base_dir = __DIR__ . '/includes/';

    // only load MFW_ classes
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }

    // strip prefix and convert underscores to dashes if you use mfw-<lowercase> filenames
    $relative = substr( $class, strlen( $prefix ) );
    $slug     = 'mfw-' . strtolower( str_replace( '_', '-', $relative ) );

    // try core/, admin/, fetchers/, etc. in turn
    $paths = [
        $base_dir . 'core/'      . $slug . '.php',
        $base_dir . 'admin/'     . $slug . '.php',
        $base_dir . 'fetchers/'  . $slug . '.php',
        $base_dir . 'processors/'. $slug . '.php',
        $base_dir . 'integrations/' . $slug . '.php',
        $base_dir . 'utilities/'. $slug . '.php',
        $base_dir . 'services/'  . $slug . '.php',
    ];

    foreach ( $paths as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
});

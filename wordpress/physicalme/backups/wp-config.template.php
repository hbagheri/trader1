<?php
/**
 * WordPress Configuration Template for PhysicalMe
 *
 * IMPORTANT: This is a TEMPLATE. Copy this to wp-config.php and fill in the actual values.
 * Keep database credentials secure - never commit actual credentials to git.
 *
 * During recovery, use these values:
 * - DB_NAME: physicalme
 * - DB_USER: physics_user
 * - DB_PASSWORD: sAtURN2#6)1 (from .env file)
 * - DB_HOST: db (for Docker), localhost (for standalone)
 */

// Database configuration
define( 'DB_NAME', 'physicalme' );
define( 'DB_USER', 'physics_user' );
define( 'DB_PASSWORD', 'sAtURN2#6)1' );
define( 'DB_HOST', 'db' ); // 'db' for Docker, 'localhost' for standalone MySQL

// Domain detection for multi-environment setup
$_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost:50081';
$_is_production = strpos( $_host, 'physicsme.ir' ) !== false;
$_is_local = strpos( $_host, 'localhost' ) !== false || strpos( $_host, '127.0.0.1' ) !== false;

// Production domain routing
if ( $_is_production ) {
	define( 'WP_HOME', 'https://physicsme.ir' );
	define( 'WP_SITEURL', 'https://physicsme.ir' );
	define( 'FORCE_SSL_ADMIN', true );
	define( 'FORCE_SSL', true );
} elseif ( $_is_local ) {
	define( 'WP_HOME', 'http://localhost:50081' );
	define( 'WP_SITEURL', 'http://localhost:50081' );
	define( 'FORCE_SSL_ADMIN', false );
	define( 'FORCE_SSL', false );
} else {
	// Default for other domains/environments
	define( 'WP_HOME', 'http://' . $_host );
	define( 'WP_SITEURL', 'http://' . $_host );
	define( 'FORCE_SSL_ADMIN', false );
}

// Authentication unique keys and salts (keep consistent in production)
// Generate new ones at: https://api.wordpress.org/secret-key/1.1/salt/
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

// Database table prefix (default)
$table_prefix = 'wp_';

// WordPress debugging (disable in production)
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );

// Multisite (only if needed)
define( 'WP_ALLOW_MULTISITE', false );

// Memory limit for WordPress
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );

// Disable file editing for security
define( 'DISALLOW_FILE_EDIT', true );

// Absolute path to the WordPress directory
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

// Sets up WordPress vars and included files
require_once( ABSPATH . 'wp-settings.php' );

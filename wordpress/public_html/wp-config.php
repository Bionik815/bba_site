<?php
if ( file_exists( __DIR__ . '/wp-config-local.php' ) ) {
	require_once __DIR__ . '/wp-config-local.php';
}

define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'change_me_wp_db_name' );
}

/** Database username */
if ( ! defined( 'DB_USER' ) ) {
	define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'change_me_wp_db_user' );
}

/** Database password */
if ( ! defined( 'DB_PASSWORD' ) ) {
	define( 'DB_PASSWORD', getenv( 'WP_DB_PASSWORD' ) ?: 'change_me_wp_db_password' );
}

/** Database hostname */
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ?: '127.0.0.1' );
}

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', getenv( 'WP_AUTH_KEY' ) ?: 'change_me_auth_key' );
}
if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
	define( 'SECURE_AUTH_KEY', getenv( 'WP_SECURE_AUTH_KEY' ) ?: 'change_me_secure_auth_key' );
}
if ( ! defined( 'LOGGED_IN_KEY' ) ) {
	define( 'LOGGED_IN_KEY', getenv( 'WP_LOGGED_IN_KEY' ) ?: 'change_me_logged_in_key' );
}
if ( ! defined( 'NONCE_KEY' ) ) {
	define( 'NONCE_KEY', getenv( 'WP_NONCE_KEY' ) ?: 'change_me_nonce_key' );
}
if ( ! defined( 'AUTH_SALT' ) ) {
	define( 'AUTH_SALT', getenv( 'WP_AUTH_SALT' ) ?: 'change_me_auth_salt' );
}
if ( ! defined( 'SECURE_AUTH_SALT' ) ) {
	define( 'SECURE_AUTH_SALT', getenv( 'WP_SECURE_AUTH_SALT' ) ?: 'change_me_secure_auth_salt' );
}
if ( ! defined( 'LOGGED_IN_SALT' ) ) {
	define( 'LOGGED_IN_SALT', getenv( 'WP_LOGGED_IN_SALT' ) ?: 'change_me_logged_in_salt' );
}
if ( ! defined( 'NONCE_SALT' ) ) {
	define( 'NONCE_SALT', getenv( 'WP_NONCE_SALT' ) ?: 'change_me_nonce_salt' );
}
if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', getenv( 'WP_CACHE_KEY_SALT' ) ?: 'change_me_cache_key_salt' );
}


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

if ( ! defined( 'FS_METHOD' ) ) {
	define( 'FS_METHOD', 'direct' );
}
if ( ! defined( 'COOKIEHASH' ) ) {
	define( 'COOKIEHASH', getenv( 'WP_COOKIEHASH' ) ?: md5( (string) ( getenv( 'WP_DB_NAME' ) ?: 'local' ) ) );
}
if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
	define( 'WP_AUTO_UPDATE_CORE', 'minor' );
}
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

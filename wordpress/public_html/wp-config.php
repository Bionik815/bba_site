<?php
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
define( 'DB_NAME', 'u632291655_CIS0T' );

/** Database username */
define( 'DB_USER', 'u632291655_O8isY' );

/** Database password */
define( 'DB_PASSWORD', 'FUh7tIXmE1' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

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
define( 'AUTH_KEY',          'ED1%@Uk(xWyx)Op<O4}L>QqX,FLH?A?)0 vrKVJVNCNnJ)/#^SS$Bg~1(uQ& &o3' );
define( 'SECURE_AUTH_KEY',   'POyQMP/7FxwTOHH7ld{9n*aBf}%-2&FJw]wr2^ <wI#zc!*w@ A1=HyJM`F<=i<O' );
define( 'LOGGED_IN_KEY',     'Aq;9y<3KEhAPnVCWhYZge5INPM+,eH7QJD}sT]0Q@Yj.]P~jb2fvY%T,xQ}bzbZ6' );
define( 'NONCE_KEY',         '*F~vK?Tz7)pmu~mHl~<hp[T#X~=W~fzT_G78)eS)v LH]3|;~U@Fg2#~b+aq0Dh{' );
define( 'AUTH_SALT',         'PsX<|z_k;.)gU3wmX_PTiX{h:Tx@e|ySAx<e|>;iHJb!dr-8}fFdI`1#_FEQx7Dt' );
define( 'SECURE_AUTH_SALT',  '.+6p;T i3[3ykvqXs[U$bamZbb?r_wj(?N{2ea]ef{7,#$6>4ATo*zmxb_Z~w,^|' );
define( 'LOGGED_IN_SALT',    'Q7ihTI;KFe/{?S&zKAfrYtau#VKka3S?{7N)owCV64$9aFCC7&h&)m1*/#)_1P]K' );
define( 'NONCE_SALT',        'F1$-e5U_!V%]VUwaLZ.R_2>=~ZeUiju?VH[X{h3Utkx}zcpsPQjctq7]K5pViy]`' );
define( 'WP_CACHE_KEY_SALT', 'K&b^p$GBH&!h^aK?qXO%=/4YT,?2R/z1%-h@8Kt/0+QTkPJDlraPW^{RE9C<:D2j' );


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

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '6e577a1441f636e49fcf31deaa190438' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

<?php
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
define( 'DB_NAME', 'sgd_db' );

/** Database username */
define( 'DB_USER', 'sgd_user' );

/** Database password */
define( 'DB_PASSWORD', 'sgd_pass' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',          'bX]AVDD|{p-@L+T_!>Rj#T:.B]wp,L?,0I[{=lsG?<U%KN-!LL>9}}Ak*h!XTe#q' );
define( 'SECURE_AUTH_KEY',   '/+{2Zt(x/<r=Qor,F2mIg7#9UE>h+wzz7}cz_%%DgE]V}:RX[^qY#1T2[<{B?>9S' );
define( 'LOGGED_IN_KEY',     'd4U_TZ{0>(oLgR~6?tc7i!T)rWt8YZ&W2[gE8{.PPIYP%88W~iyw_;1Z-FEMWi!,' );
define( 'NONCE_KEY',         'CiCc[W_UjoRQxLJi_z|qY S]0hB9Fx`LTg2nElXaC;>uPO@?cEo;i:ud%Xj0;i4p' );
define( 'AUTH_SALT',         'y9l|yVc{zzR^&8>D.D:%=$21U%HjI&;)@+3Dfwz].cI=zmg>{V6q~eV~tCHl$LLk' );
define( 'SECURE_AUTH_SALT',  '+L+@pvDjP8~SK&84y~<5XdXZM627fd9N@Rv9,7*0C~u5T56O@L%(uOY%fCuX%eB^' );
define( 'LOGGED_IN_SALT',    'fa3vmAV1[dbFXsM?w$PYuH$OsP P7~{my32^GIN%U=vYzInY<_]>(mCpbNPXc&OL' );
define( 'NONCE_SALT',        ':2R`0:8+yD|MB$Wgmg@-kamy1l&t8cnjRSt}nKFPX_aismw-]{Cr;i^KHCAL|_Z#' );
define( 'WP_CACHE_KEY_SALT', 'y.0i#onsY8@yw27)V~%p+91[gi6oHJ}7-@v2Pa_tHN5SWE61:1q4p&#WyD|i)H=,' );


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

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

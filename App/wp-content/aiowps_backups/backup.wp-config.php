<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// Enable WP_DEBUG mode
#define( 'WP_DEBUG', true );

// Enable Debug logging to the /wp-content/debug.log file
#define( 'WP_DEBUG_LOG', true );

// Disable display of errors and warnings
#define( 'WP_DEBUG_DISPLAY', true );
#@ini_set( 'display_errors', 1 );

// Use dev versions of core JS and CSS files (only needed if you are modifying these core files)
#define( 'SCRIPT_DEBUG', true ); 

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'redeWP' );
/** MySQL database username */
define('DB_USER', 'admin' );
/** MySQL database password */
define('DB_PASSWORD', 'ytCoagrtyr9T6Gh6' );
/** MySQL hostname */
define('DB_HOST', 'ftsa-prod-moodle.ctpfam86ebqf.us-east-2.rds.amazonaws.com' );
/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8' );
$table_prefix  = 'wp8e_';
/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '' );
define('WP_REDIS_PORT', 6379);
define('WP_CACHE_KEY_SALT','wpcursos_');
define('WP_REDIS_HOST', 'elasticache-ftsa-prod.so8wyy.0001.use2.cache.amazonaws.com');
define('WP_HOME', 'https://cursos.ftsa.edu.br');
define('WP_SITEURL', 'https://cursos.ftsa.edu.br');
define('WP_DEBUG', false);
define('FS_METHOD', 'direct' );
define('FORCE_SSL_ADMIN', true);
$_SERVER['SERVER_PORT'] = 443;
// in some setups HTTP_X_FORWARDED_PROTO might contain
// a comma-separated list e.g. http,https
// so check for https existence
if (strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false)
$_SERVER['HTTPS']='on';
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'f-bo&IH`UXxPe*)Cz=5EQpQ!vR7M]IK44%KpkA[{-9bu+9W4&/:-Ru+lb-`3,. ^');
define('SECURE_AUTH_KEY',  ':,z[F~i+aoL c()7c&!ADTJ|;-`4t5kdDVtjwwNXM_?|ax&X}X+wy$:FOsDUWYHw');
define('LOGGED_IN_KEY',    '~YO5BzLsKujHF/riO+!ugOi@Key|1YP%~IBriPhK.RMDBhGs^HQ(qt0r:X*sMU w');
define('NONCE_KEY',        'eNb}w`t$|+&WH+U^EZexIZ)Ng9v-|mJ3Q.7YWtAIFzBETs;j:7)8 HigOs|8Ig@F');
define('AUTH_SALT',        '3=obula|.u?k+R +Zf=db!KHw.k1_t-;IjR2U<}[xcBwgN`(J ,D:+aUAl_|i^B<');
define('SECURE_AUTH_SALT', '@^w*s5&bmJK>)V|lt1gwm!jq#AvC<gr<V5xRw=,oY}~-gvLH>|B02QuG6v4K:8ou');
define('LOGGED_IN_SALT',   'PT/GV;]A7*XjZCF`1We /2#/+EY-.i>4(tmW`+r{T^+b-=#G5{+>kWV<0ZLK?wu/');
define('NONCE_SALT',       '43MPq{72guU!7z7-h[W -^x#ztA:N5,+ckra=NA_J8`$F Oyq84IFkO0-ibWo>Q`');        /**#@-*/
/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', true );
$_SERVER['HTTPS']='on';
define('FORCE_SSL_ADMIN', true);
define('RELOCATE', TRUE);
/* That's all, stop editing! Happy publishing. */
/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}
require_once( ABSPATH . 'wp-settings.php' );

<?php
/**
 * The base configuration for WordPress
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 * @package WordPress
 */

/* ============================================================
 *  CORE: Database / Cache / URLs
 * ============================================================ */
define('DB_NAME', '' );
define('DB_USER', '' );
define('DB_PASSWORD', '' );
define('DB_HOST', '' );
define('DB_CHARSET', 'utf8' );
$table_prefix  = '';

define('DB_COLLATE', '' );
define('WP_REDIS_PORT', 6379);
define('WP_CACHE_KEY_SALT','');
define('WP_REDIS_HOST', '');
define('WP_HOME', '');
define('WP_SITEURL', '');

/* ============================================================
 *  DEBUG
 * ============================================================ */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Ajusta o nível de erro para não registrar E_DEPRECATED
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

// Garantir que não mostre erros na tela em produção
@ini_set( 'display_errors', 0 );

/* ============================================================
 *  SSL / PROXY
 * ============================================================ */
define('FS_METHOD', 'direct' );
define('FORCE_SSL_ADMIN', true);
$_SERVER['SERVER_PORT'] = 443;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) {
    $_SERVER['HTTPS'] = 'on';
}
$_SERVER['HTTPS']='on';

/* ============================================================
 *  AUTH KEYS / SALTS
 *  (mantidos conforme informado)
 * ============================================================ */
define('AUTH_KEY',         'f-bo&IH`UXxPe*)Cz=5EQpQ!vR7M]IK44%KpkA[{-9bu+9W4&/:-Ru+lb-`3,. ^');
define('SECURE_AUTH_KEY',  ':,z[F~i+aoL c()7c&!ADTJ|;-`4t5kdDVtjwwNXM_?|ax&X}X+wy$:FOsDUWYHw');
define('LOGGED_IN_KEY',    '~YO5BzLsKujHF/riO+!ugOi@Key|1YP%~IBriPhK.RMDBhGs^HQ(qt0r:X*sMU w');
define('NONCE_KEY',        'eNb}w`t$|+&WH+U^EZexIZ)Ng9v-|mJ3Q.7YWtAIFzBETs;j:7)8 HigOs|8Ig@F');
define('AUTH_SALT',        '3=obula|.u?k+R +Zf=db!KHw.k1_t-;IjR2U<}[xcBwgN`(J ,D:+aUAl_|i^B<');
define('SECURE_AUTH_SALT', '@^w*s5&bmJK>)V|lt1gwm!jq#AvC<gr	V5xRw=,oY}~-gvLH>|B02QuG6v4K:8ou');
define('LOGGED_IN_SALT',   'PT/GV;]A7*XjZCF`1We /2#/+EY-.i>4(tmW`+r{T^+b-=#G5{+>kWV<0ZLK?wu/');
define('NONCE_SALT',       '43MPq{72guU!7z7-h[W -^x#ztA:N5,+ckra=NA_J8`$F Oyq84IFkO0-ibWo>Q`');

/* ============================================================
 *  MISC
 * ============================================================ */
define('RELOCATE', true);

/* ==== CIELO E-commerce (API 3.0) DESATIVADO ==== */
/*
 * Para operar exclusivamente por Checkout, NÃO definir as constantes abaixo.
 * Caso existam, alguns plugins podem tentar consultar o endpoint E-commerce.
 *
 * // define('CIELO_MERCHANT_ID',  'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
 * // define('CIELO_MERCHANT_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
 */

/* ==== CIELO Checkout (OAuth2) ATIVO ==== */
if (!defined('CIELO_CHECKOUT_CLIENT_ID')) {
  define('CIELO_CHECKOUT_CLIENT_ID', '26fa3362-4d15-4dba-93cd-c65f88e788d8'); // Produção
}
if (!defined('CIELO_CHECKOUT_CLIENT_SECRET')) {
  define('CIELO_CHECKOUT_CLIENT_SECRET', 'eIltF14xsRTx8VdoOrEY4P5mYP7ZKf4XjktXCzKdfdg='); // Produção
}

/* Forçar ambiente de produção do Checkout (false = produção, true = sandbox) */
if (!defined('FTSA_CHECKOUT_SANDBOX')) {
  define('FTSA_CHECKOUT_SANDBOX', false);
}

/* (Opcional) Sinalizador explícito para plugins próprios: “Checkout-only” */
if (!defined('FTSA_CIELO_FORCE_CHECKOUT_ONLY')) {
  define('FTSA_CIELO_FORCE_CHECKOUT_ONLY', true);
}

/* ============================================================
 *  BOOTSTRAP
 * ============================================================ */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}
if (!defined('WP_CACHE')) {
  define('WP_CACHE', true);
}
require_once( ABSPATH . 'wp-settings.php' );

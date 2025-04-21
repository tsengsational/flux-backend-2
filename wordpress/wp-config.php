<?php
/**
 * The base configuration for WordPress
 */

// ** Database settings - You can get this info from your web host ** //
define('DB_NAME', 'wordpress');
define('DB_USER', 'j_tseng-wp');
define('DB_PASSWORD', 'spiders_are_friends_4%');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 */
define('AUTH_KEY',         'v1B;|cmcY}|.3h&Vq|>]wB}=d6k{PBAX1E<yOvq#_0X_!2GUlCp.d6o-4gGy^3UD');
define('SECURE_AUTH_KEY',  'C,VdQ/Y7*fJDw]5T|b ]i838e&aKhD-,j8~sIKg%g]HJr<-9~?;p@VV33(]>vb~l');
define('LOGGED_IN_KEY',    'p>@I@qTrFfg=Z4dt(auWt-?74fgD]-#L mDxC1&I|?y93k!9hbu2~z:uz43/LQI,');
define('NONCE_KEY',        'FO%^)m.Br-jZ>*PbK!5q<pm/*z9OFIz< 1xU2Fu;-yjq):fvDXvw,;VI6~f K5^_');
define('AUTH_SALT',        'p^g|6^(k4x*Q_|U@e/j|.X-N/NQ#KsX3$O:1N+;l.w`[NDKFd3813%MTa+4USc6_');
define('SECURE_AUTH_SALT', 'Hv`j9ZSZkgrMMw^]>Z;XCqw>oL|aJ>?<dS&x8|3]|rY?wCcK?jj ?OCP2@d +vnK');
define('LOGGED_IN_SALT',   '5/J]7b;hL_9!vY9bB7K/IO>,^a9*TWKo=~+6BIcjY,g-!B11pw02rn<cX=`*+yR-');
define('NONCE_SALT',       '[6[ [N&on!st5`W$~4)`8IMv+ZGOf-w-@O&{^fGJ,NAW|V~/<1148[!^P SLmlM)');

/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 */
define('WP_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */

// If we're behind a proxy server and using HTTPS, we need to alert WordPress of that fact
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) {
    $_SERVER['HTTPS'] = 'on';
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
    define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php'); 
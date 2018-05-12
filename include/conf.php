<?php

/*
 * NOTE: this was written for php7. It will not work on php5 or lower without modifications.
 */

/*
 * Basic site settings.
 */
define("SITE_NAME", "zWeb");
define("SITE_URL", "http://localhost");

/*
 * Email settings.
 */
define("EMAIL_FROM", "localhost");
define("EMAIL_ADDR", "noreply@localhost");

/*
 * These are used for cookies and whatnot.
 */
define("SITE_DOMAIN", "localhost");
define("SITE_HTPATH", "/");
define("SITE_HTTPSONLY", false);

/*
 * Your google analytics id.
 */
define("SITE_GOOGLE_ANALYTICS_ID", "ID");
define("GOOGLE_RECAPTCHA_PRIVATE_KEY", "PRIVATEKEY");
define("GOOGLE_RECAPTCHA_PUBLIC_KEY", "PUBLICKEY");

/*
 * Set to year of launch or gmdate("Y").
 * Example: set to 2018, in 2018 copyright will show 2018, in 2019 it'll show 2018-2019.
 * If you set to gmdate("Y") it will always only show current year.
 */
define("SITE_COPYRIGHT", 2018);

/*
 * Set dir names for css and js directories.
 */
define("SITE_CSSDIR", "css");
define("SITE_JSDIR", "js");

/*
 * Rewrite or not.
 * Example: auth.php?page=login vs. auth/login
 * Requires correct server config.
 */
define("SITE_REWRITE", false);

/*
 * Pagination
 */
define("FORUM_POSTSPPAGE", 15);

?>
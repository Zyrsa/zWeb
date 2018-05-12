<?php

define("__ROOT__", dirname(__FILE__));

require_once(__ROOT__."/include/conf.php");
require_once(__ROOT__."/include/db.php");
require_once(__ROOT__."/include/auth.php");
require_once(__ROOT__."/include/core.php");
require_once(__ROOT__."/include/html.php");

$core = new core();
$db = new db();
$auth = new auth($db);
$html = new html($auth->uid);

/*
 * Articles
 */
if (!isset($core->get["page"])) {   
    $pagestr = "<p>articles</p>";
    $secret = $auth->mksecret(64);
    $password = $auth->mksecret(32);
    $hash = hash_hmac("sha512", $password, $secret);
    $crypt = $auth->generate_hash($password, 14);
    $pagestr .= "<p>password (". strlen($password) ."): ". $password ."<br>
        secret (". strlen($secret) ."): ". $secret ."<br>
        hash (". strlen($hash) ."): ". $hash ."<br>
        crypt (". strlen($crypt) ."): ". $crypt ." (". ($auth->is_valid_pw($password, $crypt) ? "valid" : "invalid") .")</p>";
    $html->makehtml("Articles", $pagestr);
    die();
}
/*
 * About
 */
elseif ($core->get["page"] == "about") {
    $pagestr = "<p>about</p>";
    $html->makehtml("About", $pagestr);
    die();
}
/*
 * Contact
 */
elseif ($core->get["page"] == "contact") {
    $pagestr = "<p>contact</p>";
    $html->makehtml("Contact", $pagestr);
    die();
}
/*
 * 404
 */
else {
    $html->make404();
    die();
}

die();

?>
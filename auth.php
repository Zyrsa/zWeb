<?php

/*
 * TODO: change &email=$email to &param=$email everywhere.
 */

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
 * Login
 */
if (!isset($core->get["page"])) {
    if ($auth->uid  > 0) {
        $html->makehtml("Login", "<p>You are already logged in.</p>");
        die();
    }    
    $pagestr = "
<form method=\"post\" action=\"". $html->format_link("auth.php?page=dologin") ."\" id=\"loginform\">
    <div class=\"formcontainer\">
        <div class=\"formfield\">
            <input name=\"user\" data-validation=\"length alphanumeric\" placeholder=\"Username\" required=\"required\"
                data-validation-length=\"3-32\"
                data-validation-error-msg=\"Must be alphanumeric (3-32 chars)\"
                data-validation-error-msg-container=\"#usererror\">
        </div>
        <div id=\"usererror\"></div>
        <div class=\"formfield\">
            <input name=\"passw\" type=\"password\" data-validation=\"length\" placeholder=\"Password\" required=\"required\"
                data-validation-length=\"min8\"
                data-validation-error-msg-container=\"#passworderror\">
        </div>
        <div id=\"passworderror\"></div>
        <div class=\"formsubmit\">
            <input type=\"submit\" value=\"Login\">
        </div>
    </div>
</form>
<p class=\"center\">You can <a href=\"". $html->format_link("auth.php?page=register") ."\">sign up here</a>.<br/>
I need to <a href=\"". $html->format_link("auth.php?page=reset") ."\">reset my password</a> or
<a href=\"". $html->format_link("auth.php?page=resend") ."\">resend validation email</a>.</p>
<script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
<script src='https://www.google.com/recaptcha/api.js'></script>
<script>
$.validate({
    modules : 'html5, security'
});
</script>";
    
    $html->makehtml("Login", $pagestr);
    unset($pagestr);
    die();
}
elseif ($core->get["page"] == "dologin") {
    if ($auth->uid  > 0) {
        $html->makehtml("Login", "<p>You are already logged in.</p>");
        die();
    }
    $user = $core->post["user"];
    $passw = $core->post["passw"];
    
    $row = $db->select_row("SELECT id, passhash, status FROM users WHERE user = ? LIMIT 1", array($user), "s");
    if (!$row) {
        $html->makehtml("Login", "<p>Login failed. Check login details.</p>");
        die();
    }
    $uid = $row->id;
    $passhash = $row->passhash;
    $status = $row->status;

    if (!$auth->is_valid_pw($passw, $passhash)) {
        $html->makehtml("Login", "<p>Login failed. Check login details.</p>");
        die();
    }
    if ($status == "pending") {
        $html->makehtml("Login", "<p>Your account is still pending admin review.</p>");
        die();
    }
    if ($status == "suspended") {
        $html->makehtml("Login", "<p>Your account is suspended.</p>");
        die();
    }
    /*
     * Success
     */
    $html->login($uid);
    $sesshash = $auth->generate_hash($auth->mksecret(32));
    $db->update("UPDATE users SET sesshash = ?, lastlogin = ? WHERE id = ? LIMIT 1", array($sesshash, time(), $uid), "sii");
    $auth->do_login($uid, $passhash, $sesshash);
    /*
     * Do something
     */
    header("Location: ". SITE_URL . SITE_HTPATH);
    $html->makehtml("Login", "<p>You have now logged in.</p>");
    die();
}
/*
 * Account registration
 */
elseif ($core->get["page"] == "register") {
    if ($auth->uid  > 0) {
        $html->makehtml("Register", "<p>You are already logged in.</p>");
        die();
    }
    $pagestr = "
<form method=\"post\" action=\"". $html->format_link("auth.php?page=doregister") ."\" id=\"registerform\">
    <div class=\"formcontainer\">
        <div class=\"formfield\">
            <input name=\"user\" data-validation=\"length alphanumeric\" placeholder=\"Username\" required=\"required\"
                data-validation-length=\"3-32\"
                data-validation-error-msg=\"Must be alphanumeric (3-32 chars)\"
                data-validation-error-msg-container=\"#usererror\">
        </div>
        <div id=\"usererror\"></div>
        <div class=\"formfield\">
            <input name=\"email\" data-validation=\"email length\" placeholder=\"Email\" required=\"required\"
                data-validation-length=\"max128\"
                data-validation-error-msg-container=\"#emailerror\">
        </div>
        <div id=\"emailerror\"></div>
        <div class=\"formfield\">
            <input name=\"passw\" type=\"password\" data-validation=\"length strength\" placeholder=\"Password\" required=\"required\"
                data-validation-length=\"min8\" data-validation-strength=\"2\"
                data-validation-error-msg-container=\"#passworderror\">
        </div>
        <div id=\"passworderror\"></div>
        <div class=\"formfield\">
            <input name=\"passagain\" type=\"password\" data-validation=\"confirmation\" data-validation-confirm=\"passw\" placeholder=\"Password again\" required=\"required\"
                data-validation-error-msg=\"Passwords do not match\"
                data-validation-error-msg-container=\"#passagainerror\">
        </div>
        <div id=\"passagainerror\"></div>
        <div class=\"g-recaptcha\" data-theme=\"dark\" data-sitekey=\"". GOOGLE_RECAPTCHA_PUBLIC_KEY ."\"></div>
        <div class=\"formsubmit\">
            <input type=\"submit\" value=\"Register\">
        </div>
    </div>
</form>
<script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
<script src='https://www.google.com/recaptcha/api.js'></script>
<script>
$.validate({
    modules : 'html5, security'
});
</script>";
    
    $html->makehtml("Register", $pagestr);
    unset($pagestr);
    die();
}
elseif ($core->get["page"] == "doregister") {
    if ($auth->uid  > 0) {
        $html->makehtml("Register", "<p>You are already logged in.</p>");
        die();
    }
    $user = $core->post["user"];
    $email = $core->post["email"];
    $passw = $core->post["passw"];
    $passagain = $core->post["passagain"];
    
    $post_data = "secret=". GOOGLE_RECAPTCHA_PRIVATE_KEY ."&response=".
    $core->post["g-recaptcha-response"] ."&remoteip=". $_SERVER["REMOTE_ADDR"];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER,
        array("Content-Type: application/x-www-form-urlencoded; charset=utf-8",
            "Content-Length: " . strlen($post_data)));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $googresp = curl_exec($ch);
    $decgoogresp = json_decode($googresp);
    curl_close($ch);
        
    if ($decgoogresp->success == false) {
        $html->makehtml("Register", "<p>reCAPTCHA failed.</p>");
        die();
    }
    if (!ctype_alnum($user) || strlen($user) < 3 || strlen($user) > 32) {
        $html->makehtml("Register", "<p>Username needs to be alphanumeric and between 3 and 32 charachters long.</p>");
        die();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) {
        $html->makehtml("Register", "<p>Email appears to be invalid or too long.</p>");
        die();
    }
    if ($passw !== $passagain) {
        $html->makehtml("Register", "<p>Passwords don't match.</p>");
        die();
    }
    if (strlen($passw) < 8) {
        $html->makehtml("Register", "<p>Password needs to be at least 8 charachters long.</p>");
        die();
    }
    if (strlen($passw) > 72) {
        $html->makehtml("Register", "<p>Password needs to be at most 72 charachters long.</p>");
        die();
    }
    if ($db->select_row("SELECT id FROM users WHERE user = ? LIMIT 1", array($user), "s")) {
        $html->makehtml("Register", "<p>Username already in use.</p>");
        die();
    }
    if ($db->select_row("SELECT id FROM users WHERE email = ? LIMIT 1", array($email), "s")) {
        $html->makehtml("Register", "<p>Email already in use.</p>");
        die();
    }
       
    $passhash = $auth->generate_hash($passw, 14);
    
    $editsecret = $auth->mksecret(16);
    $edittime = time();
    
    /*
     * I don't actually like using {} on single-line if-statements but my IDE fucks up without so. :/
     */
    if (SITE_REWRITE) {
        $confurl = SITE_URL . SITE_HTPATH ."auth/confirm/". $editsecret ."/". $email;
    }
    else {
        $confurl = SITE_URL . SITE_HTPATH ."auth.php?page=confirm&action=". $editsecret ."&email=". $email;
    }
    /*
     * Do the thing
     */
    $to = $user ." <". $email .">";
    $subject = "Account registration";
    $message = "Please verify your email address by visiting the url below:\r\n". $confurl ."\r\n\r\nIf this was sent in error please ignore this email.";
    $message = wordwrap($message, 70, "\r\n");
    $headers = "From: ". EMAIL_FROM ." <". EMAIL_ADDR .">" . "\r\n";
    
    mail($to, $subject, $message, $headers);

    if (!$db->insert("INSERT INTO users (user, passhash, email, editsecret, edittime, registered) VALUES (?, ?, ?, ?, ?, ?)", array($user, $passhash, $email, $editsecret, $edittime, time()), "ssssii")) {
        $html->makehtml("Register", "<p>Account creation failed.</p>");
        die();
    }
    $html->makehtml("Register", "<p>Account registered. An email has been sent to your email address, please validate your email by visiting the link in the email before you can login. Remember to check spam folders/all mail folders. The link will be valid for 15 minutes from now. If the link expires before you have a chance to validate your email address you can request that the validation email be resent.</p>");
    die();
}
/*
 * Logout
 */
elseif ($core->get["page"] == "logout") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Logout", "<p>You are not logged in.</p>");
        die();
    }
    $db->update("UPDATE users SET sesshash = NULL WHERE id = ? LIMIT 1", array($auth->uid), "i");
    $auth->do_login();
    $html->logout();
    $html->makehtml("Logout", "<p>You have been logged out.</p>");
    die();
}
/*
 * Validate email address
 */
elseif ($core->get["page"] == "confirm") {
    if ($auth->uid  > 0) {
        $html->makehtml("Validate email", "<p>You are already logged in.</p>");
        die();
    }
    $attempt = $core->get["action"];
    $email = $core->get["email"];
    
    $row = $db->select_row("SELECT id, editsecret, edittime, status FROM users WHERE email = ? LIMIT 1", array($email), "s");
    if (!$row) {
        $html->makehtml("Validate email", "<p>Invalid link.</p>");
        die();
    }
    $uid = $row->id;
    $editsecret = $row->editsecret;
    $edittime = $row->edittime;
    $status = $row->status;
    
    if ($edittime + 900 < time()) {
        $html->makehtml("Validate email", "<p>Link has expired.</p>");
        die();
    }
    if ($status != "pending") {
        $html->makehtml("Validate email", "<p>Email already verified.</p>");
        die();
    }
    if ($attempt != $editsecret) {
        $html->makehtml("Validate email", "<p>Invalid link.</p>");
        die();
    }

    if (!$db->update("UPDATE users SET editsecret = NULL, edittime = NULL, status = 'confirmed' WHERE id = ? LIMIT 1", array($uid), "i")) {
        $html->makehtml("Validate email", "<p>Failed to validate account.</p>");
        die();
    }
    $html->makehtml("Validate email", "<p>Email validated, you can now login.</p>");
    die();
}
/*
 * Reset password
 */
elseif ($core->get["page"] == "reset") {
    if ($auth->uid  > 0) {
        $html->makehtml("Reset password", "<p>You are already logged in.</p>");
        die();
    }
    $pagestr = "
<form method=\"post\" action=\"". $html->format_link("auth.php?page=doreset") ."\" id=\"resetform\">
    <div class=\"formcontainer\">
        <div class=\"formfield\">
            <input name=\"user\" data-validation=\"length alphanumeric\" placeholder=\"Username\" required=\"required\"
                data-validation-length=\"3-32\"
                data-validation-error-msg=\"Must be alphanumeric (3-32 chars)\"
                data-validation-error-msg-container=\"#usererror\">
        </div>
        <div id=\"usererror\"></div>
        <div class=\"formfield\">
            <input name=\"email\" data-validation=\"email length\" placeholder=\"Email\" required=\"required\"
                data-validation-length=\"max128\"
                data-validation-error-msg-container=\"#emailerror\">
        </div>
        <div id=\"emailerror\"></div>
        <div class=\"formsubmit\">
            <input type=\"submit\" value=\"Reset\">
        </div>
    </div>
</form>
<script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
<script src='https://www.google.com/recaptcha/api.js'></script>
<script>
$.validate({
    modules : 'html5, security'
});
</script>";
    
    $html->makehtml("Reset password", $pagestr);
    unset($pagestr);
    die();
}
elseif ($core->get["page"] == "doreset") {
    if ($auth->uid  > 0) {
        $html->makehtml("Reset password", "<p>You are already logged in.</p>");
        die();
    }
    $user = $core->post["user"];
    $email = $core->post["email"];
    $uid = 0;
    
    $row = $db->select_row("SELECT id FROM users WHERE user = ? AND email = ? LIMIT 1", array($user, $email), "ss");
    if (!$row) {
        $html->makehtml("Reset password", "<p>Account not found.</p>");
        die();
    }
    $uid = $row->id;
    
    if (!ctype_alnum($user) || strlen($user) < 3 || strlen($user) > 32) {
        $html->makehtml("Reset password", "<p>Invalid username.</p>");
        die();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) {
        $html->makehtml("Reset password", "<p>Email appears to be invalid or too long.</p>");
        die();
    }
    $passw = $auth->mksecret(16);
    $passhash = $auth->generate_hash($passw, 14);
    
    $editsecret = $auth->mksecret(16);
    $edittime = time();
                       
    $to = $user ." <". $email .">";
    $subject = "Account password reset";
    $message = "Your new login details:\r\nUsername: ". $user ."\r\nPassword: ". $passw ."\r\n\r\nIf this was sent in error please ignore this email.";
    $message = wordwrap($message, 70, "\r\n");
    $headers = "From: ". EMAIL_FROM ." <". EMAIL_ADDR .">" . "\r\n";
    
    mail($to, $subject, $message, $headers);
    
    if (!$db->update("UPDATE users SET passhash = ?, sesshash = NULL WHERE id = ? LIMIT 1", array($passhash, $uid), "si")) {
        $html->makehtml("Reset password", "<p>Failed to update account details.</p>");
        die();
    }
    $html->makehtml("Reset password", "<p>An email has been sent to your email address with your new login details.</p>");
    die();
}
/*
 * Resend validation email
 */
elseif ($core->get["page"] == "resend") {
    if ($auth->uid  > 0) {
        $html->makehtml("Resend email", "<p>You are already logged in.</p>");
        die();
    } 
    $pagestr = "
<form method=\"post\" action=\"". $html->format_link("auth.php?page=doresend") ."\" id=\"resendform\">
    <div class=\"formcontainer\">
        <div class=\"formfield\">
            <input name=\"email\" data-validation=\"email length\" placeholder=\"Email\" required=\"required\"
                data-validation-length=\"max128\"
                data-validation-error-msg-container=\"#emailerror\">
        </div>
        <div id=\"emailerror\"></div>
        <div class=\"formsubmit\">
            <input type=\"submit\" value=\"Resend\">
        </div>
    </div>
</form>
<script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
<script src='https://www.google.com/recaptcha/api.js'></script>
<script>
$.validate({
    modules : 'html5, security'
});
</script>";
    
    $html->makehtml("Resend email", $pagestr);
    unset($pagestr);
    die();
}
elseif ($core->get["page"] == "doresend") {
    if ($auth->uid  > 0) {
        $html->makehtml("Resend email", "<p>You are already logged in.</p>");
        die();
    }
    $email = $core->post["email"];
    $uid = 0;
    $user = "";
    $status = "";
    $edittime = 0;
    
    $row = $db->select_row("SELECT id, user, status, edittime FROM users WHERE email = ? LIMIT 1", array($email), "s");
    if (!$row) {
        $html->makehtml("Resend email", "<p>Account not found.</p>");
        die();
    }
    $uid = $row->id;
    $user = $row->user;
    $status = $row->status;
    $edittime = $row->edittime;
        
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) {
        $html->makehtml("Resend email", "<p>Email appears to be invalid or too long.</p>");
        die();
    }
    if ($status != "pending") {
        $html->makehtml("Resend email", "<p>Email already validated.</p>");
        die();
    }
    if ($edittime + 900 > time()) {
        $html->makehtml("Resend email", "<p>Previous validation email is still current. Remember to check spam folders/all mail folders for the validation email. Please wait 15 minutes since the last email was sent to request a new validation email.</p>");
        die();
    }         
    $editsecret = $auth->mksecret(16);
    $edittime = time();
    
    if (SITE_REWRITE) {
        $confurl = SITE_URL . SITE_HTPATH ."auth/confirm/". $editsecret ."/". $email;
    }
    else {
        $confurl = SITE_URL . SITE_HTPATH ."auth.php?page=confirm&action=". $editsecret ."&email=". $email;
    }
        
    $to = $user ." <". $email .">";
    $subject = "Account registration";
    $message = "Please verify your email address by visiting the url below:\r\n". $confurl ."\r\n\r\nIf this was sent in error please ignore this email.";
    $message = wordwrap($message, 70, "\r\n");
    $headers = "From: ". EMAIL_FROM ." <". EMAIL_ADDR .">" . "\r\n";
    
    mail($to, $subject, $message, $headers);
    
    if (!$db->update("UPDATE users SET editsecret = ?, edittime = ? WHERE id = ? LIMIT 1", array($editsecret, $edittime, $uid), "sii")) {
        $html->makehtml("Resend email", "<p>Failed to update email validation link.</p>");
        die();
    }
    $html->makehtml("Resend email", "<p>An email has been sent to your email address, please visit the link there in within 15 minutes to validate your email address before you can login.</p>");
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
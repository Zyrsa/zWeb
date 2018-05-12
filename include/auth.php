<?php

require_once(__ROOT__."/include/conf.php");

class auth {
    public $uid = 0;
    public $user = NULL;
    public $userlvl = 0;
    private $db = NULL;
    
    public function __construct($db) {
        $this->db = $db;
        if (isset($_COOKIE["uid"]) && $_COOKIE["uid"] > 0) {
            $row = $this->db->select_row("SELECT user, passhash, sesshash, userlvl FROM users WHERE ID = ? LIMIT 1", array($_COOKIE["uid"]), "i");
            $user = $row->user;
            $passhash = $row->passhash;
            $sesshash = $row->sesshash;
            $userlvl = $this->make_usrlvl_int($row->userlvl);
            
            if ($_COOKIE["passhash"] === $passhash && $_COOKIE["sesshash"] === $sesshash) {
                $this->uid = 0 + $_COOKIE["uid"];
                $this->user = $user;
                $this->userlvl = $userlvl;
                
                $this->db->update("UPDATE table SET lastseen = ? WHERE id = ? LIMIT 1", array(time(), $_COOKIE["uid"]), "ii");
                $this->do_login($_COOKIE["uid"], $passhash, $sesshash);
            }
            else {
                $this->do_login();
            }
        }
    }
    public function __destruct() {
        unset($this->uid, $this->user, $this->userlvl, $this->db);
    }
    public function do_login($uid = 0, $passhash = "", $sesshash = "") {
        setcookie("uid", $uid, time()+3600, SITE_HTPATH, SITE_DOMAIN, SITE_HTTPSONLY, false);
        setcookie("passhash", $passhash, time()+3600, SITE_HTPATH, SITE_DOMAIN, SITE_HTTPSONLY, false);
        setcookie("sesshash", $sesshash, time()+3600, SITE_HTPATH, SITE_DOMAIN, SITE_HTTPSONLY, false);
        return true;
    }
	public function mktoken($len) {
		if(!isset($len) || intval($len) <= 8 ){
		  $len = 64;
		}
		if (function_exists('random_bytes')) {
			return random_bytes($len);
		}
		if (function_exists('mcrypt_create_iv')) {
			return mcrypt_create_iv($len, MCRYPT_DEV_URANDOM);
		} 
		if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($len);
		}
		return false;
	}
    public function mksecret($len) {
        if(!isset($len) || intval($len) <= 8 ){
            $len = 64;
        }
		$secret = bin2hex($this->mktoken($len));
		return $secret;
    }
    public function generate_hash($password, $cost=11) {
        if (strlen($password) > 72)
            return false;
        
        $salt=substr(base64_encode($this->mktoken(17)),0,22);
        $salt=str_replace("+",".",$salt);
        
        $param='$'.implode('$',array(
            "2y",
            str_pad($cost,2,"0",STR_PAD_LEFT),
            $salt
        ));
        return crypt($password,$param);
    }
    public function is_valid_pw($password, $hash){
        return hash_equals($hash, crypt($password, $hash)); // return crypt($password, $hash)==$hash;
    }
    /*
     * I fully realize saving user levels in the database as integers would save space and be more useful
     * since I might even want to do something similar for displayed user levels but no, for now this is
     * the way it'll be.
     */
    public function make_usrlvl_int($usrlvl) {
        switch ($usrlvl) {
            case "owner":
                return 5;
                break;
            case "admin":
                return 4;
                break;
            case "operator":
                return 3;
                break;
            case "moderator":
                return 2;
                break;
            case "user":
                return 1;
                break;
            default:
                return 1;
                break;
        }
    }
}

?>
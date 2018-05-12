<?php

/*
 * This class is just for a random collection of functions I didn't feel like putting anywhere else.
 */

class core {
    public $get = array();
    public $post = array();
    public $files = array();
    
    public function __construct() {
        if (SITE_HTTPSONLY) {
            if (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] == false) {
                header("Location: ". SITE_URL . SITE_HTPATH, true, 301);
                die();
            }
        }
        if (!preg_match("/www./i", SITE_URL)) {
            if (preg_match("/www./i", $_SERVER["SERVER_NAME"])) {
                header("Location: ". SITE_URL . SITE_HTPATH, true, 301);
                die();
            }
        }
        if (preg_match("/www./i", SITE_URL)) {
            if (!preg_match("/www./i", $_SERVER["SERVER_NAME"])) {
                header("Location: ". SITE_URL . SITE_HTPATH, true, 301);
                die();
            }
        }
        
        foreach ($_GET as $var => $arg) {
            $this->get[$var] = $arg;
        }
        foreach ($_POST as $var => $arg) {
            $this->post[$var] = $arg;
        }
        foreach ($_FILES as $var => $arg) {
            $this->files[$var] = $arg;
        }
    }
    public function __destruct() {
        unset($this->get);
        unset($this->post);
        unset($this->files);
    }
    public function get_time_since($time) {
        $time = time() - $time;
        $time = ($time<1)? 1 : $time;
        $tokens = array (
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
            1 => 'second'
        );
        
        foreach ($tokens as $unit => $text) {
            if ($time < $unit) continue;
            $numberOfUnits = floor($time / $unit);
            return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
        }
    }
    public function format_title($str) {
        if (strlen($str) > 32)
            $str = substr($str, 0, 29) ."...";
        $str = htmlentities($str);
        return $str;
    }
    public function format_text($str) {
        /*
         * Anti goof
         */
        $str = htmlentities($str, ENT_QUOTES, "UTF-8");
        
        /*
         * Lazy method
         */
        $str = preg_replace("/\[u\](.*?)\[\/u\]/i", "<span class=\"underline\">$1</span>", $str);
        $str = preg_replace("/\[img=(.*?)\](.*?)\[\/img\]/i", "<img class=\"postimg\" src=\"$1\" alt=\"$2\"/>", $str);
        $str = preg_replace("/\[img](.*?)\[\/img\]/i", "<img class=\"postimg\" src=\"$1\" alt=\"User posted image\"/>", $str);
        $str = preg_replace("/\[url=(.*?)\](.*?)\[\/url\]/i", "<a href=\"$1\" target=\"_blank\" rel=\"external\">$2</a>", $str);
        $str = preg_replace("/\[url\](.*?)\[\/url\]/i", "<a href=\"$1\" target=\"_blank\" rel=\"external\">$1</a>", $str);
        
        /*
         * Single tag simple shit
         */
        $tags = array(
            "b" => "strong",
            "i" => "em",
            "s" => "del",
            "code" => "code"
        );
        foreach ($tags as $tag => $html) {
            preg_match_all("/\[". $tag ."\]/i", $str, $matches);
            $opens = count($matches[0]);
            
            preg_match_all("/\[\/". $tag ."\]/i", $str, $matches);
            $closes = count($matches[0]);
            
            $unclosed = $opens - $closes;
            for ($i = 0; $i < $unclosed; $i++) {
                $str .= "[/". $tag ."]";
            }
            
            $str = str_replace("[". $tag ."]", "<". $html .">", $str);
            $str = str_replace("[/". $tag ."]", "</". $html .">", $str);
        }
        
        /*
         * Quotes
         */
        preg_match_all("/\[quote\]/i", $str, $matches);
        $opens = count($matches[0]);
        
        preg_match_all("/\[\/quote\]/i", $str, $matches);
        $closes = count($matches[0]);
        
        $unclosed = $opens - $closes;
        for ($i = 0; $i < $unclosed; $i++) {
            $str .= "[/quote]";
        }
        $str = str_replace ("[quote]", "<blockquote>", $str);
        $str = preg_replace("/\[quote\=(.*?)\]/i","<blockquote><cite>$1 wrote</cite>", $str);
        $str = str_replace ("[/quote]", "</blockquote>", $str);
        
        /*
         * Clean up
         */
        $str = str_replace("  ", " &nbsp;", $str);
        $str = str_replace("\t", " &nbsp; &nbsp;", $str);
        $str = str_replace("\n", "<br/>", $str);
        
        return $str;
    }
    public function is_even($int) {
        if (is_int($int) && $int % 2 == 0)
            return true;
        return false;
    }
    public function pager($ents, $page) {
        $pages = ceil($ents / FORUM_POSTSPPAGE);
        if ($page < 1)
            $page = 1;
        if ($page > $pages)
            $page = $pages;
        
        if (SITE_REWRITE) {
            $urii = "/";
            $uri = substr($_SERVER["REQUEST_URI"], 0, strripos($_SERVER["REQUEST_URI"], "/"));
        }
        else {
            $urii = "&amp;param=";
            $uri = preg_replace("/&param=([0-9]+)/i", "", $_SERVER["REQUEST_URI"]);
        }
        
        if ($page == 1) {
            $sql_limit = FORUM_POSTSPPAGE;
            $sql_offset = 0;
        }
        else {
            $sql_limit = FORUM_POSTSPPAGE;
            $sql_offset = floor(($page - 1) * FORUM_POSTSPPAGE);
        }
        
        if ($page == 1)
            $pager = "Prev &laquo;";
        else
            $pager = "<a href=\"". $uri . $urii . ($page - 1) ."\">Prev</a> &laquo;";
        for ($i = 1; $i <= $pages; $i++) {
            if ($i > 3 && $i < $pages -2 && (($i < $page && $i < $page -1) || ($i > $page && $i > $page +1))) {
                if (substr($pager, strlen($pager) -2, strlen($pager)) == " |")
                    $pager = substr($pager, 0, strlen($pager) -2);
                if (substr($pager, strlen($pager) -5, strlen($pager)) != " ... ")
                    $pager .= " ... ";
                continue;
            }
            if ($i == $page)
                $pager .= " ". $i ." |";
            else
                $pager .= " <a href=\"". $uri . $urii . $i ."\">". $i ."</a> |";
        }
        $pager = substr($pager, 0, strlen($pager) -2);
        if ($page == $pages)
            $pager .= " &raquo; Next";
        else
            $pager .= " &raquo; <a href=\"". $uri . $urii . ($page + 1) ."\">Next</a>";
        
        return array($sql_limit, $sql_offset, $pager);
    }
    public function externalpager($uri, $ents) {
        $pages = ceil($ents / FORUM_POSTSPPAGE);
        $str = "";
        if (SITE_REWRITE)
            $urii = "/";
        else
            $urii = "&amp;param=";
            
        for ($i = 1; $i <= $pages; $i++) {
            if ($i > 3 && $i < $pages -2) {
                if (substr($str, strlen($str) -3, strlen($str)) != "...")
                    $str .= "...";
                continue;
            }
            if ($str && substr($str, strlen($str) -3, strlen($str)) != "...")
                $str .= ", ";
            $str .= "<a href=\"". $uri . $urii . $i ."\">". $i ."</a>"; 
        }
        return $str;
    }
    public function findforumpage($page, $ents, $postnum = 0) {
        switch ($page) {
            case "first":
                return 1;
                break;
            case "last":
                return ceil($ents / FORUM_POSTSPPAGE);
                break;
            default:
                return 1;
                break;
        }
    }
}

?>
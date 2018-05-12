<?php

require_once(__ROOT__."/include/conf.php");

/*
 * TODO: rewrite layout using CSS grid.
 */

class html {
    public $title = NULL;
    public $pagetitle = "Error";
    private $page_content = NULL;
    private $uid = 0;
    
    public function __construct($uid) {
        $this->uid = $uid;
    }
    public function __destruct() {
        unset($this->title, $this->pagetitle, $this->page_content, $this->uid);
    }
    public function logout() {
        $this->uid = 0;
    }
    public function login($uid = 0) {
        $this->uid = $uid;
    }
    private function nav_set_current($menu) {
        if (SITE_REWRITE) {
            $request_uri = $_SERVER["REQUEST_URI"];
            $pos = strpos($menu, "\"". $request_uri ."\"");
            if ($pos)
                $menu = substr($menu, 0, $pos -5) ."class=\"current\" ". substr($menu, $pos -5);
        }
        else {
            $request_uri = str_replace(SITE_HTPATH, "", $_SERVER["REQUEST_URI"]);
            $pos = strpos($menu, "\"". $request_uri ."\"");
            if ($pos)
                $menu = substr($menu, 0, $pos -5) ."class=\"current\" ". substr($menu, $pos -5);
        }
        return $menu;
    }
    private function rewrite_uri($menu) {
        if (SITE_REWRITE) {
            $menu = str_replace("index.php", "/", $menu);
            $menu = preg_replace("/([A-Z]+).php/i", "/$1", $menu);
            $menu = preg_replace("/\?page=([A-Z]+)/i", "/$1", $menu);
            //$menu = preg_replace("/\&action=([A-Z]+)/i", "/$1", $menu);
            $menu = str_replace("//", "/", $menu);
        }
        return $menu;
    }
    private function add_menu($html) {
        if ($this->uid > 0) {
            if (!file_exists(__ROOT__."/template/authmenu.html"))
                return "error";
            $menu = file_get_contents(__ROOT__."/template/authmenu.html");
        }
        else {
            if (!file_exists(__ROOT__."/template/menu.html"))
                return "error";
            $menu = file_get_contents(__ROOT__."/template/menu.html");
        }
        $menu = $this->rewrite_uri($menu);
        $menu = $this->nav_set_current($menu);
        
        $html = str_replace("__INSERT_MENU__", $menu, $html);
        
        return $html;
    }
    private function format_template($html) {
        if (isset($this->title))
            $html = str_replace("__TITLE__", SITE_NAME ." - ". $this->title, $html);
        else
            $html = str_replace("__TITLE__", SITE_NAME, $html);
        
        $html = $this->add_menu($html);
        
        $html = str_replace("__PAGE_TITLE__", $this->pagetitle, $html);
        $html = str_replace("__SITEURL__", SITE_URL, $html);
        $html = str_replace("__SITENAME__", SITE_NAME, $html);
        $html = str_replace("__ANALYTICS_ID__", SITE_GOOGLE_ANALYTICS_ID, $html);
        $html = str_replace("__CSSD__", SITE_HTPATH . SITE_CSSDIR, $html);
        $html = str_replace("__JSD__", SITE_HTPATH . SITE_JSDIR, $html);
        $html = str_replace("__COPY_YEAR_STR__", (gmdate("Y") == SITE_COPYRIGHT) ? gmdate("Y") : SITE_COPYRIGHT ."-". gmdate("Y"), $html);
        
        $analyticsuid = "";
        if ($this->uid)
            $analyticsuid = "gtag('set', {'user_id': '". $this->uid ."'});";
        $html = str_replace("__ANALYTICS_USER_ID_STR__", $analyticsuid, $html);
        
        return $html;
    }
    public function getheader($title = NULL) {
        if (isset($title))
            $this->title = $title;
        if (!file_exists(__ROOT__."/template/header.html"))
            return "error";
        $header = file_get_contents(__ROOT__."/template/header.html");
        $header = $this->format_template($header);
        return $header;
    }
    public function getfooter() {
        if (!file_exists(__ROOT__."/template/footer.html"))
            return "error";
        $footer = file_get_contents(__ROOT__."/template/footer.html");
        $footer = $this->format_template($footer);
        return $footer;
    }
    public function get404() {
        if (!file_exists(__ROOT__."/template/404.html"))
            return "error";
        $html = file_get_contents(__ROOT__."/template/404.html");
        $html = $this->format_template($html);
        return $html;
    }
    private function add_page_content($content) {
        $content = str_replace("__PAGE_CONTENT__", $this->page_content, $content);
        return $content;
    }
    public function get_default_content($pagetitle = "Error") {
        $this->pagetitle = $pagetitle;
        if (!file_exists(__ROOT__."/template/defaultcontent.html"))
            return "error";
        $html = file_get_contents(__ROOT__."/template/defaultcontent.html");
        $html = $this->format_template($html);
        $html = $this->add_page_content($html);
        return $html;
    }
    public function set_page_content($content = "") {
        $this->page_content = $content;
    }
    public function print_page($output) {
        $config = array("indent" => true,
            "indent-spaces" => 4,
            "hide-comments" => true,
            "enclose-text" => true,
            "logical-emphasis" => true,
            "quote-marks" => true,
            "output-html" => true,
            "show-errors" => 0,
            "show-warnings" => false,
            "wrap" => 200);
        $tidy = tidy_parse_string($output, $config, "utf8");
        unset($output);
        $tidy->cleanRepair();
        print $tidy;
        unset($tidy);
    }
    public function make404() {
        $output = $this->getheader("Error 404");
        $output .= $this->get404();
        $output .= $this->getfooter();
        $this->print_page($output);
        unset($output);
        die();
    }
    public function makehtml($title, $pagestr) {
        $output = $this->getheader($title);
        $this->set_page_content($pagestr);
        $output .= $this->get_default_content($title);
        $output .= $this->getfooter();
        $this->print_page($output);
        unset($title, $pagestr, $output);
        die();
    }
    public function format_link($link) {
        if (SITE_REWRITE) {
            $link = str_replace("&amp;", "&", $link);
            $link = str_replace("index.php", "/", $link);
            $link = preg_replace("/(.*?)\.php/i", "/$1", $link);
            $link = preg_replace("/\?page\=(.*?)/i", "/$1", $link);
            $link = preg_replace("/\&action\=(.*?)/i", "/$1", $link);
            $link = preg_replace("/\&param\=(.*?)/i", "/$1", $link);
            $link = str_replace("//", "/", $link);
        }
        return $link;
    }
}

?>
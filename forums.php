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
 * Main forum page
 */
if (!isset($core->get["page"])) {   
    $res = $db->query("SELECT id, name FROM fcats ORDER BY sort ASC");
    if (!$res) {
        $html->makehtml("Forums", "<p>No forums exist. Sorry.</p>");
        die();
    }
    $pagestr = "";
    foreach ($res as $row) {
        $pagestr .= "<div class=\"forum-box\"><div class=\"forum-top\">
                <div class=\"forum-maintop\"><p>". htmlentities($row->name) ."</p></div>
                <div class=\"forum-infotop\"><p>Last post</p></div>
            </div>";
        $res2 = $db->select("SELECT forums.id AS id, forums.name AS name, forums.description AS description, topics.name AS topic, topics.id AS topicid, users.user AS author, users.id AS authorid, posts.posttime AS posttime, posts.id AS postid
FROM forums
    LEFT JOIN topics ON forums.id = topics.parent
    LEFT JOIN posts ON topics.id = posts.parent
    LEFT JOIN users ON posts.author = users.id
WHERE
    forums.parent = ? AND
    (posts.posttime = (SELECT posttime FROM posts WHERE forum = forums.id ORDER BY posttime DESC LIMIT 1) OR posts.id IS NULL)
ORDER BY
    forums.sort ASC", array($row->id), "i");
        if ($res2) {
            $i = 0;
            foreach ($res2 as $row2) {
                $count = $db->select_row("SELECT COUNT(id) AS postcount FROM posts WHERE parent = ? LIMIT 1", array($row2->topicid), "i");
                $last = $core->findforumpage("last", $count->postcount);
                $pagestr .= "<div class=\"forum-row forum-bg". ($core->is_even($i) ? "1" : "2") ."\">
                        <div class=\"forum-mainbox\">
                            <p><a href=\"". $html->format_link("forums.php?page=viewforum&amp;action=". $row2->id) ."\">". htmlentities($row2->name) ."</a><br/>
                            ". htmlentities($row2->description) ."</p>
                        </div>
                        <div class=\"forum-infobox\">". ($row2->author && $row2->topic && $row2->posttime ? "<p>
                        by <a href=\"#". $row2->authorid ."\">". $row2->author ."</a>
                        in <a href=\"". $html->format_link("forums.php?page=viewtopic&amp;action=". $row2->topicid ."&amp;param=". $last ."#". $row2->postid) ."\">". htmlentities($row2->topic) ."</a>
                        ". $core->get_time_since($row2->posttime) ." ago.</p>" : "<p>No posts.</p>") ."</div>
                    </div>";
                $i++;
            }
        }
        $pagestr .= "</div>";
    }
    $html->makehtml("Forums", $pagestr);
    unset($pagestr);
    die();
}
/*
 * View forum
 * TODO: add pager to viewforum. Also remember to correct all links to include &amp;param=$page
 */
elseif ($core->get["page"] == "viewforum") {
    $title = "View forum";
    $forum = $core->get["action"];
    
    $row = $db->select_row("SELECT forums.name AS name, fcats.name AS catname FROM forums LEFT JOIN fcats ON fcats.id = forums.parent WHERE forums.id = ? LIMIT 1", array($forum), "i");
    if (!$row) {
        $html->makehtml("View forum", "<p>This got broken.</p>");
        die();
    }
    $title = $core->format_title($row->name);
    $pagestr = "<div class=\"forum-box\"><div class=\"forum-top\">
            <div class=\"forum-maintop\"><p>
            &raquo; <a href=\"". $html->format_link("forums.php") ."\">". $row->catname ."</a>
            &raquo; ". htmlentities($row->name) ."</p></div>
            <div class=\"forum-infotop\"><p>Last post</p></div>
        </div>";
    
    $res = $db->select("SELECT
    topics.id AS id, topics.sticky AS sticky, topics.name AS name, author.user AS author, author.id AS authorid, topics.posttime AS posttime, posts.posttime AS lastposttime, lastposter.user AS lastposter, lastposter.id AS lastposterid, posts.id AS lastpostid
FROM topics
    JOIN users author ON topics.author = author.id
    JOIN posts ON topics.id = posts.parent
    JOIN users lastposter ON posts.author = lastposter.id
WHERE
	topics.parent = ? AND
	posts.posttime = (SELECT posttime FROM posts WHERE parent = topics.id ORDER BY posttime DESC LIMIT 1)
ORDER BY
	topics.sticky ASC, posts.posttime DESC", array($forum), "i");
    if ($res) {
        $i = 0;
        foreach ($res as $row) {
            $count = $db->select_row("SELECT COUNT(id) AS postcount FROM posts WHERE parent = ? LIMIT 1", array($row->id), "i");
            $pagestr .= "<div class=\"forum-row forum-bg". ($core->is_even($i) ? "1" : "2") ."\">
                <div class=\"forum-mainbox\">
                <p>". ($row->sticky == "yes" ? "<b>Sticky:</b> " : "") ."
                <a href=\"". $html->format_link("forums.php?page=viewtopic&amp;action=". $row->id ."&amp;param=1") ."\">
                ". htmlentities($row->name) ."</a><br/>
                started by: <a href=\"#". $row->authorid ."\">". $row->author ."</a> on ". gmdate("D M jS Y \a\\t H:i:s e", $row->posttime) .".
                <br/>Pages: ". $core->externalpager($html->format_link("forums.php?page=viewtopic&amp;action=". $row->id), $count->postcount) ."</p>
                </div>
                <div class=\"forum-infobox\"><p>
                by <a href=\"#". $row->lastposterid ."\">". $row->lastposter ."</a> ". $core->get_time_since($row->lastposttime) ." ago.</p>
                </div></div>";
            $i++;
        }
    }
    else {
        $html->makehtml($title, "<p>Forum is empty. Why not create a <a href=\"". $html->format_link("forums.php?page=newtopic&amp;action=". $forum) ."\">new topic</a>?</p>");
        die();
    }
    $pagestr .= "</div>";
    
    if ($auth->uid > 0) {
        $pagestr .= "<form method=\"post\" action=\"#\">
            <div class=\"forum-reply\">
            <div class=\"forum-submitreply\">
            <input type=\"button\" onclick=\"location.href='". $html->format_link("forums.php?page=newtopic&amp;action=". $forum) ."';\" value=\"New topic\" />
            </div>
            </div>
        </form>";
    }

    $html->makehtml($title, $pagestr);
    unset($pagestr);
    die();
}
/*
 * View topic
 */
elseif ($core->get["page"] == "viewtopic") {
    /*
     * TODO: in CSS, move author info from side to top on mobile.
     */
    $title = "View topic";
    $topic = $core->get["action"];
    if (isset($core->get["param"]))
        $page = $core->get["param"];
    else
        $page = 1;
    
    $row = $db->select_row("SELECT
    topics.parent AS parent, topics.name AS name, topics.posttime AS topicposttime, forums.name AS forumname, fcats.name AS catname
FROM topics
    LEFT JOIN forums ON forums.id = topics.parent
    LEFT JOIN fcats ON fcats.id = forums.parent
WHERE topics.id = ? LIMIT 1", array($topic), "i");
    if (!$row) {
        $html->makehtml("View forum", "<p>This got broken.</p>");
        die();
    }
    $title = $core->format_title($row->name);
    $topic_posttime = $row->topicposttime;
    $count = $db->select_row("SELECT COUNT(id) AS postcount FROM posts WHERE parent = ? LIMIT 1", array($topic), "i");
    list($sql_limit, $sql_offset, $pager) = $core->pager($count->postcount, $page);
    
    $pagestr = "<p class=\"center\">". $pager ."</p>";
    $pagestr .= "<div class=\"forum-box\">
    <div class=\"forum-top\">
        <div class=\"forum-postleft nobr\">
            <p>Author</p>
        </div>
        <div class=\"forum-postbody nobr\">
            <p>
                &raquo; <a href=\"". $html->format_link("forums.php") ."\">". htmlentities($row->catname) ."</a>
                &raquo; <a href=\"". $html->format_link("forums.php?page=viewforum&amp;action=". $row->parent) ."\">". htmlentities($row->forumname) ."</a>
                &raquo; ". htmlentities($row->name) ."
            </p>
        </div>
    </div>";
    
    $res = $db->select("SELECT
    posts.id AS id, author.user AS author, author.id AS authorid, posts.posttime AS posttime, posts.body AS body, editor.user AS editor, editor.id AS editorid, posts.lastedittime AS lastedittime
FROM posts
    LEFT JOIN users author ON author.id = posts.author
    LEFT JOIN users editor ON editor.id = posts.lasteditby
WHERE
    posts.parent = ?
ORDER BY
    posts.posttime ASC
LIMIT ? OFFSET ?", array($topic, $sql_limit, $sql_offset), "iii");
    if ($res) {
        $i = 0;
        foreach ($res as $row) {
            $istopic = false;
            if ($topic_posttime == $row->posttime)
                $istopic = true;
            $pagestr .= "
                <div id=\"". $row->id ."\" class=\"forum-post forum-bg". ($core->is_even($i) ? "1" : "2") ."\">
                    <div class=\"forum-postleft forum-bg". ($core->is_even($i) ? "1" : "2") ."\">
                        <p><a href=\"#". $row->authorid ."\">". $row->author ."</a></p>
                    </div>
                    <div class=\"forum-postbody forum-bg". ($core->is_even($i) ? "1" : "2") ."\">
                        <p><!--### Post:-->
                        posted: ". gmdate("D M jS Y \a\\t H:i:s e", $row->posttime) ."
                        ". ($auth->userlvl >= 2 || $row->author == $auth->user ? "[<a href=\"". $html->format_link("forums.php?page=editpost&amp;action=". $row->id ."&amp;param=". $page) ."\">edit</a>]" : "") ."
                        ". ($auth->userlvl >= 2 || ($row->author == $auth->user && !$istopic) ? "[<a href=\"". $html->format_link("forums.php?page=delete&amp;action=". $row->id ."&amp;param=". $page) ."\">delete</a>]" : "") ."
                        ". ($auth->uid > 0 ? "[<a href=\"". $html->format_link("forums.php?page=reply&amp;action=". $topic ."&amp;param=". $row->id) ."\">quote</a>]" : "") ."
                        </p>
                        <hr/>
                        <p>". $core->format_text($row->body) ."</p>
                        ". ($row->editor && $row->lastedittime ? "<p class=\"edited\">Last edited by <a href=\"#". $row->editorid ."\">". $row->editor ."</a> on ". gmdate("D M jS Y \a\\t H:i:s e", $row->lastedittime) .".</p>" : "") ."
                    </div>
                </div>";
            $i++;
        }
    }
    else {
        $html->makehtml("View topic", "<p>This topic is broken.</p>");
        die();
    }
    $pagestr .= "</div>";
    /*
     * TODO: fix <p> element to not need ze br
     */
    $pagestr .= "<p class=\"center\"><br/>". $pager ."</p>";
    if ($auth->uid > 0) {
        /*
         * Post reply box. Now a link to a page with the box. Because reasons.
         */
        $pagestr .= "<form method=\"post\" action=\"#\">
            <div class=\"forum-reply\">
            <div class=\"forum-submitreply\">
            <input type=\"button\" onclick=\"location.href='". $html->format_link("forums.php?page=reply&amp;action=". $topic) ."';\" value=\"Reply\" />
            </div>
            </div>
        </form>";
    }
    $html->makehtml($title, $pagestr);
    unset($pagestr);
    die();
}
/*
 * Page for replying to a topic.
 */
elseif ($core->get["page"] == "reply") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Post reply", "<p>You are not logged in.</p>");
        die();
    }
    $topic = $core->get["action"];
    
    if (isset($core->get["param"]))
        $quoteid = $core->get["param"];
    else {
        $quoteid = 0;
        $quotee = 0;
        $quotebody = null;
    }
    if ($quoteid) {
        $row = $db->select_row("SELECT posts.body AS body, users.user AS quotee FROM posts LEFT JOIN users ON posts.author = users.id WHERE posts.id = ? LIMIT 1", array($quoteid), "i");
        $quotee = $row->quotee;
        $quotebody = $row->body;
    }
    
    $row = $db->select_row("SELECT parent FROM topics WHERE id = ? LIMIT 1", array($topic), "i");
    if (!$row) {
        $html->makehtml("Post reply", "<p>Invalid link.</p>");
        die();
    }
    
    $pagestr = "
            <form method=\"post\" action=\"". $html->format_link("forums.php?page=postreply&amp;action=". $topic) ."\" id=\"replyform\">
                <div class=\"forum-reply\">
                    <p>Post a reply:</p>
                    <div class=\"forum-replybox\">
                        <textarea name=\"reply\" data-validation=\"length\" placeholder=\"BB codes disabled\" required=\"required\" maxlength=\"65535\"
                            data-validation-length=\"min3\"
                            data-validation-error-msg-container=\"#replyerror\">". ($quotebody ? "[quote=". $quotee ."]". htmlentities($quotebody) ."[/quote]\n" : "") ."</textarea>
                    </div>
                    <div id=\"replyerror\"></div>
                    <div class=\"forum-submitreply\">
                        <input type=\"button\" onclick=\"location.href='". $html->format_link("forums.php?page=viewtopic&amp;action=". $topic ."&amp;param=1") ."';\" class=\"cancel\" value=\"Cancel\" />
                        <input type=\"submit\" value=\"Post\">
                    </div>
                </div>
            </form>
            <script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
            <script>
            $.validate({
            });
            </script>";
    
    $html->makehtml("Post reply", $pagestr);
    unset($pagestr);
    die();
}
/*
 * Post a reply
 */
elseif ($core->get["page"] == "postreply") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Post reply", "<p>You are not logged in.</p>");
        die();
    }
    $topic = $core->get["action"];
    $reply = $core->post["reply"];
    if (strlen($reply) > 65535) {
        $reply = substr($reply, 0, 65535);
    }
    
    $row = $db->select_row("SELECT parent FROM topics WHERE id = ? LIMIT 1", array($topic), "i");
    if (!$row) {
        $html->makehtml("Post reply", "<p>Invalid link.</p>");
        die();
    }
    $forum = $row->parent;
    
    if (empty($reply) || strlen($reply) < 3) {
        $html->makehtml("Post reply", "<p>Minimum post length is 3 charachters.</p>");
        die();
    }
    
    $insert_id = $db->insert("INSERT INTO posts (forum, parent, author, posttime, body) VALUES (?, ?, ?, ?, ?)", array($forum, $topic, $auth->uid, time(), $reply), "iiiis");
    
    $count = $db->select_row("SELECT COUNT(id) AS postcount FROM posts WHERE parent = ? LIMIT 1", array($topic), "i");
    $last = $core->findforumpage("last", $count->postcount);
    
    header("Location: ". $html->format_link("forums.php?page=viewtopic&action=". $topic ."&param=". $last ."#". $insert_id));
    $html->makehtml("Post reply", "<p>Your reply has been posted.</p>");
    
    die();
}
/*
 * Edit a post/topic
 */
elseif ($core->get["page"] == "editpost") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Edit post", "<p>You are not logged in.</p>");
        die();
    }
    $post_id = $core->get["action"];
    $post_page = $core->get["param"];
    $title = "Edit post";
    
    $row = $db->select_row("SELECT parent, author, posttime, body FROM posts WHERE id = ? LIMIT 1", array($post_id), "i");
    if (!$row) {
        $html->makehtml("Edit post", "<p>Invalid link.</p>");
        die();
    }
    $post_parent = $row->parent;
    $post_author = $row->author;
    $post_posttime = $row->posttime;
    $post_body = $row->body;
    
    if ($auth->userlvl < 2 && $post_author != $auth->uid) {
        $html->makehtml("Edit post", "<p>You cannot edit this post.</p>");
        die();
    }
    $row = $db->select_row("SELECT parent, author, posttime, sticky, name FROM topics WHERE id = ? LIMIT 1", array($post_parent), "i");
    if (!$row) {
        $html->makehtml("Edit post", "<p>Post is broken.</p>");
        die();
    }
    $topic_parent = $row->parent;
    $topic_author = $row->author;
    $topic_posttime = $row->posttime;
    $topic_sticky = $row->sticky;
    $topic_name = $row->name;
    $istopic = false;
    
    if ($post_posttime == $topic_posttime) {
        $title = "Edit topic";
        $istopic = true;
    }
    $pagestr = "
        <form method=\"post\" action=\"". $html->format_link("forums.php?page=updatepost&amp;action=". $post_id) ."\" id=\"editpostform\">
            <input type=\"hidden\" name=\"post_page\" value=\"". $post_page ."\" />
            <div class=\"forum-reply\">";
    if ($auth->userlvl >= 2 && $istopic) {
        $pagestr .= "<div class=\"forum-stickybox\"><p>Sticky:</p>
            <select name=\"sticky\">
                <option value=\"yes\"". ($topic_sticky == "yes" ? " selected=\"selected\">###" : ">#") ."Yes</option>
                <option value=\"no\"". ($topic_sticky == "no" ? " selected=\"selected\">###" : ">#") ."No</option>
            </select>
        </div><div class=\"forum-selectbox\">
        <p>Forum:</p><select name=\"movetoforum\">";
        $res3 = $db->query("SELECT id, name FROM forums ORDER BY parent ASC, sort ASC");
        foreach ($res3 as $row3) {
            $pagestr .= "<option value=\"". $row3->id ."\"". ($row3->id == $topic_parent ? " selected=\"selected\">###" : ">#") . htmlentities($row3->name) ."</option>";
        }
        $pagestr .= "</select></div>";
    }
    if ($istopic) {
        $pagestr .= "<div class=\"forum-replybox\"><p>Subject:</p>
            <input name=\"subject\" data-validation=\"length\" placeholder=\"Subject\" value=\"". htmlentities($topic_name) ."\" required=\"required\"
                data-validation-length=\"5-64\"
                data-validation-error-msg-container=\"#subjecterror\">
        </div>
        <div id=\"subjecterror\"></div>";
    }
    $pagestr .= "
        <div class=\"forum-replybox\"><p>Text:</p>
            <textarea name=\"post\" data-validation=\"length\" placeholder=\"BB codes disabled\" required=\"required\" maxlength=\"65535\"
                data-validation-length=\"min". ($istopic ? "30" : "3") ."\"
                data-validation-error-msg-container=\"#posterror\">". htmlentities($post_body) ."</textarea>
        </div>
        <div id=\"posterror\"></div>
        <div class=\"forum-submitreply\">
            <input type=\"button\" onclick=\"location.href='". $html->format_link("forums.php?page=viewtopic&amp;action=". $post_parent ."&amp;param=1") ."';\" class=\"cancel\" value=\"Cancel\" />
            <input type=\"submit\" value=\"Edit\">
        </div>
    </div>
</form>
<script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
<script>
$.validate({
});
</script>";
    
    $html->makehtml($title, $pagestr);
    unset($pagestr);
    die();
}
/*
 * Do the magic stuff.
 */
elseif ($core->get["page"] == "updatepost") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Edit post", "<p>You are not logged in.</p>");
        die();
    }
    $post_id = $core->get["action"];
    $post_page = $core->post["post_page"];
    $post_body = $core->post["post"];
    $title = "Edit post";
    
    $row = $db->select_row("SELECT parent, author, posttime FROM posts WHERE id = ? LIMIT 1", array($post_id), "i");
    if (!$row) {
        $html->makehtml("Edit post", "<p>Invalid link.</p>");
        die();
    }
    $post_parent = $row->parent;
    $post_author = $row->author;
    $post_posttime = $row->posttime;
    
    if ($auth->userlvl < 2 && $post_author != $auth->uid) {
        $html->makehtml("Edit post", "<p>You cannot edit this post.</p>");
        die();
    }
    $row = $db->select_row("SELECT id, parent, author, posttime, sticky FROM topics WHERE id = ? LIMIT 1", array($post_parent), "i");
    if ($row) {
        $topic_id = $row->id;
        $topic_parent = $row->parent;
        $topic_author = $row->author;
        $topic_posttime = $row->posttime;
        $topic_oldsticky = $row->sticky;
        $istopic = false;
        
        if ($post_posttime == $topic_posttime) {
            $topic_name = $core->post["subject"];
            $title = "Edit topic";
            $istopic = true;
            if ($auth->userlvl >= 2) {
                $topic_sticky = $core->post["sticky"];
                $topic_movetoforum = $core->post["movetoforum"];
                
                if ($topic_sticky != "yes" && $topic_sticky != "no") {
                    $topic_sticky = $topic_oldsticky;
                }
                if ($db->select_row("SELECT id FROM forums WHERE id = ? LIMIT 1", array($topic_movetoforum), "i")) {
                    $topic_parent = $topic_movetoforum;
                }
            }
            if (strlen($topic_name) > 64) {
                $topic_name = substr($topic_name, 0, 64);
            }
            if ($istopic && strlen($topic_name) < 5) {
                $html->makehtml($title, "<p>Minimum subject length is 5 charachters.</p>");
                die();
            }
            if (empty($post_body) || strlen($post_body) < 30) {
                $html->makehtml($title, "<p>Minimum post length is 30 charachters.</p>");
                die();
            }
        }
    }
    if (strlen($post_body) > 65535) {
        $post_body = substr($post_body, 0, 65535);
    }
    if (empty($post_body) || strlen($post_body) < 3) {
        $html->makehtml($title, "<p>Minimum post length is 3 charachters.</p>");
        die();
    }
    if ($istopic) {
        $db->begin_transaction();
        try {
            if (!$db->update("UPDATE topics SET parent = ?, sticky = ?, name = ? WHERE id = ? LIMIT 1", array($topic_parent, $topic_sticky, $topic_name, $topic_id), "issi")) {
                throw new Exception();
            }
            if (!$db->update("UPDATE posts SET forum = ?, parent = ?, lasteditby = ?, lastedittime = ?, body = ? WHERE id = ? LIMIT 1", array($topic_parent, $topic_id, $auth->uid, time(), $post_body, $post_id), "iiiisi")) {
                throw new Exception();
            }
            if (!$db->update("UPDATE posts SET forum = ? WHERE parent = ?", array($topic_parent, $topic_id), "ii")) {
                throw new Exception();
            }
            $db->commit();
        }
        catch(Exception $e) {
            $db->rollback();
            $html->makehtml($title, "<p>Failed to update.</p>");
            die();
        }
        header("Location: ". $html->format_link("forums.php?page=viewtopic&action=". $topic_id ."&param=1#". $post_id));
        $html->makehtml($title, "<p>Topic updated.</p>");
        die();
    }
    /*
     * Return to the correct page.
     */    
    $db->update("UPDATE posts SET lasteditby = ?, lastedittime = ?, body = ? WHERE id = ? LIMIT 1", array($auth->uid, time(), $post_body, $post_id), "iisi");
    header("Location: ". $html->format_link("forums.php?page=viewtopic&action=". $topic_id ."&param=". $post_page ."#". $post_id));
    $html->makehtml($title, "<p>Post updated.</p>");
    die();
}
/*
 * Create topic
 */
elseif ($core->get["page"] == "newtopic") {
    if ($auth->uid  <= 0) {
        $html->makehtml("New topic", "<p>You are not logged in.</p>");
        die();
    }
    $forum = $core->get["action"];
    
    $row = $db->select_row("SELECT id FROM forums WHERE id = ? LIMIT 1", array($forum), "i");
    if (!$row) {
        $html->makehtml("New topic", "<p>Invalid link.</p>");
        die();
    }
    $pagestr = "
        <form method=\"post\" action=\"". $html->format_link("forums.php?page=createtopic&amp;action=". $forum) ."\" id=\"newtopicform\">
            <div class=\"forum-reply\">
                <div class=\"forum-replybox\"><p>Subject:</p>
                    <input name=\"subject\" data-validation=\"length\" placeholder=\"Subject\" required=\"required\"
                        data-validation-length=\"5-64\"
                        data-validation-error-msg-container=\"#subjecterror\">
                </div>
                <div id=\"subjecterror\"></div>
                <div class=\"forum-replybox\"><p>Text:</p>
                    <textarea name=\"post\" data-validation=\"length\" placeholder=\"BB codes disabled\" required=\"required\" maxlength=\"65535\"
                        data-validation-length=\"min30\"
                        data-validation-error-msg-container=\"#posterror\"></textarea>
                </div>
                <div id=\"posterror\"></div>
                <div class=\"forum-submitreply\">
                    <input type=\"button\" onclick=\"location.href='". $html->format_link("forums.php?page=viewforum&amp;action=". $forum) ."';\" class=\"cancel\" value=\"Cancel\" />
                    <input type=\"submit\" value=\"Post\">
                </div>
            </div>
        </form>
        <script src=\"//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js\"></script>
        <script>
        $.validate({
        });
        </script>";
    $html->makehtml("New topic", $pagestr);
    unset($pagestr);
    die();
}
elseif ($core->get["page"] == "createtopic") {
    if ($auth->uid  <= 0) {
        $html->makehtml("New topic", "<p>You are not logged in.</p>");
        die();
    }
    $forum = $core->get["action"];
    $subject = $core->post["subject"];
    $post_body = $core->post["post"];
    
    if (strlen($subject) > 64) {
        $subject = substr($subject, 0, 64);
    }
    if (strlen($post_body) > 65535) {
        $post_body = substr($post_body, 0, 65535);
    }
        
    $row = $db->select_row("SELECT id FROM forums WHERE id = ? LIMIT 1", array($forum), "i");
    if (!$row) {
        $html->makehtml("New topic", "<p>Invalid link.</p>");
        die();
    }
    if (empty($subject) || strlen($subject) < 5) {
        $html->makehtml("New topic", "<p>Minimum subject length is 5 charachters.</p>");
        die();
    }
    if (empty($post_body) || strlen($post_body) < 30) {
        $html->makehtml("New topic", "<p>Minimum post length is 30 charachters.</p>");
        die();
    }

    $posttime = time();
    $db->begin_transaction();
    try {
        $insert_id = $db->insert("INSERT INTO topics (parent, author, posttime, name) VALUES (?, ?, ?, ?)", array($forum, $auth->uid, $posttime, $subject), "iiis");
        if (!$insert_id) {
            throw new Exception();
        }
        $newpostid = $db->insert("INSERT INTO posts (forum, parent, author, posttime, body) VALUES (?, ?, ?, ?, ?)", array($forum, $insert_id, $auth->uid, $posttime, $post_body), "iiiis");
        if (!$newpostid) {
            throw new Exception();
        }
        $db->commit();
    }
    catch(Exception $e) {
        $db->rollback();
        $html->makehtml("New topic", "<p>Failed to update.</p>");
        die();
    }
    
    header("Location: ". $html->format_link("forums.php?page=viewtopic&action=". $insert_id ."&param=1#". $newpostid));
    $html->makehtml("New topic", "<p>Topic created.</p>");
    die();
}
elseif ($core->get["page"] == "delete") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Delete post", "<p>You are not logged in.</p>");
        die();
    }
    $post_id = $core->get["action"];
    $post_page = $core->get["param"];
    $title = "Delete post";
    
    $row = $db->select_row("SELECT parent, author, posttime, body FROM posts WHERE id = ? LIMIT 1", array($post_id), "i");
    if (!$row) {
        $html->makehtml("Delete post", "<p>Invalid link.</p>");
        die();
    }
    $post_parent = $row->parent;
    $post_author = $row->author;
    $post_posttime = $row->posttime;
    $post_body = $row->body;
    
    if ($auth->userlvl < 2 && $post_author != $auth->uid) {
        $html->makehtml("Delete post", "<p>You cannot delete this post.</p>");
        die();
    }
    $row = $db->select_row("SELECT parent, author, posttime, name FROM topics WHERE id = ? LIMIT 1", array($post_parent), "i");
    if (!$row) {
        $html->makehtml("Delete post", "<p>Post is broken.</p>");
        die();
    }
    $topic_parent = $row->parent;
    $topic_author = $row->author;
    $topic_posttime = $row->posttime;
    $topic_name = $row->name;
    $istopic = false;
    
    if ($post_posttime == $topic_posttime) {
        $title = "Delete topic";
        $istopic = true;
        if ($auth->userlvl < 2) {
            $html->makehtml("Delete topic", "<p>You cannot delete topics.</p>");
            die();
        }
    }
    $pagestr = "
        <form method=\"post\" action=\"". $html->format_link("forums.php?page=dodelete&amp;action=". $post_id) ."\" id=\"deletepostform\">
            <input type=\"hidden\" name=\"post_page\" value=\"". $post_page ."\" />
            <div class=\"forum-reply\">";
    if ($istopic) {
        $pagestr .= "<div class=\"forum-replybox\"><p>Subject:</p>
            <input name=\"subject\" value=\"". $topic_name ."\" disabled=\"disabled\"
        </div>";
    }
    $pagestr .= "
        <div class=\"forum-replybox\"><p>Text:</p>
            <textarea name=\"post\" disabled=\"disabled\">". $post_body ."</textarea>
        </div>
        <div class=\"forum-submitreply\">
            <input type=\"button\" onclick=\"location.href='". $html->format_link("forums.php?page=viewtopic&amp;action=". $post_parent ."&amp;param=1") ."';\" class=\"cancel\" value=\"Cancel\" />
            <input type=\"submit\" value=\"Delete\">
        </div>
    </div>
</form>";
    
    $html->makehtml($title, $pagestr);
    unset($pagestr);
    die();
}
/*
 * Do delete stuffs
 */
elseif ($core->get["page"] == "dodelete") {
    if ($auth->uid  <= 0) {
        $html->makehtml("Delete post", "<p>You are not logged in.</p>");
        die();
    }
    $post_id = $core->get["action"];
    $post_page = $core->post["post_page"];
    $title = "Delete post";
    
    $row = $db->select_row("SELECT parent, author, posttime FROM posts WHERE id = ? LIMIT 1", array($post_id), "i");
    if (!$row) {
        $html->makehtml("Delete post", "<p>Invalid link.</p>");
        die();
    }
    $post_parent = $row->parent;
    $post_author = $row->author;
    $post_posttime = $row->posttime;
    
    if ($auth->userlvl < 2 && $post_author != $auth->uid) {
        $html->makehtml("Delete post", "<p>You cannot delete this post.</p>");
        die();
    }
    $row = $db->select_row("SELECT parent, author, posttime FROM topics WHERE id = ? LIMIT 1", array($post_parent), "i");
    if (!$row) {
        $html->makehtml("Delete post", "<p>Post is broken.</p>");
        die();
    }
    $topic_parent = $row->parent;
    $topic_author = $row->author;
    $topic_posttime = $row->posttime;
    $istopic = false;
    
    if ($post_posttime == $topic_posttime) {
        $title = "Delete topic";
        $istopic = true;
        if ($auth->userlvl < 2) {
            $html->makehtml("Delete topic", "<p>You cannot delete topics.</p>");
            die();
        }
        
        $db->begin_transaction();
        try {
            if (!$db->delete("DELETE FROM topics WHERE id = ? LIMIT 1", array($post_parent), "i")) {
                throw new Exception();
            }
            if (!$db->delete("DELETE FROM posts WHERE parent = ?", array($post_parent), "i")) {
                throw new Exception();
            }
            $db->commit();
        }
        catch(Exception $e) {
            $db->rollback();
            $html->makehtml("Delete topic", "<p>Failed to delete.</p>");
            die();
        }
        header("Location: ". $html->format_link("forums.php?page=viewforum&action=". $topic_parent));
        $html->makehtml("Delete topic", "<p>Topic deleted.</p>");
        die();
    }
    
    $db->delete("DELETE FROM posts WHERE id = ? LIMIT 1", array($post_id), "i");
    header("Location: ". $html->format_link("forums.php?page=viewtopic&action=". $post_parent ."&param=". $post_page));
    $html->makehtml("Delete post", "<p>Post deleted.</p>");
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
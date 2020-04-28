<?php

define('MBQ_PUSH_BLOCK_TIME', 60);    /* push block time(minutes) */
if(!class_exists('TapatalkBasePush'))
{
    require_once TT_ROOT . 'push/TapatalkBasePush.php';
}

if(!function_exists("post_bbcode_clean") && file_exists(TT_ROOT . "mobiquo_common.php"))
{
    require_once TT_ROOT . "mobiquo_common.php";
}

if(file_exists(TT_ROOT . 'parser.php') && !class_exists('Tapatalk_Parser'))
{
    require_once TT_ROOT . 'parser.php';
}

/**
 * push class

 */
Class TapatalkPush extends TapatalkBasePush {

    //init

    public function __construct($push_key, $site_url)
    {
        $this->pushKey = $push_key;
        $this->siteUrl = $site_url;

        parent::__construct($this);
    }

    function get_push_slug()
    {
        global $mybb;

        $push_slug = !empty($mybb->settings['tapatalk_push_slug'])? $mybb->settings['tapatalk_push_slug'] : 0;

        return $push_slug;
    }

    function set_push_slug($slug)
    {
        global $db;

        $updated_value = array('value' => $db->escape_string($slug));
        $db->update_query("settings", $updated_value, "name='tapatalk_push_slug'");

        rebuild_settings();
    }

    function doAfterAppLogin()
    {
        global $db, $mybb;

        $uid = (int)$mybb->user['uid'];

        if ($uid)
        {
            $db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "tapatalk_users (userid) VALUES ('$uid')", 1);

            if ($db->affected_rows() == 0)
            {
                $db->write_query("UPDATE " . TABLE_PREFIX . "tapatalk_users SET updated = CURRENT_TIMESTAMP WHERE userid = '$uid'", 1);
            }
        }

    }

    public function isIgnoreUser($uid)
    {
        global $mybb;

        $uid = intval($uid);

        if ($uid == $mybb->user['uid']) return true;

        $user = get_user($uid);
        $user_ignored_array = array();
        if(!empty($user['ignorelist']))
            $user_ignored_array = explode(',', $user['ignorelist']);

        if(in_array($mybb->user['uid'], $user_ignored_array))
        {
            return true;
        }

        if(defined("TAPATALK_PUSH" . $uid))
        {
            return true;
        }

        define("TAPATALK_PUSH" . $uid, 1);

        return false;
    }

    public function doPushPm()
    {
        global $mybb, $db, $pmhandler;
        //get user's posts num
        $postnum = $mybb->user["postnum"];

        $pm = $pmhandler->data;

        $recipients = array();
        // Build recipient list
        foreach($pm['recipients'] as $recipient)
        {
            // Send email notification of new PM if it is enabled for the recipient
            $query = $db->simple_select("privatemessages", "dateline, pmid", "uid='". $recipient['uid'] . "' AND folder='1'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
            $lastpm = $db->fetch_array($query);

            $sql = "SELECT userid FROM " . TABLE_PREFIX . "tapatalk_users WHERE userid = '" . $recipient['uid'] . "'";

            $query = $db->query($sql);

            $push_user = array();
            if($row = $db->fetch_array($query))
            {
                $push_user[] = $row['userid'];
            }

            $data = array(
                'id'          => $lastpm['pmid'],
                'title'       => self::push_clean($pm['subject']),
                'content'     => self::push_clean($pm['message'])
            );

            if(!empty($postnum))
            {
                $data['author_postcount'] = $postnum;
            }

            $this->push($data, $push_user, 'pm');
        }

    }

    public function doPushPost()
    {
        global $mybb, $db, $tid, $visible, $thread_info, $fid, $new_thread, $forum;

        $pid = $thread_info['pid'];
        if(!($tid && $fid && $pid && $visible == 1) )
        {
            return false;
        }

        $query = $db->query("
            SELECT ts.uid FROM " . TABLE_PREFIX . "forumsubscriptions ts
            RIGHT JOIN " . TABLE_PREFIX . "tapatalk_users tu ON (ts.uid=tu.userid)
            WHERE ts.fid = '$fid'
        ");

        $push_user = array();

        while($row = $db->fetch_array($query))
        {
            if($this->isIgnoreUser($row['uid'])) continue;

            $push_user[] = $row['uid'];
        }

        $data = array(
            'id'             => $tid,
            'subid'          => $pid,
            'subfid'         => $forum['fid'],
            'sub_forum_name' => self::push_clean($forum['name']),
            'title'          => self::push_clean($new_thread['subject']),
            'content'        => $new_thread['message'],
        );

        $this->push($data, $push_user, 'newtopic');
    }

    public function doPushReply()
    {
        global $mybb, $db, $tid, $pid, $visible, $thread, $post, $forum;

        if(!($tid && $pid && $visible == 1) )
        {
            return false;
        }

        $query = $db->query("
            SELECT ts.uid FROM " . TABLE_PREFIX . "threadsubscriptions ts
            RIGHT JOIN " . TABLE_PREFIX . "tapatalk_users tu ON (ts.uid=tu.userid)
            WHERE ts.tid = '$tid'
        ");

        $push_user = array();

        while($row = $db->fetch_array($query))
        {
            if($this->isIgnoreUser($row['uid'])) continue;

            $push_user[] = $row['uid'];
        }

        $data = array(
            'id'             => $tid,
            'subid'          => $pid,
            'subfid'         => $forum['fid'],
            'sub_forum_name' => self::push_clean($forum['name']),
            'title'          => self::push_clean($thread['subject']),
            'content'        => $post['message'],
        );

        $this->push($data, $push_user, 'sub');
    }

    public function doPushQuote()
    {
        global $mybb, $db, $tid, $pid, $visible, $thread , $post, $thread_info, $new_thread, $forum;

        if(!($tid && $pid && $visible == 1) )
        {
            return false;
        }

        $message = !empty($new_thread['message']) ? $new_thread['message'] : $post['message'];

        $matches = $in_username = array();
        preg_match_all('/\[quote=\'(.*?)\' pid=\'(.*?)\' dateline=\'(.*?)\'\]/', $post['message'] , $matches);
        $matches = array_unique($matches[1]);
        foreach ($matches as $key=> $username)
        {
            $username = $db->escape_string($username);

            $in_username[] = "'$username'";
        }

        if(empty($in_username)) return false;

        $in_str = implode(',', $in_username);
        $query = $db->query("SELECT u.uid FROM " . TABLE_PREFIX . "tapatalk_users AS tu RIGHT JOIN
            " . TABLE_PREFIX ."users AS u ON tu.userid = u.uid  WHERE u.username IN($in_str)");

        $push_user = array();

        while($row = $db->fetch_array($query))
        {
            if($this->isIgnoreUser($row['uid'])) continue;

            $push_user[] = $row['uid'];
        }

        $data = array(
            'id'             => $tid,
            'subid'          => $pid,
            'subfid'         => $forum['fid'],
            'sub_forum_name' => self::push_clean($forum['name']),
            'title'          => self::push_clean($thread['subject']),
            'content'        => $message,
        );

        $this->push($data, $push_user, 'quote');

    }

    public function doPushTag()
    {
        global $mybb, $db, $tid, $pid, $visible, $thread , $post, $thread_info, $new_thread, $forum;

        if(!($tid && $pid && $visible == 1) )
        {
            return false;
        }

        $message = !empty($new_thread['message']) ? $new_thread['message'] : $post['message'];

        $matches = self::getTagList($message);
        foreach ($matches as $key=> $username)
        {
            $username = $db->escape_string($username);

            $in_username[] = "'$username'";
        }

        if(empty($in_username)) return false;

        $in_str = implode(',', $in_username);

        $query = $db->query("SELECT u.uid FROM " . TABLE_PREFIX . "tapatalk_users AS tu RIGHT JOIN
            " . TABLE_PREFIX ."users AS u ON tu.userid = u.uid  WHERE u.username IN($in_str)");

        $push_user = array();

        while($row = $db->fetch_array($query))
        {
            if($this->isIgnoreUser($row['uid'])) continue;

            $push_user[] = $row['uid'];
        }

        $data = array(
            'id'             => $tid,
            'subid'          => $pid,
            'subfid'         => $forum['fid'],
            'sub_forum_name' => self::push_clean($forum['name']),
            'title'          => self::push_clean($thread['subject']),
            'content'        => $message,
        );

        $this->push($data, $push_user, 'tag');
    }

    public function doPushNewSub()
    {

        global $mybb, $db, $tid, $pid, $visible, $thread , $post, $thread_info, $new_thread, $forum;

        if(!($tid && $pid && $visible == 1) )
        {
            return false;
        }

        $message = !empty($new_thread['message']) ? $new_thread['message'] : $post['message'];

        //get topic author's uid
        $uid = $thread['uid'];

        //check if the author is tapatalk user?
        $query = $db->query("SELECT u.uid FROM " . TABLE_PREFIX . "tapatalk_users AS tu RIGHT JOIN
            " . TABLE_PREFIX ."users AS u ON tu.userid = u.uid WHERE u.uid = $uid");

        $push_user = array();

        while($row = $db->fetch_array($query))
        {
            if($this->isIgnoreUser($row['uid'])) continue;

            $push_user[] = $row['uid'];
        }

        die();
        //construct package
        $data = array(
            'id'               => $tid,
            'subid'            => $pid,
            'subfid'           => $forum['fid'],
            'sub_forum_name'   => self::push_clean($forum['name']),
            'title'            => self::push_clean($thread['subject']),
            'content'          => $message,
        );

        $this->push($data, $push_user, 'newsub');
    }

    public function push($data, $push_user, $type)
    {

        global $mybb;

        if(empty($this->pushKey)) return false;

        $data['type']        = $type;
        $data['key']         = $this->pushKey;
        $data['url']         = $this->siteUrl;
        $data['dateline']    = TIME_NOW;
        $data['author_ua']   = self::getClienUserAgent();
        $data['author_type'] = check_return_user_type($mybb->user['username']);
        $data['from_app']    = self::getIsFromApp();
        $data['authorid']    = $mybb->user['uid'];
        $data['author']      = $mybb->user['username'];

        if(!empty($data['content']) && !empty($mybb->settings['tapatalk_push_type']))
        {
            $data['content'] = self::convertContent($data['content'], $data['author']);
        }
        else if(isset($data['content']))
        {
        	unset($data['content']);
        }

        if(!empty($push_user))
        {
            $data['userid'] = implode(',', $push_user);
            $data['push'] = 1;
        }
        else
        {
            $data['push'] = 0;
        }

        self::do_push_request($data);
    }

    public function convertContent($content, $username)
    {
        global $lang;

        $parser_options = array();
        $parser_options['allow_html'] = false;
        $parser_options['allow_mycode'] = true;
        $parser_options['allow_smilies'] = false;
        $parser_options['allow_imgcode'] = true;
        $parser_options['allow_videocode'] = true;
        $parser_options['nl2br'] = true;
        $parser_options['filter_badwords'] = 1;

        $parser_options['me_username'] = $username;

        $message = post_bbcode_clean($content);
        // Post content and attachments
        $parser = new Tapatalk_Parser;
        $message = $parser->parse_message($message, $parser_options);
        $message = process_post($message, true);

        return $message;
    }
}

?>
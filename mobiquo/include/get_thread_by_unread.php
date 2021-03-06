<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_indicators.php";
require_once MYBB_ROOT."inc/functions_user.php";

require_once TT_ROOT."include/get_thread_by_post.php";

function get_thread_by_unread_func($xmlrpc_params)
{
    global $db, $mybb;

    $input = Tapatalk_Input::filterXmlInput(array(
        'topic_id'          => Tapatalk_Input::STRING,
        'posts_per_request' => Tapatalk_Input::INT,
        'return_html'       => Tapatalk_Input::INT
    ), $xmlrpc_params);
	if (preg_match('/^ann_/', $input['topic_id']))
    {
        $_GET["aid"] = intval(str_replace('ann_', '', $input['topic_id']));
        return get_announcement_func($xmlrpc_params);
    }
    $thread = get_thread($input['topic_id']);
    $tid = $input['topic_id'];
    $fid = $thread['fid'];
    if(!empty($thread['closed']))
    {
         $moved = explode("|", $thread['closed']);
         if($moved[0] == "moved")
         {
             $thread = get_thread($moved[1]);
         }
    }
    if(is_moderator($thread['fid']))
    {
        $visible = "AND (p.visible='0' OR p.visible='1')";
    }
    else
    {
        $visible = "AND p.visible='1'";
    }
	/*$cutoff = 0;
	if($mybb->settings['threadreadcut'] > 0)
	{
		$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
	}
	
    $query = $db->query("select min(p.pid) as pid from ".TABLE_PREFIX."posts p
        LEFT JOIN ".TABLE_PREFIX."threadsread tr on p.tid = tr.tid and tr.uid = '{$mybb->user['uid']}'
        where p.tid='{$thread['tid']}' and p.uid != '{$mybb->user['uid']}' and (p.dateline > tr.dateline or tr.dateline is null) and p.dateline > $cutoff $visible
        ");
	$pid = $db->fetch_field($query, 'pid');
    */
    try{
        $visibleonly="";
        // Is the currently logged in user a moderator of this forum?
        if(is_moderator($fid))
        {
            $ismod = true;
            if(is_moderator($fid, "canviewdeleted") == true || is_moderator($fid, "canviewunapprove") == true)
            {
                if(is_moderator($fid, "canviewunapprove") == true && is_moderator($fid, "canviewdeleted") == false)
                {
                    $visibleonly = " AND visible IN (0,1)";
                    $visibleonly2 = "AND p.visible IN (0,1) AND t.visible IN (0,1)";
                }
                elseif(is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == false)
                {
                    $visibleonly = " AND visible IN (-1,1)";
                    $visibleonly2 = "AND p.visible IN (-1,1) AND t.visible IN (-1,1)";
                }
                else
                {
                    $visibleonly = " AND visible IN (-1,0,1)";
                    $visibleonly2 = "AND p.visible IN (-1,0,1) AND t.visible IN (-1,0,1)";
                }
            }
        }
        else
        {
            $ismod = false;
            $visibleonly = " AND visible=1";
            $visibleonly2 = "AND p.visible=1 AND t.visible=1";
        }

        $query = $db->simple_select("threadsread", "dateline", "uid='{$mybb->user['uid']}' AND tid='{$thread['tid']}'");
        $thread_read = $db->fetch_field($query, "dateline");

        if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
        {
            $query = $db->simple_select("forumsread", "dateline", "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
            $forum_read = $db->fetch_field($query, "dateline");

            $read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
            if($forum_read == 0 || $forum_read < $read_cutoff)
            {
                $forum_read = $read_cutoff;
            }
        }
        else
        {
            $forum_read = (int)my_get_array_cookie("forumread", $fid);
        }

        if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forum_read)
        {
            $cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
            if($thread['lastpost'] > $cutoff)
            {
                if($thread_read)
                {
                    $lastread = $thread_read;
                }
                else
                {
                    // Set $lastread to zero to make sure 'lastpost' is invoked in the last IF
                    $lastread = 0;
                }
            }
        }

        if(!$lastread)
        {
            $readcookie = $threadread = (int)my_get_array_cookie("threadread", $thread['tid']);
            if($readcookie > $forum_read)
            {
                $lastread = $readcookie;
            }
            else
            {
                $lastread = $forum_read;
            }
        }

        if($cutoff && $lastread < $cutoff)
        {
            $lastread = $cutoff;
        }

        // Next, find the proper pid to link to.
        $options = array(
            "limit_start" => 0,
            "limit" => 1,
            "order_by" => "dateline",
            "order_dir" => "asc"
        );

        $lastread = (int)$lastread;
        $query = $db->simple_select("posts", "pid", "tid='{$tid}' AND dateline > '{$lastread}' {$visibleonly}", $options);
        $newpost = $db->fetch_array($query);

        if($newpost['pid'] && $lastread)
        {
            $pid = $newpost['pid'];
        }
        else
        {
            $query = $db->query("select p.pid from ".TABLE_PREFIX."posts p
                             where p.tid='{$thread['tid']}' $visible
                             order by p.dateline desc
                             limit 1");
            $pid = $db->fetch_field($query, 'pid');
        }
    }
    catch(Exception $ex)
    {
        $query = $db->query("select p.pid from ".TABLE_PREFIX."posts p
                             where p.tid='{$thread['tid']}' $visible
                             order by p.dateline desc
                             limit 1");
        $pid = $db->fetch_field($query, 'pid');
    }
    return get_thread_by_post_func(new xmlrpcval(array(
        new xmlrpcval($pid, "string"),
        new xmlrpcval($input['posts_per_request'], 'int'),
        new xmlrpcval(!!$input['return_html'], 'boolean'),
    ), 'array'));
}
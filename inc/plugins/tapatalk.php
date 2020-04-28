<?php

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

if(!defined('IN_MOBIQUO')) define('IN_MOBIQUO', true);
if(!defined('TT_ROOT'))
{
    if(empty($mybb->settings['tapatalk_directory'])) $mybb->settings['tapatalk_directory'] = 'mobiquo';
    define('TT_ROOT', MYBB_ROOT . $mybb->settings['tapatalk_directory'] . '/');
}

require_once TT_ROOT . 'push/TapatalkPush.php';
require_once TT_ROOT . "lib/classTTConnection.php";

$plugins->add_hook('error', 'tapatalk_error');
$plugins->add_hook('redirect', 'tapatalk_redirect');
$plugins->add_hook('global_start', 'tapatalk_global_start');
$plugins->add_hook('fetch_wol_activity_end', 'tapatalk_fetch_wol_activity_end');
$plugins->add_hook('pre_output_page', 'tapatalk_pre_output_page');

// hook for push
$plugins->add_hook('usercp2_addsubscription_thread', 'tapatalk_push_newsub');
$plugins->add_hook('newreply_do_newreply_end', 'tapatalk_push_reply');
$plugins->add_hook('newreply_do_newreply_end', 'tapatalk_push_quote');
$plugins->add_hook('newreply_do_newreply_end', 'tapatalk_push_tag');
$plugins->add_hook('private_do_send_end', 'tapatalk_push_pm');
$plugins->add_hook('newthread_do_newthread_end', 'tapatalk_push_newtopic');
$plugins->add_hook('newthread_do_newthread_end', 'tapatalk_push_quote');
$plugins->add_hook('newthread_do_newthread_end', 'tapatalk_push_tag');
$plugins->add_hook('online_user','tapatalk_online_user');
$plugins->add_hook('online_end','tapatalk_online_end');
$plugins->add_hook('postbit','tapatalk_postbit');
$plugins->add_hook('postbit_prev','tapatalk_postbit');
$plugins->add_hook('postbit_pm','tapatalk_postbit');
$plugins->add_hook('postbit_announcement','tapatalk_postbit');
//$plugins->add_hook('parse_message_start', "tapatalk_parse_message");
$plugins->add_hook('parse_message_end', "tapatalk_parse_message_end");
$plugins->add_hook("admin_config_settings_begin", "tapatalk_settings_update");

function tapatalk_info()
{
    /**
     * Array of information about the plugin.
     * name: The name of the plugin
     * description: Description of what the plugin does
     * website: The website the plugin is maintained at (Optional)
     * author: The name of the author of the plugin
     * authorsite: The URL to the website of the author (Optional)
     * version: The version number of the plugin
     * guid: Unique ID issued by the MyBB Mods site for version checking
     * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
     */
    return array(
        "name"          => "Tapatalk",
        "description"   => "Tapatalk Plugin for MyBB",
        "website"       => "https://www.tapatalk.com",
        "author"        => "Quoord Systems Limited",
        "authorsite"    => "https://www.tapatalk.com",
        "version"       => "4.6.4",
        "guid"          => "e7695283efec9a38b54d8656710bf92e",
        "compatibility" => "1*"
    );
}

function tapatalk_install()
{
    global $db,$mybb;
    if(!session_id())
    {
        @session_start();
    }

    tapatalk_uninstall();
    if(!$db->table_exists('tapatalk_users'))
    {
        $db->write_query("
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "tapatalk_users (
              userid int(10) NOT NULL,
              announcement smallint(5) NOT NULL DEFAULT '1',
              pm smallint(5) NOT NULL DEFAULT '1',
              subscribe smallint(5) NOT NULL DEFAULT '1',
              newtopic smallint(5) NOT NULL DEFAULT '1',
              newsub smallint(5) NOT NULL DEFAULT '1',
              quote smallint(5) NOT NULL DEFAULT '1',
              tag smallint(5) NOT NULL DEFAULT '1',
              updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (userid)
            )
        ");
    }
    if(!$db->table_exists("tapatalk_push_data"))
    {
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "tapatalk_push_data (
              push_id int(10) NOT NULL AUTO_INCREMENT,
              author varchar(100) NOT NULL,
              user_id int(10) NOT NULL DEFAULT '0',
              data_type char(20) NOT NULL DEFAULT '',
              title varchar(200) NOT NULL DEFAULT '',
              data_id int(10) NOT NULL DEFAULT '0',
              topic_id int(10) NOT NULL DEFAULT '0',
              create_time int(11) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (push_id),
              KEY user_id (user_id),
              KEY create_time (create_time),
              KEY author (author)
            ) DEFAULT CHARSET=utf8

        ");
    }
    // Insert settings in to the database
    $query = $db->write_query("SELECT disporder FROM ".TABLE_PREFIX."settinggroups ORDER BY `disporder` DESC LIMIT 1");
    $disporder = $db->fetch_field($query, 'disporder')+1;

    $setting_group = array(
        'name'          =>    'tapatalk',
        'title'         =>    'Tapatalk Options',
        'description'   =>    'Optional Tapatalk Settings allow you to fine-tune the app behaviour with the forum',
        'disporder'     =>    0,
        'isdefault'     =>    0
    );

    $db->insert_query('settinggroups', $setting_group);
    $gid = $db->insert_id();

    $query = $db->simple_select("usergroups", "gid, title", "", array('order_by' => 'title'));
    $display_group_options = array();
    while($usergroup = $db->fetch_array($query))
    {
        $display_group_options[$usergroup['gid']] = $usergroup['title'];
    }
    $select_group_str = '';
    foreach ($display_group_options as $groupid => $title)
    {
        $select_group_str .= "\n".$groupid . "=" . $title;
    }

    $settings = array(
        'push_key' => array(
            'title'         => 'Tapatalk API Key',
            'description'   => 'Formerly known as Push Key. This key is now required for secure connection between your community and Tapatalk server. Features such as Push Notification and Single Sign-On requires this key to work. ',
            'optionscode'   => 'text',
            'value'         => ''
        ),
        'register_status' => array(
            'title'         => 'In-App Registration',
            'description'   => "Verified Tapatalk users signed in from Facebook, Google or verified email address can register your forum natively in-app. Additional custom fields are also supported, althought we strongly recommend to keep the custom fields to absolute minimal to make registration easier on mobile.",
            'optionscode'   => "radio\n2=On\n0=Off",
            'value'         => 2
        ),
        'auto_approve' => array(
            'title'         => 'Automatically Approve Verified Tapatalk Members',
            'description'   => '',
            'optionscode'   => "radio\n1=On\n0=Off",
            'value'         => 1
        ),
        'register_group' => array(
            'title'         => 'User Group Assignment',
            'description'   => "You can assign users registered with Tapatalk to specific user groups. If you do not assign them to a specific group, they will be assigned a default group. ",
            'optionscode'   => 'select'.$select_group_str,
            'value'         => 2
        ),
        'ad_filter'      => array(
            'title'         => 'Disable Ads for Group',
            'description'   => "This option will prevent Tapatalk from displaying advertisements. Users in the selected groups will not be served ads when using the Tapatalk app. Please enter a comma-separated group ID",
            'optionscode'   => 'text',
            'value'         => ''
        ),
        'directory' => array(
            'title'         => 'Tapatalk Plugin Directory',
            'description'   => 'Never change it if you did not rename the Tapatalk plugin directory. And the default value is \'mobiquo\'. If you renamed the Tapatalk plugin directory, you also need to update the same setting in Tapatalk Forum Owner Area.',
            'optionscode'   => 'text',
            'value'         => 'mobiquo'
        ),
        'hide_forum' => array(
            'title'         => 'Hide Forums',
            'description'   => "Hide specific sub-forums from appearing in Tapatalk. Please enter a comma-separated sub-forum ID",
            'optionscode'   => 'text',
            'value'         => ''
        ),
        'forum_read_only' => array(
            'title'         => 'Disable New Topic',
            'description'   => "Prevent Tapatalk users to create new topic in the selected sub-forums. This feature is useful if certain forums requires additional topic fields or permission that Tapatalk does not support,Separate multiple entries with a coma.",
            'optionscode'   => 'text',
            'value'         => ''
        ),
        'custom_replace'    => array(
            'title'         => 'Thread Content Replacement (Advanced)',
            'description'   => 'Ability to match and replace thread content using PHP preg_replace function(http://www.php.net/manual/en/function.preg-replace.php). E.g. "\'pattern\', \'replacement\'" . You can define more than one replace rule on each line.',
            'optionscode'   => 'textarea',
            'value'         => ''
        ),

        'push_type' => array(
            'title'         => 'Push Notifications',
            'description'   => '"Basic Message" - "Do not include post content and images preview in Push Notifications" ,
"Rich Message" - "Includes post content and images preview in Push Notifications"',
            'optionscode'   => "radio\n1=Rich Message\n0=Basic Message",
            'value'         => 1
        ),

        'push_slug' => array(
            'title'         => '',
            'description'   => '',
            'optionscode'   => 'php',
            'value'         => '0',
        ),
        'banner_data' => array(
            'title'         => '',
            'description'   => '',
            'optionscode'   => 'php',
            'value'         => '',
        ),
        'banner_expire' => array(
            'title'         => '',
            'description'   => '',
            'optionscode'   => 'php',
            'value'         => '0',
        ),

        'datakeep' => array(
            'title'         => 'Uninstall Behaviour',
            'description'   => "Ability to retain 'tapatalk_' tables and Tapatalk settings in DB. Useful if you're re-installing Tapatalk Plugin.",
            'optionscode'   => "radio\nkeep=Keep Data\ndelete=Delete all data and tables",
            'value'         => 'keep'
        ),
    );

    $s_index = 0;
    foreach($settings as $name => $setting)
    {
        $s_index++;
        if(!empty($_SESSION['tapatalk_'.$name]) && $name != 'push_slug'  && $name != 'banner_data'  && $name != 'banner_expire')
        {
            $value = $_SESSION['tapatalk_'.$name];
            unset($_SESSION['tapatalk_'.$name]);
        }
        else
        {
            $value = $setting['value'];
        }
        $insert_settings = array(
            'name'        => $db->escape_string('tapatalk_'.$name),
            'title'       => $db->escape_string($setting['title']),
            'description' => $db->escape_string($setting['description']),
            'optionscode' => $db->escape_string($setting['optionscode']),
            'value'       => $db->escape_string($value),
            'disporder'   => $s_index,
            'gid'         => $gid,
            'isdefault'   => 0
        );
        $db->insert_query('settings', $insert_settings);
    }

    rebuild_settings();
}

function tapatalk_is_installed()
{
    global $mybb, $db;

    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk'", array('limit' => 1));
    $group = $db->fetch_array($result);

    return !empty($group['gid']) && $db->table_exists('tapatalk_users');
}

function tapatalk_uninstall()
{
    global $mybb, $db;
    if(!session_id())
    {
        @session_start();
    }
    if($db->table_exists('tapatalk_push_data') && ($mybb->settings['tapatalk_datakeep'] == 'delete' || !$db->field_exists('topic_id', 'tapatalk_push_data')))
    {
        $db->drop_table('tapatalk_push_data');
    }
    if($db->table_exists('tapatalk_users') && ($mybb->settings['tapatalk_datakeep'] == 'delete' || !$db->field_exists('tag', 'tapatalk_users')))
    {
        $db->drop_table('tapatalk_users');
    }
    if($mybb->settings['tapatalk_datakeep'] == 'keep')
    {
        foreach ($mybb->settings as $key => $value)
        {
            if(preg_match('/^(tapatalk)/', $key))
            {
                $_SESSION[$key] = $value;
            }
        }
    }
    // Remove settings
    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk'", array('limit' => 1));
    $group = $db->fetch_array($result);

    if(!empty($group['gid']))
    {
        $db->delete_query('settinggroups', "gid='{$group['gid']}'");
        $db->delete_query('settings', "gid='{$group['gid']}'");
        rebuild_settings();
    }
    // Remove byo settings
    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk_byo'", array('limit' => 1));
    $group = $db->fetch_array($result);

    if(!empty($group['gid']))
    {
        $db->delete_query('settinggroups', "gid='{$group['gid']}'");
        $db->delete_query('settings', "gid='{$group['gid']}'");
        rebuild_settings();
    }

    // Remove register settings
    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk_register'", array('limit' => 1));
    $group = $db->fetch_array($result);

    if(!empty($group['gid']))
    {
        $db->delete_query('settinggroups', "gid='{$group['gid']}'");
        $db->delete_query('settings', "gid='{$group['gid']}'");
        rebuild_settings();
    }
}

/*
function tapatalk_activate()
{
    global $mybb, $db;

}

function tapatalk_deactivate()
{
    global $db;
}
*/
/* ============================================================================================ */

function tapatalk_error($error)
{
    if(!strstr($_SERVER['PHP_SELF'],'mobiquo.php'))
    {
        return ;
    }

    if(defined('IN_MOBIQUO'))
    {
        global $lang, $include_topic_num, $search, $function_file_name;

        if ($error == $lang->error_nosearchresults)
        {
            if ($include_topic_num) {
                if($search['resulttype'] != 'posts') {
                    $response = new xmlrpcresp(new xmlrpcval(array(
                        'result'            => new xmlrpcval(true, 'boolean'),
                        'total_topic_num'   => new xmlrpcval(0, 'int'),
                        'topics'            => new xmlrpcval(array(), 'array'),
                    ), 'struct'));
                } else {
                    $response = new xmlrpcresp(new xmlrpcval(array(
                        'result'            => new xmlrpcval(true, 'boolean'),
                        'total_post_num'    => new xmlrpcval(0, 'int'),
                        'posts'             => new xmlrpcval(array(), 'array'),
                    ), 'struct'));
                }
            } else {
                $response = new xmlrpcresp(new xmlrpcval(array(), 'array'));
            }
        }
        else if ($function_file_name == 'thankyoulike' && strpos($error, $lang->tyl_redirect_back))
        {
            $response = new xmlrpcresp(new xmlrpcval(array(
                'result'        => new xmlrpcval(true, 'boolean'),
            ), 'struct'));
        }
        else
        {
            $response = new xmlrpcresp(new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(trim(strip_tags($error)), 'base64'),
            ), 'struct'));
        }
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
        exit;
    }
}

function tapatalk_redirect($args)
{
    if(!strstr($_SERVER['PHP_SELF'],'mobiquo.php'))
    {
        return ;
    }
    tapatalk_error($args['message']);
}

function tapatalk_global_start()
{
    global $mybb, $request_method, $function_file_name;

    header('Mobiquo_is_login: ' . ($mybb->user['uid'] > 0 ? 'true' : 'false'));

    if ($mybb->usergroup['canview'] != 1 && in_array($request_method, array('get_config', 'login','register','sign_in','prefetch_account','update_password','forget_password')))
    {
        define("ALLOWABLE_PAGE", 1);
    }

    if (isset($mybb->settings['no_proxy_global']))
    {
        $mybb->settings['no_proxy_global'] = 0;
    }

    if ($function_file_name == 'thankyoulike')
    {
        $mybb->input['my_post_key'] = md5($mybb->user['loginkey'].$mybb->user['salt'].$mybb->user['regdate']);
    }
}

function tapatalk_fetch_wol_activity_end(&$user_activity)
{
    global $uid_list, $aid_list, $pid_list, $tid_list, $fid_list, $ann_list, $eid_list, $plugins, $user, $parameters;
    if($user_activity['activity'] == 'unknown' && strpos($user_activity['location'], 'mobiquo') !== false)
    {
        //$user_activity['activity'] = 'tapatalk';
        $method = 'unknown';
        $path_arr = parse_url($user_activity['location']);
        $param = -2;
        $unviewableforums = get_unviewable_forums();
        if(!empty($path_arr['query']))
        {
            $param_arr = explode('&amp;', $path_arr['query']);
            $method = str_replace('method=', '', $param_arr[0]);
            $param = str_replace('params=', '', $param_arr[1]);
        }
        switch ($method)
        {
            case 'get_config':
            case 'get_forum':
            case 'get_participated_forum':
            case 'login_forum':
            case 'get_forum_status':
            case 'get_topic':
                if(is_numeric($param))
                {
                    $fid_list[] = $param;
                }
                $user_activity['activity'] = "forumdisplay";
                $user_activity['fid'] =  $param;
                break;
            case 'logout_user':
                $user_activity['activity'] = "member_logout";
                break;
            case 'get_user_info':
                $user_activity['activity'] = "member_profile";
                break;
            case 'register':
                $user_activity['activity'] = "member_register";
                break;
            case 'forget_password':
                $user_activity['activity'] = "member_lostpw";
                break;
            case 'login':
                $user_activity['activity'] = "member_login";
                break;
            case 'get_online_users':
                $user_activity['activity'] = "wol";
                break;
            case 'get_user_topic':
            case 'get_user_reply_post':
                $user_activity['activity'] = "usercp";
                break;
            case 'new_topic':
                if(is_numeric($param))
                {
                    $fid_list[] = $param;
                }
                $user_activity['activity'] = "newthread";
                $user_activity['fid'] = $param;
                break;
            case 'search':
            case 'search_topic':
            case 'search_post':
            case 'get_unread_topic':
            case 'get_participated_topic':
            case 'get_latest_topic':
                $user_activity['activity'] = "search";
                break;
            case 'get_quote_post':
            case 'reply_post':
                $user_activity['activity'] = "newreply";
                break;
            case 'get_thread':
                if(is_numeric($param))
                {
                    $tid_list[] = $param;
                }
                $user_activity['activity'] = "showthread";
                $user_activity['tid'] = $param;
                break;
            case 'get_thread_by_post':
                if(is_numeric($param))
                {
                    $pid_list[] = $param;
                    $user_activity['activity'] = "showpost";
                    $user_activity['pid'] = $param;
                }
                break;
            case 'create_message':
            case 'get_box_info':
            case 'get_box':
            case 'get_quote_pm':
            case 'delete_message':
            case 'mark_pm_unread':
                $user_activity['activity'] = "private";
                break;
            case 'get_message':
                $user_activity['activity'] = "private_read";
                break;
            default:
                if(strpos($method, 'm_') === 0)
                {
                    $user_activity['activity'] = "moderation";
                }
                else if(strstr($method,'_post'))
                {
                    $user_activity['activity'] = "showpost";
                }
                else if(strpos($user_activity['location'], 'mobiquo') !== false)
                {
                    $user_activity['activity'] = "index";
                }
                break;
        }

    }
}

function tapatalk_online_user()
{
    global $user;
    if((strpos($user['location'], 'mobiquo') !== false) && (strpos($user['location'], 'BYO') !== false))
    {
         $user['username'] = $user['username'] . '[tapatalk_byo_user]';
    }
    else if(strpos($user['location'], 'mobiquo') !== false)
    {
        $user['username'] = $user['username'] . '[tapatalk_user]';
    }
}

function tapatalk_online_end()
{
    global $online_rows,$mybb;
    $temp_online = $online_rows;

    $str = '&nbsp;<img src="'.$mybb->settings['bburl'].'/'.$mybb->settings['tapatalk_directory'].'/images/tapatalk-online.png?new" style="vertical-align:middle">';
    $online_rows = preg_replace('/<a href="(.*)">(.*)\[tapatalk_user\](<\/em><\/strong><\/span>|<\/strong><\/span>|<\/span>|<\/b><\/span>|<\/s>|\s*)<\/a>/Usi', '<a href="$1">$2$3</a>'.$str, $online_rows);
    if(empty($online_rows))
    {
        $online_rows = str_replace('[tapatalk_user]','',$temp_online);
    }
    $temp_online = $online_rows;
    $str_byo =  '&nbsp;<img src="'.$mybb->settings['bburl'].'/'.$mybb->settings['tapatalk_directory'].'/images/byo-online.png" style="vertical-align:middle">';
    $online_rows = preg_replace('/<a href="(.*)">(.*)\[tapatalk_byo_user\](<\/s>|<\/em><\/strong><\/span>|<\/strong><\/span>|<\/span>|<\/b><\/span>|\s*)<\/a>/Usi', '<a href="$1">$2$3</a>'.$str_byo, $online_rows);
    if(empty($online_rows))
    {
        $online_rows = str_replace('[tapatalk_byo_user]','',$temp_online);
    }

}

function tapatalk_pre_output_page(&$page)
{
    global $mybb, $db;
    $settings = $mybb->settings;

    $tapatalk_dir = MYBB_ROOT.$mybb->settings['tapatalk_directory'];  // default as 'mobiquo'
    $app_location = tapatalk_get_url();

    preg_match('/location=(\w+)/is', $app_location,$matches);
    if(!empty($matches[1]))
    {
        if($matches[1] == 'message')
        {
            $matches[1] = 'pm';
        }
        $page_type = $matches[1];
    }
    $app_location = str_replace("location=other", 'location=index', $app_location);
    $app_ios_id = $settings['tapatalk_app_ios_id'];
    $app_android_id = $settings['tapatalk_android_url'];

    //disable hiding forums' ads.
    $global_forum_active = true;
    $fid = 0;

    //get fid
    if($page_type == "forum")
    {
        preg_match('/fid=(\d+)/is', $app_location, $matches_forum);
        if(!empty($matches_forum[1]))
        {
            $fid = $matches_forum[1];
        }
    }
    else if($page_type == "topic")
    {
        preg_match('/tid=(\d+)/is', $app_location, $matches);
        if(!empty($matches[1]))
        {
            $tid = $matches[1];
            $query_sql = "SELECT fid FROM ".TABLE_PREFIX."threads WHERE tid = '$tid';";
            $query = $db->write_query($query_sql);
            $result = $db->fetch_field($query, 'fid');
            $fid = intval($result);
            $app_location = $app_location . "&fid=$fid";
        }
    }
    else if($page_type == "index")
    {
        $page_type = "home";
    }

    $hide_forum_id = $settings['tapatalk_hide_forum'];
    $hide_forum_arr = explode(",", $hide_forum_id);
    if(!empty($hide_forum_id))
    {
        foreach ($hide_forum_arr as $value) {
            if($fid == $value)
            {
                $global_forum_active = false;
                break;
            }
            else
            {
                $query_sql = "SELECT parentlist FROM ".TABLE_PREFIX."forums WHERE fid = '$fid';";
                $query = $db->write_query($query_sql);
                $result = $db->fetch_field($query, 'parentlist');
                $parent_list_arr = explode(",", $result);
                foreach ($parent_list_arr as $parent_value)
                {
                    if($parent_value == $value)
                    {
                        $global_forum_active = false;
                        break;
                    }
                }
            }
        }
    }

    // Get settings
    $result_tapatalk_banner_data = !empty($mybb->settings['tapatalk_banner_data'])? $mybb->settings['tapatalk_banner_data'] : '';
    $TT_bannerControlData = !empty($result_tapatalk_banner_data) ? unserialize($result_tapatalk_banner_data) : false;
    $app_banner_enable = isset($mybb->settings['smart_app_banner']) ? $mybb->settings['smart_app_banner'] : 1;
    $google_indexing_enabled = isset($mybb->settings['google_app_indexing_enabled']) ? $mybb->settings['google_app_indexing_enabled'] : 1;

    $TT_connection = new classTTConnection();
    $TT_connection->calcSwitchOptions($TT_bannerControlData, $app_banner_enable, $google_indexing_enabled);

    if(isset($TT_bannerControlData['byo_info']) && !empty($TT_bannerControlData['byo_info']))
    {
        $app_rebranding_id = $TT_bannerControlData['byo_info']['app_rebranding_id'];
        $app_url_scheme = $TT_bannerControlData['byo_info']['app_url_scheme'];
        $app_android_id = $TT_bannerControlData['byo_info']['app_android_id'];
        $app_ios_id = $TT_bannerControlData['byo_info']['app_ios_id'];
    }

    $app_head_include = '';

    if (file_exists($tapatalk_dir . '/smartbanner/head.inc.php') && $global_forum_active)
    {
        include($tapatalk_dir . '/smartbanner/head.inc.php');
    }

    $page = str_ireplace("</head>", $app_head_include . "\n</head>", $page);
}

function tapatalk_postbit(&$post)
{
    global $mybb;
    require_once MYBB_ROOT.$mybb->settings['tapatalk_directory'].'/emoji/emoji.php';
    $post['message'] = emoji_name_to_unified($post['message']);
    $post['message'] = emoji_unified_to_html($post['message']);
    return $post;
}
function tapatalk_get_url()
{
    global $mybb;
    $location = get_current_location();
    $split_loc = explode(".php", $location);
    $parameters = $param_arr = array();
    if($split_loc[0] == $location)
    {
        $filename = '';
    }
    else
    {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
    }
    if($split_loc[1])
    {
        $temp = explode("&amp;", my_substr($split_loc[1], 1));
        foreach($temp as $param)
        {
            $temp2 = explode("=", $param, 2);
            $parameters[$temp2[0]] = $temp2[1];
        }
    }
    switch ($filename)
    {
        case "forumdisplay":
            $param_arr['fid'] = $parameters['fid'];
            $param_arr['location'] = 'forum';
            $param_arr['page'] = isset($parameters['page']) ? intval($parameters['page']) : 1;
            $param_arr['perpage'] = $mybb->settings['threadsperpage'];
            break;
        case "index":
        case '':
            $param_arr['location'] = 'index';
            break;
        case "private":
            if($parameters['action'] == "read")
            {
                $param_arr['location'] = 'message';
                $param_arr['mid'] = $parameters['pmid'];
            }
            break;
        case "search":
            $param_arr['location'] = "search";
            break;
        case "showthread":
            if(!empty($parameters['pid']))
            {
                //$param_arr['fid'] = $parameters['fid'];
                $param_arr['location'] = 'post';
                $param_arr['tid'] = $parameters['tid'];
                $param_arr['pid'] = $parameters['pid'];
            }
            else
            {
                //$param_arr['fid'] = $parameters['fid'];
                $param_arr['location'] = 'topic';
                $param_arr['tid'] = $parameters['tid'];
            }
            $param_arr['page'] = isset($parameters['page']) ? intval($parameters['page']) : 1;
            $param_arr['perpage'] = $mybb->settings['postsperpage'];
            break;
        case "member":
            if($parameters['action'] == "login" || $parameters['action'] == "do_login")
            {
                $param_arr['location'] = 'login';
            }
            elseif($parameters['action'] == "profile")
            {
                $param_arr['location'] = 'profile';
                $param_arr['uid'] = $parameters['uid'];
            }

            break;
        case "online":
            $param_arr['location'] = 'online';
            break;
        default:
            $param_arr['location'] = 'other';
            break;
    }
    $queryString = http_build_query($param_arr);
    $url = $mybb->settings['bburl'] . '/?' .$queryString;
    $url = preg_replace('/https?:\/\//', '', $url);
    return $url;
}

// push related functions
function tapatalk_push_reply()
{
	global $mybb;
    $tapatalk_push = new TapatalkPush($mybb->settings['tapatalk_push_key'], $mybb->settings['bburl']);
    $tapatalk_push->doPushReply();
}

function tapatalk_push_quote()
{
    global $mybb;
    $tapatalk_push = new TapatalkPush($mybb->settings['tapatalk_push_key'], $mybb->settings['bburl']);
    $tapatalk_push->doPushQuote();
}

function tapatalk_push_tag()
{
    global $mybb;
    $tapatalk_push = new TapatalkPush($mybb->settings['tapatalk_push_key'], $mybb->settings['bburl']);
    $tapatalk_push->doPushTag();
}

function tapatalk_push_newtopic()
{
    global $mybb;
    $tapatalk_push = new TapatalkPush($mybb->settings['tapatalk_push_key'], $mybb->settings['bburl']);
    $tapatalk_push->doPushPost();
}

function tapatalk_push_pm()
{
    global $mybb;
    $tapatalk_push = new TapatalkPush($mybb->settings['tapatalk_push_key'], $mybb->settings['bburl']);
    $tapatalk_push->doPushPm();
}

function tapatalk_push_newsub()
{
    global $mybb;
    $tapatalk_push = new TapatalkPush($mybb->settings['tapatalk_push_key'], $mybb->settings['bburl']);
    $tapatalk_push->doPushNewSub();
}

/*function tapatalk_parse_message(&$message)
{
    if(strstr($_SERVER['PHP_SELF'],'mobiquo.php'))
    {
        return ;
    }
    //add tapatalk thumbnail
    $message = preg_replace_callback('/(\[img\])(http:\/\/img.tapatalk.com\/d\/[0-9]{2}\/[0-9]{2}\/[0-9]{2})(.*?)(\[\/img\])/i',
            create_function(
                '$matches',
                'return \'[url=http://tapatalk.com/tapatalk_image.php?img=\'.base64_encode($matches[2].\'/original\'.$matches[3]).\']\'.$matches[1].$matches[2].\'/thumbnail\'.$matches[3].$matches[4].\'[/url]\';'
            ),
    $message);

}*/

function tapatalk_parse_message_end(&$message)
{
    if(strstr($_SERVER['PHP_SELF'],'mobiquo.php'))
    {
        return ;
    }
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
    $message = preg_replace('/\[emoji(\d+)\]/i', '<img style="vertical-align: middle;" src="'.$protocol.'://emoji.tapatalk-cdn.com/emoji\1.png" />', $message);
}

function tapatalk_settings_update()
{
    global $db;
    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk_register'", array('limit' => 1));
    $group = $db->fetch_array($result);

    if(isset($_GET['gid']) && $_GET['gid'] == $group['gid'])
    {
        $query = $db->simple_select("usergroups", "gid, title", "", array('order_by' => 'title'));
        $display_group_options = array();
        while($usergroup = $db->fetch_array($query))
        {
            $display_group_options[$usergroup['gid']] = $usergroup['title'];
        }
        $select_group_str = '';
        foreach ($display_group_options as $groupid => $title)
        {
            $select_group_str .= "\n".$groupid . "=" . $title;
        }
        $select_group_str = $db->escape_string($select_group_str);
        $db->update_query("settings",array('optionscode' => 'select'.$select_group_str),"name = 'tapatalk_register_group'");
    }

}

if (!function_exists('http_build_query'))
{
    function http_build_query($data, $prefix = null, $sep = '', $key = '')
    {
        $ret = array();
        foreach ((array )$data as $k => $v) {
            $k = urlencode($k);
            if (is_int($k) && $prefix != null) {
                $k = $prefix . $k;
            }

            if (!empty($key)) {
                $k = $key . "[" . $k . "]";
            }

            if (is_array($v) || is_object($v)) {
                array_push($ret, http_build_query($v, "", $sep, $k));
            } else {
                array_push($ret, $k . "=" . urlencode($v));
            }
        }

        if (empty($sep)) {
            $sep = ini_get("arg_separator.output");
        }

        return implode($sep, $ret);
    }
}

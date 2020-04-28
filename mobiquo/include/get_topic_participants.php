<?php

/*defined('IN_MOBIQUO') or exit;
include_once TT_ROOT."include/function.php";

function get_topic_participants_func($xmlrpc_params)
{
    global $lang, $db, $mybb;
    
    $lang->load("member");
    
    $input = Tapatalk_Input::filterXmlInput(array(
        'topic_id' => Tapatalk_Input::INT,
        'max_num'  => Tapatalk_Input::INT
    ), $xmlrpc_params);
    $api_key = $mybb->settings['tapatalk_push_key'];
    if(empty($api_key))
    {
        error($lang->error_nomember);
    }
    if(empty($input['max_num'])) $input['max_num'] = 20;
    $query = $db->simple_select("users", "uid,username,email,avatar", " uid IN(SELECT DISTINCT uid  FROM mybb_posts WHERE tid = " . $input['topic_id'] . ") ",array("order_by" => "uid", "order_dir" => "asc", "limit" => $input['max_num']));    
    $user_lists = array();
       
    while ($row = $db->fetch_array($query))
    {
        $user_lists[] = new xmlrpcval(array(
            'username'     => new xmlrpcval($row['username'], 'base64'),
            'user_id'       => new xmlrpcval($row['uid'], 'string'),
            'icon_url'      => new xmlrpcval(absolute_url($row['avatar']), 'string'),
            'enc_email'     => new xmlrpcval(encrypt($row['email'],$api_key), 'base64')
        ), 'struct');
    }
    
    $online_users = new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'list'         => new xmlrpcval($user_lists, 'array'),
    ), 'struct');

    return new xmlrpcresp($online_users);
}*/
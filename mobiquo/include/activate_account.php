<?php
defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";

function activate_account_func($xmlrpc_params)
{
	global $db, $lang, $mybb, $cache;
	
    $lang->load("member");
    $input = Tapatalk_Input::filterXmlInput(array(
        'email' => Tapatalk_Input::STRING,
        'token' => Tapatalk_Input::STRING,
        'code' => Tapatalk_Input::STRING,
   
    ), $xmlrpc_params);
    $result_text = '';
    require_once TT_ROOT."lib/classTTJson.php";
    require_once TT_ROOT."lib/classTTConnection.php";
	$connection = new classTTConnection();
    $verify_result = $connection->signinVerify($input['token'],$input['code'],$mybb->settings['bburl'],$mybb->settings['tapatalk_push_key']);
    if(!empty($verify_result['inactive']))
    {
    	$status = 2;
    }
    else if(!$verify_result['result'])
    {
    	$status = 4;
    }
    else if ($verify_result['email'] != $input['email'])
    {
    	$status = 3;
    }
    else 
    {
		$options = array(
			'username_method' => 1, // get user by email
			'fields' => '*',
		);
		$user = get_user_by_username($input['email'], $options);
		if(!$user)
		{
			$status = 1;
		}
		else if($mybb->settings['regtype'] == "admin" || $mybb->settings['regtype'] == "both")
		{
			$status = 5;
			$result_text = $lang->error_activated_by_admin;
		}
		else if($user['usergroup'] != 5)
		{
			$status = 5;
			$result_text = $lang->error_alreadyactivated;
		}
		else 
		{
			$uid = $user['uid'];
			
			$db->delete_query("awaitingactivation", "uid='".$user['uid']."' AND (type='r' OR type='e')");
	
			if($user['usergroup'] == 5)
			{
				$db->update_query("users", array("usergroup" => 2), "uid='".$user['uid']."'");		
				$cache->update_awaitingactivation();
			}
			
		}
    }
    
    $result = array (
		'result'            => new xmlrpcval(true, 'boolean'),
	);
	
	if(!empty($status)) 
	{
	    $result['status'] = new xmlrpcval($status);
	    $result['result'] = new xmlrpcval(false, 'boolean');
	}
	if(!empty($result_text))
	{
		$result['result_text'] = new xmlrpcval($result_text, 'base64');
		$result['result'] = new xmlrpcval(false, 'boolean');
	}
	return new xmlrpcresp(new xmlrpcval($result, 'struct'));
	
}
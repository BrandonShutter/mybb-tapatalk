<?php

defined('IN_MOBIQUO') or exit;

function get_config_func()
{
    global $mobiquo_config, $mybb, $cache, $db;

    $config_list = array(
        'sys_version'   => new xmlrpcval($mybb->version, 'string'),
        'version'       => new xmlrpcval($mobiquo_config['version'], 'string'),
        'is_open'       => new xmlrpcval(isset($cache->cache['plugins']['active']['tapatalk']), 'boolean'),
        'guest_okay'    => new xmlrpcval($mybb->usergroup['canview'] && $mybb->settings['boardclosed'] == 0 && empty($mybb->settings['forcelogin']), 'boolean'),
    );
    if(!isset($cache->cache['plugins']['active']['tapatalk']))
    {
    	$config_list['is_open'] = new xmlrpcval(false,'boolean');
        $config_list['result_text'] = new xmlrpcval(basic_clean('Tapatalk is disabled'), 'base64');
    }
    if ($mybb->settings['boardclosed'])
    {
    	$config_list['is_open'] = new xmlrpcval(false,'boolean');
        $config_list['result_text'] = new xmlrpcval(basic_clean($mybb->settings['boardclosed_reason']), 'base64');
    }

    // First, load the stats cache.
	$stats = $cache->read("stats");
	$config_list['stats'] = new xmlrpcval(array(
        'topic'    => new xmlrpcval($stats['numthreads'], 'int'),
        'user'     => new xmlrpcval($stats['numusers'], 'int'),
		'post'     => new xmlrpcval($stats['numposts'], 'int'),
    ), 'struct');


    if(version_compare($mybb->version, '1.6.9','>=') && !$mybb->settings['disableregs'])
    {
    	$mobiquo_config['inappreg'] = 1;
    }
    if(version_compare($mybb->version, '1.8.0','>='))
    {
    	$mobiquo_config['announcement'] = 1;
        if($mybb->settings['soft_delete'] == 1)
        {
            $mobiquo_config['soft_delete'] = 1;
        }

    }
	if($mybb->settings['disableregs'] == 1)
    {
    	$mobiquo_config['sign_in'] = 0;
	    $mobiquo_config['inappreg'] = 0;

	    $mobiquo_config['sso_signin'] = 0;
	    $mobiquo_config['sso_register'] = 0;
	    $mobiquo_config['native_register'] = 0;
    }

	if (!function_exists('curl_init') && !@ini_get('allow_url_fopen'))
	{
	    $mobiquo_config['sign_in'] = 0;
	    $mobiquo_config['inappreg'] = 0;

	    $mobiquo_config['sso_login'] = 0;
	    $mobiquo_config['sso_signin'] = 0;
	    $mobiquo_config['sso_register'] = 0;
	}
	if(isset($mybb->settings['tapatalk_register_status']))
	{
		if($mybb->settings['tapatalk_register_status'] == 0)
		{
			$mobiquo_config['sign_in'] = 0;
		    $mobiquo_config['inappreg'] = 0;

		    $mobiquo_config['sso_signin'] = 0;
		    $mobiquo_config['sso_register'] = 0;
		    $mobiquo_config['native_register'] = 0;
		}
		elseif($mybb->settings['tapatalk_register_status'] == 1)
		{
			$mobiquo_config['inappreg'] = 0;
			$mobiquo_config['sign_in'] = 0;

		    $mobiquo_config['sso_signin'] = 0;
		    $mobiquo_config['sso_register'] = 0;
		}
	}
    //Add banner_control
    $mobiquo_config['banner_control'] = 1;
    //Started_by support
    $mobiquo_config['search_started_by'] = 1;

	if($mybb->settings['username_method'] == 0)
	{
		$mobiquo_config['login_type'] = 'username';
	}
	else if($mybb->settings['username_method'] == 1)
	{
		$mobiquo_config['login_type'] = 'email';
	}
	else if($mybb->settings['username_method'] == 2)
	{
		$mobiquo_config['login_type'] = 'both';
	}
    foreach($mobiquo_config as $key => $value){
        if(!array_key_exists($key, $config_list) && $key != 'thlprefix'){
            $config_list[$key] = new xmlrpcval($value, 'string');
        }
    }
    if (!$mybb->user['uid'])
    {
        if($mybb->usergroup['cansearch']) {
            $config_list['guest_search'] = new xmlrpcval('1', 'string');
        }

        if($mybb->usergroup['canviewonline']) {
            $config_list['guest_whosonline'] = new xmlrpcval('1', 'string');
        }
    }

    if($mybb->settings['minsearchword'] < 1)
    {
        $mybb->settings['minsearchword'] = 3;
    }

    $config_list['min_search_length'] = new xmlrpcval(intval($mybb->settings['minsearchword']), 'int');
    if(!empty($mybb->settings['tapatalk_push_key'])) {
    	$config_list['api_key'] = new xmlrpcval(md5($mybb->settings['tapatalk_push_key']), 'string');
    }
    $config_list['ads_disabled_group'] = new xmlrpcval($mybb->settings['tapatalk_ad_filter'], 'string');
    $isTTServerCall = false;
    if(isset($_SERVER['HTTP_X_TT']))
    {
		$code = trim($_SERVER['HTTP_X_TT']);
        if (!class_exists('classTTConnection')){
            if (!defined('IN_MOBIQUO')){
                define('IN_MOBIQUO', true);
            }
            require_once(TT_ROOT.'/lib/classTTConnection.php');
        }
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code,'get_config');
        if($response)
        {
            $isTTServerCall = true;
        }
    }
	if(!$isTTServerCall)
    {
    	$version_arr = explode('_', $mobiquo_config['version']);
        $config_list['version'] = new xmlrpcval($version_arr[0],'string');
        unset($config_list['sys_version']);
        unset($config_list['stats']);
    }
    else
    {
        $config_list['php_version'] =  new xmlrpcval(phpversion(),'string');
        $config_list['hook_version'] =  new xmlrpcval($mobiquo_config['version'],'string');
        $config_list['release_timestamp'] = new xmlrpcval(1469739347,'string');
        $push_slug = !empty($mybb->settings['tapatalk_push_slug'])? $mybb->settings['tapatalk_push_slug'] : 0;
        $config_list['push_slug'] = new xmlrpcval(json_encode(unserialize($push_slug)),'string');
        $result_tapatalk_banner_data = !empty($mybb->settings['tapatalk_banner_data'])? $mybb->settings['tapatalk_banner_data'] : '';
        $config_list['smartbanner_info'] = new xmlrpcval(json_encode(unserialize($result_tapatalk_banner_data)),'string');
    }
    $response = new xmlrpcval($config_list, 'struct');
    return new xmlrpcresp($response);
}


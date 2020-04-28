<?php
defined('IN_MOBIQUO') or exit;

function prefetch_account_func()
{
	global $mybb,$db,$lang;
	$lang->load("member");
	$user = tt_get_user_by_email($mybb->input['email']);

	if(empty($user['uid']))
	{
		$result = array (
			'result'            => new xmlrpcval(false, 'boolean'),
		    'result_text'       => new xmlrpcval($lang->error_nomember, 'base64'),
		);	
	}
	else 
	{
		//Add custom_register_fields for register 

		$result = array(
			'result'            => new xmlrpcval(true, 'boolean'),
			'result_text'       => new xmlrpcval('', 'base64'),
			'user_id'           => new xmlrpcval($user['uid'], 'string'),
			'login_name'        => new xmlrpcval(basic_clean($user['username']), 'base64'),
			'display_name'      => new xmlrpcval(basic_clean($user['username']), 'base64'),
			'avatar'            => new xmlrpcval(absolute_url($user['avatar']), 'string'),
		);
	}

	//if(version_compare($mybb->version, '1.8.0','>='))
    {
    	//Query request fields for register
		$requiredArr = array();
		$query = $db->query("
	        SELECT * FROM " . TABLE_PREFIX . "profilefields WHERE required = '1';
	    ");

	    if(!empty($query))
	    {
		    while($resultArr = $db->fetch_array($query))
		    {
		    	$options = '';
		    	$cboxDefault = '';
		    	$typeArr = explode("\n", $resultArr['type']);

		    	
		    	if(is_array($typeArr))
		    	{
		    		$type = $typeArr[0];
		    		
		    		//Rename
		    		if(trim($type) === 'text')
		    		{
		    			$type = 'input';
		    		}
		    		if(trim($type) === 'checkbox')
		    		{
		    			$type = 'cbox';
		    		}
		    		if(trim($type) === 'select')
		    		{
		    			$type = 'drop';
		    		}
		    	}

		    	$requiredArr = array(
		    		'name'           =>   new xmlrpcval($resultArr['name'], 'base64'),
		    		'description'    =>   new xmlrpcval($resultArr['description'], 'base64'),
					'key'            =>   new xmlrpcval(strtolower($resultArr['fid']), 'string'),
					'type'           =>   new xmlrpcval($type, 'string'),
					'default'        =>   new xmlrpcval('', 'base64'),
		    	);

		    	if($type == 'radio' || $type == 'cbox' || $type == 'drop')
		    	{
		    		for($i = 1;$i < count($typeArr);$i ++)
		    		{
		    			if($i == 1)
		    			{
		    				$options = $typeArr[$i] . '=' . $typeArr[$i];
		    			}
		    			else
		    			{
		    				$options = $options . '|' . $typeArr[$i] . '=' . $typeArr[$i];
		    			}
		    		}

		    		$requiredArr['options'] = new xmlrpcval($options, 'base64');
		    	}

		    	if($type == 'cbox')
		    	{
		    		for($i = 1;$i < count($typeArr);$i ++)
		    		{
		    			if($i == 1)
		    			{
		    				$cboxDefault = '';
		    			}
		    			else
		    			{
		    				$cboxDefault = $cboxDefault . '|' . '';
		    			}
		    		}
		    		$requiredArr['default'] = new xmlrpcval($cboxDefault, 'base64');
		    	}
		    	$required_custom_fields[] = new xmlrpcval($requiredArr, 'struct');
		    }

		    //Add custom_register_fields field
		    $result['custom_register_fields'] = new xmlrpcval($required_custom_fields, 'array');
		} 
    }
	

	return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}
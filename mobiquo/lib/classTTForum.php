<?php
defined('IN_MOBIQUO') or exit;

class TTForum implements TTSSOForumInterface
{
    // return user info array, including key 'email', 'id', etc.
    public function getUserByID($uid)
    {
        require_once MYBB_ROOT."/inc/functions.php";
        if(!function_exists('get_user'))
        {
            return tt_get_user_by_id($uid);
        }
        else
        {
            return get_user($uid);
        }
    }

    // return user info array, including key 'email', 'id', etc.
    public function getUserByEmail($email)
    {
        require_once MYBB_ROOT."/inc/functions.php";
        if(!function_exists('get_user_by_username'))
        {
            return tt_get_user_by_email($email);
        }
        else
        {
            return get_user_by_username($email, array('username_method' => 1,'fields' => '*'));
        }
    }
    
    public function getUserByName($username)
    {
        require_once MYBB_ROOT."/inc/functions.php";
    	if(!function_exists('get_user_by_username'))
        {
            return tt_get_user_id_by_name($username);
        }
        else 
        {
            return get_user_by_username($username, array('fields' => '*'));
        }
    }

    // the response should be bool to indicate if the username meet the forum requirement
    public function validateUsernameHandle($username)
    {
        // Set up user handler.
        global $mybb;
        require_once MYBB_ROOT."inc/datahandlers/user.php";
        $userhandler = new UserDataHandler("insert");
        $user = array(
            "username" => $username,
        );
        $userhandler->set_data($user);
        if(!$userhandler->verify_username() || $userhandler->verify_username_exists())
        {
            return false;
        }
        return true;
    }

    // the response should be bool to indicate if the password meet the forum requirement
    public function validatePasswordHandle($password)
    {
        // Set up user handler.
        global $mybb;
        require_once MYBB_ROOT."inc/datahandlers/user.php";
        $userhandler = new UserDataHandler("insert");
        $user = array(
            "username" => $mybb->input['username'],
            "password" => $mybb->input['password'],
            "password2" => $mybb->input['password'],
        );
        $userhandler->set_data($user);
        if(!$userhandler->verify_password())
        {
            return false;
        }
        return true;
    }

    // create a user, $verified indicate if it need user activation
    public function createUserHandle($email, $username, $password, $verified, $custom_register_fields, $profile, &$errors)
    {
        global $mybb, $lang, $session, $db, $cache, $user;
        if($mybb->settings['disableregs'] == 1 || (isset($mybb->settings['tapatalk_register_status']) && $mybb->settings['tapatalk_register_status'] == 0))
        {
            $errors[] = $lang->registrations_disabled;
            return;
        }
        
        // Set up user handler.
        require_once MYBB_ROOT."inc/datahandlers/user.php";
        $userhandler = new UserDataHandler("insert");
        
        $bday = array(
            "day" => '01',
            "month" => '01',
            "year" => 1970,
        );
        $birthday_arr = explode('-', $profile['birthday']);
        if(count($birthday_arr) == 3)
        {
            $bday = array(
                "day" => $birthday_arr[2],
                "month" => $birthday_arr[1],
                "year" => $birthday_arr[0],
            );
        }

        
        //Set profile_fields
        $user_field = array();
        $profile_fields = array();
        $fid = 0;
        if(isset($_POST['profile_fields']))
        {
            $profileArr = $_POST['profile_fields'];
            
            foreach ($profileArr as $key => $value) {


                $queryPos = $db->query("
                    SELECT fid FROM " . TABLE_PREFIX . "profilefields WHERE fid = '".$db->escape_string($key)."';
                ");

                if(!empty($queryPos))
                {
                    while($resultArr = $db->fetch_array($queryPos))
                    {
                        $fid = $resultArr['fid'];
                    }
                }

                $profile_fields['fid' . $fid] = $value;
            }

        } 
        
        $user_field = $profile_fields;

        $auto_approve = (int) (isset($mybb->settings['tapatalk_auto_approve']) ? $mybb->settings['tapatalk_auto_approve'] : 1);
        if(($auto_approve && $verified) || $mybb->settings['regtype'] == 'instant' 
        || ($mybb->settings['regtype'] == 'verify' && $verified)
        || ($mybb->settings['regtype'] == 'randompass' && $verified)
        )
        {
            $usergroup = 2;
        }
        else 
        {
            $usergroup = 5;
        }
        
        // Set the data for the new user.
        $user = array(
            "username" => $username,
            "password" => $password,
            "password2" => $password,
            "email" => $email,
            "email2" => $email,
            "usergroup" => $usergroup, 
            "referrer" => '',
            "timezone" => $mybb->settings['timezoneoffset'],
            "language" => '',
            "regip" => $session->ipaddress,
            "longregip" => my_ip2long($session->ipaddress),
            "coppa_user" => 0,
            "birthday" => $bday,
            "website" => $profile['link'],
            "user_fields" => $user_field,
            "signature" => $profile['signature'],
            "option" => array(),
            "regdate" => TIME_NOW,
            "lastvisit" => TIME_NOW,
            "profile_fields" => $profile_fields
        );  

        if(!empty($mybb->settings['tapatalk_register_group']) && $usergroup != $mybb->settings['tapatalk_register_group'])
        {
            $user['additionalgroups'] = $mybb->settings['tapatalk_register_group'];
        }
        if(!empty($profile['avatar_url']))
        {
            $updated_avatar = tt_update_avatar_url($profile->avatar_url);
        }
        
        $userhandler->set_data($user);
        $userhandler->verify_birthday();
        $userhandler->verify_options();
        
        if(!$userhandler->verify_email() || $userhandler->verify_username_exists() || !$userhandler->verify_password() || !$userhandler->verify_username())
        {
            $friendly_errors = $userhandler->get_friendly_errors();
            $errors[] = $friendly_errors[0];
            return false;
        }
        $userhandler->set_validated(true);
        $user = $userhandler->insert_user();
        $user_info = $user;
        if($mybb->settings['regtype'] == "verify" && $usergroup == 5)
        {
            $activationcode = random_str();
            $now = TIME_NOW;
            $activationarray = array(
                "uid" => $user_info['uid'],
                "dateline" => TIME_NOW,
                "code" => $activationcode,
                "type" => "r"
            );
            $db->insert_query("awaitingactivation", $activationarray);
            $emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
            switch($mybb->settings['username_method'])
            {
                case 0:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
                    break;
                case 1:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount1, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
                    break;
                case 2:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount2, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
                    break;
                default:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
                    break;
            }
            my_mail($user_info['email'], $emailsubject, $emailmessage);
        }
        else if($mybb->settings['regtype'] == "randompass" && $usergroup == 5)
        {
            $emailsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
            switch($mybb->settings['username_method'])
            {
                case 0:
                    $emailmessage = $lang->sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
                    break;
                case 1:
                    $emailmessage = $lang->sprintf($lang->email_randompassword1, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
                    break;
                case 2:
                    $emailmessage = $lang->sprintf($lang->email_randompassword2, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
                    break;
                default:
                    $emailmessage = $lang->sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
                    break;
            }
            my_mail($user_info['email'], $emailsubject, $emailmessage);
        }
        else if($mybb->settings['regtype'] == "admin")
        {
            $groups = $cache->read("usergroups");
            $admingroups = array();
            if(!empty($groups)) // Shouldn't be...
            {
                foreach($groups as $group)
                {
                    if($group['cancp'] == 1)
                    {
                        $admingroups[] = (int)$group['gid'];
                    }
                }
            }

            if(!empty($admingroups))
            {
                $sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
                foreach($admingroups as $admingroup)
                {
                    switch($db->type)
                    {
                        case 'pgsql':
                        case 'sqlite':
                            $sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
                            break;
                        default:
                            $sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
                            break;
                    }
                }
                $q = $db->simple_select('users', 'uid,username,email,language', $sqlwhere);
                while($recipient = $db->fetch_array($q))
                {
                    // First we check if the user's a super admin: if yes, we don't care about permissions
                    $is_super_admin = is_super_admin($recipient['uid']);
                    if(!$is_super_admin)
                    {
                        // Include admin functions
                        if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
                        {
                            continue;
                        }

                        require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";

                        // Verify if we have permissions to access user-users
                        require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
                        if(function_exists("user_admin_permissions"))
                        {
                            // Get admin permissions
                            $adminperms = get_admin_permissions($recipient['uid']);

                            $permissions = user_admin_permissions();
                            if(array_key_exists('users', $permissions['permissions']) && $adminperms['user']['users'] != 1)
                            {
                                continue; // No permissions
                            }
                        }
                    }

                    // Load language
                    if($recipient['language'] != $mybb->user['language'] && $lang->language_exists($recipient['language']))
                    {
                        $reset_lang = true;
                        $lang->set_language($recipient['language']);
                        $lang->load("member");
                    }

                    $subject = $lang->sprintf($lang->newregistration_subject, $mybb->settings['bbname']);
                    $message = $lang->sprintf($lang->newregistration_message, $recipient['username'], $mybb->settings['bbname'], $user['username']);
                    my_mail($recipient['email'], $subject, $message);
                }

                // Reset language
                if(isset($reset_lang))
                {
                    $lang->set_language($mybb->user['language']);
                    $lang->load("member");
                }
            }
        }
        else if($mybb->settings['regtype'] == "both")
        {
            $groups = $cache->read("usergroups");
            $admingroups = array();
            if(!empty($groups)) // Shouldn't be...
            {
                foreach($groups as $group)
                {
                    if($group['cancp'] == 1)
                    {
                        $admingroups[] = (int)$group['gid'];
                    }
                }
            }

            if(!empty($admingroups))
            {
                $sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
                foreach($admingroups as $admingroup)
                {
                    switch($db->type)
                    {
                        case 'pgsql':
                        case 'sqlite':
                            $sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
                            break;
                        default:
                            $sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
                            break;
                    }
                }
                $q = $db->simple_select('users', 'uid,username,email,language', $sqlwhere);
                while($recipient = $db->fetch_array($q))
                {
                    // First we check if the user's a super admin: if yes, we don't care about permissions
                    $is_super_admin = is_super_admin($recipient['uid']);
                    if(!$is_super_admin)
                    {
                        // Include admin functions
                        if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
                        {
                            continue;
                        }

                        require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";

                        // Verify if we have permissions to access user-users
                        require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
                        if(function_exists("user_admin_permissions"))
                        {
                            // Get admin permissions
                            $adminperms = get_admin_permissions($recipient['uid']);

                            $permissions = user_admin_permissions();
                            if(array_key_exists('users', $permissions['permissions']) && $adminperms['user']['users'] != 1)
                            {
                                continue; // No permissions
                            }
                        }
                    }

                    // Load language
                    if($recipient['language'] != $mybb->user['language'] && $lang->language_exists($recipient['language']))
                    {
                        $reset_lang = true;
                        $lang->set_language($recipient['language']);
                        $lang->load("member");
                    }

                    $subject = $lang->sprintf($lang->newregistration_subject, $mybb->settings['bbname']);
                    $message = $lang->sprintf($lang->newregistration_message, $recipient['username'], $mybb->settings['bbname'], $user['username']);
                    my_mail($recipient['email'], $subject, $message);
                }

                // Reset language
                if(isset($reset_lang))
                {
                    $lang->set_language($mybb->user['language']);
                    $lang->load("member");
                }
            }
            
            $activationcode = random_str();
            $activationarray = array(
                "uid" => $user['id'],
                "dateline" => TIME_NOW,
                "code" => $activationcode,
                "type" => "b"
            );
            $db->insert_query("awaitingactivation", $activationarray);
            $emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
            switch($mybb->settings['username_method'])
            {
                case 0:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
                    break;
                case 1:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount1, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
                    break;
                case 2:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount2, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
                    break;
                default:
                    $emailmessage = $lang->sprintf($lang->email_activateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
                    break;
            }
            my_mail($user['email'], $emailsubject, $emailmessage);
        }
        
        if(!empty($updated_avatar))
        {
            $db->update_query("users", $updated_avatar, "uid='".$user['uid']."'");
        }    
        return $user;
    }

    // login to an existing user, return result as bool
    public function loginUserHandle($userInfo, $register)
    {
        return tt_login_success($userInfo, $register);
    }

    // return forum api key
    public function getAPIKey()
    {
        global $mybb;
        return isset($mybb->settings['tapatalk_push_key']) ? $mybb->settings['tapatalk_push_key'] : '';
    }

    // return forum url
    public function getForumUrl()
    {
        global $mybb;
        return $mybb->settings['bburl'];
    }

    // email obtain from userInfo for compared with TTEmail
    public function getEmailByUserInfo($userInfo)
    {
        return $userInfo['email'];
    }
}
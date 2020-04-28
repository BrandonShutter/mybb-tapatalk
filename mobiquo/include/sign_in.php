<?php
defined('IN_MOBIQUO') or exit;
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once TT_ROOT . "lib/classTTSSO.php";
require_once TT_ROOT . "lib/classTTForum.php";
require_once TT_ROOT . "lib/classTTConnection.php";

function sign_in_func()
{
    global $lang, $mybb, $plugins;

    // Load global language phrases
    $lang->load("member");

    $data['token'] = trim($_POST['token']);
    $data['code'] = trim($_POST['code']);
    $data['username'] = $mybb->input['username'];
    $data['password'] = $mybb->input['password'];
    $data['email'] = $mybb->input['email'];

    // forum disabled registration
    $is_register = !empty($data['password']);
    if($is_register && ($mybb->settings['disableregs'] == 1 || (isset($mybb->settings['tapatalk_register_status']) && $mybb->settings['tapatalk_register_status'] == 0)))
    {
        $result_text = $mybb->settings['disableregs'] == 1 ? $lang->registrations_disabled : 'Sorry, but you cannot register at this time because the administrator has disabled new account registrations from APP.';

        $result = array(
            'result' => new xmlrpcval(false, 'boolean'),
            'result_text' => new xmlrpcval($result_text, 'base64'),
        );
        return new xmlrpcresp(new xmlrpcval($result, 'struct'));
    }

    //disable all hooks when signin
    $plugins->hooks = array();
    $sso = new TTSSOBase(new TTForum());
    $sso->signIn($data);
    if ($sso->result === false)
    {
        $errors = $sso->errors;
        $result = array(
            'result' => new xmlrpcval(false, 'boolean'),
            'result_text' => new xmlrpcval(isset($errors[0]) && !empty($errors[0]) ? $errors[0] : '', 'base64'),
        );
        if(!empty($sso->status))
        {
            $result['status'] = new xmlrpcval($sso->status, 'string');
        }
        return new xmlrpcresp(new xmlrpcval($result, 'struct'));
    }
    return $sso->result;
}

function tt_update_avatar_url($avatar_url)
{
    global $mybb, $db;
    $avatar_url = preg_replace("#script:#i", "", $avatar_url);
    $avatar_url = preg_replace("/^(https)/", 'http', $avatar_url);
    $ext = get_extension($avatar_url);

    // Copy the avatar to the local server (work around remote URL access disabled for getimagesize)
    $file = fetch_remote_file($avatar_url);

    if(!$file)
    {
        return false;
    }
    else
    {
        $tmp_name = $mybb->settings['avataruploadpath']."/remote_".md5(random_str());
        $fp = @fopen($tmp_name, "wb");
        if(!$fp)
        {
            return false;
        }
        else
        {
            fwrite($fp, $file);
            fclose($fp);
            list($width, $height, $type) = @getimagesize($tmp_name);
            @unlink($tmp_name);
            if(!$type)
            {
                return false;
            }
        }
    }

    if($width && $height && $mybb->settings['maxavatardims'] != "")
    {
        list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
        if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
        {
            return false;
        }
    }

    if($width > 0 && $height > 0)
    {
        $avatar_dimensions = intval($width)."|".intval($height);
    }
    else
    {
        return false;
    }

    $updated_avatar = array(
        "avatar" => $db->escape_string($avatar_url.'?dateline='.TIME_NOW),
        "avatardimensions" => $avatar_dimensions,
        "avatartype" => "remote"
    );
    return $updated_avatar;
}


<?php
define('IN_MOBIQUO', true);
define('TT_ROOT', getcwd() . DIRECTORY_SEPARATOR);
define('TT_PATH', basename(TT_ROOT));
define('MBQ_PATH', (($getcwd = getcwd()) ? $getcwd : '.') . '/');
define('MBQ_3RD_LIB_PATH', (($getcwd = getcwd()) ? $getcwd : '.') . '/lib/');
require_once(MBQ_3RD_LIB_PATH. "ExceptionHelper.php");
include './pretreat.php';
if(MBQ_DEBUG == 0) @ob_start();
require_once './config/config.php';
require_once './lib/xmlrpc.inc';
require_once './lib/xmlrpcs.inc';
require_once './xmlrpcs.php';
require_once './server_define.php';
require_once './mobiquo_common.php';
require_once './input.php';
require_once './xmlrpcresp.php';
require_once './env_setting.php';

require_once(MBQ_PATH . '/logger.php');
require_once(MBQ_3RD_LIB_PATH . '/MbqBaseStatus.php');
class TTFakePlugin
{
    public function add_hook()
    {}
}

class MbqStatus extends MbqBaseStatus
{

    public function GetLoggedUserName()
    {
        global $mybb;
        return $mybb->user['username'];
    }
    protected function GetMobiquoFileSytemDir()
    {
        return TT_ROOT;
    }
    protected function GetMobiquoDir()
    {
        return 'mobiquo';
    }
    protected function GetApiKey()
    {
        global $mybb;
        return $mybb->settings['tapatalk_push_key'];
    }
    protected function GetForumUrl()
    {
        global $mybb;
        return $mybb->settings['bburl'];
    }
    protected function GetPushSlug()
    {
        global $mybb;
        $push_slug = !empty($mybb->settings['tapatalk_push_slug'])? $mybb->settings['tapatalk_push_slug'] : 0;
        return $push_slug;
    }

    protected function ResetPushSlug()
    {
        global $db;

        $updated_value = array('value' => $db->escape_string(0));
        $db->update_query("settings", $updated_value, "name='tapatalk_push_slug'");

        rebuild_settings();
    }

    protected function GetBYOInfo()
    {
        global $mybb;
        $result_tapatalk_banner_data = !empty($mybb->settings['tapatalk_banner_data'])? $mybb->settings['tapatalk_banner_data'] : '';
        $TT_bannerControlData = !empty($result_tapatalk_banner_data) ? unserialize($result_tapatalk_banner_data) : false;
        $result_tapatalk_banner_expire = !empty($mybb->settings['tapatalk_banner_expire'])? $mybb->settings['tapatalk_banner_expire'] : 0;
        $TT_expireTime = !empty($result_tapatalk_banner_expire) ? intval($result_tapatalk_banner_expire) : 0;
        $app_banner_enable = isset($mybb->settings['smart_app_banner']) ? $mybb->settings['smart_app_banner'] : 1;
        $google_indexing_enabled = isset($mybb->settings['google_app_indexing_enabled']) ? $mybb->settings['google_app_indexing_enabled'] : 1;

        $tapatalk_dir = 'mobiquo';
        include_once('lib/classTTConnection.php');
        $TT_connection = new classTTConnection();
        $TT_connection->calcSwitchOptions($TT_bannerControlData, $app_banner_enable, $google_indexing_enabled);
        $TT_bannerControlData['update'] = $TT_expireTime;
        $TT_bannerControlData['banner_enable'] = $app_banner_enable;
        $TT_bannerControlData['google_enable'] = $google_indexing_enabled;

        return $TT_bannerControlData;
    }
    protected function ResetBYOInfo()
    {
        global $db;
        $tapatalk_dir = 'mobiquo';
        $TT_bannerControlData = null;
        include_once('lib/classTTConnection.php');
        $TT_connection = new classTTConnection();
        $TT_bannerControlData = $TT_connection->getForumInfo($this->GetForumUrl(), $this->GetApiKey());
        $updated_value = array('value' => $db->escape_string(serialize($TT_bannerControlData)));
        $db->update_query("settings", $updated_value, "name='tapatalk_banner_data'");
        $updated_value = array('value' => time());
        $db->update_query("settings", $updated_value, "name='tapatalk_banner_expire'");
        rebuild_settings();
    }
    protected function GetOtherPlugins()
    {
        $dir = @opendir(MYBB_ROOT."inc/plugins/");
        if($dir)
        {
            while($file = readdir($dir))
            {
                $ext = get_extension($file);
                if($ext == "php")
                {
                    $plugins_list[] = $file;
                }
            }
            @sort($plugins_list);
        }
        @closedir($dir);
        if(!empty($plugins_list))
	    {
		    $a_plugins = $i_plugins = array();
            global $plugins;
            $plugins = new TTFakePlugin();
		    foreach($plugins_list as $plugin_file)
		    {
			    require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			    $codename = str_replace(".php", "", $plugin_file);
			    $infofunc = $codename."_info";

			    if(!function_exists($infofunc))
			    {
				    continue;
			    }

			    $plugininfo = $infofunc();
			    $plugininfo['codename'] = $codename;

			    if($active_plugins[$codename])
			    {
				    // This is an active plugin
				    $plugininfo['is_active'] = 1;

				    $a_plugins[] = $plugininfo;
				    continue;
			    }

			    // Either installed and not active or completely inactive
			    $i_plugins[] = $plugininfo;
		    }
        }
        $result = array();
        foreach($i_plugins as $plugin)
        {
            $result[] = array('name'=>$plugin['name'], 'version'=>$plugin['version']);
        }
        return $result;
    }
    public function UserIsAdmin()
    {
        global $mybb;
        if (check_return_user_type($mybb->user['username']) == 'admin') {
            return true;
        }
        return false;
    }
    protected function GetPluginVersion()
    {
        global $mobiquo_config;
        return $mobiquo_config['version'];
    }
}
$mbqStatus = new MbqStatus();


<?php

defined('IN_MOBIQUO') or exit;
require_once TT_ROOT . "lib/classTTJson.php";
require_once TT_ROOT . "lib/classTTConnection.php";
require_once TT_ROOT . "lib/classTTCipherEncrypt.php";
require_once TT_ROOT . "include/get_user_info.php";
function reset_push_slug()
{
    global $db;
    $code = trim($_REQUEST['code']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'reset_push_slug');
    $result = false;
    if($response === true)
    {
        $updated_value = array('value' => 0);
        $db->update_query("settings", $updated_value, "name='tapatalk_push_slug'");
        rebuild_settings();
        $result = true;
    }
    $data = array(
         'result' => $result,
         'result_text' => $response,
     );

    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;

}

function set_api_key()
{
    global $db;
    $code = trim($_REQUEST['code']);
    $key = trim($_REQUEST['key']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_api_key');
    $result = false;
    if($response === true)
    {
        $updated_value = array('value' => $db->escape_string($key));
        $db->update_query("settings", $updated_value, "name='tapatalk_push_key'");
        rebuild_settings();
        $result = true;
    }
    $data = array(
         'result' => $result,
         'result_text' => $response,
     );

    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
}
function set_forum_info()
{
    global $db,$mybb;
    $code = trim($_REQUEST['code']);
    $key = trim($_REQUEST['key']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_forum_info');
    $data = array('result' => false, 'result_text' =>  'modify fail');
    if($response === true)
    {
        if(isset($_REQUEST['api_key']))
        {
            $key = $_REQUEST['api_key'];
            $updated_value = array('value' => $db->escape_string($key));
            $db->update_query("settings", $updated_value, "name='tapatalk_push_key'");
            rebuild_settings();
            $result_tapatalk_banner_data = !empty($mybb->settings['tapatalk_banner_data'])? $mybb->settings['tapatalk_banner_data'] : '';
            $TT_bannerControlData = !empty($result_tapatalk_banner_data) ? unserialize($result_tapatalk_banner_data) : false;
             if ($key == $mybb->settings['tapatalk_push_key']){
                $data = array(
                     'result' => true,
                     'api_key' => $key,
                     'forum_info' => $TT_bannerControlData
                 );
            }
        }
        if(isset($_REQUEST['banner_info']))
        {
            $TT_bannerControlData  = json_decode($_REQUEST['banner_info'], true);
            if($TT_bannerControlData === true)
            {
                $TT_bannerControlData = $connection->getForumInfo($mybb->settings['bburl'], $mybb->settings['tapatalk_push_key']);
            }
            $updated_value = array('value' => $db->escape_string(serialize($TT_bannerControlData)));
            $db->update_query("settings", $updated_value, "name='tapatalk_banner_data'");
            $updated_value = array('value' => time());
            $db->update_query("settings", $updated_value, "name='tapatalk_banner_expire'");
            rebuild_settings();

            $result_tapatalk_banner_data = !empty($mybb->settings['tapatalk_banner_data'])? $mybb->settings['tapatalk_banner_data'] : '';
            $TT_bannerControlData = !empty($result_tapatalk_banner_data) ? unserialize($result_tapatalk_banner_data) : false;
            $data = array(
                    'result' => true,
                    'api_key' => $mybb->settings['tapatalk_push_key'],
                    'forum_info' => $TT_bannerControlData
                );

        }
    }

    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
}

//user_subscription
function get_user_subscription_func()
{
    global $db;

    $code = trim($_POST['code']);
    $uid = intval(isset($_POST['uid']) ? $_POST['uid'] : 0);
    $format = trim($_POST['format']);

    $connection = new classTTConnection();
    $response = $connection->actionVerification($code, 'user_subscription');

    $forum_list = array();
    $topic_list = array();
    if($response === true)
    {

        $query = $db->query("
            SELECT fs.fid, f.name
            FROM ".TABLE_PREFIX."forumsubscriptions fs
            LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid)
            WHERE fs.uid='".$uid."'
            ORDER BY f.name ASC;
        ");

        while($forum = $db->fetch_array($query))
        {
            $forum_list[] = array('forum_id' => $forum['fid'], 'forum_name' => basic_clean($forum['name']));
        }

        // Fetch topic subscriptions
        $query = $db->query("
            SELECT t.tid
            FROM ".TABLE_PREFIX."threadsubscriptions t
            WHERE t.uid='".$uid."';
        ");

        while($topics = $db->fetch_array($query))
        {
            $topic_list[] = $topics['tid'];
        }

        $result = array(
            'result'           => true,
            'result_text'      => $response,
            'forums'           => $forum_list,
            'topics'           => $topic_list
        );

    }
    else
    {
         $result = array(
            'result'           => false,
            'result_text'      => $response,
            'forums'           => $forum_list,
            'topics'           => $topic_list
        );
    }

    if($format == 'serialize')
    {
        echo serialize($result);
    }
    else
    {
        echo json_encode($result);
    }
}

function push_content_check_func()
{
    global $mybb, $db;
    $code = trim($_POST['code']);
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';
    $data = unserialize(trim($_POST['data']));

    $result = array('result' => false);
    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'push_content_check');
        if ($response !== true){
            $result['result_text'] = $response;
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        if(!isset($mybb->settings['tapatalk_push_key']) || !isset($data['key']) || $mybb->settings['tapatalk_push_key'] != $data['key']){
            $result['result_text'] = 'Incorrect API Key';
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        if (!isset($data['dateline']) || time() - intval($data['dateline']) > 86400){
            $result['result_text'] = 'Time Out';
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        switch ($data['type']){
            case 'newtopic':
            case 'sub':
            case 'quote':
            case 'newsub':
            case 'tag':
                $query = "
                    SELECT p.pid
                    FROM " . TABLE_PREFIX . "posts AS p
                    WHERE p.pid={$data['subid']}
                        AND p.tid={$data['id']}
                        AND p.uid={$data['authorid']}
                        AND p.dateline={$data['dateline']}
                ";
                break;
            case 'pm':
                $id = $data['id'];
                if (preg_match('/_(\d+)$/', $id, $matches)){
                    $id = $matches[1];
                }
                $query = "
                    SELECT pmt.pmid
                    FROM " . TABLE_PREFIX. "privatemessages as pmt
                    WHERE pmt.pmid={$id}
                        AND pmt.fromid={$data['authorid']}
                        AND pmt.dateline={$data['dateline']}
                ";
                break;
        }
        if (isset($query) && !empty($query)){
            $query_result = $db->query($query);
            if (!empty($query_result)){
                $result['result']=true;
            }
        }
    }catch (Exception $e){
        $result['result_text'] = $e->getMessage();
    }
    echo ($format == 'json') ? json_encode($result) : serialize($result);
    exit;
}

?>

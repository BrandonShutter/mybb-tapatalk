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
if(isset($_POST['session']) && isset($_POST['api_key']) && isset($_POST['subject']) && isset($_POST['body']) || isset($_POST['email_target']))
{
   require_once TT_ROOT . 'include/invitation.php';
}
$rpcServer = new Tapatalk_xmlrpcs($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding = 'UTF-8';

if(!empty($_POST['method_name'])){
    $xml = new xmlrpcmsg($_POST['method_name']);
    $request = $xml->serialize();
    $response = $rpcServer->service($request);
} else {
    $response = $rpcServer->service();
}

exit;
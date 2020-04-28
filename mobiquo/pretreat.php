<?php
// if (isset($_GET['welcome']))
// {
//     include('./smartbanner/app.php');
//     exit;
// }

if (isset($_POST['method_name']) && $_POST['method_name'] == 'verify_connection'){
    $type = isset($_POST['type']) ? $_POST['type'] : 'both';
    $code = isset($_POST['code']) ? $_POST['code'] : '';
    require_once TT_ROOT."lib/classTTConnection.php";
    $connection = new classTTConnection();
    echo serialize($connection->verify_connection($type, $code));
    exit;
}

if(isset($_GET['method_name']) && $_GET['method_name'] != 'set_api_key' && $_SERVER['REQUEST_METHOD'] == 'GET')
{
    include 'web.php';
    exit;
}
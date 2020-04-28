<?php
if($_SERVER['REQUEST_METHOD'] == 'GET')
{
	echo '<b>Attachment Upload Interface for Tapatalk Application</b><br/><br/>';
	echo '<br/>For more details, please visit <a href="https://www.tapatalk.com" target="_blank">https://www.tapatalk.com</a>';
	exit;
}	
require "./mobiquo.php";
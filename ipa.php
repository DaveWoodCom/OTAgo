<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

require_once('common.php');
require_once('configuration.php');
require_once($authFile);

$username = $_GET['u'];
$password = $_GET['p'];
if (empty($username)) {
	$username = $_SERVER['PHP_AUTH_USER'];
}
if (empty($password)) {
	$password = $_SERVER['PHP_AUTH_PW'];
}

preventCaching();

if (!isValidUser($username, $password)) {
    requestAuthentication();
    exit();
}

readfile($ipaFile);

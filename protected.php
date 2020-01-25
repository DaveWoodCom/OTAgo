<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

require_once('common.php');
if (file_exists('configuration.php')) {
	require_once('configuration.php');
}
else {
	require_once('configuration.default.php');
}
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

$html = file_get_contents($webTemplate);

$manifestURL = $baseURL . 'manifest.php?u=' . urlencode($username) . '&p=' . urlencode($password);
$installURL = 'itms-services://?action=download-manifest&url=' . urlencode($manifestURL);

$html = str_replace($installURLPlacehHolder, $installURL, $html);
echo $html;
